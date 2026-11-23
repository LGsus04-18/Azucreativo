-- Ejecutar DESPUÉS de importar las bases de datos originales. Haz copia de seguridad.
USE login_db;
ALTER TABLE usuarios MODIFY COLUMN rol ENUM('administrador','cliente','empleado') NOT NULL DEFAULT 'cliente';
-- Crea una cuenta de empleado desde phpMyAdmin usando un usuario EXISTENTE:
-- UPDATE usuarios SET rol='empleado' WHERE usuario='nombre_empleado';
USE Azucreativo_db;
CREATE TABLE IF NOT EXISTS Corte_Caja (
 ID_Corte INT AUTO_INCREMENT PRIMARY KEY,
 ID_Usuario INT NOT NULL,
 Fecha_Inicio DATETIME NOT NULL,
 Fecha_Cierre DATETIME NOT NULL,
 Num_Ventas INT NOT NULL,
 Total_Efectivo DECIMAL(12,2) NOT NULL,
 Total_Tarjeta DECIMAL(12,2) NOT NULL,
 Total_Transferencia DECIMAL(12,2) NOT NULL,
 Efectivo_Contado DECIMAL(12,2) NOT NULL,
 Diferencia DECIMAL(12,2) NOT NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS Venta_Empleado (
 ID_Venta INT PRIMARY KEY,
 ID_Usuario INT NOT NULL,
 ID_Corte INT NULL,
 INDEX ix_vendedor_corte (ID_Usuario,ID_Corte),
 CONSTRAINT fk_venta_empleado_venta FOREIGN KEY (ID_Venta) REFERENCES Venta(ID_Venta),
 CONSTRAINT fk_venta_empleado_corte FOREIGN KEY (ID_Corte) REFERENCES Corte_Caja(ID_Corte)
) ENGINE=InnoDB;
