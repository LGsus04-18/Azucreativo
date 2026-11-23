-- Datos opcionales para probar el registro de ventas.
-- Importa este archivo DESPUÉS de crear Azucreativo_db y sus tablas.
USE Azucreativo_db;

INSERT INTO Categoria (Nombre, Descripcion)
SELECT 'Dulces de prueba', 'Categoría de ejemplo para verificar el módulo de ventas'
WHERE NOT EXISTS (SELECT 1 FROM Categoria WHERE Nombre = 'Dulces de prueba');

SET @id_categoria_prueba = (SELECT ID_Categoria FROM Categoria WHERE Nombre = 'Dulces de prueba' ORDER BY ID_Categoria LIMIT 1);

INSERT INTO Producto (ID_Categoria, Nombre, Descripcion, Precio, Stock)
SELECT @id_categoria_prueba, 'Paleta de prueba', 'Producto de ejemplo para validar una venta', 5.00, 100
WHERE NOT EXISTS (SELECT 1 FROM Producto WHERE Nombre = 'Paleta de prueba');
