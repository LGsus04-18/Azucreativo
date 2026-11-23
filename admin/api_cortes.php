<?php
declare(strict_types=1);
session_start();header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
function send(array $a,int $status=200):never {http_response_code($status);echo json_encode($a,JSON_UNESCAPED_UNICODE);exit;}
if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol']??'')!=='administrador')send(['ok'=>false,'mensaje'=>'No autorizado.'],403);
require __DIR__.'/../config/conexion_azucreativo.php';
$body=json_decode(file_get_contents('php://input'),true);if(!is_array($body))$body=[];
$action=(string)($body['accion']??$_GET['accion']??'listar');
try {
 if($action==='listar'){
  $c=$pdo->query('SELECT ID_Corte,ID_Usuario,Fecha_Cierre,Num_Ventas,Total_Efectivo,Total_Tarjeta,Total_Transferencia,Efectivo_Contado,Diferencia FROM Corte_Caja ORDER BY ID_Corte DESC LIMIT 500')->fetchAll();
  $a=$pdo->query('SELECT ID_Auditoria,ID_Corte,ID_Admin,Fecha,Contado_Anterior,Contado_Nuevo,Motivo FROM Auditoria_Corte_Caja ORDER BY ID_Auditoria DESC LIMIT 100')->fetchAll();
  send(['ok'=>true,'cortes'=>$c,'auditoria'=>$a]);
 }
 if($action==='corregir'){
  $id=filter_var($body['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
  $contado=$body['contado']??null;$motivo=trim((string)($body['motivo']??''));
  if(!$id || !is_numeric($contado) || !is_finite((float)$contado) || (float)$contado<0 || strlen($motivo)<5 || mb_strlen($motivo)>500)send(['ok'=>false,'mensaje'=>'Indica un folio, efectivo válido y motivo de al menos 5 caracteres.'],422);
  $pdo->beginTransaction();
  $q=$pdo->prepare('SELECT Efectivo_Contado,Total_Efectivo FROM Corte_Caja WHERE ID_Corte=? FOR UPDATE');$q->execute([$id]);$old=$q->fetch();
  if(!$old)throw new RuntimeException('Corte no encontrado.');
  $nuevo=round((float)$contado,2);
  $u=$pdo->prepare('UPDATE Corte_Caja SET Efectivo_Contado=?,Diferencia=? WHERE ID_Corte=?');$u->execute([$nuevo,round($nuevo-(float)$old['Total_Efectivo'],2),$id]);
  $log=$pdo->prepare('INSERT INTO Auditoria_Corte_Caja(ID_Corte,ID_Admin,Contado_Anterior,Contado_Nuevo,Motivo) VALUES(?,?,?,?,?)');$log->execute([$id,(int)$_SESSION['usuario_id'],$old['Efectivo_Contado'],$nuevo,$motivo]);
  $pdo->commit();send(['ok'=>true,'mensaje'=>'Corte corregido y auditado.']);
 }
 send(['ok'=>false,'mensaje'=>'Acción desconocida.'],400);
} catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('Admin cortes: '.$e->getMessage());send(['ok'=>false,'mensaje'=>$e instanceof RuntimeException?$e->getMessage():'Error de base de datos. Comprueba la migración de cortes.'],500);}
