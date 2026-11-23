<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "1. PHP funciona<br>";
require 'conexion.php';
echo "2. Conexión OK<br>";

$r = $pdo->query("SELECT COUNT(*) AS total FROM usuarios")->fetch();
echo "3. Usuarios en la tabla: " . $r['total'];