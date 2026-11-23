<?php
declare(strict_types=1);
session_start();header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
function identificadorRol(string $rol,int $id):string{
 $prefijos=['empleado'=>'EMP','cliente'=>'CLI','administrador'=>'ADM'];
 return ($prefijos[$rol]??'USR').'-'.str_pad((string)$id,5,'0',STR_PAD_LEFT);
}
function reply(array $a,int $s=200):never{http_response_code($s);echo json_encode($a,JSON_UNESCAPED_UNICODE);exit;}
$uid=(int)($_SESSION['usuario_id']??0);$rol=$_SESSION['rol']??'';
if(!$uid||!in_array($rol,['administrador','empleado'],true))reply(['ok'=>false,'mensaje'=>'Sin permisos'],403);
if(isset($_SERVER['HTTP_ORIGIN']) && parse_url($_SERVER['HTTP_ORIGIN'],PHP_URL_HOST)!==($_SERVER['HTTP_HOST']?explode(':',$_SERVER['HTTP_HOST'])[0]:''))reply(['ok'=>false,'mensaje'=>'Origen inválido'],403);
if($_SERVER['REQUEST_METHOD']!=='POST')reply(['ok'=>false,'mensaje'=>'Método inválido'],405);
require_once __DIR__.'/../config/autorizaciones.php';
$b=json_decode(file_get_contents('php://input'),true);if(!is_array($b))$b=[];
$accion=$b['accion']??'';
try{
 $db=authDb();
 if($accion==='listar'){
  if($rol!=='administrador')reply(['ok'=>false,'mensaje'=>'Solo administradores'],403);
  $cuentas=$db->query('SELECT id,usuario,correo,rol,activo FROM usuarios ORDER BY id DESC')->fetchAll();
  try{require_once __DIR__.'/../config/conexion_azucreativo.php';$vdb=$pdo;$maps=$vdb->query('SELECT ID_Usuario_Login,ID_Empleado FROM Empleado_Usuario_Login')->fetchAll(PDO::FETCH_KEY_PAIR);foreach($cuentas as &$cuenta)$cuenta['id_empleado']=$maps[$cuenta['id']]??null;unset($cuenta);}catch(Throwable $ex){error_log('Listado de vinculos: '.$ex->getMessage());}
  try{
   require_once __DIR__.'/../config/conexion_azucreativo.php';$vdb=$pdo;
   $cm=$vdb->query('SELECT ID_Usuario_Login,ID_Cliente FROM Cliente_Usuario_Web')->fetchAll(PDO::FETCH_KEY_PAIR);
  }catch(Throwable $ex){$cm=[];error_log('Listado de clientes: '.$ex->getMessage());}
  foreach($cuentas as &$cuenta){
   $idCuenta=(int)$cuenta['id'];$tipo=$cuenta['rol'];
   $idRol=$tipo==='empleado'?(int)($cuenta['id_empleado']??0):($tipo==='cliente'?(int)($cm[$idCuenta]??0):$idCuenta);
   $cuenta['identificador']=$idRol>0?identificadorRol($tipo,$idRol):'Sin vincular';
   $cuenta['id_rol']=$idRol?:null;
  }unset($cuenta);
  reply(['ok'=>true,'datos'=>$cuentas]);
 }
 if($accion==='renovar'){
  if($rol!=='administrador')reply(['ok'=>false,'mensaje'=>'Solo administradores'],403);
  $s=$db->prepare("SELECT password FROM usuarios WHERE id=? AND rol='administrador' AND activo=1");$s->execute([$uid]);$hash=$s->fetchColumn();
  if(!$hash||!password_verify((string)($b['password_actual']??''),$hash))reply(['ok'=>false,'mensaje'=>'Contraseña de acceso incorrecta'],403);
  $clave=(string)($b['nueva_clave']??'');if(!preg_match('/^[0-9]{4}$/D',$clave))reply(['ok'=>false,'mensaje'=>'La clave de autorización debe tener exactamente 4 dígitos numéricos'],422);
  $verificar=$db->prepare('SELECT 1 FROM admin_autorizaciones WHERE id_usuario=?');$verificar->execute([$uid]);$yaExistia=(bool)$verificar->fetchColumn();
  $s=$db->prepare('INSERT INTO admin_autorizaciones(id_usuario,clave_hash,vence_en) VALUES (?, ?, DATE_ADD(NOW(),INTERVAL 15 DAY)) ON DUPLICATE KEY UPDATE clave_hash=VALUES(clave_hash),vence_en=VALUES(vence_en),actualizado_en=NOW()');$s->execute([$uid,password_hash($clave,PASSWORD_DEFAULT)]);
  auditar($db,$uid,'renovar_clave','administrador');reply(['ok'=>true,'mensaje'=>$yaExistia?'Confirmación: la clave de autorización se modificó correctamente. Vigencia: 15 días desde ahora.':'Confirmación: la clave de autorización se creó correctamente. Vigencia: 15 días desde ahora.','tipo'=>$yaExistia?'modificada':'creada']);
 }
 if(!in_array($accion,['guardar','estado','eliminar'],true))reply(['ok'=>false,'mensaje'=>'Acción inválida'],400);
 if(in_array($accion,['estado','eliminar'],true)&&$rol!=='administrador')reply(['ok'=>false,'mensaje'=>'Solo administradores'],403);
 $autorizador=exigirAutorizacion($db,$b);
 if($accion==='estado'){
  $id=(int)($b['id']??0);$activo=(int)($b['activo']??-1);
  if($id<1||!in_array($activo,[0,1],true)||$id===$uid)reply(['ok'=>false,'mensaje'=>'Operación no permitida'],422);
  $s=$db->prepare('UPDATE usuarios SET activo=? WHERE id=?');$s->execute([$activo,$id]);
  auditar($db,$uid,$activo?'reactivar_cuenta':'desactivar_cuenta',(string)$id);reply(['ok'=>true,'mensaje'=>'Estado actualizado']);
 }
 if($accion==='eliminar'){
  if($rol!=='administrador')reply(['ok'=>false,'mensaje'=>'Solo administradores'],403);
  $id=(int)($b['id']??0);
  if($id<1||$id===$uid)reply(['ok'=>false,'mensaje'=>'No puedes eliminar tu propia cuenta'],422);
  $confirmacion=(string)($b['confirmacion']??'');
  if($confirmacion!=='ELIMINAR')reply(['ok'=>false,'mensaje'=>'Se requiere confirmar la eliminación definitiva'],422);
  // Una sola conexión y transacción para ambas bases de datos: no dejar bajas a medias.
  $db->beginTransaction();
  try{
   $q=$db->prepare('SELECT id,rol FROM login_db.usuarios WHERE id=? FOR UPDATE');$q->execute([$id]);$target=$q->fetch(PDO::FETCH_ASSOC);
   if(!$target){$db->rollBack();reply(['ok'=>false,'mensaje'=>'Perfil no encontrado'],404);}
   $role=(string)$target['rol'];
   $business='azucreativo_db';
   $q=$db->prepare("SELECT ID_Empleado FROM {$business}.Empleado_Usuario_Login WHERE ID_Usuario_Login=?");$q->execute([$id]);$eid=(int)($q->fetchColumn()?:0);
   $q=$db->prepare("SELECT ID_Cliente FROM {$business}.Cliente_Usuario_Web WHERE ID_Usuario_Login=?");$q->execute([$id]);$cid=(int)($q->fetchColumn()?:0);
   // Sustituir referencias históricas a la cuenta por NULL o 0 cuando el esquema
   // permita 0 sin FK. Nunca borrar ventas, detalles, cortes ni reportes.
   $anonimizar=function(string $tabla,string $col,int $valor)use($db,$business):void{
    if($valor<1)return;
    $q=$db->prepare('SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?');
    $q->execute([$business,$tabla,$col]);$nullable=$q->fetchColumn();
    if($nullable===false)return;
    $q=$db->prepare('SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL');
    $q->execute([$business,$tabla,$col]);$hasFk=(int)$q->fetchColumn()>0;
    if($nullable==='YES'){$nuevo=null;}
    elseif(!$hasFk){$nuevo=0;}
    else{throw new RuntimeException("No se puede anonimizar {$tabla}.{$col} sin una migración segura de su clave foránea.");}
    $stmt=$db->prepare("UPDATE `{$business}`.`{$tabla}` SET `{$col}`=? WHERE `{$col}`=?");$stmt->execute([$nuevo,$valor]);
   };
   foreach([['Venta_Empleado','ID_Usuario',$id],['Venta_Empleado','ID_Empleado',$eid],['Venta_Cliente_Web','ID_Usuario_Login',$id],['Corte_Caja','ID_Usuario',$id],['Venta','ID_Usuario',$id],['Venta','ID_Empleado',$eid],['Venta','ID_Cliente',$cid]] as $ref){$anonimizar(...$ref);}
   $db->prepare("DELETE FROM {$business}.Empleado_Usuario_Login WHERE ID_Usuario_Login=?")->execute([$id]);
   $db->prepare("DELETE FROM {$business}.Cliente_Usuario_Web WHERE ID_Usuario_Login=?")->execute([$id]);
   // No eliminar empleados/clientes utilizados por otras relaciones: en tal caso
   // MySQL bloquea la operación y se revierte todo, sin pérdida de ventas.
   if($eid)$db->prepare("DELETE FROM {$business}.Empleado WHERE ID_Empleado=?")->execute([$eid]);
   if($cid)$db->prepare("DELETE FROM {$business}.Cliente WHERE ID_Cliente=?")->execute([$cid]);
   // La auditoría histórica se mantiene, pero sin una referencia al perfil eliminado.
   $q=$db->prepare("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='login_db' AND TABLE_NAME='auditoria_autorizaciones' AND COLUMN_NAME='actor_id'");$q->execute();$nullable=$q->fetchColumn();
   if($nullable==='YES')$db->prepare('UPDATE login_db.auditoria_autorizaciones SET actor_id=NULL WHERE actor_id=?')->execute([$id]);
   elseif($nullable==='NO'){
    $q=$db->prepare("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA='login_db' AND TABLE_NAME='auditoria_autorizaciones' AND COLUMN_NAME='actor_id' AND REFERENCED_TABLE_NAME IS NOT NULL");$q->execute();
    if((int)$q->fetchColumn())throw new RuntimeException('La auditoría tiene una relación obligatoria con la cuenta. Se necesita adaptar el esquema para eliminarla de forma segura.');
    $db->prepare('UPDATE login_db.auditoria_autorizaciones SET actor_id=0 WHERE actor_id=?')->execute([$id]);
   }
   // Las autorizaciones tienen ON DELETE CASCADE; quitar otras dependencias si existen.
   $db->prepare('DELETE FROM login_db.admin_autorizaciones WHERE id_usuario=?')->execute([$id]);
   $db->prepare('DELETE FROM login_db.usuarios WHERE id=?')->execute([$id]);
   auditar($db,$uid,'eliminacion_definitiva','perfil id '.$id.' rol '.$role.'; ventas conservadas sin usuario');
   $db->commit();
  }catch(Throwable $e){if($db->inTransaction())$db->rollBack();error_log('Eliminar perfil definitivamente: '.$e->getMessage());reply(['ok'=>false,'mensaje'=>'No se eliminó el perfil. Existen relaciones que requieren revisión para conservar las ventas sin perder datos. Consulta el registro de errores de PHP.'],409);}
  reply(['ok'=>true,'mensaje'=>'Perfil eliminado definitivamente. Se conservaron las ventas históricas sin vinculación al usuario.']);
 }
 $id=(int)($b['id']??0);$esEdicion=$id>0;$targetRol=(string)($b['rol']??'');
 if(!in_array($targetRol,['cliente','empleado','administrador'],true)||($rol==='empleado'&&($targetRol!=='cliente'||$id>0)))reply(['ok'=>false,'mensaje'=>'Rol u operación no permitida'],403);
 $usuario=trim((string)($b['usuario']??''));$correo=trim((string)($b['correo']??''));$pass=(string)($b['password']??'');
 if(strlen($usuario)<3||strlen($usuario)>50||!filter_var($correo,FILTER_VALIDATE_EMAIL)||($pass!==''&&strlen($pass)<12)||($id===0&&$pass===''))reply(['ok'=>false,'mensaje'=>'Usuario 3–50 caracteres, correo válido y contraseña nueva de al menos 12 caracteres'],422);
 // Los IDs de cliente y empleado se generan en tablas distintas; no se solicitan manualmente.
 if($targetRol==='empleado'){
  require_once __DIR__.'/../config/conexion_azucreativo.php';$ventasDb=$pdo;
  if($esEdicion){
   $q=$ventasDb->prepare('SELECT ID_Empleado FROM Empleado_Usuario_Login WHERE ID_Usuario_Login=?');$q->execute([$id]);
   $eid=(int)($q->fetchColumn()?:0);
  }else{$eid=0;}
 }
 if($id>0){
  $s=$db->prepare('SELECT rol FROM usuarios WHERE id=?');$s->execute([$id]);$actual=$s->fetchColumn();if(!$actual)reply(['ok'=>false,'mensaje'=>'Cuenta no encontrada'],404);
  if($id===$uid&&$targetRol!=='administrador')reply(['ok'=>false,'mensaje'=>'No puedes quitarte tu rol administrador'],422);
  $sql='UPDATE usuarios SET usuario=?,correo=?,rol=?'.($pass!==''?',password=?':'').' WHERE id=?';$params=[$usuario,$correo,$targetRol];if($pass!=='')$params[]=password_hash($pass,PASSWORD_DEFAULT);$params[]=$id;$db->prepare($sql)->execute($params);
 }else{$s=$db->prepare('INSERT INTO usuarios(usuario,correo,password,rol) VALUES (?,?,?,?)');$s->execute([$usuario,$correo,password_hash($pass,PASSWORD_DEFAULT),$targetRol]);$id=(int)$db->lastInsertId();}
 // Alta automática de empleado con ID propio, independiente de Cliente e ID de login.
 if($targetRol==='empleado'){
  require_once __DIR__.'/../config/conexion_azucreativo.php';$ventasDb=$pdo;
  if($eid<1){
   $cols=$ventasDb->query('SHOW COLUMNS FROM Empleado')->fetchAll(PDO::FETCH_ASSOC);
   $nombre=trim((string)($b['nombre']??''))?:$usuario;
   $apellido=trim((string)($b['apellido']??''))?:'Empleado';
   $fields=[];$values=[];
   foreach($cols as $c){
    $field=$c['Field'];$low=strtolower($field);
    if($low==='id_empleado')continue;
    if(str_contains($low,'apellido')){$fields[]=$field;$values[]=$apellido;}
    elseif(str_contains($low,'nombre')){$fields[]=$field;$values[]=$nombre;}
    elseif(in_array($low,['correo','email','correo_electronico'],true)){$fields[]=$field;$values[]=$correo;}
    elseif($c['Null']==='NO' && $c['Default']===null && stripos((string)$c['Extra'],'auto_increment')===false){
     reply(['ok'=>false,'mensaje'=>'La tabla Empleado exige el campo '.$field.'. Es necesario configurar ese campo antes del alta automática.'],422);
    }
   }
   $ventasDb->beginTransaction();
   try{
    if($fields){$names=implode(',',array_map(fn($v)=>'`'.str_replace('`','``',$v).'`',$fields));$marks=implode(',',array_fill(0,count($fields),'?'));$ventasDb->prepare("INSERT INTO Empleado ($names) VALUES ($marks)")->execute($values);}
    else{$ventasDb->exec('INSERT INTO Empleado () VALUES ()');}
    $eid=(int)$ventasDb->lastInsertId();
    if($eid<1)throw new RuntimeException('No se generó ID_Empleado. Comprueba que sea AUTO_INCREMENT.');
    $map=$ventasDb->prepare('INSERT INTO Empleado_Usuario_Login(ID_Usuario_Login,ID_Empleado) VALUES (?,?)');$map->execute([$id,$eid]);
    $ventasDb->commit();
   }catch(Throwable $e){$ventasDb->rollBack();throw $e;}
  }
 }
 // Para cuentas nuevas de cliente, crear el registro de Cliente y su asociación.
 // En caso de error entre bases se registra el problema para su reparación.
 if($targetRol==='cliente'){
  require __DIR__.'/../config/conexion_azucreativo.php';$ventasDb=$pdo;
  $map=$ventasDb->prepare('SELECT ID_Cliente FROM Cliente_Usuario_Web WHERE ID_Usuario_Login=?');$map->execute([$id]);
  if(!$map->fetchColumn()){
   $nombre=trim((string)($b['nombre']??''))?:$usuario;$apellido=trim((string)($b['apellido']??''))?:'Cliente';
   $ventasDb->beginTransaction();
   try{$s=$ventasDb->prepare('INSERT INTO Cliente(Nombre,Apellido) VALUES (?,?)');$s->execute([$nombre,$apellido]);$cid=(int)$ventasDb->lastInsertId();$s=$ventasDb->prepare('INSERT INTO Cliente_Usuario_Web(ID_Usuario_Login,ID_Cliente) VALUES (?,?)');$s->execute([$id,$cid]);$ventasDb->commit();}catch(Throwable $e){$ventasDb->rollBack();throw $e;}
  }
 }
 auditar($db,$uid,$id?'guardar_cuenta':'crear_cuenta',$targetRol.':'.$id);
 $idRol=$targetRol==='empleado'?$eid:($targetRol==='cliente'?($cid??null):$id);
 if($targetRol==='cliente'&&!isset($cid)){
  $q=$ventasDb->prepare('SELECT ID_Cliente FROM Cliente_Usuario_Web WHERE ID_Usuario_Login=?');$q->execute([$id]);$idRol=(int)$q->fetchColumn();
 }
 $identificador=$idRol?identificadorRol($targetRol,(int)$idRol):'Sin vincular';
 reply(['ok'=>true,'mensaje'=>($pass!==''?($esEdicion?'Confirmación: la contraseña de acceso se modificó correctamente. ':'Confirmación: la contraseña de acceso se creó correctamente. '):'').'Cuenta guardada. Identificador: '.$identificador.' · ID de acceso: '.$id.($targetRol==='empleado'?' · ID de empleado: '.$eid:''),'id'=>$id,'id_empleado'=>$targetRol==='empleado'?$eid:null,'identificador'=>$identificador]);
}catch(PDOException $e){error_log('Cuentas: '.$e->getMessage());reply(['ok'=>false,'mensaje'=>'Error de base de datos: revisa la migración y que usuario/correo sean únicos.'],500);}catch(Throwable $e){error_log('Cuentas: '.$e->getMessage());reply(['ok'=>false,'mensaje'=>'No se pudo completar la operación; revisa tablas de clientes y asociaciones.'],500);}
