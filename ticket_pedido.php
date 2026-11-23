<?php
declare(strict_types=1);
session_start();
if(!isset($_SESSION['usuario_id'])){http_response_code(403);exit('Inicia sesión.');}
require __DIR__.'/config/conexion_azucreativo.php';
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
if(!$id){http_response_code(400);exit('Folio inválido.');}
$q=$pdo->prepare('SELECT * FROM Pedido_Cliente WHERE ID_Pedido=?');$q->execute([$id]);$pedido=$q->fetch(PDO::FETCH_ASSOC);
if(!$pedido){http_response_code(404);exit('Pedido no encontrado.');}
$rol=$_SESSION['rol']??'';
if($rol==='cliente'&&(int)$pedido['ID_Usuario_Login']!==(int)$_SESSION['usuario_id']){http_response_code(403);exit('No puedes consultar este pedido.');}
if(!in_array($rol,['cliente','empleado','administrador'],true)){http_response_code(403);exit('Acceso no autorizado.');}
$q=$pdo->prepare('SELECT Nombre_Producto,Cantidad,Precio_Unitario,Subtotal FROM Pedido_Cliente_Detalle WHERE ID_Pedido=?');$q->execute([$id]);$items=$q->fetchAll(PDO::FETCH_ASSOC);
$ventaId=null;try{$q=$pdo->prepare('SELECT ID_Venta FROM Pedido_Venta WHERE ID_Pedido=?');$q->execute([$id]);$ventaId=$q->fetchColumn();}catch(Throwable $e){error_log('Pedido_Venta: '.$e->getMessage());}
function h($x){return htmlspecialchars((string)$x,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function pesos($x){return '$'.number_format((float)$x,2);}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><title>Comprobante de pedido #<?=h($id)?></title><style>body{font:14px Arial;background:#eaf2ff;color:#18335e}.ticket{width:80mm;box-sizing:border-box;padding:16px;margin:20px auto;background:white;box-shadow:0 2px 15px #aac}img{display:block;max-width:110px;max-height:100px;margin:auto}h2,p{text-align:center}h2{color:#245cc1}table{width:100%;font-size:12px}td{text-align:right;padding:5px 2px}td:first-child{text-align:left}.line{border-top:1px dashed #888;margin:12px 0}button{display:block;margin:15px auto;background:#2563eb;color:white;border:0;border-radius:6px;padding:10px 22px}@media print{body{background:white}.ticket{margin:0;box-shadow:none}button{display:none}}</style><link rel="stylesheet" href="/Azucreativo/config/avisos-legibles.css?v=20261122">
</head><body><button onclick="window.print()">Imprimir comprobante</button><article class="ticket"><img src="img/logo-azucreativo.jpeg" alt="Logo Azucreativo"><h2>AZUCREATIVO</h2><p><strong><?= $ventaId?'PEDIDO CONFIRMADO':'COMPROBANTE DE PEDIDO · NO ES TICKET DE PAGO' ?></strong></p><div class="line"></div><div>Pedido #<?=h($id)?></div><div>Fecha: <?=h($pedido['Fecha'])?></div><div>Cliente: <?=h($pedido['Usuario'])?></div><div>Método elegido: <?=h($pedido['Metodo_Pago'])?></div><div>Estado: <?=h($pedido['Estado'])?></div><div class="line"></div><table><?php foreach($items as $x):?><tr><td><?=h($x['Nombre_Producto'])?> × <?=h($x['Cantidad'])?></td><td><?=pesos($x['Subtotal'])?></td></tr><?php endforeach;?></table><div class="line"></div><h3 style="text-align:right">Total <?=pesos($pedido['Total'])?></h3><?php if($ventaId):?><p>Ticket de venta #<?=h($ventaId)?></p><?php else:?><p>El pago y las existencias se confirmarán en tienda. No se ha realizado ningún cargo.</p><?php endif;?></article></body></html>