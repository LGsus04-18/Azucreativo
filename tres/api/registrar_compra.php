<?php
session_start();
if (isset($_SESSION['usuario_id']) && ($_SESSION['rol'] ?? '') !== 'administrador') {
    if (str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/')) { http_response_code(403); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>false,'mensaje'=>'Acceso exclusivo para administradores.']); }
    else { header('Location: /Azucreativo/cliente/catalogo.php'); }
    exit;
}

header('Content-Type: application/json; charset=utf-8');
function responder($data, $status=200) { http_response_code($status); echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
if (!isset($_SESSION['usuario_id'])) responder(['error'=>'Inicia sesión'],401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder(['error'=>'Método no permitido'],405);
require __DIR__.'/../../config/conexion_azucreativo.php';
$d=json_decode(file_get_contents('php://input'),true);
if (!is_array($d)) responder(['error'=>'Datos inválidos'],400);
require_once __DIR__.'/../../config/autorizaciones.php';
$loginDb=authDb();
$autorizador=exigirAutorizacion($loginDb,$d);

$proveedor=filter_var($d['ID_Proveedor']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
$producto=filter_var($d['ID_Producto']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
$cantidad=filter_var($d['Cantidad']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
$precio=$d['Precio_Unitario']??null;
$metodo=$d['Metodo_Pago']??'';
$fecha=$d['Fecha_Compra']??'';
$dt=DateTime::createFromFormat('!Y-m-d',$fecha);
if (!$proveedor||!$producto||!$cantidad||!is_numeric($precio)||(float)$precio<0||!is_finite((float)$precio)||!in_array($metodo,['Efectivo','Crédito','Transferencia'],true)||!$dt||$dt->format('Y-m-d')!==$fecha) responder(['error'=>'Revisa proveedor, producto, cantidad, precio, método y fecha'],422);
if ($cantidad>1000000||(float)$precio>100000000) responder(['error'=>'Cantidad o precio fuera de rango'],422);
$total=number_format($cantidad*(float)$precio,2,'.','');
try {
 $pdo->beginTransaction();
 $s=$pdo->prepare('SELECT ID_Proveedor FROM Proveedor WHERE ID_Proveedor=?');$s->execute([$proveedor]);if(!$s->fetch())throw new DomainException('El proveedor no existe');
 $s=$pdo->prepare('SELECT ID_Producto FROM Producto WHERE ID_Producto=? FOR UPDATE');$s->execute([$producto]);if(!$s->fetch())throw new DomainException('El producto no existe');
 $s=$pdo->prepare('INSERT INTO Compra (ID_Proveedor,Fecha_Compra,Metodo_Pago,Total_Compra) VALUES (?,?,?,?)');$s->execute([$proveedor,$fecha,$metodo,$total]);$compra=$pdo->lastInsertId();
 $s=$pdo->prepare('INSERT INTO Detalle_Compra (ID_Compra,ID_Producto,Cantidad,Precio_Unitario,Subtotal) VALUES (?,?,?,?,?)');$s->execute([$compra,$producto,$cantidad,$precio,$total]);
 // Una compra crea una entrada de inventario, sin sobrescribir movimientos anteriores.
 $s=$pdo->prepare('INSERT INTO Inventario (ID_Producto,Cantidad,Fecha_Entrada,Fecha_Salida) VALUES (?,?,NOW(),NULL)');$s->execute([$producto,$cantidad]);
 $s=$pdo->prepare('UPDATE Producto SET Stock=Stock+? WHERE ID_Producto=?');$s->execute([$cantidad,$producto]);
 $pdo->commit();auditar($loginDb,(int)$_SESSION['usuario_id'],'compra_proveedor',(string)$compra);responder(['ok'=>true,'id_compra'=>$compra,'producto'=>$producto,'unidades_agregadas'=>$cantidad]);
} catch(DomainException $e) {if($pdo->inTransaction())$pdo->rollBack();responder(['error'=>$e->getMessage()],422);
} catch(Throwable $e) {if($pdo->inTransaction())$pdo->rollBack();error_log('Error compra/inventario: '.$e->getMessage());responder(['error'=>'No se pudo registrar la compra. Verifica la estructura de las tablas.'],500);}
