<?php
session_start();
if (isset($_SESSION['usuario_id']) && ($_SESSION['rol'] ?? '') !== 'administrador') {
    if (str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/')) { http_response_code(403); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>false,'mensaje'=>'Acceso exclusivo para administradores.']); }
    else { header('Location: /Azucreativo/cliente/catalogo.php'); }
    exit;
}

date_default_timezone_set('America/Mexico_City');   // hora de México
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

require __DIR__ . '/../../config/conexion_azucreativo.php';

$datos = json_decode(file_get_contents('php://input'), true) ?? [];

function entero_positivo($valor) {
    $valor = trim((string)($valor ?? ''));
    return ($valor !== '' && ctype_digit($valor) && (int)$valor >= 1) ? (int)$valor : false;
}

function validar_fecha($valor) {
    $valor = trim((string)($valor ?? ''));
    if ($valor === '') {
        return null;
    }
    $d = DateTime::createFromFormat('Y-m-d', $valor);
    if (!$d || $d->format('Y-m-d') !== $valor) {
        return false;
    }
    return $valor;
}

// Si el día no cambió se conserva la hora que ya tenía; si cambió, se usa la hora actual
function fecha_con_hora($nueva, $actual, $hora) {
    if ($nueva === null) {
        return null;                                   // campo vacío
    }
    if ($actual !== null && substr((string)$actual, 0, 10) === $nueva) {
        return $actual;                                // mismo día: conserva fecha y hora guardadas
    }
    return $nueva . ' ' . $hora;                       // día nuevo: con la hora actual
}

$id         = entero_positivo($datos['ID_Inventario'] ?? '');
$idProducto = entero_positivo($datos['ID_Producto'] ?? '');
$cantidad   = trim((string)($datos['Cantidad'] ?? ''));
$entrada    = validar_fecha($datos['Fecha_Entrada'] ?? '');
$salida     = validar_fecha($datos['Fecha_Salida'] ?? '');

if ($id === false) {
    http_response_code(400);
    echo json_encode(['error' => 'ID_Inventario no válido']);
    exit;
}
if ($idProducto === false) {
    http_response_code(400);
    echo json_encode(['error' => 'El ID_Producto debe ser un número entero válido']);
    exit;
}
if ($cantidad === '' || !ctype_digit($cantidad)) {
    http_response_code(400);
    echo json_encode(['error' => 'La cantidad debe ser un número entero']);
    exit;
}
if ($entrada === false || $salida === false) {
    http_response_code(400);
    echo json_encode(['error' => 'Fecha no válida']);
    exit;
}

try {
    $pdo->beginTransaction();
    // Verificar que el registro exista
    $existe = $pdo->prepare("SELECT ID_Producto,Cantidad,Fecha_Entrada, Fecha_Salida FROM Inventario WHERE ID_Inventario = :id FOR UPDATE");
    $existe->execute([':id' => $id]);
    $actual = $existe->fetch();
    if (!$actual) {
        http_response_code(404);
        echo json_encode(['error' => 'El registro no existe']);
        exit;
    }

    $hora = (new DateTime())->format('H:i:s');
    // Fecha_Entrada vacía: se conserva la que ya tenía
    $entrada = ($entrada === null)
        ? $actual['Fecha_Entrada']
        : fecha_con_hora($entrada, $actual['Fecha_Entrada'], $hora);
    $salida = fecha_con_hora($salida, $actual['Fecha_Salida'], $hora);

    $oldDelta=$actual['Fecha_Salida'] === null ? (int)$actual['Cantidad'] : -(int)$actual['Cantidad'];
    $newDelta=$salida === null ? (int)$cantidad : -(int)$cantidad;
    $diffs=[(int)$actual['ID_Producto']=>-$oldDelta];
    $diffs[(int)$idProducto]=($diffs[(int)$idProducto]??0)+$newDelta;
    ksort($diffs);
    foreach($diffs as $pid=>$diff){
        $lock=$pdo->prepare('SELECT Stock FROM Producto WHERE ID_Producto=? FOR UPDATE');$lock->execute([$pid]);$row=$lock->fetch();
        if(!$row) throw new DomainException('El producto no existe');
        if((int)$row['Stock']+$diff<0) throw new DomainException('No hay existencias suficientes para modificar este movimiento');
        $upd=$pdo->prepare('UPDATE Producto SET Stock=Stock+? WHERE ID_Producto=?');$upd->execute([$diff,$pid]);
    }
    $stmt = $pdo->prepare(
        "UPDATE Inventario
            SET ID_Producto = :producto,
                Cantidad = :cantidad,
                Fecha_Entrada = :entrada,
                Fecha_Salida = :salida
          WHERE ID_Inventario = :id"
    );
    $stmt->execute([
        ':producto' => $idProducto,
        ':cantidad' => (int)$cantidad,
        ':entrada'  => $entrada,
        ':salida'   => $salida,
        ':id'       => $id,
    ]);

    $pdo->commit();
    echo json_encode(['ok' => true, 'id' => $id]);
} catch (Throwable $e) {
    if($pdo->inTransaction())$pdo->rollBack();
    http_response_code($e instanceof DomainException ? 422 : 500);
    echo json_encode(['error'=>$e instanceof DomainException ? $e->getMessage() : 'No se pudo modificar el movimiento']);
}
/*} catch (PDOException $e) {
    http_response_code(500);
    // "detalle" ayuda a depurar; quítalo cuando el sistema esté en producción.
    echo json_encode([
        'error'   => 'Error al actualizar en la base de datos',
        'detalle' => $e->getMessage(),
    ]);
}*/
