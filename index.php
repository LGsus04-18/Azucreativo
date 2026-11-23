<?php
session_start();
if (isset($_SESSION['usuario_id']) && ($_SESSION['rol'] ?? '') !== 'administrador') {
    if (str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/')) { http_response_code(403); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>false,'mensaje'=>'Acceso exclusivo para administradores.']); }
    else { header('Location: /Azucreativo/cliente/catalogo.php'); }
    exit;
}

if (!isset($_SESSION['usuario_id'])) {
    header("Location: /Azucreativo/login/login.php");
    exit;
}
// Carga inicial del catálogo desde MySQL, también disponible si JavaScript falla.
$catalogoInicial = [];
$catalogoError = '';
try {
    require __DIR__ . '/config/conexion_azucreativo.php';
    $catalogoInicial = $pdo->query('SELECT ID_Producto, Nombre, Descripcion, Precio, Stock FROM Producto ORDER BY Nombre')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Catálogo inicial Azucreativo: ' . $e->getMessage());
    $catalogoError = 'No se pudieron consultar los productos. Revisa MySQL y la tabla Producto.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestión - Dulcería Azucreativo</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="/Azucreativo/config/menu-horizontal.css"><script src="/Azucreativo/config/menu-plegable.js" defer></script>
<link rel="stylesheet" href="/Azucreativo/config/tarjeta_demo.css"><script src="/Azucreativo/config/tarjeta_demo.js?v=20261122-admin-tarjeta-fix3" defer></script><link rel="stylesheet" href="/Azucreativo/config/scroll-final.css?v=3"><link rel="stylesheet" href="/Azucreativo/config/avisos-legibles.css?v=20261122">

<link rel="stylesheet" href="/Azucreativo/config/mobile-responsive.css?v=20261122logout2">
<script src="/Azucreativo/config/mobile-responsive.js?v=20261122" defer></script>
</head>
<body><div style="padding:8px 20px;background:#dcecff"><a href="/Azucreativo/admin/usuarios.php">🔐 Administrar cuentas y claves de autorización</a></div>
    <div id="toast-container" class="toast-container"></div>

    <div class="app-container">
        <aside class="sidebar">
            <?php $activo = 'home'; include __DIR__ . '/config/menu.php'; ?>
        </aside>

        <main class="main-content">
            <header class="header-bar">
                <div class="header-left">
                    <span class="header-icon">🏪</span>
                    <h1 id="current-view-title" class="view-title">Inicio</h1>
                </div>
                <div class="header-user">
                    <span>Hola, <?= htmlspecialchars($_SESSION['usuario']) ?></span>
                    <a href="/Azucreativo/login/logout.php" class="btn-logout">Cerrar sesión</a>
                </div>
            </header>

            <div class="view-container">
                <!-- VISTA POS -->
                <section id="view-pos" class="view-section active">

                    <!-- Banner Dulcería -->
                    <div class="brand-banner">
                        <div class="brand-logo-container">
                            <img src="img/logo-azucreativo.jpeg" alt="Logo Azucreativo" class="brand-logo" onerror="this.src='https://via.placeholder.com/60?text=Dulces'">
                        </div>
                        <h2><?php echo "Dulcería Azucreativo"; ?></h2>
                    </div>

                    <!-- POS Grid -->
                    <div class="pos-grid">

                        <!-- Catálogo -->
                        <div class="card catalog-card">

                            <div class="pos-catalog-header">
                                <div class="header-titles">
                                    <h3>Catálogo de Productos</h3>
                                    <p class="subtitle">Administra o agrega dulces al carrito actual</p>
                                </div>

                                <!-- Barra centrada con búsqueda y botones -->
                                <div class="search-bar-container">
                                    <div class="search-input-wrapper">
                                        <span class="search-icon">🔍</span>
                                        <input type="text" id="pos-search" placeholder="Buscar dulce..." onkeyup="renderPosCatalog()">
                                    </div>
                                    <button onclick="renderPosCatalog()" class="btn-search">Buscar</button>
                                    <button onclick="clearPosSearch()" class="btn-clear">✕ Limpiar</button>
                                </div>
                            </div>

                            <div id="pos-products-grid" class="products-grid">
                                <?php if ($catalogoError): ?>
                                    <p role="alert"><?= htmlspecialchars($catalogoError, ENT_QUOTES, 'UTF-8') ?></p>
                                <?php elseif (!$catalogoInicial): ?>
                                    <p>Aún no hay productos registrados. Agrégalos en el apartado Productos.</p>
                                <?php else: foreach ($catalogoInicial as $producto): ?>
                                    <div class="product-card">
                                        <div>
                                            <div class="product-card-top"><span class="product-emoji">🍬</span><span class="stock-badge">Stock: <?= (int)$producto['Stock'] ?></span></div>
                                            <h4 class="product-title"><?= htmlspecialchars($producto['Nombre'], ENT_QUOTES, 'UTF-8') ?></h4>
                                            <div class="product-category"><?= htmlspecialchars($producto['Descripcion'] ?? 'General', ENT_QUOTES, 'UTF-8') ?></div>
                                        </div>
                                        <div class="product-card-bottom">
                                            <span class="product-price">$<?= number_format((float)$producto['Precio'], 2) ?></span>
                                            <button type="button" class="btn btn-pink btn-sm" onclick="addToCart(<?= (int)$producto['ID_Producto'] ?>)" <?= (int)$producto['Stock'] <= 0 ? 'disabled' : '' ?>><?= (int)$producto['Stock'] <= 0 ? 'Agotado' : '+ Agregar' ?></button>
                                        </div>
                                    </div>
                                <?php endforeach; endif; ?>
                            </div>
                        </div>

                        <!-- Carrito -->
                        <div class="card cart-card">
                            <div class="cart-header">
                                <div class="cart-title-wrapper">
                                    <span class="cart-icon">🛒</span>
                                    <h3>Carrito Actual</h3>
                                </div>
                                <div class="cart-header-actions">
                                    <button onclick="emptyCart()" class="btn btn-danger-outline btn-sm">🗑️ Vaciar</button>
                                    <span id="cart-item-count" class="badge badge-pink">0 Items</span>
                                </div>
                            </div>

                            <div id="cart-items-list" class="cart-items-list">
                                <!-- Lista de compras dinámicas -->
                            </div>

                            <div class="cart-summary">
                                <div class="summary-row">
                                    <span>Subtotal:</span>
                                    <span id="cart-subtotal">$0.00</span>
                                </div>
                                <div class="summary-row">
                                    <span>Impuesto adicional:</span>
                                    <span id="cart-tax">$0.00</span>
                                </div>
                                <div class="summary-row total-row">
                                    <span>Total a Pagar:</span>
                                    <span id="cart-total" class="total-amount">$0.00</span>
                                </div>

                                <div class="form-group" style="margin-bottom: 12px;">
                                    <label for="cart-cliente">Cliente de la venta:</label>
                                    <select id="cart-cliente" class="form-input"><option value="0" selected>Cliente no registrado (venta de mostrador)</option></select>
                                    <small style="color:#222">Puedes cobrar sin registrar al cliente. Si tiene cuenta, selecciónalo en la lista.</small>
                                </div>
                                <div class="form-group" style="margin-bottom:12px;">
                                    <label for="cart-payment">Método de pago:</label>
                                    <select id="cart-payment" class="form-input" onchange="actualizarMetodoPago()">
                                        <option value="Efectivo">Efectivo</option>
                                        <option value="Tarjeta">Tarjeta</option>
                                        <option value="Transferencia">Transferencia</option>
                                    </select>
                                </div>
                                <div class="payment-inputs" id="cart-cash-fields">
                                    <div class="form-group">
                                        <label>Efectivo Recibido ($):</label>
                                        <input type="number" id="cart-cash" oninput="calculateChange()" placeholder="0.00" min="0" step="0.50" class="form-input">
                                    </div>
                                    <div class="form-group">
                                        <label>Cambio:</label>
                                        <div id="cart-change" class="change-box">Falta $0.00</div>
                                    </div>
                                </div>

                                <div class="demo-card-fields" id="demo-card-fields" hidden><strong>💳 Pago con tarjeta</strong><p>Simulación de pago. No ingreses tarjetas bancarias reales.</p><label for="demo-card-number">Número de tarjeta</label><input id="demo-card-number" inputmode="numeric" autocomplete="off" placeholder="•••• •••• •••• 4242" maxlength="23"><label for="demo-card-name">Nombre del titular</label><input id="demo-card-name" autocomplete="off" maxlength="80" placeholder="PERSONA DE PRUEBA"><div class="demo-card-row"><div><label for="demo-card-exp">Vencimiento</label><input id="demo-card-exp" autocomplete="off" placeholder="12/30" maxlength="5"></div><div><label for="demo-card-cvv">CVV</label><input id="demo-card-cvv" type="password" inputmode="numeric" autocomplete="off" placeholder="123" maxlength="4"></div></div><small>Ingresa 16 dígitos de ejemplo. Solo se conservarán los últimos 4 para el ticket.</small></div><div class="checkout-actions">
                                    <button onclick="quickAddProductModal()" class="btn btn-secondary flex-1">+ Agregar Prod.</button>
                                    <button id="cart-checkout" onclick="processCheckout()" class="btn btn-gradient flex-2">💳 Cobrar y generar ticket</button>
                                </div>
                            </div>

                        </div>
                    </div>

                </section>
            </div>
        </main>
    </div>

    <script>window.CATALOGO_INICIAL = <?= json_encode($catalogoInicial, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?>;</script>
    <script src="script.js?v=20261122-admin-carrito-mostrador-fix4"></script>
</body>
</html>