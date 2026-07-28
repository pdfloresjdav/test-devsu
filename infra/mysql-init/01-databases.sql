-- Cada microservicio con persistencia relacional tiene su propia base de
-- datos dentro del mismo contenedor MySQL local (una por servicio, no
-- compartida) -- este script solo corre la primera vez que se crea el
-- volumen de datos de MySQL (docker-entrypoint-initdb.d).

CREATE DATABASE IF NOT EXISTS svc_transferencias CHARACTER SET utf8mb4;
