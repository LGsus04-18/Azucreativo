<?php
/** Diagnóstico no destructivo y reparación selectiva. Ejecutar solo desde CLI. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Solo CLI'); }
$fix = in_array('--reparar', $argv, true);
$report = []; $issues=0;
function logLine(string $status,string $message): void { global $report,$issues; $line="[$status] $message"; $report[]=$line; echo $line.PHP_EOL; if($status==='ERROR'||$status==='AVISO')$issues++; }
function dbConnect(string $name): ?PDO {
 try { $host=getenv('AZ_DB_HOST')?:'localhost';$port=getenv('AZ_DB_PORT')?:'3306';$user=getenv('AZ_DB_USER')?:'root';$pass=getenv('AZ_DB_PASS')?:'';
  return new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
 } catch(Throwable $e) {logLine('ERROR',"No se pudo conectar a $name: ".$e->getMessage());return null;}
}
function tableInfo(PDO $db,string $name): ?array { $s=$db->prepare('SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND LOWER(TABLE_NAME)=LOWER(?) LIMIT 1');$s->execute([$name]);return $s->fetch()?:null; }
function colInfo(PDO $db,string $table,string $column): ?array {$s=$db->prepare('SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_KEY FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND LOWER(TABLE_NAME)=LOWER(?) AND LOWER(COLUMN_NAME)=LOWER(?) LIMIT 1');$s->execute([$table,$column]);return $s->fetch()?:null;}
function foreignKey(PDO $db,string $table,string $column,string $ref):bool {$s=$db->prepare('SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND LOWER(TABLE_NAME)=LOWER(?) AND LOWER(COLUMN_NAME)=LOWER(?) AND LOWER(REFERENCED_TABLE_NAME)=LOWER(?)');$s->execute([$table,$column,$ref]);return (int)$s->fetchColumn()>0;}
function sql(PDO $db,string $query,string $message):bool {try{$db->exec($query);logLine('OK',$message);return true;}catch(Throwable $e){logLine('ERROR',$message.' - '.$e->getMessage());return false;}}
function missingRefs(PDO $db,string $child,string $field,string $parent,string $key):int {$q="SELECT COUNT(*) FROM `$child` a LEFT JOIN `$parent` b ON a.`$field`=b.`$key` WHERE a.`$field` IS NOT NULL AND b.`$key` IS NULL";return (int)$db->query($q)->fetchColumn();}
logLine('INFO','Diagnóstico Azucreativo '.date('c').($fix?' (modo reparación)':' (solo lectura)'));
$login=dbConnect('login_db');$main=dbConnect('azucreativo_db');
if($login){
 $u=tableInfo($login,'usuarios');if(!$u)logLine('ERROR','Falta login_db.usuarios');else {
  logLine('OK','usuarios existe; motor '.$u['ENGINE']);
  foreach(['id','usuario','correo','password','rol','activo'] as $c){if(!colInfo($login,$u['TABLE_NAME'],$c))logLine('AVISO',"Falta usuarios.$c");}
  if($fix && !colInfo($login,$u['TABLE_NAME'],'activo'))sql($login,'ALTER TABLE `'.$u['TABLE_NAME'].'` ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1','Crear usuarios.activo');
  if($fix && !colInfo($login,$u['TABLE_NAME'],'rol'))sql($login,"ALTER TABLE `".$u['TABLE_NAME']."` ADD COLUMN rol ENUM('administrador','empleado','cliente') NOT NULL DEFAULT 'cliente'",'Crear usuarios.rol (REVISAR roles existentes manualmente)');
 }
 $a=tableInfo($login,'admin_autorizaciones');if(!$a){logLine('AVISO','Falta admin_autorizaciones');if($fix && $u){$id=colInfo($login,$u['TABLE_NAME'],'id');if($id && preg_match('/^(int|bigint|mediumint|smallint|tinyint)(\(\d+\))?( unsigned)?$/i',$id['COLUMN_TYPE'])){sql($login,'CREATE TABLE IF NOT EXISTS admin_autorizaciones (id_usuario '.$id['COLUMN_TYPE'].' NOT NULL PRIMARY KEY, clave_hash VARCHAR(255) NOT NULL, vence_en DATETIME NOT NULL, actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB','Crear tabla admin_autorizaciones');}}}
 if(!tableInfo($login,'auditoria_autorizaciones')){logLine('AVISO','Falta auditoria_autorizaciones');if($fix)sql($login,'CREATE TABLE IF NOT EXISTS auditoria_autorizaciones (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,actor_id INT NOT NULL,accion VARCHAR(80) NOT NULL,objeto VARCHAR(120) NOT NULL,fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB','Crear tabla de auditoría');}
 if($u && tableInfo($login,'admin_autorizaciones')){
  $uc=colInfo($login,$u['TABLE_NAME'],'id');$ac=colInfo($login,'admin_autorizaciones','id_usuario');
  if($uc&&$ac&&$uc['COLUMN_TYPE']!==$ac['COLUMN_TYPE'])logLine('AVISO','Tipo incompatible admin_autorizaciones.id_usuario / usuarios.id');
  elseif($uc&&$ac&&!foreignKey($login,'admin_autorizaciones','id_usuario','usuarios')){
   $n=missingRefs($login,'admin_autorizaciones','id_usuario',$u['TABLE_NAME'],'id');
   if($n)logLine('ERROR',"$n autorizaciones sin usuario; no se agrega FK");
   elseif($fix && strtoupper($u['ENGINE'])==='INNODB')sql($login,'ALTER TABLE admin_autorizaciones ADD CONSTRAINT fk_admin_autorizacion_usuario FOREIGN KEY (id_usuario) REFERENCES `'.$u['TABLE_NAME'].'`(id) ON DELETE CASCADE','Agregar FK autorizaciones/usuarios');
   else logLine('AVISO','Falta FK autorizaciones/usuarios');
  }
 }
}
if($main){
 foreach(['Cliente','Producto','Inventario','Venta','Detalle_Venta','Venta_Empleado','Corte_Caja','Cliente_Usuario_Web','Venta_Cliente_Web'] as $t){$v=tableInfo($main,$t);logLine($v?'OK':'AVISO',($v?'Tabla '.$v['TABLE_NAME'].' motor '.$v['ENGINE']:'Falta tabla '.$t));}
 $v=tableInfo($main,'Venta');$ve=tableInfo($main,'Venta_Empleado');$c=tableInfo($main,'Corte_Caja');
 if($v&&$ve){$parent=colInfo($main,$v['TABLE_NAME'],'ID_Venta');$child=colInfo($main,$ve['TABLE_NAME'],'ID_Venta');
  if($parent&&$child){
   $orphans=missingRefs($main,$ve['TABLE_NAME'],'ID_Venta',$v['TABLE_NAME'],'ID_Venta');
   if($orphans)logLine('ERROR',"$orphans ventas de empleado sin venta principal");
   if($parent['COLUMN_TYPE']!==$child['COLUMN_TYPE']){
    logLine('AVISO','Tipos distintos Venta.ID_Venta='.$parent['COLUMN_TYPE'].' y Venta_Empleado.ID_Venta='.$child['COLUMN_TYPE']);
    if($fix && !$orphans && preg_match('/^int(\(\d+\))? unsigned$/i',$parent['COLUMN_TYPE']) && preg_match('/^int(\(\d+\))?$/i',$child['COLUMN_TYPE']) && (int)$main->query('SELECT COUNT(*) FROM `'.$ve['TABLE_NAME'].'` WHERE ID_Venta < 0')->fetchColumn()===0){sql($main,'ALTER TABLE `'.$ve['TABLE_NAME'].'` MODIFY ID_Venta INT UNSIGNED NOT NULL','Igualar ID_Venta a INT UNSIGNED');$child=colInfo($main,$ve['TABLE_NAME'],'ID_Venta');}
   }
   if($parent['COLUMN_TYPE']===$child['COLUMN_TYPE'] && !$orphans && !foreignKey($main,$ve['TABLE_NAME'],'ID_Venta',$v['TABLE_NAME'])){
    if($fix && strtoupper($v['ENGINE'])==='INNODB' && strtoupper($ve['ENGINE'])==='INNODB')sql($main,'ALTER TABLE `'.$ve['TABLE_NAME'].'` ADD CONSTRAINT fk_ve_venta_audit FOREIGN KEY (ID_Venta) REFERENCES `'.$v['TABLE_NAME'].'`(ID_Venta) ON DELETE RESTRICT ON UPDATE CASCADE','Crear FK Venta_Empleado -> Venta');else logLine('AVISO','Falta FK Venta_Empleado -> Venta');
   }
  }
  $emp=colInfo($main,$ve['TABLE_NAME'],'ID_Empleado');if($emp&&$emp['IS_NULLABLE']==='NO')logLine('INFO','Venta_Empleado.ID_Empleado es obligatorio: empleado/api.php utiliza Empleado_Usuario_Login; ejecutar vincular_empleados_cli.php para revisar las vinculaciones.');
 }
 if($c&&$ve){$pc=colInfo($main,$c['TABLE_NAME'],'ID_Corte');$cc=colInfo($main,$ve['TABLE_NAME'],'ID_Corte');if($pc&&$cc){$n=missingRefs($main,$ve['TABLE_NAME'],'ID_Corte',$c['TABLE_NAME'],'ID_Corte');if($n)logLine('ERROR',"$n cortes referenciados inexistentes");if($pc['COLUMN_TYPE']!==$cc['COLUMN_TYPE'])logLine('AVISO','Tipos distintos de ID_Corte');elseif(!$n&&!foreignKey($main,$ve['TABLE_NAME'],'ID_Corte',$c['TABLE_NAME'])){if($fix)sql($main,'ALTER TABLE `'.$ve['TABLE_NAME'].'` ADD CONSTRAINT fk_ve_corte_audit FOREIGN KEY (ID_Corte) REFERENCES `'.$c['TABLE_NAME'].'`(ID_Corte) ON DELETE SET NULL ON UPDATE CASCADE','Crear FK Venta_Empleado -> Corte_Caja');else logLine('AVISO','Falta FK Venta_Empleado -> Corte_Caja');}}}
 if($v&&tableInfo($main,'Detalle_Venta'))logLine('INFO','Revisar manualmente una venta de prueba: registro de Detalle_Venta, salida de Inventario y descuento de Producto.Stock');
 if($main && tableInfo($main,'Cliente')){$s=$main->query("SELECT COUNT(*) FROM Cliente WHERE Nombre='Cliente' AND Apellido='Anónimo'");$n=(int)$s->fetchColumn();if(!$n){logLine('AVISO','Falta cliente anónimo');if($fix)sql($main,"INSERT INTO Cliente(Nombre,Apellido) VALUES ('Cliente','Anónimo')",'Crear cliente anónimo');}}
}
$report[]='NOTA: No se han probado flujos web ni escrituras de ventas reales; requiere XAMPP y pruebas manuales.';
$file=__DIR__.'/reporte_diagnostico_azucreativo.txt';file_put_contents($file,implode(PHP_EOL,$report).PHP_EOL);echo 'Reporte: '.$file.PHP_EOL;
