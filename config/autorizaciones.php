<?php
declare(strict_types=1);
function authDb(): PDO {require __DIR__.'/../login/conexion.php';return $pdo;}
function validarAutorizacion(PDO $db,int $adminId,string $clave): bool {
 if($adminId<1 || !preg_match('/^[0-9]{4}$/D', $clave)) return false;
 $s=$db->prepare("SELECT a.clave_hash,a.vence_en FROM admin_autorizaciones a JOIN usuarios u ON u.id=a.id_usuario WHERE a.id_usuario=? AND u.rol='administrador' AND u.activo=1");
 $s->execute([$adminId]);$r=$s->fetch();
 return $r && strtotime($r['vence_en'])>time() && password_verify($clave,$r['clave_hash']);
}
function exigirAutorizacion(PDO $db,array $body): int {
 $actor=(int)($_SESSION['usuario_id']??0);
 $id=(int)($body['admin_autorizador']??$actor);
 if(!validarAutorizacion($db,$id,(string)($body['clave_autorizacion']??''))) {
  http_response_code(403);header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok'=>false,'mensaje'=>'Clave de autorización administrativa incorrecta, no configurada o vencida.']);exit;
 }
 return $id;
}
function auditar(PDO $db,int $actor,string $accion,string $objeto): void {
 $s=$db->prepare('INSERT INTO auditoria_autorizaciones(actor_id,accion,objeto) VALUES (?,?,?)');$s->execute([$actor,$accion,$objeto]);
}
