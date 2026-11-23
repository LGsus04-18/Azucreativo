<?php
declare(strict_types=1);
session_start();
if (!isset($_SESSION['usuario_id']) || !in_array(($_SESSION['rol'] ?? ''), ['administrador','empleado','cliente'], true)) {
    http_response_code(403); exit('Acceso no autorizado.');
}
require __DIR__ . '/config/conexion_azucreativo.php';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) { http_response_code(400); exit('Folio inválido.'); }
try {
    $stmt = $pdo->prepare('SELECT v.ID_Venta, v.Fecha_Venta, v.Metodo_Pago, v.Total, v.Estado_Venta, c.Nombre, c.Apellido FROM Venta v JOIN Cliente c ON c.ID_Cliente=v.ID_Cliente WHERE v.ID_Venta=?');
    $stmt->execute([$id]); $venta=$stmt->fetch(PDO::FETCH_ASSOC);
    if (!$venta) { http_response_code(404); exit('No existe el ticket solicitado.'); }
    if (($_SESSION['rol'] ?? '') === 'cliente') {
        $own=$pdo->prepare('SELECT 1 FROM Venta_Cliente_Web WHERE ID_Venta=? AND ID_Usuario_Login=?');
        $own->execute([$id,(int)$_SESSION['usuario_id']]);
        if (!$own->fetchColumn()) { http_response_code(403); exit('Solo puedes consultar tus propias compras.'); }
    }
    if (($_SESSION['rol'] ?? '') === 'empleado') {
        $own=$pdo->prepare('SELECT 1 FROM Venta_Empleado WHERE ID_Venta=? AND ID_Usuario=?');
        $own->execute([$id, (int)$_SESSION['usuario_id']]);
        if (!$own->fetchColumn()) {
            // El empleado también puede consultar tickets de compras web o invitados,
            // pero no tickets de ventas realizadas por otros empleados.
            $web=$pdo->prepare("SELECT 1 FROM Venta v JOIN Cliente c ON c.ID_Cliente=v.ID_Cliente LEFT JOIN Venta_Cliente_Web w ON w.ID_Venta=v.ID_Venta WHERE v.ID_Venta=? AND (w.ID_Venta IS NOT NULL OR (c.Nombre='Cliente' AND c.Apellido='Invitado')) AND NOT EXISTS (SELECT 1 FROM Venta_Empleado ve WHERE ve.ID_Venta=v.ID_Venta)");
            $web->execute([$id]);
            if (!$web->fetchColumn()) { http_response_code(403); exit('No tienes permiso para consultar este ticket.'); }
        }
    }
    $cardQuery=$pdo->prepare('SELECT Ultimos4 FROM Venta_Tarjeta_Demo WHERE ID_Venta=?');
    $cardQuery->execute([$id]); $tarjeta4=$cardQuery->fetchColumn();
    $stmt = $pdo->prepare('SELECT p.Nombre, d.Cantidad, d.Precio_Unitario, d.Subtotal FROM Detalle_Venta d JOIN Producto p ON p.ID_Producto=d.ID_Producto WHERE d.ID_Venta=? ORDER BY d.ID_DetalleVenta');
    $stmt->execute([$id]); $detalles=$stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { error_log('Ticket: '.$e->getMessage()); http_response_code(500); exit('No fue posible consultar el ticket.'); }
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function dinero($v): string { return '$'.number_format((float)$v,2); }
$recibido = filter_input(INPUT_GET, 'recibido', FILTER_VALIDATE_FLOAT);
$mostrarCambio = $venta['Metodo_Pago']==='Efectivo' && $recibido !== null && $recibido !== false && $recibido >= (float)$venta['Total'];
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Ticket #<?= h($id) ?> · Azucreativo</title>
<style>body{margin:0;background:#eef4ff;font:14px Arial,sans-serif;color:#172b4d}.ticket{box-sizing:border-box;width:80mm;max-width:100%;margin:20px auto;padding:16px;background:white;box-shadow:0 3px 20px #aac2e1}.logo{display:block;max-width:115px;max-height:110px;object-fit:contain;margin:0 auto 8px}h1{text-align:center;color:#1d4ed8;font-size:19px;margin:4px 0}p{text-align:center;margin:5px 0}.line{border-top:1px dashed #777;margin:12px 0}table{width:100%;border-collapse:collapse;font-size:12px}td,th{padding:5px 2px;text-align:right}td:first-child,th:first-child{text-align:left}.tot{font-weight:bold;font-size:16px}.actions{text-align:center;margin:15px}button{background:#2563eb;color:white;border:0;padding:11px 22px;border-radius:8px;cursor:pointer}@media print{@page{size:80mm auto;margin:3mm}body{background:white}.ticket{margin:0;padding:4mm;box-shadow:none;width:100%}.actions{display:none}}</style><link rel="stylesheet" href="/Azucreativo/config/avisos-legibles.css?v=20261122">
</head><body>
<div class="actions"><button onclick="window.print()">🖨️ Imprimir ticket</button></div>
<article class="ticket"><img class="logo" src="img/logo-azucreativo.jpeg" alt="Logo Azucreativo"><h1>AZUCREATIVO</h1><p>¡Gracias por tu compra!</p><div class="line"></div>
<div><strong>Ticket:</strong> #<?= h($venta['ID_Venta']) ?></div><div><strong>Fecha:</strong> <?= h($venta['Fecha_Venta']) ?></div><div><strong>Cliente:</strong> <?= h(trim($venta['Nombre'].' '.$venta['Apellido'])) ?></div><div><strong>Pago:</strong> <?= h($venta['Metodo_Pago']) ?></div>
<?php if($venta['Metodo_Pago']==='Tarjeta' && $tarjeta4): ?><div><strong>Tarjeta de prueba:</strong> •••• •••• •••• <?= h($tarjeta4) ?></div><div><small>SIMULACIÓN · Sin cargo bancario</small></div><?php endif; ?><div class="line"></div><table><thead><tr><th>Producto</th><th>Cant.</th><th>P. unit.</th><th>Importe</th></tr></thead><tbody><?php foreach($detalles as $d): ?><tr><td><?= h($d['Nombre']) ?></td><td><?= h($d['Cantidad']) ?></td><td><?= dinero($d['Precio_Unitario']) ?></td><td><?= dinero($d['Subtotal']) ?></td></tr><?php endforeach; ?></tbody></table><div class="line"></div>
<div class="tot" style="text-align:right">TOTAL: <?= dinero($venta['Total']) ?></div>
<?php if($mostrarCambio): ?><div style="text-align:right">Recibido: <?= dinero($recibido) ?></div><div style="text-align:right">Cambio: <?= dinero($recibido-(float)$venta['Total']) ?></div><?php endif; ?>
<div class="line"></div><p>Conserva tu ticket para cualquier aclaración.</p><p>¡Vuelve pronto!</p></article></body></html>
