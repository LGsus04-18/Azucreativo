-- Ejecutar una sola vez en phpMyAdmin después de importar login_db.sql.
USE login_db;
ALTER TABLE usuarios ADD COLUMN rol ENUM('administrador','cliente') NOT NULL DEFAULT 'cliente';
-- Conservar el administrador existente; los nuevos registros siempre serán clientes.
UPDATE usuarios SET rol='administrador' WHERE usuario='admin';
-- Para otros administradores, asignar el rol manualmente desde una cuenta autorizada.
