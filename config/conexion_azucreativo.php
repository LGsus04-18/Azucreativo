<?php
// Conexión compartida a la base de datos del sistema Azucreativo.
$host = 'localhost';
$port = '3306';
$dbname = 'Azucreativo_db';
$user = 'root';
$password = '';

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    // No mostrar credenciales ni detalles internos de la conexión al usuario.
    exit('No se pudo conectar con Azucreativo_db. Verifica que MySQL esté iniciado en XAMPP y que la base exista.');
}
