<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}
$r=$_SESSION['rol']??'cliente';
header('Location: '.($r==='administrador'?'/Azucreativo/index.php':($r==='empleado'?'/Azucreativo/empleado/index.php':'/Azucreativo/cliente/catalogo.php'))); exit;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel</title>
<link rel="stylesheet" href="/Azucreativo/config/avisos-legibles.css?v=20261122">
</head>
<body>
    <h1>Bienvenido, <?= htmlspecialchars($_SESSION['usuario']) ?> 👋</h1>
    <p>Esta página solo la ven usuarios que iniciaron sesión.</p>
    <a href="logout.php">Cerrar sesión</a>
</body>
</html>