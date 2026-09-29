-- Ejecutar si ya tenías la base creada (agrega la tabla de sesiones para Vercel).
USE sistema_nfc;
CREATE TABLE IF NOT EXISTS sesiones (
  id VARCHAR(128) PRIMARY KEY,
  datos MEDIUMTEXT NOT NULL,
  expira DATETIME NOT NULL
);
