output "client_id" {
  value = auth0_client.bp_web.client_id
}

output "resource_server_identifier" {
  value = auth0_resource_server.bp_web_api.identifier
}
