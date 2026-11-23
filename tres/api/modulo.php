<?php
session_start();
if (isset($_SESSION['usuario_id']) && ($_SESSION['rol'] ?? '') !== 'administrador') {
    if (str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/')) { http_response_code(403); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>false,'mensaje'=>'Acceso exclusivo para administradores.']); }
    else { header('Location: /Azucreativo/cliente/catalogo.php'); }
    exit;
}

header('Content-Type: application/json; charset=utf-8');
if (!isset($_SESSION['usuario_id'])) { http_response_code(401); echo json_encode(['error'=>'Sesión requerida']); exit; }
require __DIR__.'/../../config/conexion_azucreativo.php';
function reply($data,$status=200){http_response_code($status);echo json_encode($data,JSON_UNESCAPED_UNICODE);exit;}
$entity=$_GET['entity']??'';
$defs=[
 'proveedor'=>['table'=>'Proveedor','pk'=>'ID_Proveedor','fields'=>['Nombre','Correo','Telefono','Direccion']],
 'compra'=>['table'=>'Compra','pk'=>'ID_Compra','fields'=>['ID_Proveedor','Fecha_Compra','Metodo_Pago','Total_Compra']],
 'detalle'=>['table'=>'Detalle_Compra','pk'=>'ID_DetalleCompra','fields'=>['ID_Compra','ID_Producto','Cantidad','Precio_Unitario','Subtotal']]
];
if(!isset($defs[$entity]))reply(['error'=>'Entidad inválida'],400);
$d=$defs[$entity];$table=$d['table'];$pk=$d['pk'];
try {
 if($_SERVER['REQUEST_METHOD']==='GET'){
  $where=[];$params=[];
  foreach(array_merge([$pk],$d['fields']) as $field){$value=trim((string)($_GET[$field]??''));if($value==='')continue;
   if(in_array($field,['Nombre','Correo'],true)){$where[]="`$field` LIKE ?";$params[]='%'.$value.'%';}
   elseif(in_array($field,['Fecha_Compra'],true)){$where[]="DATE(`$field`) = ?";$params[]=$value;}
   else {$where[]="`$field` = ?";$params[]=$value;}
  }
  $sql="SELECT * FROM `$table`".($where?' WHERE '.implode(' AND ',$where):'')." ORDER BY `$pk` DESC LIMIT 500";
  $s=$pdo->prepare($sql);$s->execute($params);reply($s->fetchAll());
 }
 if($_SERVER['REQUEST_METHOD']!=='POST')reply(['error'=>'Método no permitido'],405);
 if(in_array($entity,['compra','detalle'],true))reply(['error'=>'Las compras y sus detalles solo se registran desde el formulario de compra con autorización administrativa.'],403);
 $body=json_decode(file_get_contents('php://input'),true);
 if(!is_array($body))reply(['error'=>'JSON inválido'],400);
 $values=[];
 foreach($d['fields'] as $f){$values[$f]=trim((string)($body[$f]??''));}
 if($entity==='proveedor'){
  if($values['Nombre']===''||!filter_var($values['Correo'],FILTER_VALIDATE_EMAIL))reply(['error'=>'Nombre y correo válido son obligatorios'],422);
 } elseif($entity==='compra'){
  if(!ctype_digit($values['ID_Proveedor'])||(int)$values['ID_Proveedor']<1||!in_array($values['Metodo_Pago'],['Efectivo','Crédito','Transferencia'],true))reply(['error'=>'Proveedor o método de pago inválido'],422);
  $dt=DateTime::createFromFormat('!Y-m-d',$values['Fecha_Compra']);if(!$dt||$dt->format('Y-m-d')!==$values['Fecha_Compra'])reply(['error'=>'Fecha inválida'],422);
  if(!is_numeric($values['Total_Compra'])||(float)$values['Total_Compra']<0)reply(['error'=>'Total inválido'],422);
 } else {
  foreach(['ID_Compra','ID_Producto','Cantidad'] as $f)if(!ctype_digit($values[$f])||(int)$values[$f]<1)reply(['error'=>"$f debe ser entero positivo"],422);
  if(!is_numeric($values['Precio_Unitario'])||(float)$values['Precio_Unitario']<0)reply(['error'=>'Precio inválido'],422);
  $values['Subtotal']=number_format((int)$values['Cantidad']*(float)$values['Precio_Unitario'],2,'.','');
 }
 $id=trim((string)($body[$pk]??''));
 if($id!==''&&(!ctype_digit($id)||(int)$id<1))reply(['error'=>'ID inválido'],422);
 $pdo->beginTransaction();
 if($id!==''){
  $set=implode(',',array_map(fn($f)=>"`$f`=?",$d['fields']));
  $s=$pdo->prepare("UPDATE `$table` SET $set WHERE `$pk`=?");$s->execute([...array_values($values),$id]);
 }else{
  $cols=implode(',',array_map(fn($f)=>"`$f`",$d['fields']));$q=implode(',',array_fill(0,count($d['fields']),'?'));
  $s=$pdo->prepare("INSERT INTO `$table` ($cols) VALUES ($q)");$s->execute(array_values($values));$id=$pdo->lastInsertId();
 }
 $pdo->commit();reply(['ok'=>true,'id'=>$id]);
}catch(PDOException $e){if($pdo->inTransaction())$pdo->rollBack();reply(['error'=>'No se pudo completar la operación. Comprueba las tablas y claves relacionadas.'],500);}
