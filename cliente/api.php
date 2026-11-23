<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/tarjeta_demo_backend.php';

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function salida(array $r,int $status=200): never {http_response_code($status);echo json_encode($r,JSON_UNESCAPED_UNICODE);exit;}
if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol']??'')!=='cliente') salida(['ok'=>false,'mensaje'=>'Solo clientes.'],403);
try { require __DIR__.'/../config/conexion_azucreativo.php';
 $body=json_decode(file_get_contents('php://input'),true);if(!is_array($body))$body=[];
 $accion=(string)($body['accion']??$_GET['accion']??'');
 if ($accion==='catalogo') {salida(['ok'=>true,'datos'=>$pdo->query('SELECT ID_Producto,Nombre,Descripcion,Precio,Stock FROM Producto ORDER BY Nombre')->fetchAll(PDO::FETCH_ASSOC)]);}
 if ($accion==='mis_ventas') {
  $q=$pdo->prepare('SELECT v.ID_Venta,v.Fecha_Venta,v.Metodo_Pago,v.Estado_Venta,v.Total FROM Venta v JOIN Venta_Cliente_Web w ON w.ID_Venta=v.ID_Venta WHERE w.ID_Usuario_Login=? ORDER BY v.ID_Venta DESC LIMIT 50');
  $q->execute([(int)$_SESSION['usuario_id']]);salida(['ok'=>true,'datos'=>$q->fetchAll(PDO::FETCH_ASSOC)]);
 }
 if ($accion==='venta') {
  $metodo=(string)($body['metodo']??'');try{$tarjeta4=tarjetaDemoUltimos4($body,$metodo);}catch(InvalidArgumentException $e){salida(['ok'=>false,'mensaje'=>$e->getMessage()],422);}$items=$body['items']??[];
  if(!in_array($metodo,['Tarjeta','Transferencia'],true)||!is_array($items)||!count($items))salida(['ok'=>false,'mensaje'=>'Selecciona productos y método de pago.'],422);
  if(($body['pago_confirmado']??false)!==true)salida(['ok'=>false,'mensaje'=>'Confirma que el pago se realizó antes de registrar una venta pagada.'],422);
  $cantidades=[];foreach($items as $item){$id=filter_var($item['id']??null,FILTER_VALIDATE_INT);$qty=filter_var($item['cantidad']??null,FILTER_VALIDATE_INT);if(!$id||$id<1||!$qty||$qty<1)salida(['ok'=>false,'mensaje'=>'Producto o cantidad no válidos.'],422);$cantidades[$id]=($cantidades[$id]??0)+$qty;if($cantidades[$id]>100000)salida(['ok'=>false,'mensaje'=>'Cantidad excesiva.'],422);}
  ksort($cantidades);$pdo->beginTransaction();
  // Se crea un cliente de ventas propio para esta cuenta y se reutiliza después.
  $map=$pdo->prepare('SELECT ID_Cliente FROM Cliente_Usuario_Web WHERE ID_Usuario_Login=? FOR UPDATE');$map->execute([(int)$_SESSION['usuario_id']]);$cid=$map->fetchColumn();
  if(!$cid){
   $nuevo=$pdo->prepare('INSERT INTO Cliente(Nombre,Apellido) VALUES (?,?)');$nuevo->execute([(string)$_SESSION['usuario'],'Web']);$cid=(int)$pdo->lastInsertId();
   $rel=$pdo->prepare('INSERT INTO Cliente_Usuario_Web(ID_Usuario_Login,ID_Cliente) VALUES (?,?)');$rel->execute([(int)$_SESSION['usuario_id'],$cid]);
  }
  $lineas=[];$total=0.0;$q=$pdo->prepare('SELECT Nombre,Precio,Stock FROM Producto WHERE ID_Producto=? FOR UPDATE');
  foreach($cantidades as $id=>$qty){$q->execute([$id]);$p=$q->fetch(PDO::FETCH_ASSOC);if(!$p||$qty>(int)$p['Stock'])throw new RuntimeException('Existencias insuficientes para el producto #'.$id);$precio=(float)$p['Precio'];$sub=round($precio*$qty,2);$total+=$sub;$lineas[]=[$id,$qty,$precio,$sub];}
  $v=$pdo->prepare("INSERT INTO Venta(ID_Cliente,Metodo_Pago,Estado_Venta,Total) VALUES (?,?,'Pagada',?)");$v->execute([(int)$cid,$metodo,round($total,2)]);$vid=(int)$pdo->lastInsertId();
  $d=$pdo->prepare('INSERT INTO Detalle_Venta(ID_Venta,ID_Producto,Cantidad,Precio_Unitario,Subtotal) VALUES (?,?,?,?,?)');$up=$pdo->prepare('UPDATE Producto SET Stock=Stock-? WHERE ID_Producto=? AND Stock>=?');
  // Registrar cada salida en Inventario, además de actualizar Producto.Stock.
  // Fecha_Entrada NULL identifica una salida; Fecha_Salida registra la fecha real.
  $mov=$pdo->prepare('INSERT INTO Inventario (ID_Producto,Cantidad,Fecha_Entrada,Fecha_Salida) VALUES (?,?,NULL,NOW())');
  foreach($lineas as [$id,$qty,$precio,$sub]){$d->execute([$vid,$id,$qty,$precio,$sub]);$up->execute([$qty,$id,$qty]);if($up->rowCount()!==1)throw new RuntimeException('Stock insuficiente al registrar la venta.');$mov->execute([$id,$qty]);}
  $w=$pdo->prepare('INSERT INTO Venta_Cliente_Web(ID_Venta,ID_Usuario_Login) VALUES (?,?)');$w->execute([$vid,(int)$_SESSION['usuario_id']]);
  registrarTarjetaDemo($pdo,$vid,$tarjeta4);$pdo->commit();salida(['ok'=>true,'folio'=>$vid,'total'=>round($total,2),'mensaje'=>'Venta registrada y existencias actualizadas.']);
 }
 salida(['ok'=>false,'mensaje'=>'Acción no válida.'],400);
} catch(Throwable $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();error_log('Ventas cliente: '.$e->getMessage());salida(['ok'=>false,'mensaje'=>$e instanceof RuntimeException?$e->getMessage():($e instanceof PDOException && $e->getCode()==='42S02'?'Falta una tabla de ventas o inventario. Comprueba Venta, Detalle_Venta, Inventario, Cliente_Usuario_Web y Venta_Cliente_Web.':'No se pudo completar la operación. Verifica la base de datos y contacta al administrador.')],500);}
