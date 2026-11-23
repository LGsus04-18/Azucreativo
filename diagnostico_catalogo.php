<?php
session_start();
if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol'] ?? '') !== 'administrador') { http_response_code(403); exit('Acceso exclusivo para administradores.'); }
header('Content-Type: text/html; charset=utf-8');
function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$checks=[];
try {
    require __DIR__.'/config/conexion_azucreativo.php';
    $checks[]=['Conexión MySQL','Correcta'];
    $checks[]=['Base seleccionada',(string)$pdo->query('SELECT DATABASE()')->fetchColumn()];
    $tablas=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $checks[]=['Tablas disponibles',implode(', ', $tablas) ?: '(ninguna)'];
    $producto=array_values(array_filter($tablas,fn($t)=>strcasecmp($t,'Producto')===0));
    if (!$producto) { $checks[]=['Tabla Producto','NO EXISTE: importa el esquema de Azucreativo en esta base de datos']; }
    else {
        $cols=$pdo->query('SHOW COLUMNS FROM `'.str_replace('`','``',$producto[0]).'`')->fetchAll(PDO::FETCH_COLUMN);
        $checks[]=['Columnas de Producto',implode(', ', $cols)];
        $faltan=array_diff(['ID_Producto','Nombre','Descripcion','Precio','Stock'], $cols);
        $checks[]=['Columnas necesarias',$faltan?'FALTAN: '.implode(', ', $faltan):'Todas presentes'];
        $checks[]=['Productos registrados',(string)$pdo->query('SELECT COUNT(*) FROM `'.str_replace('`','``',$producto[0]).'`')->fetchColumn()];
        if (!$faltan) {
            $rows=$pdo->query('SELECT ID_Producto,Nombre,Precio,Stock FROM `'.str_replace('`','``',$producto[0]).'` ORDER BY ID_Producto DESC LIMIT 5')->fetchAll();
            $checks[]=['Últimos productos', $rows ? implode(' | ',array_map(fn($r)=>$r['ID_Producto'].': '.$r['Nombre'].' ($'.$r['Precio'].', stock '.$r['Stock'].')',$rows)) : 'No hay registros'];
        }
    }
} catch (Throwable $e) { error_log('Diagnóstico Azucreativo: '.$e->getMessage()); $checks[]=['Error','No se pudo completar la consulta. Revisa el registro de errores PHP y la configuración de MySQL.']; }
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Diagnóstico de catálogo · Azucreativo</title><style>body{font-family:system-ui;background:#edf4ff;color:#163d73;margin:0;padding:30px}main{max-width:900px;margin:auto;background:white;padding:30px;border-radius:18px;box-shadow:0 8px 30px #1b4e9220}h1{margin-top:0}table{width:100%;border-collapse:collapse;table-layout:fixed}th,td{border-bottom:1px solid #dce9fa;padding:13px;text-align:left;overflow-wrap:anywhere}th{width:32%;background:#f4f8ff}a{color:#1954b0}</style><link rel="stylesheet" href="/Azucreativo/config/avisos-legibles.css?v=20261122">

<link rel="stylesheet" href="/Azucreativo/config/mobile-responsive.css?v=20261122logout2">
<script src="/Azucreativo/config/mobile-responsive.js?v=20261122" defer></script>
</head><body><main><h1>Diagnóstico de catálogo</h1><p>Esta página muestra la conexión y la estructura de la base de datos, sin contraseñas.</p><table><?php foreach($checks as $c): ?><tr><th><?=h($c[0])?></th><td><?=h($c[1])?></td></tr><?php endforeach; ?></table><p>Si la tabla Producto no existe o tiene columnas distintas, comparte esta información para adaptar el sistema a tu base real.</p><p><a href="index.php">Volver al inicio</a></p></main></body></html>
