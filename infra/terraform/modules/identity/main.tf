# Decision 3.5/3.6/3.7 (item 14.8): Auth0 como Authorization Server real,
# reemplazando a `oidc-server-mock` (usado en local/CI, Fases 0-13). El
# `client_id` publico "bp-web" y el resource/scope "bp-web" replican
# exactamente `CLIENTS_CONFIGURATION_INLINE`/`API_RESOURCES_INLINE` de
# `docker-compose.yml` -- mismo contrato para que el codigo de los
# frontends (`oidcConfig.ts`/`authConfig.ts`) no tenga que cambiar entre
# ambientes, solo la URL del issuer.

resource "auth0_client" "bp_web" {
  name     = "${var.name_prefix}-bp-web"
  app_type = "spa"

  # Cliente publico, sin secret -- Authorization Code + PKCE obligatorio
  # (decision 3.6), igual que el cliente ya preconfigurado en mock-oidc.
  oidc_conformant = true

  jwt_configuration {
    alg = "RS256"
  }

  callbacks = concat(var.web_redirect_uris, var.mobile_redirect_uris)
  allowed_logout_urls = concat(
    [for uri in var.web_redirect_uris : replace(uri, "/callback", "")],
    [for uri in var.mobile_redirect_uris : replace(uri, "/callback", "")],
  )

  grant_types = ["authorization_code", "refresh_token"]

  refresh_token {
    rotation_type   = "rotating" # Refresh Token Rotation, decision 3.6
    expiration_type = "expiring"
    leeway          = 0
    token_lifetime  = 2592000 # 30 dias
  }
}

resource "auth0_resource_server" "bp_web_api" {
  name       = "${var.name_prefix}-bp-web-api"
  identifier = "bp-web"

  token_lifetime                                  = 3600
  skip_consent_for_verifiable_first_party_clients = true
}

# El provider auth0 v1.x separo la gestion de scopes del resource server en
# un recurso aparte (antes era un bloque "scopes" inline dentro de
# auth0_resource_server, removido en la v1.0 del provider).
resource "auth0_resource_server_scopes" "bp_web_api" {
  resource_server_identifier = auth0_resource_server.bp_web_api.identifier

  scopes {
    name        = "bp-web"
    description = "Scope de acceso a la API interna de BP (equivalente a bp-web en mock-oidc)"
  }
}

# MFA adaptativo (decision 3.5): factor de riesgo basado en dispositivo
# nuevo, ubicacion inusual, etc. -- se activa solo cuando el motor de
# riesgo de Auth0 lo considera necesario, no en cada login.
resource "auth0_guardian" "this" {
  policy = "confidence-score" # adaptativo -- Auth0 decide segun senales de riesgo, no "siempre" ni "nunca"

  webauthn_platform {
    enabled = true # Face ID / Windows Hello (decision 3.7: login recurrente)
  }

  webauthn_roaming {
    enabled = true # llaves de seguridad externas (Yubikey, etc.)

    user_verification = "preferred"
  }

  phone {
    enabled = false # decision 3.7 usa biometria nativa + WebAuthn, no SMS OTP como segundo factor
  }
}

# step-up: un `acr_values` reforzado para operaciones sensibles (decision
# 3.6) se pide desde el propio codigo del cliente al armar la URL de
# autorizacion (ver frontend-web/src/auth/oidcConfig.ts) -- no es un
# recurso de Terraform, es un parametro de request en tiempo de ejecucion,
# documentado aca para dejar explicita la trazabilidad completa de la
# decision.
