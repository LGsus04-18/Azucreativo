<?php
session_start();
if (isset($_SESSION['usuario_id']) && ($_SESSION['rol'] ?? '') !== 'administrador') {
    if (str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/')) { http_response_code(403); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>false,'mensaje'=>'Acceso exclusivo para administradores.']); }
    else { header('Location: /Azucreativo/cliente/catalogo.php'); }
    exit;
}

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

require __DIR__ . '/../../config/conexion_azucreativo.php';


$where  = [];
$params = [];

if (($_GET['id_inventario'] ?? '') !== '') {
    $where[] = 'ID_Inventario = :idi';
    $params[':idi'] = (int) $_GET['id_inventario'];
}
if (($_GET['id_producto'] ?? '') !== '') {
    $where[] = 'ID_Producto = :idp';
    $params[':idp'] = (int) $_GET['id_producto'];
}
if (($_GET['cantidad'] ?? '') !== '') {
    $where[] = 'Cantidad = :cantidad';
    $params[':cantidad'] = (int) $_GET['cantidad'];
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['fecha_entrada'] ?? '')) {
    $where[] = 'DATE(Fecha_Entrada) = :fe';
    $params[':fe'] = $_GET['fecha_entrada'];
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['fecha_salida'] ?? '')) {
    $where[] = 'DATE(Fecha_Salida) = :fs';
    $params[':fs'] = $_GET['fecha_salida'];
}

$sql = "SELECT Inventario.*, Producto.Nombre AS Nombre_Producto FROM Inventario LEFT JOIN Producto ON Inventario.ID_Producto = Producto.ID_Producto";
$where = array_map(fn($w) => preg_replace('/(?<![.\w])(?:ID_Inventario|ID_Producto|Cantidad|Fecha_Entrada|Fecha_Salida)(?![\w])/', 'Inventario.$0', $w), $where);
if ($where) {
    $sql .= " WHERE " . implode(' AND ', $where);
}

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode($stmt->fetchAll());
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al consultar la tabla']);
}