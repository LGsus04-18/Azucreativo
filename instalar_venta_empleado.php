<?php
/** Instalador de tablas faltantes para Azucreativo. ELIMINAR DESPUÉS DE USAR. */
session_start();
header('Content-Type: text/html; charset=utf-8');
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) { http_response_code(403); exit('Solo disponible desde localhost.'); }
if (($_SESSION['rol'] ?? '') !== 'administrador') { http_response_code(403); exit('Inicia sesión como administrador antes de abrir este archivo.'); }
if (empty($_SESSION['instalar_venta_token'])) $_SESSION['instalar_venta_token'] = bin2hex(random_bytes(24));
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$resultado = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!hash_equals($_SESSION['instalar_venta_token'], $_POST['token'] ?? '')) { http_response_code(403); exit('Solicitud inválida.'); }
  try {
    require __DIR__ . '/config/conexion_azucreativo.php';
    if (!isset($pdo) || !($pdo instanceof PDO)) throw new RuntimeException('No se encontró la conexión PDO.');
    $bd = $pdo->query('SELECT DATABASE()')->fetchColumn();
    if (strcasecmp((string)$bd, 'Azucreativo_db') !== 0) throw new RuntimeException('La conexión apunta a otra base de datos: ' . $bd);
    $existeVenta = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND LOWER(table_name) = 'venta'")->fetchColumn();
    if (!$existeVenta) throw new RuntimeException('No existe la tabla Venta. No se modificó nada.');
    $columna = $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND LOWER(table_name)='venta' AND LOWER(column_name)='id_venta'")->fetchColumn();
    if (!$columna) throw new RuntimeException('La tabla Venta no tiene la columna ID_Venta. No se modificó nada.');
    $pdo->exec('CREATE TABLE IF NOT EXISTS `Corte_Caja` (`ID_Corte` INT AUTO_INCREMENT PRIMARY KEY, `ID_Usuario` INT NOT NULL, `Fecha_Inicio` DATETIME NOT NULL, `Fecha_Cierre` DATETIME NOT NULL, `Num_Ventas` INT NOT NULL, `Total_Efectivo` DECIMAL(12,2) NOT NULL, `Total_Tarjeta` DECIMAL(12,2) NOT NULL, `Total_Transferencia` DECIMAL(12,2) NOT NULL, `Efectivo_Contado` DECIMAL(12,2) NOT NULL, `Diferencia` DECIMAL(12,2) NOT NULL) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE IF NOT EXISTS `Venta_Empleado` (`ID_Venta` INT PRIMARY KEY, `ID_Usuario` INT NOT NULL, `ID_Corte` INT NULL, INDEX `ix_vendedor_corte` (`ID_Usuario`,`ID_Corte`), CONSTRAINT `fk_venta_empleado_venta` FOREIGN KEY (`ID_Venta`) REFERENCES `Venta`(`ID_Venta`), CONSTRAINT `fk_venta_empleado_corte` FOREIGN KEY (`ID_Corte`) REFERENCES `Corte_Caja`(`ID_Corte`)) ENGINE=InnoDB');
    // Compatibilidad con tablas antiguas que usan ID_Empleado.
    $cols = $pdo->query("SELECT LOWER(COLUMN_NAME) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND LOWER(TABLE_NAME)='venta_empleado'")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('id_usuario', $cols, true)) {
      if (!in_array('id_empleado', $cols, true)) throw new RuntimeException('Venta_Empleado no tiene ID_Empleado ni ID_Usuario.');
      $pdo->exec('ALTER TABLE `Venta_Empleado` ADD COLUMN `ID_Usuario` INT NULL');
      $pdo->exec('UPDATE `Venta_Empleado` SET `ID_Usuario`=`ID_Empleado` WHERE `ID_Usuario` IS NULL');
    }
    if (!in_array('id_corte', $cols, true)) $pdo->exec('ALTER TABLE `Venta_Empleado` ADD COLUMN `ID_Corte` INT NULL');
    $resultado = 'Correcto: Venta_Empleado tiene ID_Usuario e ID_Corte. Elimina este instalador al terminar.';
  } catch (Throwable $e) {
    $resultado = 'No se pudo completar: ' . $e->getMessage() . '. Revisa la estructura de las tablas en phpMyAdmin.';
  }
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Instalar tablas de caja · Azucreativo</title><style>body{font:16px system-ui;background:#eff6ff;color:#172554;margin:0;padding:48px 16px}.panel{max-width:650px;margin:auto;background:white;border-radius:16px;padding:30px;box-shadow:0 10px 30px #1d4ed822}button{background:#2563eb;color:white;border:0;border-radius:8px;padding:12px 20px;cursor:pointer}.note{background:#dbeafe;padding:12px;border-radius:8px}p{line-height:1.6}</style><link rel="stylesheet" href="/Azucreativo/config/avisos-legibles.css?v=20261122">

<link rel="stylesheet" href="/Azucreativo/config/mobile-responsive.css?v=20261122logout2">
<script src="/Azucreativo/config/mobile-responsive.js?v=20261122" defer></script>
</head><body><main class="panel"><h1>Instalar tablas de ventas y caja</h1><p>Esta herramienta crea las tablas faltantes <strong>Venta_Empleado</strong> y <strong>Corte_Caja</strong> en <code>Azucreativo_db</code>, sin borrar registros existentes.</p><p class="note">Antes de continuar, realiza una copia de seguridad de la base de datos.</p><?php if ($resultado): ?><p class="note"><?= h($resultado) ?></p><?php endif; ?><form method="post"><input type="hidden" name="token" value="<?= h($_SESSION['instalar_venta_token']) ?>"><button type="submit">Crear o verificar tablas</button></form><p><small>Por seguridad, solo funciona desde localhost con sesión de administrador. Borra este archivo después de utilizarlo.</small></p></main></body></html>
