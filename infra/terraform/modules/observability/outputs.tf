output "alerts_topic_arn" {
  value = aws_sns_topic.alerts.arn
}

output "guardduty_detector_id" {
  value = aws_guardduty_detector.this.id
}

output "canary_name" {
  value = aws_synthetics_canary.critical_flow.name
}
