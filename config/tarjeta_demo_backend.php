<?php
// Datos de demostración: no almacenar PAN, CVV, nombre ni fecha de vencimiento.
function tarjetaDemoUltimos4(array $entrada, string $metodo): ?string {
    if ($metodo !== 'Tarjeta') return null;
    $ultimos = $entrada['tarjeta_ultimos4'] ?? null;
    // Aceptar cuatro cifras como texto o número; nunca aceptar ni guardar el PAN completo.
    if (is_int($ultimos)) $ultimos = (string)$ultimos;
    if (!is_string($ultimos)) {
        throw new InvalidArgumentException('El formulario no envió los últimos cuatro dígitos. Comprueba que se hayan actualizado los archivos JavaScript del módulo de ventas.');
    }
    $ultimos = trim($ultimos);
    if (!preg_match('/^[0-9]{4}$/D', $ultimos)) {
        throw new InvalidArgumentException('El pago requiere exactamente los últimos cuatro dígitos de la tarjeta. Vuelve a ingresar los 16 dígitos en el formulario.');
    }
    return $ultimos;
}
function registrarTarjetaDemo(PDO $pdo, int $ventaId, ?string $ultimos): void {
    if ($ultimos === null) return;
    $q=$pdo->prepare('INSERT INTO Venta_Tarjeta_Demo (ID_Venta, Ultimos4) VALUES (?, ?)');
    $q->execute([$ventaId, $ultimos]);
}
