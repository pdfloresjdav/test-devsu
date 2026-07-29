# Un repositorio ECR por servicio -- el pipeline de despliegue (item 14.9)
# hace build+push aca antes de actualizar la task definition de ECS.

resource "aws_ecr_repository" "this" {
  for_each = var.services

  name                 = "${var.name_prefix}/${each.key}"
  image_tag_mutability = "IMMUTABLE" # una vez pusheado un tag no se pisa -- trazabilidad de que imagen corre en cada momento

  image_scanning_configuration {
    scan_on_push = true # escaneo de vulnerabilidades (seccion 9.4)
  }

  tags = var.tags
}

resource "aws_ecr_lifecycle_policy" "this" {
  for_each = var.services

  repository = aws_ecr_repository.this[each.key].name

  policy = jsonencode({
    rules = [{
      rulePriority = 1
      description  = "Conservar solo las ultimas 20 imagenes para no acumular costo de storage indefinidamente (seccion 9.7)"
      selection = {
        tagStatus   = "any"
        countType   = "imageCountMoreThan"
        countNumber = 20
      }
      action = { type = "expire" }
    }]
  })
}
