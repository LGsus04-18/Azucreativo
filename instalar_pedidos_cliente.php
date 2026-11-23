<?php
/** Instalador local de pedidos; eliminar tras utilizarlo. */
declare(strict_types=1);
session_start();
header('Content-Type: text/html; charset=utf-8');
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'], true)) { http_response_code(403); exit('Solo localhost.'); }
if (($_SESSION['rol'] ?? '') !== 'administrador') { http_response_code(403); exit('Inicia sesión como administrador.'); }
if (empty($_SESSION['instalar_pedidos_token'])) $_SESSION['instalar_pedidos_token'] = bin2hex(random_bytes(24));
function esc($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 if (!hash_equals($_SESSION['instalar_pedidos_token'], (string)($_POST['token'] ?? ''))) { http_response_code(403); exit('Token inválido.'); }
 try {
  require __DIR__.'/config/conexion_azucreativo.php';
  if (strcasecmp((string)$pdo->query('SELECT DATABASE()')->fetchColumn(), 'Azucreativo_db') !== 0) throw new RuntimeException('Conexión a otra base de datos.');
  $pdo->exec("CREATE TABLE IF NOT EXISTS `Pedido_Cliente` (
   `ID_Pedido` INT AUTO_INCREMENT PRIMARY KEY,
   `ID_Usuario_Login` INT NOT NULL,
   `Usuario` VARCHAR(100) NOT NULL,
   `Fecha` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
   `Metodo_Pago` ENUM('Efectivo','Tarjeta','Transferencia') NOT NULL,
   `Estado` ENUM('Pendiente','Confirmado','Cancelado','Entregado') NOT NULL DEFAULT 'Pendiente',
   `Total` DECIMAL(12,2) NOT NULL,
   INDEX `idx_pedido_usuario` (`ID_Usuario_Login`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $pdo->exec("CREATE TABLE IF NOT EXISTS `Pedido_Cliente_Detalle` (
   `ID_Detalle` INT AUTO_INCREMENT PRIMARY KEY,
   `ID_Pedido` INT NOT NULL,
   `ID_Producto` INT NOT NULL,
   `Nombre_Producto` VARCHAR(255) NOT NULL,
   `Cantidad` INT NOT NULL,
   `Precio_Unitario` DECIMAL(12,2) NOT NULL,
   `Subtotal` DECIMAL(12,2) NOT NULL,
   CONSTRAINT `fk_pedido_detalle` FOREIGN KEY (`ID_Pedido`) REFERENCES `Pedido_Cliente` (`ID_Pedido`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $required=['Pedido_Cliente'=>['ID_Pedido','ID_Usuario_Login','Usuario','Fecha','Metodo_Pago','Estado','Total'], 'Pedido_Cliente_Detalle'=>['ID_Detalle','ID_Pedido','ID_Producto','Nombre_Producto','Cantidad','Precio_Unitario','Subtotal']];
  foreach ($required as $tabla=>$columnas) {
   $q=$pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?'); $q->execute([$tabla]);
   $actual=array_map('strtolower',$q->fetchAll(PDO::FETCH_COLUMN));
   foreach ($columnas as $c) if (!in_array(strtolower($c),$actual,true)) throw new RuntimeException('La tabla '.$tabla.' ya existe pero falta la columna '.$c.'. No se alteraron columnas existentes.');
  }
  $mensaje='Tablas de pedidos verificadas correctamente. Ya puedes probar la página del cliente.';
 } catch (Throwable $e) { error_log('Instalador pedidos: '.$e->getMessage()); $mensaje='Error: '.$e->getMessage(); }
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Instalar pedidos Azucreativo</title><style>body{font:16px system-ui;background:#eff6ff;color:#1e3a8a;padding:30px}main{max-width:700px;margin:auto;background:white;padding:30px;border-radius:16px;box-shadow:0 8px 28px #1d4ed822}button{background:#2563eb;color:white;border:0;border-radius:8px;padding:12px 20px;cursor:pointer}p{line-height:1.6}</style><link rel="stylesheet" href="/Azucreativo/config/avisos-legibles.css?v=20261122">

<link rel="stylesheet" href="/Azucreativo/config/mobile-responsive.css?v=20261122logout2">
<script src="/Azucreativo/config/mobile-responsive.js?v=20261122" defer></script>
</head><body><main><h1>Instalar tablas de pedidos</h1><p>Crea o verifica Pedido_Cliente y Pedido_Cliente_Detalle en Azucreativo_db. No borra registros existentes. Haz una copia de seguridad antes de continuar.</p><?php if ($mensaje): ?><p><strong><?=esc($mensaje)?></strong></p><?php endif; ?><form method="post"><input type="hidden" name="token" value="<?=esc($_SESSION['instalar_pedidos_token'])?>"><button>Crear o verificar tablas de pedidos</button></form><p>Elimina este archivo cuando termines.</p></main></body></html>
