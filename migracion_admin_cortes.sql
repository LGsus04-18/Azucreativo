-- Importar en phpMyAdmin después de roles_empleados_caja.sql. No elimina cortes existentes.
USE Azucreativo_db;
CREATE TABLE IF NOT EXISTS Auditoria_Corte_Caja (
 ID_Auditoria INT AUTO_INCREMENT PRIMARY KEY,
 ID_Corte INT NOT NULL,
 ID_Admin INT NOT NULL,
 Fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 Contado_Anterior DECIMAL(12,2) NOT NULL,
 Contado_Nuevo DECIMAL(12,2) NOT NULL,
 Motivo VARCHAR(500) NOT NULL,
 INDEX idx_auditoria_corte (ID_Corte),
 CONSTRAINT fk_auditoria_corte FOREIGN KEY (ID_Corte) REFERENCES Corte_Caja(ID_Corte)
) ENGINE=InnoDB;
