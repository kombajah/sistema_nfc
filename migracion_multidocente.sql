-- Ejecutar SOLO si vienes de la versión anterior (un solo maestro, cursos sin dueño).
-- Recomendado: respaldar la base antes de correr esto.
USE sistema_nfc;

ALTER TABLE maestros ADD COLUMN rol ENUM('admin','docente') NOT NULL DEFAULT 'docente';
UPDATE maestros SET rol='admin' WHERE id = (SELECT id FROM (SELECT MIN(id) id FROM maestros) t); -- el primero pasa a admin

CREATE TABLE IF NOT EXISTS asignaturas (
  id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(100) NOT NULL, docente_id INT NOT NULL,
  FOREIGN KEY (docente_id) REFERENCES maestros(id) ON DELETE CASCADE
);
-- Asignatura genérica para migrar los cursos existentes (edítala luego).
INSERT INTO asignaturas (nombre, docente_id) SELECT 'General', id FROM maestros WHERE rol='admin' LIMIT 1;

ALTER TABLE cursos ADD COLUMN docente_id INT NULL, ADD COLUMN asignatura_id INT NULL;
UPDATE cursos SET docente_id=(SELECT id FROM maestros WHERE rol='admin' LIMIT 1),
                  asignatura_id=(SELECT id FROM asignaturas LIMIT 1) WHERE docente_id IS NULL;
ALTER TABLE cursos MODIFY docente_id INT NOT NULL, MODIFY asignatura_id INT NOT NULL,
  ADD FOREIGN KEY (docente_id) REFERENCES maestros(id) ON DELETE CASCADE,
  ADD FOREIGN KEY (asignatura_id) REFERENCES asignaturas(id) ON DELETE CASCADE;
