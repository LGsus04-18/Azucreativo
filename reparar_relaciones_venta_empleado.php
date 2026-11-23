<?php
declare(strict_types=1);
session_start();
header('Content-Type: text/html; charset=utf-8');
if (!in_array($_SERVER['REMOTE_ADDR']??'', ['127.0.0.1','::1'],true) || ($_SESSION['rol']??'')!=='administrador') {http_response_code(403);exit('Acceso restringido a administrador desde localhost.');}
if(!isset($_SESSION['csrf_reparacion_ve'])) $_SESSION['csrf_reparacion_ve']=bin2hex(random_bytes(32));
function esc($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
$messages=[];$errors=[];
try {
 require __DIR__.'/config/conexion_azucreativo.php';
 if(strcasecmp((string)$pdo->query('SELECT DATABASE()')->fetchColumn(),'azucreativo_db')!==0)throw new RuntimeException('Conexión a base incorrecta.');
 $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
 // Resolver nombres reales (MySQL Linux puede distinguir mayúsculas/minúsculas).
 $all=$pdo->query('SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()')->fetchAll(PDO::FETCH_ASSOC);
 $actual=[];
 foreach($all as $t){$actual[strtolower($t['TABLE_NAME'])]=$t;}
 $table=function(string $logical)use($actual):string{
   $r=$actual[strtolower($logical)]??null;
   if(!$r)throw new RuntimeException("No existe la tabla $logical en la base seleccionada.");
   if(strcasecmp((string)$r['ENGINE'],'InnoDB')!==0)throw new RuntimeException("La tabla $logical usa motor ".$r['ENGINE']." (se requiere InnoDB).");
   return $r['TABLE_NAME'];
 };
 $venta=$table('venta');$ve=$table('venta_empleado');$corte=$table('corte_caja');
 $quote=function(string $name):string{return '`'.str_replace('`','``',$name).'`';};
 $colStmt=$pdo->prepare('SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND BINARY TABLE_NAME=BINARY ?');
 $column=function(string $t,string $c)use($colStmt):array{
   $colStmt->execute([$t]);
   foreach($colStmt->fetchAll(PDO::FETCH_ASSOC) as $r)if(strcasecmp($r['COLUMN_NAME'],$c)===0)return $r;
   throw new RuntimeException("Falta la columna $t.$c");
 };
 $fkStmt=$pdo->prepare('SELECT CONSTRAINT_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND BINARY TABLE_NAME=BINARY ? AND BINARY COLUMN_NAME=BINARY ? AND REFERENCED_TABLE_NAME IS NOT NULL');
 $relations=[['ID_Venta',$venta,'ID_Venta','fk_ve_venta_reparada'],['ID_Corte',$corte,'ID_Corte','fk_ve_corte_reparada']];
 foreach($relations as [$cc,$parent,$pc]){$column($ve,$cc);$column($parent,$pc);}
 $messages[]="Tablas localizadas: $venta, $ve, $corte (InnoDB).";
 if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!hash_equals($_SESSION['csrf_reparacion_ve'],(string)($_POST['csrf']??'')))throw new RuntimeException('Token inválido.');
  foreach($relations as [$cc,$parent,$pc,$constraint]){
   try {
    $source=$column($ve,$cc);$target=$column($parent,$pc);
    $type=$target['COLUMN_TYPE'];
    if(!preg_match('/^(tinyint|smallint|mediumint|int|bigint)(\(\d+\))?( unsigned)?$/i',$type))throw new RuntimeException("Tipo no admitido: $type");
    $childCol=$source['COLUMN_NAME'];$parentCol=$target['COLUMN_NAME'];
    $v=$quote($ve);$p=$quote($parent);$ccq=$quote($childCol);$pcq=$quote($parentCol);
    $fkStmt->execute([$ve,$childCol]);$existing=$fkStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($existing as $f){
      if(strcasecmp($f['REFERENCED_TABLE_NAME'],$parent)===0 && strcasecmp($f['REFERENCED_COLUMN_NAME'],$parentCol)===0){$messages[]="Ya existe la relación $ve.$cc → $parent.$pc";continue 2;}
    }
    if($existing)throw new RuntimeException('La columna ya tiene una FK hacia otra tabla; requiere revisión manual.');
    $orphans=(int)$pdo->query("SELECT COUNT(*) FROM $v x LEFT JOIN $p y ON x.$ccq=y.$pcq WHERE x.$ccq IS NOT NULL AND y.$pcq IS NULL")->fetchColumn();
    if($orphans)throw new RuntimeException("Hay $orphans registros huérfanos. No se realizó ningún cambio en esta relación.");
    if(stripos($type,'unsigned')!==false){
      $negative=(int)$pdo->query("SELECT COUNT(*) FROM $v WHERE $ccq < 0")->fetchColumn();
      if($negative)throw new RuntimeException("Hay $negative valores negativos; conversión UNSIGNED no segura.");
    }
    if(strcasecmp($source['COLUMN_TYPE'],$type)!==0){
      $null=$source['IS_NULLABLE']==='YES'?'NULL':'NOT NULL';
      $pdo->exec("ALTER TABLE $v MODIFY COLUMN $ccq $type $null");
      $messages[]="Corregido $ve.$cc: $type $null";
    }
    // InnoDB crea un índice auxiliar si no existe uno adecuado.
    $pdo->exec("ALTER TABLE $v ADD CONSTRAINT ".$quote($constraint)." FOREIGN KEY ($ccq) REFERENCES $p ($pcq) ON UPDATE CASCADE ".($cc==='ID_Corte'?'ON DELETE SET NULL':'ON DELETE RESTRICT'));
    $messages[]="Creada relación $ve.$cc → $parent.$pc";
   }catch(Throwable $e){$errors[]="$cc: ".$e->getMessage();}
  }
 }else $messages[]='Diagnóstico preparado. Haz respaldo y presiona Reparar.';
}catch(Throwable $e){$errors[]=$e->getMessage();}
?><!doctype html><html lang="es"><meta charset="utf-8"><title>Reparar relaciones de ventas de empleados</title><style>body{font:16px system-ui;background:#eff6ff;padding:24px}main{max-width:850px;margin:auto;background:white;padding:25px;border-radius:12px}li{margin:8px 0}.error{color:#991b1b}button{padding:12px;background:#2563eb;color:white;border:0;border-radius:8px}</style><main><h1>Reparar Venta_Empleado</h1><p><strong>Antes de continuar, exporta Azucreativo_db desde phpMyAdmin.</strong> Esta reparación comprueba referencias huérfanas y valores negativos antes de cambiar tipos y crear claves foráneas. Los cambios DDL de MySQL no se revierten automáticamente.</p><form method="post"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf_reparacion_ve']??'')?>"><button>Reparar relaciones compatibles</button></form><h2>Resultados</h2><ul><?php foreach($messages as $m):?><li><?=esc($m)?></li><?php endforeach;?></ul><?php if($errors):?><h2>Requieren revisión</h2><ul class="error"><?php foreach($errors as $e):?><li><?=esc($e)?></li><?php endforeach;?></ul><?php endif;?><p>Después de usar, elimina este instalador.</p></main></html>
