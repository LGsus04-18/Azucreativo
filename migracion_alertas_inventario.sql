-- Ejecutar UNA vez en phpMyAdmin, tras respaldar la base de datos.
USE `azucreativo_db`;
ALTER TABLE `producto` ADD COLUMN `Stock_Minimo` INT NOT NULL DEFAULT 10;
-- Si ya existe Stock_Minimo, NO vuelvas a ejecutar el ALTER.
