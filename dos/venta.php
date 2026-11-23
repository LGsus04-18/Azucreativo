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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Venta - Dulcería Azucreativo</title>
    <link rel="stylesheet" href="venta.css">
    <link rel="stylesheet" href="/Azucreativo/config/menu-horizontal.css"><script src="/Azucreativo/config/menu-plegable.js" defer></script>
<link rel="stylesheet" href="/Azucreativo/config/scroll-final.css?v=3"><link rel="stylesheet" href="/Azucreativo/config/avisos-legibles.css?v=20261122">

<link rel="stylesheet" href="/Azucreativo/config/mobile-responsive.css?v=20261122logout2">
<script src="/Azucreativo/config/mobile-responsive.js?v=20261122" defer></script>
</head>
<body>
    <div id="toast-container" class="toast-container"></div>

    <div class="app-container">


    <aside class="sidebar">
    <?php $activo = 'venta'; include __DIR__ . '/../config/menu.php'; ?>
</aside>

        <main class="main-content">
            <header class="header-bar">
                <div class="header-left">
                    <span class="header-icon">📊</span>
                    <h1 id="current-view-title" class="view-title">Módulo: Venta</h1>
                </div>
                <div class="header-user">
                    <span>Hola, <?= htmlspecialchars($_SESSION['usuario']) ?></span>
                    <a href="/Azucreativo/login/logout.php" class="btn-logout">Cerrar sesión</a>
                </div>
            </header>

            <div class="view-container">
                <section id="view-entity" class="view-section">
                    <div class="card form-card">
                        <h3 style="margin-top:0;">Cobrar venta — Administrador</h3>
                        <a href="#field-ID_Cliente" class="btn btn-blue" style="display:inline-block;margin-bottom:12px">🛒 Cobrar cliente no registrado</a>
                        <p style="margin:0 0 14px;color:#222;">Para cobrar a alguien sin cuenta, selecciona <strong>Cliente no registrado (venta de mostrador)</strong>. No es necesario crearle un perfil.</p>
                        <form id="entity-form" onsubmit="event.preventDefault(); saveEntityRecord();">
                            <div id="entity-form-fields" class="form-grid">
                                <div class="form-group"><label for="field-ID_Cliente">Tipo de cliente *</label>
                                  <select class="form-input" id="field-ID_Cliente" required>
                                    <option value="0" selected>Cliente no registrado (venta de mostrador)</option>
                                  </select>
                                  <small style="display:block;color:#222;margin-top:6px">Seleccionado por defecto: puedes cobrar sin registrar al cliente.</small>
                                </div>
                                <div class="form-group"><label for="field-Metodo_Pago">Método de pago *</label><select class="form-input" id="field-Metodo_Pago"><option>Efectivo</option><option>Tarjeta</option><option>Transferencia</option></select></div>
                                <div class="form-group"><label for="field-Estado_Venta">Estado *</label><select class="form-input" id="field-Estado_Venta"><option>Pagada</option><option>Pendiente</option><option>Cancelada</option></select></div>
                            </div>
                            <div class="form-group" style="margin-top:16px;">
                                <label for="product-picker">Producto</label>
                                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                                    <select id="product-picker" class="form-input" style="flex:1;min-width:220px;"></select>
                                    <input id="product-quantity" type="number" min="1" value="1" class="form-input" aria-label="Cantidad" style="max-width:120px;">
                                    <button type="button" onclick="addProductToCart()" class="btn btn-blue">Agregar producto</button>
                                </div>
                            </div>
                            <div class="table-wrapper" style="margin-top:12px;">
                                <table class="data-table">
                                    <thead><tr><th>PRODUCTO</th><th>PRECIO</th><th>CANTIDAD</th><th>SUBTOTAL</th><th>ACCIÓN</th></tr></thead>
                                    <tbody id="sale-cart-body"><tr><td colspan="5">Agrega productos para comenzar.</td></tr></tbody>
                                </table>
                            </div>
                            <p style="text-align:right;font-weight:700;">Total: $<span id="sale-total">0.00</span></p>
                            <div class="form-actions">
                                <button type="button" onclick="loadSales()" class="btn btn-blue">Actualizar ventas</button>
                                <button type="button" onclick="clearEntityForm()" class="btn btn-secondary">Limpiar</button>
                                <button type="submit" class="btn btn-save">Cobrar y generar ticket</button>
                            </div>
                        </form>
                    </div>

                    <div class="card form-card" style="margin-top:18px;">
                        <h3 style="margin-top:0;">Consultar ticket</h3>
                        <div style="display:flex;gap:10px;flex-wrap:wrap;">
                            <input id="ticket-search" class="form-input" type="number" min="1" placeholder="Número de ticket / ID de venta" style="flex:1;min-width:220px;">
                            <button type="button" onclick="searchTicket()" class="btn btn-blue">Consultar</button>
                        </div>
                        <div id="ticket-result" style="margin-top:14px;"></div>
                    </div>
                    <div class="card table-card">
                        <div class="table-header">
                            <h3>Registros de la Tabla: <span id="table-name-display" class="highlight-text">Venta</span></h3>
                            <span id="record-count" class="subtitle">0 registros</span>
                        </div>
                        <div class="table-wrapper">
                            <table class="data-table">
                                <thead id="entity-table-head"></thead>
                                <tbody id="entity-table-body"></tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <script src="venta.js?v=20261122-admin-mostrador-fix2"></script>
</body>
</html>
