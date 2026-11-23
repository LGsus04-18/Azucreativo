<?php
/*
 * Menú lateral compartido por TODAS las páginas.
 *
 * Uso: antes del include, indica cuál página está activa:
 *   <?php $activo = 'cliente'; include __DIR__ . '/../config/menu.php'; ?>
 *
 * Si cambias el nombre de una carpeta, edítalo SOLO aquí
 * y se actualiza en todas las páginas.
 */
$raiz = '/Azucreativo';

$menu = [
    'home'           => ['Página Principal', '🛍️', "$raiz/index.php"],
    'cliente'        => ['Cliente',          '👥', "$raiz/dos/cliente.php"],
    'venta'          => ['Venta',            '📊', "$raiz/dos/venta.php"],
    'detalle_venta'  => ['Detalle_Venta',    '📋', "$raiz/dos/detalle_venta.php"],
    'producto'       => ['Producto',         '🍬', "$raiz/uno/producto.php"],
    'categoria'      => ['Categoría',        '🏷️', "$raiz/uno/categoria.php"],
    'proveedor'      => ['Proveedor',        '🚚', "$raiz/tres/proveedor.php"],
    'compra'         => ['Compra',           '🛒', "$raiz/tres/compra.php"],
    'detalle_compra' => ['Detalle_Compra',   '📑', "$raiz/tres/detalle_compra.php"],
    'usuarios'       => ['Cuentas y permisos', '🔐', "$raiz/admin/usuarios.php"],
    'reportes'       => ['Reportes y gráficas', '📊', "$raiz/admin/reportes.php"],
    'cortes'         => ['Cortes de caja', '💰', "$raiz/admin/cortes.php"],
    'inventario'     => ['Inventario',       '🗃️', "$raiz/Mani/inventario.php"],
];

$activo = $activo ?? '';
?>
<nav class="sidebar-nav" id="azu-main-navigation" aria-label="Navegación principal">
    <a href="<?= htmlspecialchars($menu['home'][2], ENT_QUOTES, 'UTF-8') ?>" class="sidebar-item<?= $activo === 'home' ? ' active' : '' ?>"><span class="nav-label"><span class="icon">🏠</span> Inicio</span></a>
    <?php
    $grupos = [
        'Ventas y clientes' => ['cliente','venta','detalle_venta'],
        'Productos e inventario' => ['producto','categoria','inventario'],
        'Compras y proveedores' => ['proveedor','compra','detalle_compra'],
        'Administración' => ['usuarios','cortes','reportes'],
    ];
    $iconos = ['Ventas y clientes'=>'🛒','Productos e inventario'=>'🍬','Compras y proveedores'=>'📦','Administración'=>'⚙️'];
    foreach ($grupos as $titulo => $claves):
        $abierto = in_array($activo, $claves, true);
    ?>
    <details class="azu-nav-group" <?= $abierto ? 'open' : '' ?>>
        <summary><span><?= $iconos[$titulo] ?> <?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') ?></span><span class="azu-chevron" aria-hidden="true">⌄</span></summary>
        <div class="azu-nav-subitems">
        <?php foreach ($claves as $clave): [$etiqueta,$icono,$url] = $menu[$clave]; ?>
            <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" class="sidebar-item<?= $activo === $clave ? ' active' : '' ?>"><span class="nav-label"><span class="icon"><?= $icono ?></span><?= htmlspecialchars(str_replace('_', ' ', $etiqueta), ENT_QUOTES, 'UTF-8') ?></span></a>
        <?php endforeach; ?>
        </div>
    </details>
    <?php endforeach; ?>
</nav>
<?php if (($_SESSION['rol'] ?? '') === 'administrador'): ?>
<div class="azu-alert-widget" id="azu-alert-widget">
  <a class="azu-alert-bell" href="/Azucreativo/Mani/inventario.php#azu-alertas-panel" aria-label="Ver alertas de inventario" title="Alertas de inventario">🔔<span id="azu-bell-count" class="azu-alert-count" hidden></span></a>
  <span class="azu-alert-tooltip" id="azu-alert-tooltip" role="status">Consultando inventario…</span>
</div>
<script src="/Azucreativo/config/campana-inventario.js" defer></script>
<?php endif; ?>
