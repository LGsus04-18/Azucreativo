<?php
/** Crea una referencia técnica por venta, sin registrar una cuenta de acceso. */
function crearClienteMostrador(PDO $pdo): int {
    if (!$pdo->inTransaction()) {
        throw new LogicException('El cliente de mostrador debe crearse dentro de la transacción de venta.');
    }
    $stmt = $pdo->prepare("INSERT INTO Cliente (Nombre, Apellido, Correo) VALUES ('Cliente', 'No registrado', NULL)");
    $stmt->execute();
    return (int)$pdo->lastInsertId();
}
