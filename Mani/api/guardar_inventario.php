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

// ID_Producto: obligatorio, entero positivo (debe existir en la tabla Producto)
$idProducto = trim((string)($datos['ID_Producto'] ?? ''));
if ($idProducto === '' || !ctype_digit($idProducto) || (int)$idProducto < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'El ID_Producto debe ser un número entero válido']);
    exit;
}

// Cantidad: obligatoria, entero >= 0
$cantidad = trim((string)($datos['Cantidad'] ?? ''));
if ($cantidad === '' || !ctype_digit($cantidad)) {
    http_response_code(400);
    echo json_encode(['error' => 'La cantidad debe ser un número entero']);
    exit;
}

// Fechas: opcionales (formato AAAA-MM-DD). Si vienen vacías se guarda NULL.
function validarFecha($valor) {
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

$fechaEntrada = validarFecha($datos['Fecha_Entrada'] ?? '');
$fechaSalida  = validarFecha($datos['Fecha_Salida'] ?? '');

if ($fechaEntrada === false || $fechaSalida === false) {
    http_response_code(400);
    echo json_encode(['error' => 'Fecha no válida']);
    exit;
}

// Fecha + hora de la máquina (servidor):
//  - Fecha_Entrada vacía  -> ahora mismo (fecha y hora)
//  - día elegido          -> ese día con la hora actual
$ahora = new DateTime();
$hora  = $ahora->format('H:i:s');
$fechaEntrada = ($fechaEntrada === null) ? $ahora->format('Y-m-d H:i:s') : "$fechaEntrada $hora";
$fechaSalida  = ($fechaSalida === null) ? null : "$fechaSalida $hora";

try {
    $pdo->beginTransaction();
    $lock=$pdo->prepare('SELECT Stock FROM Producto WHERE ID_Producto=? FOR UPDATE');
    $lock->execute([(int)$idProducto]);
    $actual=$lock->fetch();
    if (!$actual) throw new DomainException('El producto no existe');
    $delta = $fechaSalida === null ? (int)$cantidad : -(int)$cantidad;
    if ((int)$actual['Stock']+$delta<0) throw new DomainException('No hay existencias suficientes');
    $stmt = $pdo->prepare(
        "INSERT INTO Inventario (ID_Producto, Cantidad, Fecha_Entrada, Fecha_Salida)
         VALUES (:producto, :cantidad, :entrada, :salida)"
    );
    $stmt->execute([
        ':producto' => (int)$idProducto,
        ':cantidad' => (int)$cantidad,
        ':entrada'  => $fechaEntrada,
        ':salida'   => $fechaSalida,
    ]);

    $idNuevo=$pdo->lastInsertId();
    $upd=$pdo->prepare('UPDATE Producto SET Stock=Stock+? WHERE ID_Producto=?');
    $upd->execute([$delta,(int)$idProducto]);
    $pdo->commit();
    echo json_encode(['ok' => true, 'id' => $idNuevo]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code($e instanceof DomainException ? 422 : 500);
    echo json_encode(['error'=>$e instanceof DomainException ? $e->getMessage() : 'No se pudo registrar el movimiento']);
}
/*} catch (PDOException $e) {
    http_response_code(500);
    // "detalle" ayuda a depurar; quítalo cuando el sistema esté en producción.
    echo json_encode([
        'error'   => 'Error al guardar en la base de datos',
        'detalle' => $e->getMessage(),
    ]);
}*/
