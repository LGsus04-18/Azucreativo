<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/tarjeta_demo_backend.php';
require_once __DIR__ . '/../config/cliente_mostrador.php';

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function out(array $x,int $status=200): never {http_response_code($status);echo json_encode($x,JSON_UNESCAPED_UNICODE);exit;}
if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol']??'')!=='empleado') out(['ok'=>false,'mensaje'=>'Acceso restringido a empleados.'],403);
require __DIR__.'/../config/conexion_azucreativo.php';
require_once __DIR__.'/../config/vinculo_empleado.php';
$uid=(int)$_SESSION['usuario_id'];
$body=json_decode(file_get_contents('php://input'),true);if(!is_array($body))$body=[];
$action=(string)($body['accion']??$_GET['accion']??'');
try {
 if($action==='catalogo') { $r=$pdo->query('SELECT ID_Producto,Nombre,Precio,Stock FROM Producto ORDER BY Nombre')->fetchAll();out(['ok'=>true,'datos'=>$r]); }
 if($action==='clientes') { $r=$pdo->query("SELECT ID_Cliente,Nombre,Apellido FROM Cliente WHERE NOT (Nombre='Cliente' AND Apellido IN ('No registrado','Invitado','Anónimo') AND (Correo IS NULL OR Correo LIKE 'anonimo-%@clientes.invalid')) ORDER BY Nombre")->fetchAll();out(['ok'=>true,'datos'=>$r]); }
 if($action==='pedidos') { $r=$pdo->query("SELECT ID_Pedido,Usuario,Fecha,Metodo_Pago,Estado,Total FROM Pedido_Cliente WHERE Estado='Pendiente' ORDER BY Fecha DESC LIMIT 50")->fetchAll();out(['ok'=>true,'datos'=>$r]); }
 if($action==='resumen') {
  $s=$pdo->prepare("SELECT COUNT(*) cantidad,COALESCE(SUM(v.Total),0) total,COALESCE(SUM(CASE WHEN v.Metodo_Pago='Efectivo' THEN v.Total ELSE 0 END),0) efectivo,COALESCE(SUM(CASE WHEN v.Metodo_Pago='Tarjeta' THEN v.Total ELSE 0 END),0) tarjeta,COALESCE(SUM(CASE WHEN v.Metodo_Pago='Transferencia' THEN v.Total ELSE 0 END),0) transferencia FROM Venta v JOIN Venta_Empleado ve ON ve.ID_Venta=v.ID_Venta WHERE ve.ID_Usuario=? AND v.Estado_Venta='Pagada' AND ve.ID_Corte IS NULL");
  $s->execute([$uid]);out(['ok'=>true,'datos'=>$s->fetch()]);
 }
 if($action==='venta') {
  $cid=(int)($body['cliente']??0); $metodo=(string)($body['metodo']??'');try{$tarjeta4=tarjetaDemoUltimos4($body,$metodo);}catch(InvalidArgumentException $e){out(['ok'=>false,'mensaje'=>$e->getMessage()],422);}$items=$body['items']??[];
  if($cid<0||!in_array($metodo,['Efectivo','Tarjeta','Transferencia'],true)||!is_array($items)||!count($items))out(['ok'=>false,'mensaje'=>'Completa cliente, método y productos.'],422);
  if($cid>0){$c=$pdo->prepare('SELECT 1 FROM Cliente WHERE ID_Cliente=?');$c->execute([$cid]);if(!$c->fetchColumn())out(['ok'=>false,'mensaje'=>'Cliente no válido.'],422);}
  $cantidades=[];foreach($items as $item){$id=(int)($item['id']??0);$qty=(int)($item['cantidad']??0);if($id<1||$qty<1)out(['ok'=>false,'mensaje'=>'Cantidad inválida.'],422);$cantidades[$id]=($cantidades[$id]??0)+$qty;}
  ksort($cantidades);$pdo->beginTransaction();if($cid===0)$cid=crearClienteMostrador($pdo);$lineas=[];$total=0;
  $q=$pdo->prepare('SELECT Nombre,Precio,Stock FROM Producto WHERE ID_Producto=? FOR UPDATE');
  foreach($cantidades as $id=>$qty){$q->execute([$id]);$prod=$q->fetch();if(!$prod||$qty>(int)$prod['Stock'])throw new RuntimeException('Producto inexistente o sin existencias suficientes (ID '.$id.').');$precio=(float)$prod['Precio'];$sub=round($precio*$qty,2);$total+=$sub;$lineas[]=[$id,$qty,$precio,$sub];}
  $v=$pdo->prepare("INSERT INTO Venta(ID_Cliente,Metodo_Pago,Estado_Venta,Total) VALUES (?,?,'Pagada',?)");$v->execute([$cid,$metodo,round($total,2)]);$vid=(int)$pdo->lastInsertId();
  $d=$pdo->prepare('INSERT INTO Detalle_Venta(ID_Venta,ID_Producto,Cantidad,Precio_Unitario,Subtotal) VALUES (?,?,?,?,?)');$stock=$pdo->prepare('UPDATE Producto SET Stock=Stock-? WHERE ID_Producto=?');
  $mov=$pdo->prepare('INSERT INTO Inventario (ID_Producto,Cantidad,Fecha_Entrada,Fecha_Salida) VALUES (?,?,NULL,NOW())');
  foreach($lineas as [$id,$qty,$precio,$sub]){$d->execute([$vid,$id,$qty,$precio,$sub]);$stock->execute([$qty,$id]);if($stock->rowCount()!==1)throw new RuntimeException('No se pudo descontar el producto #'.$id);$mov->execute([$id,$qty]);}
  $idEmpleado=obtenerIdEmpleado($pdo,$uid);$a=$pdo->prepare('INSERT INTO Venta_Empleado(ID_Venta,ID_Empleado,ID_Usuario) VALUES (?,?,?)');$a->execute([$vid,$idEmpleado,$uid]);registrarTarjetaDemo($pdo,$vid,$tarjeta4);$pdo->commit();out(['ok'=>true,'folio'=>$vid,'total'=>round($total,2)]);
 }
 if($action==='confirmar_pedido') {
  $pedidoId=filter_var($body['pedido']??null,FILTER_VALIDATE_INT);
  $clienteId=filter_var($body['cliente']??null,FILTER_VALIDATE_INT);
  if(!$pedidoId||!$clienteId)out(['ok'=>false,'mensaje'=>'Selecciona un pedido y un cliente de ventas.'],422);
  $pdo->beginTransaction();
  $ped=$pdo->prepare('SELECT * FROM Pedido_Cliente WHERE ID_Pedido=? FOR UPDATE');$ped->execute([$pedidoId]);$pedido=$ped->fetch();
  if(!$pedido||$pedido['Estado']!=='Pendiente')throw new RuntimeException('Este pedido ya fue procesado o no existe.');
  $c=$pdo->prepare('SELECT 1 FROM Cliente WHERE ID_Cliente=?');$c->execute([$clienteId]);if(!$c->fetchColumn())throw new RuntimeException('Cliente de venta inexistente.');
  $det=$pdo->prepare('SELECT ID_Producto,Cantidad FROM Pedido_Cliente_Detalle WHERE ID_Pedido=? ORDER BY ID_Producto');$det->execute([$pedidoId]);$items=$det->fetchAll();
  if(!$items)throw new RuntimeException('Pedido sin productos.');
  $q=$pdo->prepare('SELECT Nombre,Precio,Stock FROM Producto WHERE ID_Producto=? FOR UPDATE');$lineas=[];$total=0.0;
  foreach($items as $item){$id=(int)$item['ID_Producto'];$qty=(int)$item['Cantidad'];$q->execute([$id]);$prod=$q->fetch();if(!$prod||$qty<1||$qty>(int)$prod['Stock'])throw new RuntimeException('Stock insuficiente para producto #'.$id);$precio=(float)$prod['Precio'];$sub=round($precio*$qty,2);$total+=$sub;$lineas[]=[$id,$qty,$precio,$sub];}
  $v=$pdo->prepare("INSERT INTO Venta(ID_Cliente,Metodo_Pago,Estado_Venta,Total) VALUES (?,?,'Pagada',?)");$v->execute([$clienteId,$pedido['Metodo_Pago'],round($total,2)]);$ventaId=(int)$pdo->lastInsertId();
  $dv=$pdo->prepare('INSERT INTO Detalle_Venta(ID_Venta,ID_Producto,Cantidad,Precio_Unitario,Subtotal) VALUES (?,?,?,?,?)');$up=$pdo->prepare('UPDATE Producto SET Stock=Stock-? WHERE ID_Producto=? AND Stock>=?');
  $mov=$pdo->prepare('INSERT INTO Inventario (ID_Producto,Cantidad,Fecha_Entrada,Fecha_Salida) VALUES (?,?,NULL,NOW())');
  foreach($lineas as [$id,$qty,$precio,$sub]){$dv->execute([$ventaId,$id,$qty,$precio,$sub]);$up->execute([$qty,$id,$qty]);if($up->rowCount()!==1)throw new RuntimeException('Stock insuficiente.');$mov->execute([$id,$qty]);}
  $idEmpleado=obtenerIdEmpleado($pdo,$uid);$ve=$pdo->prepare('INSERT INTO Venta_Empleado(ID_Venta,ID_Empleado,ID_Usuario) VALUES (?,?,?)');$ve->execute([$ventaId,$idEmpleado,$uid]);
  $pv=$pdo->prepare('INSERT INTO Pedido_Venta(ID_Pedido,ID_Venta) VALUES (?,?)');$pv->execute([$pedidoId,$ventaId]);
  $estado=$pdo->prepare("UPDATE Pedido_Cliente SET Estado='Confirmado' WHERE ID_Pedido=? AND Estado='Pendiente'");$estado->execute([$pedidoId]);
  $pdo->commit();out(['ok'=>true,'folio'=>$ventaId,'pedido'=>$pedidoId,'total'=>round($total,2)]);
 }
 if($action==='corte') {
  // La autorización se valida EN EL SERVIDOR antes de escribir el corte.
  $adminId=filter_var($body['admin_autorizador']??null,FILTER_VALIDATE_INT);
  $pin=(string)($body['clave_autorizacion']??'');
  if(!$adminId || !preg_match('/^[0-9]{4}$/D',$pin)) out(['ok'=>false,'mensaje'=>'Indica el ID del administrador y su PIN de 4 dígitos.'],403);
  require_once __DIR__.'/../config/autorizaciones.php';
  try {
   $loginDb=authDb();
   if(!validarAutorizacion($loginDb,(int)$adminId,$pin)) out(['ok'=>false,'mensaje'=>'Autorización denegada: administrador inactivo, PIN incorrecto o vencido.'],403);
  } catch(Throwable $authError) {
   error_log('Autorización de corte: '.$authError->getMessage());
   out(['ok'=>false,'mensaje'=>'No se pudo validar la autorización con login_db. Revisa la migración de seguridad y la conexión.'],503);
  }
  $contado=$body['efectivo_contado']??null;
  if(!is_numeric($contado)||$contado<0)out(['ok'=>false,'mensaje'=>'Captura el efectivo contado.'],422);
  $pdo->beginTransaction();
  $q=$pdo->prepare('SELECT ve.ID_Venta,v.Total,v.Metodo_Pago FROM Venta_Empleado ve JOIN Venta v ON v.ID_Venta=ve.ID_Venta WHERE ve.ID_Usuario=? AND ve.ID_Corte IS NULL AND v.Estado_Venta=\'Pagada\' FOR UPDATE');
  $q->execute([$uid]);$rows=$q->fetchAll();if(!$rows)throw new RuntimeException('No hay ventas pendientes de corte.');
  $sum=['Efectivo'=>0.0,'Tarjeta'=>0.0,'Transferencia'=>0.0];foreach($rows as $r)$sum[$r['Metodo_Pago']]+=(float)$r['Total'];
  $ins=$pdo->prepare('INSERT INTO Corte_Caja(ID_Usuario,Fecha_Inicio,Fecha_Cierre,Num_Ventas,Total_Efectivo,Total_Tarjeta,Total_Transferencia,Efectivo_Contado,Diferencia) VALUES (?,NOW(),NOW(),?,?,?,?,?,?)');
  $ins->execute([$uid,count($rows),round($sum['Efectivo'],2),round($sum['Tarjeta'],2),round($sum['Transferencia'],2),round((float)$contado,2),round((float)$contado-$sum['Efectivo'],2)]);
  $corte=(int)$pdo->lastInsertId();$u=$pdo->prepare('UPDATE Venta_Empleado SET ID_Corte=? WHERE ID_Venta=? AND ID_Usuario=? AND ID_Corte IS NULL');foreach($rows as $r)$u->execute([$corte,$r['ID_Venta'],$uid]);
  $pdo->commit();
  try { auditar($loginDb,(int)$adminId,'autorizar_corte_caja','Corte #'.$corte.'; empleado usuario #'.$uid); }
  catch(Throwable $auditError) { error_log('Auditoría corte #'.$corte.': '.$auditError->getMessage()); }
  out(['ok'=>true,'corte'=>$corte,'ventas'=>count($rows),'efectivo'=>$sum['Efectivo'],'tarjeta'=>$sum['Tarjeta'],'transferencia'=>$sum['Transferencia'],'diferencia'=>round((float)$contado-$sum['Efectivo'],2)]);
 }
 out(['ok'=>false,'mensaje'=>'Acción no válida.'],400);
} catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('Empleado API: '.$e->getMessage());out(['ok'=>false,'mensaje'=>$e instanceof RuntimeException?$e->getMessage():'Error de base de datos. Importa primero roles_empleados_caja.sql y comprueba las tablas.'],500);}
