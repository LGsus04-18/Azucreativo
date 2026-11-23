<?php
ob_start();
$activo = '';
include __DIR__ . '/menu.php';
ob_end_clean();

echo '<h3>Revisión de rutas del menú</h3><table border="1" cellpadding="6">';
foreach ($menu as $clave => [$etiqueta, $icono, $url]) {
    $existe = file_exists($_SERVER['DOCUMENT_ROOT'] . $url);
    echo '<tr><td>' . htmlspecialchars($etiqueta) . '</td><td>' . htmlspecialchars($url) . '</td><td>'
       . ($existe ? '✅ existe' : '❌ NO existe') . '</td></tr>';
}
echo '</table>';