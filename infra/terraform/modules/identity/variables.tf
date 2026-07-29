variable "name_prefix" {
  type = string
}

variable "environment" {
  type = string
}

variable "web_redirect_uris" {
  description = "redirect_uri validos para la SPA -- localhost:5173 en dev, el dominio real de BP en prod."
  type        = list(string)
}

variable "mobile_redirect_uris" {
  description = "redirect_uri validos para la app movil (Expo/scheme nativo una vez fuera de development build)."
  type        = list(string)
}
