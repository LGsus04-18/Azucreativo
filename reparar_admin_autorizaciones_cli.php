<?php
/** Reparador seguro, solo CLI. No ejecuta cambios destructivos. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {http_response_code(403);exit('Solo consola');}
require __DIR__.'/login/conexion.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {fwrite(STDERR,"No se pudo conectar a login_db.\n");exit(1);}
$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
function fail(string $m): never {fwrite(STDERR,"ERROR: $m\n");exit(1);}
function cols(PDO $db,string $table): array {
 $q=$db->prepare('SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_KEY,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$q->execute([$table]);return array_column($q->fetchAll(PDO::FETCH_ASSOC),null,'COLUMN_NAME');
}
function engine(PDO $db,string $table): ?string {
 $q=$db->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$q->execute([$table]);$v=$q->fetchColumn();return $v===false?null:(string)$v;
}
function fkExists(PDO $db): bool {
 $q=$db->query("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='admin_autorizaciones' AND COLUMN_NAME='id_usuario' AND REFERENCED_TABLE_NAME='usuarios' AND REFERENCED_COLUMN_NAME='id'");return (int)$q->fetchColumn()>0;
}
try {
 $db=$pdo->query('SELECT DATABASE()')->fetchColumn();echo "Base conectada: $db\n";
 if(strtolower((string)$db)!=='login_db') fail('La conexión no apunta a login_db.');
 $uEngine=engine($pdo,'usuarios');if(!$uEngine)fail('Falta tabla usuarios.');
 if(strtolower($uEngine)!=='innodb')fail('usuarios no usa InnoDB; haz respaldo y revisa antes de convertir.');
 $u=cols($pdo,'usuarios');if(!isset($u['id']))fail('usuarios no tiene columna id.');
 $type=strtolower($u['id']['COLUMN_TYPE']);
 if(!preg_match('/^(tinyint|smallint|mediumint|int|integer|bigint)(\(\d+\))?( unsigned)?$/',$type))fail("Tipo de usuarios.id no soportado: $type");
 if(!in_array($u['id']['COLUMN_KEY'],['PRI','UNI'],true))fail('usuarios.id no es PRIMARY/UNIQUE.');
 $e=engine($pdo,'admin_autorizaciones');
 if(!$e){
  echo "Creando admin_autorizaciones con id_usuario $type ...\n";
  $pdo->exec("CREATE TABLE `admin_autorizaciones` (`id_usuario` $type NOT NULL PRIMARY KEY,`clave_hash` VARCHAR(255) NOT NULL,`vence_en` DATETIME NOT NULL,`actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 } elseif(strtolower($e)!=='innodb')fail('admin_autorizaciones no usa InnoDB.');
 $a=cols($pdo,'admin_autorizaciones');
 foreach(['id_usuario','clave_hash','vence_en','actualizado_en'] as $c)if(!isset($a[$c]))fail("Falta admin_autorizaciones.$c; se requiere revisión manual.");
 if(strtolower($a['id_usuario']['COLUMN_TYPE'])!==$type){
  $count=(int)$pdo->query('SELECT COUNT(*) FROM admin_autorizaciones')->fetchColumn();
  if($count>0)fail("Tipo incompatible: admin_autorizaciones.id_usuario={$a['id_usuario']['COLUMN_TYPE']}, usuarios.id=$type. Hay $count registros; no se alteran automáticamente.");
  echo "Ajustando id_usuario de tabla vacía a $type ...\n";
  $pdo->exec("ALTER TABLE `admin_autorizaciones` MODIFY COLUMN `id_usuario` $type NOT NULL");
 }
 if(!fkExists($pdo)){
  $orphans=$pdo->query('SELECT COUNT(*) FROM admin_autorizaciones a LEFT JOIN usuarios u ON u.id=a.id_usuario WHERE u.id IS NULL')->fetchColumn();
  if((int)$orphans>0)fail("Hay $orphans autorizaciones sin usuario; no se agrega clave foránea.");
  $exists=$pdo->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='fk_admin_autorizacion_usuario' AND CONSTRAINT_TYPE='FOREIGN KEY'")->fetchColumn();
  if((int)$exists>0)fail('El nombre fk_admin_autorizacion_usuario ya está ocupado por otra relación.');
  $pdo->exec('ALTER TABLE `admin_autorizaciones` ADD CONSTRAINT `fk_admin_autorizacion_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE');
  echo "Clave foránea creada.\n";
 } else echo "Clave foránea ya existe.\n";
 echo "OK: estructura de autorizaciones verificada. No se cambiaron PIN ni contraseñas.\n";
} catch(Throwable $ex){fail($ex->getMessage());}
