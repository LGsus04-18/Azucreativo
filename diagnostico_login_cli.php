<?php
/** Ejecutar SOLO por CLI: C:\xampp\php\php.exe C:\xampp\htdocs\Azucreativo\diagnostico_login_cli.php */
if (PHP_SAPI !== 'cli') {http_response_code(403);exit('Solo CLI');}
try {
 require __DIR__.'/login/conexion.php';
 echo "Conexion login_db: OK\n";
 $db=$pdo->query('SELECT DATABASE()')->fetchColumn();echo "Base seleccionada: $db\n";
 $c=$pdo->query("SHOW TABLES LIKE 'usuarios'")->fetchColumn();
 if (!$c) throw new RuntimeException('No existe la tabla usuarios en login_db');
 require __DIR__.'/login/esquema.php';prepararRoles($pdo);
 echo "Columnas rol y activo: verificadas\n";
 $count=$pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();echo "Cuentas encontradas: $count\n";
 echo "Diagnostico terminado. No se cambiaron contrasenas.\n";
} catch(Throwable $e) {fwrite(STDERR,"ERROR: ".$e->getMessage()."\n");exit(1);}
