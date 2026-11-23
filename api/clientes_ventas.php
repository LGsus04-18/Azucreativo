<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/tarjeta_demo_backend.php';
require_once __DIR__ . '/../config/cliente_mostrador.php';

session_start();
if (isset($_SESSION['usuario_id']) && ($_SESSION['rol'] ?? '') !== 'administrador') {
    if (str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/')) { http_response_code(403); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>false,'mensaje'=>'Acceso exclusivo para administradores.']); }
    else { header('Location: /Azucreativo/cliente/catalogo.php'); }
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'mensaje' => 'La sesión terminó. Inicia sesión de nuevo.']);
    exit;
}

require_once __DIR__ . '/../config/conexion_azucreativo.php';

function responder(array $datos, int $codigo = 200): never {
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function jsonEntrada(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '[]', true);
    return is_array($data) ? $data : [];
}
function requerido($valor): bool { return trim((string)$valor) !== ''; }

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $entrada = $method === 'POST' ? jsonEntrada() : $_GET;
    $accion = (string)($entrada['accion'] ?? $_GET['accion'] ?? '');

    switch ($accion) {
        case 'listar_clientes': {
            $stmt = $pdo->query("SELECT ID_Cliente, Nombre, Apellido, Correo, Telefono, Direccion FROM Cliente WHERE NOT (Nombre = 'Cliente' AND Apellido = 'No registrado' AND (Correo LIKE 'anonimo-%@clientes.invalid' OR Correo IS NULL)) ORDER BY ID_Cliente DESC");
            responder(['ok' => true, 'datos' => $stmt->fetchAll()]);
        }
        case 'buscar_clientes': {
            $q = trim((string)($entrada['q'] ?? ''));
            if ($q === '') responder(['ok' => false, 'mensaje' => 'Escribe un correo, teléfono o apellido para buscar.'], 422);
            $stmt = $pdo->prepare("SELECT ID_Cliente, Nombre, Apellido, Correo, Telefono, Direccion FROM Cliente WHERE NOT (Nombre = 'Cliente' AND Apellido = 'No registrado' AND (Correo LIKE 'anonimo-%@clientes.invalid' OR Correo IS NULL)) AND (Correo LIKE :correo OR Telefono LIKE :telefono OR Apellido LIKE :apellido) ORDER BY Apellido, Nombre");
            $like = '%' . $q . '%';
            $stmt->execute([':correo' => $like, ':telefono' => $like, ':apellido' => $like]);
            responder(['ok' => true, 'datos' => $stmt->fetchAll()]);
        }
        case 'guardar_cliente': {
            require_once __DIR__.'/../config/autorizaciones.php';
            $loginDb=authDb();$autorizador=exigirAutorizacion($loginDb,$entrada);
            $id = (int)($entrada['ID_Cliente'] ?? 0);
            $nombre = trim((string)($entrada['Nombre'] ?? ''));
            $apellido = trim((string)($entrada['Apellido'] ?? ''));
            $correo = trim((string)($entrada['Correo'] ?? ''));
            $telefono = trim((string)($entrada['Telefono'] ?? ''));
            $direccion = trim((string)($entrada['Direccion'] ?? ''));
            if ($nombre === '' || $apellido === '') responder(['ok' => false, 'mensaje' => 'Nombre y apellido son obligatorios.'], 422);
            if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) responder(['ok' => false, 'mensaje' => 'El correo no tiene un formato válido.'], 422);
            $correo = $correo === '' ? null : $correo;
            $telefono = $telefono === '' ? null : $telefono;
            $direccion = $direccion === '' ? null : $direccion;
            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE Cliente SET Nombre=?, Apellido=?, Correo=?, Telefono=?, Direccion=? WHERE ID_Cliente=?');
                $stmt->execute([$nombre, $apellido, $correo, $telefono, $direccion, $id]);
                responder(['ok' => true, 'mensaje' => 'Cliente actualizado correctamente.', 'id' => $id]);
            }
            $stmt = $pdo->prepare('INSERT INTO Cliente (Nombre, Apellido, Correo, Telefono, Direccion) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$nombre, $apellido, $correo, $telefono, $direccion]);
            responder(['ok' => true, 'mensaje' => 'Cliente registrado correctamente.', 'id' => (int)$pdo->lastInsertId()]);
        }
        case 'eliminar_cliente': {
            require_once __DIR__.'/../config/autorizaciones.php';
            $loginDb=authDb();$autorizador=exigirAutorizacion($loginDb,$entrada);
            $id = (int)($entrada['ID_Cliente'] ?? 0);
            if ($id < 1) responder(['ok' => false, 'mensaje' => 'ID de cliente inválido.'], 422);
            $stmt = $pdo->prepare('SELECT Nombre, Apellido, Correo FROM Cliente WHERE ID_Cliente=?');
            $stmt->execute([$id]);
            $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$cliente) responder(['ok'=>false,'mensaje'=>'El cliente ya no existe. Actualiza la lista.'],404);
            // No borrar perfiles registrados desde el módulo de clientes.
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM Cliente_Usuario_Web WHERE ID_Cliente=?');
            $stmt->execute([$id]);
            if ((int)$stmt->fetchColumn() > 0) responder(['ok'=>false,'mensaje'=>'Este cliente tiene una cuenta registrada. Administra su perfil desde Administración de cuentas.'],409);
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM Venta WHERE ID_Cliente=?');
            $stmt->execute([$id]);
            $ventas = (int)$stmt->fetchColumn();
            $anonimo = ($cliente['Nombre'] === 'Cliente' && $cliente['Apellido'] === 'No registrado'
                && preg_match('/^anonimo-[0-9]+@clientes\.invalid$/', (string)$cliente['Correo']));
            if ($ventas > 0) {
                if ($anonimo) responder(['ok'=>true,'mensaje'=>'Cliente anónimo retirado de la lista. Se conserva su registro técnico para proteger las ventas históricas.']);
                responder(['ok'=>false,'mensaje'=>'No se puede borrar este cliente porque tiene '.$ventas.' venta(s). Sus datos y ventas se conservaron. Si necesitas anonimizarlo, realiza primero una revisión de sus datos.'],409);
            }
            try {
                $stmt = $pdo->prepare('DELETE FROM Cliente WHERE ID_Cliente = ?');
                $stmt->execute([$id]);
                responder(['ok'=>true,'mensaje'=>'Cliente sin ventas eliminado definitivamente.']);
            } catch (PDOException $ex) {
                if ($ex->getCode() === '23000') responder(['ok'=>false,'mensaje'=>'No se eliminó: el cliente tiene otros registros relacionados. Se conservaron sus datos y ventas.'],409);
                throw $ex;
            }
        }
        case 'listar_productos': {
            $stmt = $pdo->query('SELECT ID_Producto, Nombre, Precio, Stock FROM Producto ORDER BY Nombre');
            responder(['ok' => true, 'datos' => $stmt->fetchAll()]);
        }
        case 'listar_ventas': {
            $stmt = $pdo->query('SELECT v.ID_Venta, v.ID_Cliente, CONCAT(c.Nombre, " ", c.Apellido) AS Cliente, v.Fecha_Venta, v.Metodo_Pago, v.Estado_Venta, v.Total FROM Venta v INNER JOIN Cliente c ON c.ID_Cliente=v.ID_Cliente ORDER BY v.ID_Venta DESC');
            responder(['ok' => true, 'datos' => $stmt->fetchAll()]);
        }
        case 'guardar_venta': {
            $idCliente = (int)($entrada['ID_Cliente'] ?? 0);
            $metodo = (string)($entrada['Metodo_Pago'] ?? 'Efectivo');
            try { $tarjeta4=tarjetaDemoUltimos4($entrada,$metodo); } catch (InvalidArgumentException $e) { responder(['ok'=>false,'mensaje'=>$e->getMessage()],422); }
            $estado = (string)($entrada['Estado_Venta'] ?? 'Pagada');
            $items = $entrada['items'] ?? [];
            if ($idCliente < 0) responder(['ok' => false, 'mensaje' => 'Selecciona un cliente válido.'], 422);
            if (!in_array($metodo, ['Efectivo', 'Tarjeta', 'Transferencia'], true)) responder(['ok' => false, 'mensaje' => 'Método de pago no válido.'], 422);
            if (!in_array($estado, ['Pagada', 'Pendiente', 'Cancelada'], true)) responder(['ok' => false, 'mensaje' => 'Estado de venta no válido.'], 422);
            if (!is_array($items) || count($items) === 0) responder(['ok' => false, 'mensaje' => 'Agrega al menos un producto a la venta.'], 422);
            if ($idCliente > 0) {
                $stmt = $pdo->prepare('SELECT ID_Cliente FROM Cliente WHERE ID_Cliente = ?');
                $stmt->execute([$idCliente]);
                if (!$stmt->fetch()) responder(['ok' => false, 'mensaje' => 'El cliente seleccionado no existe.'], 422);
            }

            $pdo->beginTransaction();
            if ($idCliente === 0) $idCliente = crearClienteMostrador($pdo);
            $lineas = [];
            $total = 0.0;
            foreach ($items as $item) {
                $idProducto = (int)($item['ID_Producto'] ?? 0);
                $cantidad = (int)($item['Cantidad'] ?? 0);
                if ($idProducto < 1 || $cantidad < 1) throw new RuntimeException('Cada producto debe tener una cantidad mayor a cero.');
                $stmtProducto = $pdo->prepare('SELECT ID_Producto, Nombre, Precio, Stock FROM Producto WHERE ID_Producto = ? FOR UPDATE');
                $stmtProducto->execute([$idProducto]);
                $producto = $stmtProducto->fetch();
                if (!$producto) throw new RuntimeException("No existe el producto con ID {$idProducto}.");
                if ((int)$producto['Stock'] < $cantidad) throw new RuntimeException("Stock insuficiente para {$producto['Nombre']}. Disponible: {$producto['Stock']}.");
                $precio = (float)$producto['Precio'];
                $subtotal = round($precio * $cantidad, 2);
                $total += $subtotal;
                $lineas[] = ['ID_Producto' => $idProducto, 'Cantidad' => $cantidad, 'Precio_Unitario' => $precio, 'Subtotal' => $subtotal];
            }
            $stmtVenta = $pdo->prepare('INSERT INTO Venta (ID_Cliente, Metodo_Pago, Estado_Venta, Total) VALUES (?, ?, ?, ?)');
            $stmtVenta->execute([$idCliente, $metodo, $estado, round($total, 2)]);
            $idVenta = (int)$pdo->lastInsertId();
            $stmtDetalle = $pdo->prepare('INSERT INTO Detalle_Venta (ID_Venta, ID_Producto, Cantidad, Precio_Unitario, Subtotal) VALUES (?, ?, ?, ?, ?)');
            $stmtStock = $pdo->prepare('UPDATE Producto SET Stock = Stock - ? WHERE ID_Producto = ?');
            foreach ($lineas as $linea) {
                $stmtDetalle->execute([$idVenta, $linea['ID_Producto'], $linea['Cantidad'], $linea['Precio_Unitario'], $linea['Subtotal']]);
                $stmtStock->execute([$linea['Cantidad'], $linea['ID_Producto']]);
            }
            registrarTarjetaDemo($pdo,$idVenta,$tarjeta4);
            $pdo->commit();
            responder(['ok' => true, 'mensaje' => 'Venta registrada correctamente.', 'ID_Venta' => $idVenta, 'Total' => round($total, 2)]);
        }
        case 'consultar_ticket': {
            $id = (int)($entrada['ID_Venta'] ?? $entrada['q'] ?? 0);
            if ($id < 1) responder(['ok' => false, 'mensaje' => 'Escribe un número de ticket válido.'], 422);
            $stmt = $pdo->prepare('SELECT v.ID_Venta, v.Fecha_Venta, v.Metodo_Pago, v.Estado_Venta, v.Total, c.ID_Cliente, c.Nombre, c.Apellido, c.Correo, c.Telefono FROM Venta v INNER JOIN Cliente c ON c.ID_Cliente=v.ID_Cliente WHERE v.ID_Venta = ?');
            $stmt->execute([$id]);
            $venta = $stmt->fetch();
            if (!$venta) responder(['ok' => false, 'mensaje' => 'No se encontró una venta con ese número de ticket.'], 404);
            $stmtDetalle = $pdo->prepare('SELECT dv.ID_DetalleVenta, dv.ID_Producto, p.Nombre AS Producto, dv.Cantidad, dv.Precio_Unitario, dv.Subtotal FROM Detalle_Venta dv INNER JOIN Producto p ON p.ID_Producto=dv.ID_Producto WHERE dv.ID_Venta = ? ORDER BY dv.ID_DetalleVenta');
            $stmtDetalle->execute([$id]);
            responder(['ok' => true, 'venta' => $venta, 'detalles' => $stmtDetalle->fetchAll()]);
        }
        case 'listar_detalles': {
            $stmt = $pdo->query('SELECT dv.ID_DetalleVenta, dv.ID_Venta, dv.ID_Producto, p.Nombre AS Producto, dv.Cantidad, dv.Precio_Unitario, dv.Subtotal FROM Detalle_Venta dv INNER JOIN Producto p ON p.ID_Producto=dv.ID_Producto ORDER BY dv.ID_DetalleVenta DESC');
            responder(['ok' => true, 'datos' => $stmt->fetchAll()]);
        }
        default:
            responder(['ok' => false, 'mensaje' => 'Acción no reconocida.'], 400);
    }
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    if ($e->getCode() === '23000') responder(['ok' => false, 'mensaje' => 'No se pudo guardar: revisa que el correo no esté duplicado y que las relaciones sean válidas.'], 409);
    responder(['ok' => false, 'mensaje' => 'Error de base de datos. Verifica que las tablas y columnas coincidan con el SQL importado.'], 500);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    responder(['ok' => false, 'mensaje' => $e->getMessage()], 422);
}
