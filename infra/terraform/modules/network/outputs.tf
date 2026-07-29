output "vpc_id" {
  value = aws_vpc.this.id
}

output "public_subnet_ids" {
  value = aws_subnet.public[*].id
}

output "private_subnet_ids" {
  value = aws_subnet.private[*].id
}

output "availability_zones" {
  value = local.az_names
}

output "edge_security_group_id" {
  value = aws_security_group.edge.id
}

output "ecs_tasks_security_group_id" {
  value = aws_security_group.ecs_tasks.id
}

output "data_security_group_id" {
  value = aws_security_group.data.id
}
