<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "1. Inicio<br>";
session_start();
echo "2. Sesión OK<br>";
require 'conexion.php';
echo "3. Conexión OK<br>";

$stmt = $pdo->prepare("SELECT id, usuario, correo, password FROM usuarios WHERE usuario = :u OR correo = :c LIMIT 1");
$stmt->execute([':u' => 'admin', ':c' => 'admin']);
$fila = $stmt->fetch();
echo "4. Consulta OK<br>";

var_dump($fila ? 'Usuario encontrado' : 'No encontrado');