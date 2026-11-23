-- Cuentas de prueba para Azucreativo. No modifica al administrador.
-- Ejecutar en phpMyAdmin > SQL, DESPUES de importar login_db y preparar roles.
USE login_db;
-- Garantiza que la columna rol exista con los tres roles permitidos.
SET @tiene_rol = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='usuarios' AND COLUMN_NAME='rol');
SET @ddl = IF(@tiene_rol=0, "ALTER TABLE usuarios ADD COLUMN rol ENUM('administrador','cliente','empleado') NOT NULL DEFAULT 'cliente'", "ALTER TABLE usuarios MODIFY COLUMN rol ENUM('administrador','cliente','empleado') NOT NULL DEFAULT 'cliente'");
PREPARE sentencia FROM @ddl;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
-- No sobrescribir cuentas existentes con el mismo nombre o correo.
INSERT INTO usuarios (usuario, correo, password, rol) SELECT 'empleado_demo', 'empleado.demo@azucreativo.local', '$2y$12$t8CBsm8myOTD9MxRpo2blunNA6t8wB/oFWDEOqMc4bf12JBgBxxk6', 'empleado' WHERE NOT EXISTS (SELECT 1 FROM usuarios WHERE usuario='empleado_demo' OR correo='empleado.demo@azucreativo.local');
INSERT INTO usuarios (usuario, correo, password, rol) SELECT 'cliente_demo', 'cliente.demo@azucreativo.local', '$2y$12$xLhVPliiOhvbYZJuD7geuO0s7WS82xCkeCXpBNMxT9/jHXXk3Eff.', 'cliente' WHERE NOT EXISTS (SELECT 1 FROM usuarios WHERE usuario='cliente_demo' OR correo='cliente.demo@azucreativo.local');
SELECT usuario, correo, rol FROM usuarios WHERE usuario IN ('empleado_demo','cliente_demo');
