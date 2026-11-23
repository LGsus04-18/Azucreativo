-- Importar en Azucreativo_db desde phpMyAdmin. No altera usuarios ni tablas existentes.
CREATE TABLE IF NOT EXISTS Pedido_Cliente (
 ID_Pedido INT AUTO_INCREMENT PRIMARY KEY,
 ID_Usuario_Login INT NOT NULL,
 Usuario VARCHAR(100) NOT NULL,
 Fecha TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 Metodo_Pago ENUM('Efectivo','Tarjeta','Transferencia') NOT NULL,
 Estado ENUM('Pendiente','Confirmado','Cancelado','Entregado') NOT NULL DEFAULT 'Pendiente',
 Total DECIMAL(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS Pedido_Cliente_Detalle (
 ID_Detalle INT AUTO_INCREMENT PRIMARY KEY,
 ID_Pedido INT NOT NULL,
 ID_Producto INT NOT NULL,
 Nombre_Producto VARCHAR(255) NOT NULL,
 Cantidad INT NOT NULL,
 Precio_Unitario DECIMAL(12,2) NOT NULL,
 Subtotal DECIMAL(12,2) NOT NULL,
 CONSTRAINT fk_pedido_detalle FOREIGN KEY (ID_Pedido) REFERENCES Pedido_Cliente(ID_Pedido)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
