<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
if (empty($_SESSION['usuario_id']) || ($_SESSION['rol'] ?? '') !== 'administrador') { http_response_code(403); echo json_encode(['ok'=>false,'mensaje'=>'Acceso denegado']); exit; }
require __DIR__ . '/../config/conexion_azucreativo.php';
function respuesta($data,$status=200){http_response_code($status);echo json_encode($data,JSON_UNESCAPED_UNICODE);exit;}
try {
  $accion=$_GET['accion'] ?? $_POST['accion'] ?? 'listar';
  if ($accion==='guardar_minimo') {
    if ($_SERVER['REQUEST_METHOD']!=='POST') respuesta(['ok'=>false,'mensaje'=>'Método no permitido'],405);
    if (!hash_equals($_SESSION['csrf_inventario'] ?? '',$_POST['csrf'] ?? '')) respuesta(['ok'=>false,'mensaje'=>'Token inválido'],403);
    $id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    $min=filter_var($_POST['minimo']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
    if ($id===false || $min===false || $id===null || $min===null) respuesta(['ok'=>false,'mensaje'=>'Valores inválidos'],422);
    $s=$pdo->prepare('UPDATE Producto SET Stock_Minimo=? WHERE ID_Producto=?');$s->execute([$min,$id]);
    respuesta(['ok'=>true,'mensaje'=>'Mínimo actualizado']);
  }
  $s=$pdo->query('SELECT ID_Producto,Nombre,Stock,Stock_Minimo FROM Producto ORDER BY CASE WHEN Stock<=0 THEN 0 WHEN Stock<=Stock_Minimo THEN 1 ELSE 2 END, Nombre');
  $productos=$s->fetchAll();$conteos=['disponibles'=>0,'bajos'=>0,'agotados'=>0];
  foreach($productos as &$x){$x['Stock']=(int)$x['Stock'];$x['Stock_Minimo']=(int)$x['Stock_Minimo'];$x['estado']=$x['Stock']<=0?'agotado':($x['Stock']<=$x['Stock_Minimo']?'bajo':'disponible');$conteos[$x['estado']==='agotado'?'agotados':($x['estado']==='bajo'?'bajos':'disponibles')]++;}unset($x);
  respuesta(['ok'=>true,'conteos'=>$conteos,'productos'=>$productos]);
} catch(Throwable $e){error_log('Alertas inventario: '.$e->getMessage());respuesta(['ok'=>false,'mensaje'=>'No se pudo consultar el inventario. Comprueba que ejecutaste la migración SQL.'],500);}
