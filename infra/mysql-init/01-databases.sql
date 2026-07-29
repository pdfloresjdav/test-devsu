-- Each microservice with relational persistence has its own database
-- inside the same local MySQL container (one per service, not shared) --
-- this script only runs the first time the MySQL data volume is created
-- (docker-entrypoint-initdb.d).

CREATE DATABASE IF NOT EXISTS svc_transfers CHARACTER SET utf8mb4;
