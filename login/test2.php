<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'conexion.php';

echo "<h3>Columnas de la tabla usuarios:</h3>";
$cols = $pdo->query("SHOW COLUMNS FROM usuarios")->fetchAll();
foreach ($cols as $c) {
    echo $c['Field'] . "<br>";
}

echo "<h3>Contenido:</h3>";
$filas = $pdo->query("SELECT * FROM usuarios")->fetchAll();
foreach ($filas as $f) {
    echo "id: " . $f['id'] . "<br>";
    echo "password (inicio): " . substr($f['password'] ?? 'NO EXISTE', 0, 10) . "<br>";
}

echo "<h3>Prueba de contraseña admin123:</h3>";
$hash = $filas[0]['password'] ?? '';
echo password_verify('admin123', $hash) ? "✅ Coincide" : "❌ No coincide";