-- Ejecutar en phpMyAdmin con copia de seguridad previa.
USE Azucreativo_db;
CREATE TABLE IF NOT EXISTS Cliente_Usuario_Web (
 ID_Usuario_Login INT NOT NULL PRIMARY KEY,
 ID_Cliente INT NOT NULL UNIQUE,
 CONSTRAINT fk_cuw_cliente FOREIGN KEY (ID_Cliente) REFERENCES Cliente(ID_Cliente)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS Venta_Cliente_Web (
 ID_Venta INT NOT NULL PRIMARY KEY,
 ID_Usuario_Login INT NOT NULL,
 INDEX idx_vcw_usuario (ID_Usuario_Login),
 CONSTRAINT fk_vcw_venta FOREIGN KEY (ID_Venta) REFERENCES Venta(ID_Venta)
) ENGINE=InnoDB;
