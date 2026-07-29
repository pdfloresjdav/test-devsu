output "cloudfront_domain_name" {
  value = aws_cloudfront_distribution.edge.domain_name
}

output "cloudfront_arn" {
  value = aws_cloudfront_distribution.edge.arn
}

output "web_acl_arn" {
  value = aws_wafv2_web_acl.edge.arn
}
