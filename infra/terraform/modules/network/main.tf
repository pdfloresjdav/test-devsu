# Modulo de red -- decision 9.2 (Alta disponibilidad): VPC Multi-AZ con
# subredes publicas (edge: NAT, futuros load balancers) y privadas (ECS
# Fargate, Aurora, ElastiCache), replicando el diagrama de despliegue
# (seccion 7 del documento de arquitectura).

data "aws_availability_zones" "available" {
  state = "available"
}

locals {
  az_names = slice(data.aws_availability_zones.available.names, 0, var.availability_zone_count)

  # /20 por subred alcanza para los task ENIs de Fargate + margen de
  # crecimiento sin tener que resegmentar la VPC.
  public_subnet_cidrs  = [for i in range(var.availability_zone_count) : cidrsubnet(var.vpc_cidr, 4, i)]
  private_subnet_cidrs = [for i in range(var.availability_zone_count) : cidrsubnet(var.vpc_cidr, 4, i + var.availability_zone_count)]
}

resource "aws_vpc" "this" {
  cidr_block           = var.vpc_cidr
  enable_dns_support   = true
  enable_dns_hostnames = true

  tags = merge(var.tags, { Name = "${var.name_prefix}-vpc" })
}

resource "aws_internet_gateway" "this" {
  vpc_id = aws_vpc.this.id

  tags = merge(var.tags, { Name = "${var.name_prefix}-igw" })
}

resource "aws_subnet" "public" {
  count = var.availability_zone_count

  vpc_id                  = aws_vpc.this.id
  cidr_block              = local.public_subnet_cidrs[count.index]
  availability_zone       = local.az_names[count.index]
  map_public_ip_on_launch = true

  tags = merge(var.tags, { Name = "${var.name_prefix}-public-${local.az_names[count.index]}", Tier = "public" })
}

resource "aws_subnet" "private" {
  count = var.availability_zone_count

  vpc_id            = aws_vpc.this.id
  cidr_block        = local.private_subnet_cidrs[count.index]
  availability_zone = local.az_names[count.index]

  tags = merge(var.tags, { Name = "${var.name_prefix}-private-${local.az_names[count.index]}", Tier = "private" })
}

# Un NAT Gateway por AZ (no uno compartido) -- si compartieramos uno solo,
# la caida de esa AZ tumbaria la salida a internet de las demas, violando
# el principio Multi-AZ de la seccion 9.2.
resource "aws_eip" "nat" {
  count  = var.availability_zone_count
  domain = "vpc"

  tags = merge(var.tags, { Name = "${var.name_prefix}-nat-eip-${local.az_names[count.index]}" })
}

resource "aws_nat_gateway" "this" {
  count = var.availability_zone_count

  allocation_id = aws_eip.nat[count.index].id
  subnet_id     = aws_subnet.public[count.index].id

  tags = merge(var.tags, { Name = "${var.name_prefix}-nat-${local.az_names[count.index]}" })

  depends_on = [aws_internet_gateway.this]
}

resource "aws_route_table" "public" {
  vpc_id = aws_vpc.this.id

  route {
    cidr_block = "0.0.0.0/0"
    gateway_id = aws_internet_gateway.this.id
  }

  tags = merge(var.tags, { Name = "${var.name_prefix}-public-rt" })
}

resource "aws_route_table_association" "public" {
  count = var.availability_zone_count

  subnet_id      = aws_subnet.public[count.index].id
  route_table_id = aws_route_table.public.id
}

resource "aws_route_table" "private" {
  count = var.availability_zone_count

  vpc_id = aws_vpc.this.id

  route {
    cidr_block     = "0.0.0.0/0"
    nat_gateway_id = aws_nat_gateway.this[count.index].id
  }

  tags = merge(var.tags, { Name = "${var.name_prefix}-private-rt-${local.az_names[count.index]}" })
}

resource "aws_route_table_association" "private" {
  count = var.availability_zone_count

  subnet_id      = aws_subnet.private[count.index].id
  route_table_id = aws_route_table.private[count.index].id
}

# --- Security Groups: cada capa solo acepta trafico de la capa anterior ---

resource "aws_security_group" "edge" {
  name_prefix = "${var.name_prefix}-edge-"
  description = "WAF/CloudFront -> API Gateway (seccion 5.1)"
  vpc_id      = aws_vpc.this.id

  ingress {
    description = "HTTPS publico"
    from_port   = 443
    to_port     = 443
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = merge(var.tags, { Name = "${var.name_prefix}-edge-sg" })

  lifecycle {
    create_before_destroy = true
  }
}

resource "aws_security_group" "ecs_tasks" {
  name_prefix = "${var.name_prefix}-ecs-tasks-"
  description = "Trafico interno entre BFFs y microservicios de negocio (mTLS a nivel de aplicacion, seccion 9.4)"
  vpc_id      = aws_vpc.this.id

  ingress {
    description     = "Desde el edge (API Gateway via VPC Link) hacia los BFFs"
    from_port       = 8000
    to_port         = 8000
    protocol        = "tcp"
    security_groups = [aws_security_group.edge.id]
  }

  ingress {
    description = "Trafico interno servicio a servicio dentro de la misma SG"
    from_port   = 8000
    to_port     = 8000
    protocol    = "tcp"
    self        = true
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = merge(var.tags, { Name = "${var.name_prefix}-ecs-tasks-sg" })

  lifecycle {
    create_before_destroy = true
  }
}

resource "aws_security_group" "data" {
  name_prefix = "${var.name_prefix}-data-"
  description = "Aurora MySQL + ElastiCache Redis -- solo alcanzables desde las tareas ECS (decision 3.10/3.8)"
  vpc_id      = aws_vpc.this.id

  ingress {
    description     = "MySQL"
    from_port       = 3306
    to_port         = 3306
    protocol        = "tcp"
    security_groups = [aws_security_group.ecs_tasks.id]
  }

  ingress {
    description     = "Redis"
    from_port       = 6379
    to_port         = 6379
    protocol        = "tcp"
    security_groups = [aws_security_group.ecs_tasks.id]
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = merge(var.tags, { Name = "${var.name_prefix}-data-sg" })

  lifecycle {
    create_before_destroy = true
  }
}
