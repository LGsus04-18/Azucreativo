<?php
/** Instalador local y temporal. Eliminar al terminar. */
declare(strict_types=1);
session_start();
header('Content-Type: text/html; charset=utf-8');
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'], true)) {http_response_code(403);exit('Solo localhost.');}
if (($_SESSION['rol'] ?? '') !== 'administrador') {http_response_code(403);exit('Inicia sesión como administrador.');}
if (!isset($_SESSION['token_cliente_inst'])) $_SESSION['token_cliente_inst']=bin2hex(random_bytes(24));
function esc($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
$mensajes=[];$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
 if (!hash_equals($_SESSION['token_cliente_inst'],(string)($_POST['token']??''))) {http_response_code(403);exit('Token inválido');}
 try {
  require __DIR__.'/config/conexion_azucreativo.php';
  if (strcasecmp((string)$pdo->query('SELECT DATABASE()')->fetchColumn(),'Azucreativo_db')!==0) throw new RuntimeException('Conexión a base de datos incorrecta.');
  $getTable=$pdo->prepare('SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND LOWER(TABLE_NAME)=LOWER(?) LIMIT 1');
  $getColumn=$pdo->prepare('SELECT COLUMN_TYPE,IS_NULLABLE,COLUMN_KEY FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND LOWER(TABLE_NAME)=LOWER(?) AND LOWER(COLUMN_NAME)=LOWER(?) LIMIT 1');
  $table=function(string $name)use($getTable):array{$getTable->execute([$name]);return $getTable->fetch(PDO::FETCH_ASSOC)?:[];};
  $column=function(string $name,string $col)use($getColumn):array{$getColumn->execute([$name,$col]);return $getColumn->fetch(PDO::FETCH_ASSOC)?:[];};
  foreach (['Cliente'=>'ID_Cliente','Venta'=>'ID_Venta'] as $t=>$c) {
   $info=$table($t);$col=$column($t,$c);
   if (!$info||!$col) throw new RuntimeException("Falta la tabla $t o la columna $c. No se modificó la base de datos.");
   if (strtolower((string)$info['ENGINE'])!=='innodb') throw new RuntimeException("La tabla $t debe usar InnoDB para agregar claves foráneas. No se modificó.");
   if (!preg_match('/^(tinyint|smallint|mediumint|int|bigint)(\(\d+\))?( unsigned)?$/i',(string)$col['COLUMN_TYPE'])) throw new RuntimeException("Tipo de $t.$c no compatible con este instalador: ".$col['COLUMN_TYPE']);
  }
  $key=$pdo->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND LOWER(TABLE_NAME)=LOWER(?) AND LOWER(COLUMN_NAME)=LOWER(?) AND SEQ_IN_INDEX=1");
  foreach (['Cliente'=>'ID_Cliente','Venta'=>'ID_Venta'] as $t=>$c){$key->execute([$t,$c]);if(!(int)$key->fetchColumn())throw new RuntimeException("$t.$c necesita un índice para la relación. No se modificó.");}
  $cType=$column('Cliente','ID_Cliente')['COLUMN_TYPE'];$vType=$column('Venta','ID_Venta')['COLUMN_TYPE'];
  $pdo->exec('CREATE TABLE IF NOT EXISTS `Cliente_Usuario_Web` (`ID_Usuario_Login` INT NOT NULL PRIMARY KEY, `ID_Cliente` '. $cType .' NOT NULL UNIQUE, CONSTRAINT `fk_cuw_cliente` FOREIGN KEY (`ID_Cliente`) REFERENCES `Cliente`(`ID_Cliente`)) ENGINE=InnoDB');
  $mensajes[]='Tabla Cliente_Usuario_Web creada o encontrada.';
  $pdo->exec('CREATE TABLE IF NOT EXISTS `Venta_Cliente_Web` (`ID_Venta` '. $vType .' NOT NULL PRIMARY KEY, `ID_Usuario_Login` INT NOT NULL, INDEX `idx_vcw_usuario` (`ID_Usuario_Login`), CONSTRAINT `fk_vcw_venta` FOREIGN KEY (`ID_Venta`) REFERENCES `Venta`(`ID_Venta`)) ENGINE=InnoDB');
  $mensajes[]='Tabla Venta_Cliente_Web creada o encontrada.';
  foreach (['Cliente_Usuario_Web'=>['ID_Cliente'=>$cType],'Venta_Cliente_Web'=>['ID_Venta'=>$vType]] as $t=>$cols){foreach($cols as $c=>$expected){$actual=$column($t,$c);if(!$actual||strtolower((string)$actual['COLUMN_TYPE'])!==strtolower($expected))throw new RuntimeException("$t.$c existe con un tipo distinto ($actual[COLUMN_TYPE]); se requiere revisar los registros antes de modificarlo.");}}
  $checks=[['Cliente_Usuario_Web','ID_Cliente','Cliente','ID_Cliente','fk_cuw_cliente'],['Venta_Cliente_Web','ID_Venta','Venta','ID_Venta','fk_vcw_venta']];
  $fk=$pdo->prepare('SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND LOWER(TABLE_NAME)=LOWER(?) AND LOWER(COLUMN_NAME)=LOWER(?) AND LOWER(REFERENCED_TABLE_NAME)=LOWER(?) AND LOWER(REFERENCED_COLUMN_NAME)=LOWER(?)');
  foreach($checks as [$child,$cc,$parent,$pc,$constraint]) {
   $fk->execute([$child,$cc,$parent,$pc]);
   if(!(int)$fk->fetchColumn()) {
    $pdo->exec('ALTER TABLE `'.$child.'` ADD CONSTRAINT `'.$constraint.'` FOREIGN KEY (`'.$cc.'`) REFERENCES `'.$parent.'`(`'.$pc.'`)');
    $mensajes[]='Relación '.$child.' → '.$parent.' creada.';
   } else $mensajes[]='Relación '.$child.' → '.$parent.' verificada.';
  }
  $mensajes[]='Listo. Elimina este instalador después de comprobar el acceso del cliente.';
 }catch(Throwable $e){$error=$e->getMessage();error_log('Instalador cliente: '.$error);}
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Reparar ventas cliente · Azucreativo</title><style>body{font:16px system-ui;background:#eff6ff;color:#172554;padding:40px 16px}.panel{max-width:720px;margin:auto;background:white;padding:28px;border-radius:16px;box-shadow:0 12px 35px #1d4ed822}button{background:#2563eb;color:white;border:0;padding:12px 20px;border-radius:9px;cursor:pointer}.ok{color:#166534}.error{color:#991b1b;background:#fee2e2;padding:12px;border-radius:8px}li{margin:8px 0}</style><link rel="stylesheet" href="/Azucreativo/config/avisos-legibles.css?v=20261122">

<link rel="stylesheet" href="/Azucreativo/config/mobile-responsive.css?v=20261122logout2">
<script src="/Azucreativo/config/mobile-responsive.js?v=20261122" defer></script>
</head><body><main class="panel"><h1>Reparar conexiones de ventas de clientes</h1><p>Comprueba las tablas Cliente y Venta y crea las relaciones de Cliente_Usuario_Web y Venta_Cliente_Web con el tipo real de sus identificadores.</p><p><strong>Antes de continuar:</strong> exporta una copia de seguridad de Azucreativo_db. No elimina datos ni altera las tablas principales.</p><?php if($error):?><p class="error"><?=esc($error)?></p><?php endif;?><?php if($mensajes):?><ul class="ok"><?php foreach($mensajes as $m):?><li><?=esc($m)?></li><?php endforeach;?></ul><?php endif;?><form method="post"><input type="hidden" name="token" value="<?=esc($_SESSION['token_cliente_inst'])?>"><button type="submit">Crear o verificar conexiones</button></form><p><small>Solo funciona desde localhost con sesión de administrador. Eliminar después de usar.</small></p></main></body></html>
