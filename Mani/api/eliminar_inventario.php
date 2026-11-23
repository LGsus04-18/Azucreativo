<?php
session_start();
if (isset($_SESSION['usuario_id']) && ($_SESSION['rol'] ?? '') !== 'administrador') {
    if (str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/')) { http_response_code(403); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>false,'mensaje'=>'Acceso exclusivo para administradores.']); }
    else { header('Location: /Azucreativo/cliente/catalogo.php'); }
    exit;
}

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

$id = trim((string)($datos['ID_Inventario'] ?? ''));
if ($id === '' || !ctype_digit($id) || (int)$id < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'ID_Inventario no válido']);
    exit;
}

try {
    $pdo->beginTransaction();
    $lock=$pdo->prepare('SELECT ID_Producto,Cantidad,Fecha_Salida FROM Inventario WHERE ID_Inventario=? FOR UPDATE');
    $lock->execute([(int)$id]);$mov=$lock->fetch();
    if(!$mov)throw new DomainException('El registro no existe');
    $delta=$mov['Fecha_Salida']===null ? -(int)$mov['Cantidad'] : (int)$mov['Cantidad'];
    $stock=$pdo->prepare('SELECT Stock FROM Producto WHERE ID_Producto=? FOR UPDATE');
    $stock->execute([$mov['ID_Producto']]);$producto=$stock->fetch();
    if(!$producto || (int)$producto['Stock']+$delta<0)throw new DomainException('No se puede eliminar: existencias insuficientes');
    $stmt = $pdo->prepare("DELETE FROM Inventario WHERE ID_Inventario = :id");
    $stmt->execute([':id' => (int)$id]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'El registro no existe']);
        exit;
    }

    $upd=$pdo->prepare('UPDATE Producto SET Stock=Stock+? WHERE ID_Producto=?');$upd->execute([$delta,$mov['ID_Producto']]);
    $pdo->commit();
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    if($pdo->inTransaction())$pdo->rollBack();
    http_response_code($e instanceof DomainException ? 422 : 500);
    echo json_encode(['error'=>$e instanceof DomainException ? $e->getMessage() : 'No se pudo eliminar el movimiento']);
}
/*} catch (PDOException $e) {
    http_response_code(500);
    // "detalle" ayuda a depurar; quítalo cuando el sistema esté en producción.
    echo json_encode([
        'error'   => 'Error al eliminar en la base de datos',
        'detalle' => $e->getMessage(),
    ]);
}*/
