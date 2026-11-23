<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__.'/../config/tarjeta_demo_backend.php';
function respond(array $x,int $status=200):never {http_response_code($status);echo json_encode($x,JSON_UNESCAPED_UNICODE);exit;}
try {
 require __DIR__.'/../config/conexion_azucreativo.php';
 $data=json_decode(file_get_contents('php://input'),true);if(!is_array($data))$data=[];
 $action=(string)($data['accion']??$_GET['accion']??'');
 if($action==='catalogo')respond(['ok'=>true,'datos'=>$pdo->query('SELECT ID_Producto,Nombre,Descripcion,Precio,Stock FROM Producto ORDER BY Nombre')->fetchAll(PDO::FETCH_ASSOC)]);
 if($action!=='venta')respond(['ok'=>false,'mensaje'=>'Acción no válida.'],400);
 if(!hash_equals((string)($_SESSION['invitado_csrf']??''),(string)($data['csrf']??''))||empty($_SESSION['invitado_csrf']))respond(['ok'=>false,'mensaje'=>'Sesión vencida. Actualiza la página.'],403);
 $metodo=(string)($data['metodo']??'');
 if(!in_array($metodo,['Tarjeta','Transferencia'],true))respond(['ok'=>false,'mensaje'=>'Método de pago no válido.'],422);
 try{$last4=tarjetaDemoUltimos4($data,$metodo);}catch(InvalidArgumentException $e){respond(['ok'=>false,'mensaje'=>$e->getMessage()],422);}
 if(($data['pago_confirmado']??false)!==true)respond(['ok'=>false,'mensaje'=>'Confirma el pago antes de registrar la venta.'],422);
 $items=$data['items']??[];
 if(!is_array($items)||!count($items)||count($items)>100)respond(['ok'=>false,'mensaje'=>'Selecciona productos válidos.'],422);
 $quantities=[];
 foreach($items as $item){$id=filter_var($item['id']??null,FILTER_VALIDATE_INT);$qty=filter_var($item['cantidad']??null,FILTER_VALIDATE_INT);if(!$id||$id<1||!$qty||$qty<1)respond(['ok'=>false,'mensaje'=>'Cantidad o producto no válido.'],422);$quantities[$id]=($quantities[$id]??0)+$qty;if($quantities[$id]>10000)respond(['ok'=>false,'mensaje'=>'Cantidad excesiva.'],422);}
 ksort($quantities);
 $pdo->beginTransaction();
 $lines=[];$total=0;
 $q=$pdo->prepare('SELECT Nombre,Precio,Stock FROM Producto WHERE ID_Producto=? FOR UPDATE');
 foreach($quantities as $id=>$qty){$q->execute([$id]);$product=$q->fetch(PDO::FETCH_ASSOC);if(!$product||$qty>(int)$product['Stock'])throw new RuntimeException('Existencias insuficientes para producto #'.$id);$price=(float)$product['Precio'];$sub=round($price*$qty,2);$total+=$sub;$lines[]=[$id,$qty,$price,$sub];}
 // Cada venta de invitado tiene un cliente técnico propio, sin datos personales ni cuenta de acceso.
 $new=$pdo->prepare("INSERT INTO Cliente(Nombre,Apellido,Correo) VALUES ('Cliente','Invitado',NULL)");
 $new->execute();$clientId=(int)$pdo->lastInsertId();
 $v=$pdo->prepare("INSERT INTO Venta(ID_Cliente,Metodo_Pago,Estado_Venta,Total) VALUES (?,?,'Pagada',?)");$v->execute([$clientId,$metodo,round($total,2)]);$saleId=(int)$pdo->lastInsertId();
 $d=$pdo->prepare('INSERT INTO Detalle_Venta(ID_Venta,ID_Producto,Cantidad,Precio_Unitario,Subtotal) VALUES (?,?,?,?,?)');
 $stock=$pdo->prepare('UPDATE Producto SET Stock=Stock-? WHERE ID_Producto=? AND Stock>=?');
 $inv=$pdo->prepare('INSERT INTO Inventario(ID_Producto,Cantidad,Fecha_Entrada,Fecha_Salida) VALUES (?,?,NULL,NOW())');
 foreach($lines as [$id,$qty,$price,$sub]){$d->execute([$saleId,$id,$qty,$price,$sub]);$stock->execute([$qty,$id,$qty]);if($stock->rowCount()!==1)throw new RuntimeException('Stock insuficiente.');$inv->execute([$id,$qty]);}
 registrarTarjetaDemo($pdo,$saleId,$last4);
 $pdo->commit();
 if(!isset($_SESSION['ventas_invitado'])||!is_array($_SESSION['ventas_invitado']))$_SESSION['ventas_invitado']=[];
 $_SESSION['ventas_invitado'][(string)$saleId]=true;
 if(count($_SESSION['ventas_invitado'])>30)$_SESSION['ventas_invitado']=array_slice($_SESSION['ventas_invitado'],-30,null,true);
 respond(['ok'=>true,'folio'=>$saleId,'total'=>round($total,2),'mensaje'=>'Venta registrada.']);
}catch(Throwable $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();error_log('Compra invitado: '.$e->getMessage());respond(['ok'=>false,'mensaje'=>$e instanceof RuntimeException?$e->getMessage():'No se pudo registrar la compra. Revisa la configuración de MySQL.'],500);}
