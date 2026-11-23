<?php
/** Migración mínima compatible con instalaciones antiguas. */
function prepararRoles(PDO $pdo): void {
    $tabla = $pdo->query("SHOW TABLES LIKE 'usuarios'")->fetchColumn();
    if (!$tabla) throw new RuntimeException('No existe login_db.usuarios');
    $cols = $pdo->query('SHOW COLUMNS FROM `usuarios`')->fetchAll(PDO::FETCH_ASSOC);
    $map = [];
    foreach ($cols as $c) $map[strtolower($c['Field'])] = $c;
    foreach (['id','usuario','correo','password'] as $required) {
        if (!isset($map[$required])) throw new RuntimeException('Falta la columna usuarios.'.$required);
    }
    if (!isset($map['rol'])) {
        $pdo->exec("ALTER TABLE `usuarios` ADD COLUMN `rol` ENUM('administrador','cliente','empleado') NOT NULL DEFAULT 'cliente'");
    } elseif (stripos($map['rol']['Type'], "'empleado'") === false) {
        $pdo->exec("ALTER TABLE `usuarios` MODIFY COLUMN `rol` ENUM('administrador','cliente','empleado') NOT NULL DEFAULT 'cliente'");
    }
    if (!isset($map['activo'])) {
        $pdo->exec('ALTER TABLE `usuarios` ADD COLUMN `activo` TINYINT(1) NOT NULL DEFAULT 1');
    }
}
