<?php
session_start();
if (isset($_SESSION['usuario_id']) && ($_SESSION['rol'] ?? '') !== 'administrador') {
    if (str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/')) { http_response_code(403); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>false,'mensaje'=>'Acceso exclusivo para administradores.']); }
    else { header('Location: /Azucreativo/cliente/catalogo.php'); }
    exit;
}
header('Content-Type: application/json; charset=utf-8');
if(!isset($_SESSION['usuario_id'])){http_response_code(401);echo json_encode(['error'=>'Inicia sesión']);exit;}
require __DIR__.'/../../config/conexion_azucreativo.php';
try {
 $proveedores=$pdo->query('SELECT ID_Proveedor, Nombre FROM Proveedor ORDER BY Nombre')->fetchAll();
 $productos=$pdo->query('SELECT ID_Producto, Nombre FROM Producto ORDER BY Nombre')->fetchAll();
 echo json_encode(['proveedores'=>$proveedores,'productos'=>$productos],JSON_UNESCAPED_UNICODE);
} catch(Throwable $e){http_response_code(500);echo json_encode(['error'=>'No se pudieron cargar los catálogos']);}
