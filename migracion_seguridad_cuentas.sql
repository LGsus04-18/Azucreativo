-- Importar en phpMyAdmin: no borra registros existentes.
USE login_db;
CREATE TABLE IF NOT EXISTS admin_autorizaciones (
 id_usuario INT NOT NULL PRIMARY KEY,
 clave_hash VARCHAR(255) NOT NULL,
 vence_en DATETIME NOT NULL,
 actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_admin_autorizacion_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS auditoria_autorizaciones (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 actor_id INT NOT NULL,
 accion VARCHAR(80) NOT NULL,
 objeto VARCHAR(120) NOT NULL,
 fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS activo TINYINT(1) NOT NULL DEFAULT 1;
-- Cliente anónimo: ejecutar la inserción siguiente solo si no existe ya un cliente de mostrador.
USE azucreativo_db;
INSERT INTO Cliente (Nombre,Apellido)
SELECT 'Cliente','Anónimo' WHERE NOT EXISTS
 (SELECT 1 FROM Cliente WHERE Nombre='Cliente' AND Apellido='Anónimo');
