<?php
/** Instalador y asignación explícita de usuarios de login_db a IDs de empleados. Solo CLI. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit('Solo CLI');}
$host=getenv('AZ_DB_HOST')?:'localhost';$port=getenv('AZ_DB_PORT')?:'3306';$user=getenv('AZ_DB_USER')?:'root';$pass=getenv('AZ_DB_PASS')?:'';
function conectar(string $db):PDO {global $host,$port,$user,$pass;return new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);}
function tabla(PDO $db,string $nombre):?string {$s=$db->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND LOWER(TABLE_NAME)=LOWER(?) LIMIT 1');$s->execute([$nombre]);return $s->fetchColumn()?:null;}
try{
 $db=conectar('azucreativo_db');$login=conectar('login_db');
 $args=$argv;array_shift($args);
 if(in_array('--instalar',$args,true)){
  $db->exec('CREATE TABLE IF NOT EXISTS Empleado_Usuario_Login (ID_Usuario_Login INT NOT NULL PRIMARY KEY,ID_Empleado INT NOT NULL UNIQUE,Fecha_Vinculacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
  echo "OK: tabla de vinculación creada/verificada. No se asignaron identidades automáticamente.\n";
 }
 if(!tabla($db,'Empleado_Usuario_Login'))throw new RuntimeException('Primero ejecuta --instalar');
 foreach($args as $arg){if(!str_starts_with($arg,'--vincular='))continue;
  $valor=substr($arg,11);if(!preg_match('/^([1-9][0-9]*):([1-9][0-9]*)$/D',$valor,$m))throw new RuntimeException('Formato: --vincular=ID_USUARIO_LOGIN:ID_EMPLEADO');
  $uid=(int)$m[1];$eid=(int)$m[2];$s=$login->prepare('SELECT rol,activo FROM usuarios WHERE id=?');$s->execute([$uid]);$u=$s->fetch();if(!$u||$u['rol']!=='empleado'||(int)$u['activo']!==1)throw new RuntimeException('El usuario de login_db no existe o no es empleado activo.');
  if($emp=tabla($db,'Empleado')){$q=$db->prepare("SELECT 1 FROM `$emp` WHERE ID_Empleado=?");$q->execute([$eid]);if(!$q->fetchColumn())throw new RuntimeException('No existe el ID_Empleado en la tabla Empleado.');}
  else {echo "AVISO: no existe tabla Empleado; comprueba que el ID_Empleado $eid sea el identificador correcto antes de continuar.\n";}
  $q=$db->prepare('SELECT ID_Usuario_Login FROM Empleado_Usuario_Login WHERE ID_Empleado=?');$q->execute([$eid]);$dueno=$q->fetchColumn();if($dueno!==false&&(int)$dueno!==$uid)throw new RuntimeException('Este ID_Empleado ya está asignado a otro usuario.');
  $q=$db->prepare('INSERT INTO Empleado_Usuario_Login(ID_Usuario_Login,ID_Empleado) VALUES (?,?) ON DUPLICATE KEY UPDATE ID_Empleado=VALUES(ID_Empleado),Fecha_Vinculacion=NOW()');$q->execute([$uid,$eid]);echo "OK: usuario login #$uid vinculado con empleado #$eid.\n";
 }
 echo "\nVinculaciones actuales:\n";foreach($db->query('SELECT ID_Usuario_Login,ID_Empleado FROM Empleado_Usuario_Login ORDER BY ID_Usuario_Login') as $r)echo 'Usuario #'.$r['ID_Usuario_Login'].' => Empleado #'.$r['ID_Empleado']."\n";
 echo "\nUso: php vincular_empleados_cli.php --instalar\n     php vincular_empleados_cli.php --vincular=ID_USUARIO_LOGIN:ID_EMPLEADO\n";
}catch(Throwable $e){fwrite(STDERR,'ERROR: '.$e->getMessage()."\n");exit(1);}
