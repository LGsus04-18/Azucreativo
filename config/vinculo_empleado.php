<?php
declare(strict_types=1);
/** Identidad de empleado explícita, sin asumir que login_db.usuarios.id = ID_Empleado. */
function obtenerIdEmpleado(PDO $db, int $usuarioLogin): int {
    $s=$db->prepare('SELECT ID_Empleado FROM Empleado_Usuario_Login WHERE ID_Usuario_Login=?');
    try { $s->execute([$usuarioLogin]); $id=$s->fetchColumn(); }
    catch(PDOException $e) { throw new RuntimeException('Falta configurar la vinculación de empleados. Ejecuta vincular_empleados_cli.php --instalar.',0,$e); }
    if($id===false || (int)$id<1) throw new RuntimeException('Tu cuenta no está vinculada a un ID_Empleado. Solicita al administrador que ejecute vincular_empleados_cli.php --vincular=USUARIO:EMPLEADO.');
    return (int)$id;
}
