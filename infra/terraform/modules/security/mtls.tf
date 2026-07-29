# Seccion 9.4: "mTLS entre microservicios internos -- ningun trafico
# interno viaja en texto plano". AWS App Mesh (Envoy) es el "equivalente"
# que menciona el item 14.5 -- da mTLS automatico entre las tareas sin que
# cada servicio Laravel tenga que implementar su propio manejo de
# certificados.

resource "aws_acmpca_certificate_authority" "internal" {
  type = "ROOT"

  certificate_authority_configuration {
    key_algorithm     = "RSA_2048"
    signing_algorithm = "SHA256WITHRSA"

    subject {
      common_name  = "${var.name_prefix}-internal-ca"
      organization = "BP"
    }
  }

  permanent_deletion_time_in_days = 7

  tags = var.tags
}

resource "aws_appmesh_mesh" "this" {
  name = "${var.name_prefix}-mesh"

  spec {
    egress_filter {
      # Solo trafico explicitamente ruteado dentro del mesh -- cualquier
      # llamada saliente no declarada (ej. a un dominio no esperado) se
      # bloquea en vez de dejarse pasar por defecto.
      type = "DROP_ALL"
    }
  }

  tags = var.tags
}

locals {
  service_names = ["svc-customer-data", "svc-movements", "svc-transfers", "svc-audit", "svc-notifications", "bff-web", "bff-mobile"]
}

# Un Virtual Node por servicio -- cada uno presenta un certificado emitido
# por la CA privada de arriba (via ACM, integrado con App Mesh) y exige el
# mismo certificado de quien le habla, logrando mTLS real sin cambiar el
# codigo de la aplicacion Laravel.
resource "aws_appmesh_virtual_node" "this" {
  for_each = toset(local.service_names)

  name      = each.key
  mesh_name = aws_appmesh_mesh.this.id

  spec {
    listener {
      port_mapping {
        port     = 8000
        protocol = "http"
      }

      tls {
        mode = "STRICT"

        certificate {
          acm {
            certificate_arn = aws_acm_certificate.service[each.key].arn
          }
        }

        # Limitacion real de App Mesh (no de esta arquitectura): la
        # validacion de confianza del LADO LISTENER (server validando el
        # certificado del cliente que le habla, es decir mTLS real y no
        # solo TLS) solo admite las fuentes "file" o "sds" -- "acm" como
        # fuente de confianza solo esta soportado del lado client_policy
        # (un nodo validando al servidor al que llama), no aca. Se usa
        # "file" apuntando a una ruta convencional; falta agregar al
        # modulo compute el paso que efectivamente escribe el certificado
        # de esta CA privada (aws_acmpca_certificate_authority.internal)
        # en esa ruta dentro de cada tarea (ej. un init container que
        # llama GetCertificateAuthorityCertificate) antes de que STRICT
        # mTLS de extremo a extremo funcione en la practica.
        validation {
          trust {
            file {
              certificate_chain = "/certs/internal-ca.pem"
            }
          }
        }
      }
    }

    service_discovery {
      aws_cloud_map {
        namespace_name = "${var.name_prefix}.internal"
        service_name   = each.key
      }
    }
  }

  tags = var.tags
}

resource "aws_acm_certificate" "service" {
  for_each = toset(local.service_names)

  domain_name               = "${each.key}.${var.name_prefix}.internal"
  certificate_authority_arn = aws_acmpca_certificate_authority.internal.arn
  key_algorithm             = "RSA_2048"

  tags = var.tags
}
