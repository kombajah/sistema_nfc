CREATE DATABASE IF NOT EXISTS sistema_nfc CHARACTER SET utf8mb4;
USE sistema_nfc;

CREATE TABLE maestros (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  rol ENUM('admin','docente') NOT NULL DEFAULT 'docente'
);
-- El admin inicial se crea con instalar.php (no lo insertes a mano: necesita password_hash de PHP).

CREATE TABLE asignaturas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  docente_id INT NOT NULL,
  FOREIGN KEY (docente_id) REFERENCES maestros(id) ON DELETE CASCADE
);

-- El mismo nombre de curso puede repetirse: cada fila es "un curso dictado por
-- un docente, en una asignatura", con sus propios alumnos y puntajes.
CREATE TABLE cursos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  docente_id INT NOT NULL,
  asignatura_id INT NOT NULL,
  FOREIGN KEY (docente_id) REFERENCES maestros(id) ON DELETE CASCADE,
  FOREIGN KEY (asignatura_id) REFERENCES asignaturas(id) ON DELETE CASCADE
);

CREATE TABLE alumnos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  curso_id INT NOT NULL,
  nombre VARCHAR(100) NOT NULL,
  nfc_uid VARCHAR(100) UNIQUE,
  qr_code VARCHAR(40) UNIQUE,
  FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE
);

CREATE TABLE categorias (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(50) NOT NULL
);
INSERT INTO categorias (nombre) VALUES ('Excelente actitud'),('Participación'),('Tarea completada');
-- Las categorías/motivos son compartidas por todos los docentes. Si prefieres que
-- cada docente maneje las suyas, se puede agregar una columna docente_id aquí también.

CREATE TABLE config (clave VARCHAR(50) PRIMARY KEY, valor VARCHAR(100) NOT NULL);
INSERT INTO config (clave, valor) VALUES ('tasa_canje', '10');
-- Tasa de canje global. Si cada docente necesita su propia tasa, se puede
-- convertir en (docente_id, valor) más adelante.

CREATE TABLE registro_puntos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  alumno_id INT NOT NULL,
  categoria_id INT NOT NULL,
  puntos TINYINT NOT NULL,
  fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (alumno_id) REFERENCES alumnos(id) ON DELETE CASCADE,
  FOREIGN KEY (categoria_id) REFERENCES categorias(id)
);

CREATE TABLE canjes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  alumno_id INT NOT NULL,
  puntos_virtuales INT NOT NULL,
  puntos_base INT NOT NULL,
  observacion VARCHAR(200) DEFAULT '',
  fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (alumno_id) REFERENCES alumnos(id) ON DELETE CASCADE
);
