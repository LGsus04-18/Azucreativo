<?php
/** Restablecimiento LOCAL: ejecutar exclusivamente con PHP CLI, nunca desde el navegador. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit('No disponible.'); }
function pedir(string $mensaje): string { fwrite(STDOUT, $mensaje); $v=fgets(STDIN); return $v===false?'':trim($v); }
echo "\nAZUCREATIVO - Restablecer contraseña del administrador\n";
echo "Este proceso solo modifica la contraseña de la cuenta existente 'admin' en login_db.\n\n";
$confirmar=pedir("Escribe RESTABLECER para continuar: ");
if ($confirmar !== 'RESTABLECER') { echo "Cancelado.\n"; exit(1); }
$clave=pedir("Nueva contraseña (mínimo 12 caracteres; se mostrará al escribir): ");
if (strlen($clave)<12 || strlen($clave)>200) { fwrite(STDERR,"La contraseña debe tener entre 12 y 200 caracteres.\n"); exit(1); }
$repetir=pedir("Repite la contraseña: ");
if (!hash_equals($clave,$repetir)) { fwrite(STDERR,"Las contraseñas no coinciden.\n"); exit(1); }
unset($repetir);
require __DIR__.'/login/conexion.php';
try {
    $pdo->beginTransaction();
    $stmt=$pdo->prepare('SELECT id FROM usuarios WHERE usuario = ? FOR UPDATE');
    $stmt->execute(['admin']);
    $admin=$stmt->fetch();
    if (!$admin) { $pdo->rollBack(); fwrite(STDERR,"No existe la cuenta 'admin'. No se creó otra cuenta.\n"); exit(1); }
    $hash=password_hash($clave,PASSWORD_DEFAULT);
    unset($clave);
    $update=$pdo->prepare('UPDATE usuarios SET password = ? WHERE id = ?');
    $update->execute([$hash,$admin['id']]);
    $pdo->commit();
    echo "\nContraseña actualizada para el usuario admin. Inicia sesión con la nueva clave.\n";
    echo "Elimina restablecer_admin_cli.php al terminar.\n";
} catch(Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR,"No se pudo actualizar la contraseña. Verifica MySQL, login_db y la tabla usuarios.\n"); exit(1);
}
