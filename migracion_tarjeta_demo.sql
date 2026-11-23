-- Ejecutar una sola vez en phpMyAdmin, después de respaldar la base.
USE azucreativo_db;
CREATE TABLE IF NOT EXISTS Venta_Tarjeta_Demo (
 ID_Venta INT(10) UNSIGNED NOT NULL PRIMARY KEY,
 Ultimos4 CHAR(4) NOT NULL,
 CONSTRAINT fk_tarjeta_demo_venta FOREIGN KEY (ID_Venta) REFERENCES Venta(ID_Venta) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Nunca almacenar número completo, CVV, nombre ni vencimiento.
