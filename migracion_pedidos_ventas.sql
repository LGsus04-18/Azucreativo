-- Importar en Azucreativo_db, despues de pedidos_cliente.sql y roles_empleados_caja.sql.
USE Azucreativo_db;
CREATE TABLE IF NOT EXISTS Pedido_Venta (
 ID_Pedido INT NOT NULL PRIMARY KEY,
 ID_Venta INT NOT NULL UNIQUE,
 CONSTRAINT fk_pv_pedido FOREIGN KEY (ID_Pedido) REFERENCES Pedido_Cliente(ID_Pedido),
 CONSTRAINT fk_pv_venta FOREIGN KEY (ID_Venta) REFERENCES Venta(ID_Venta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
