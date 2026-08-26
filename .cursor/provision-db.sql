-- Idempotent local development database for the Proveedores La Sociedad patches.
--
-- The full application (SystemSuplidor/) and its real .sql dumps are NOT part of
-- this repository (they are gitignored). This script creates just enough schema
-- for the self-contained validation logic in patches/rnc/ to run against a real
-- MySQL/MariaDB instance during development and smoke testing.

CREATE DATABASE IF NOT EXISTS suplidores_dev
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'suplidor'@'127.0.0.1' IDENTIFIED BY 'suplidor';
CREATE USER IF NOT EXISTS 'suplidor'@'localhost' IDENTIFIED BY 'suplidor';
GRANT ALL PRIVILEGES ON suplidores_dev.* TO 'suplidor'@'127.0.0.1';
GRANT ALL PRIVILEGES ON suplidores_dev.* TO 'suplidor'@'localhost';
FLUSH PRIVILEGES;

USE suplidores_dev;

CREATE TABLE IF NOT EXISTS proveedores (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  nombre_empresa VARCHAR(255) NOT NULL,
  usuario        VARCHAR(100) UNIQUE,
  correo         VARCHAR(255) UNIQUE,
  telefono       VARCHAR(50),
  rnc_cedula     VARCHAR(50),
  activo         TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO proveedores (id, nombre_empresa, usuario, correo, telefono, rnc_cedula, activo)
VALUES (1, 'Suplidor Existente SRL', 'existente', 'existente@example.com', '8090000000', '044-1234567-8', 1);
