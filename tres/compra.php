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
    <title>Compra - Dulcería Azucreativo</title>
    <link rel="stylesheet" href="compra.css">
    <link rel="stylesheet" href="/Azucreativo/config/menu-horizontal.css"><script src="/Azucreativo/config/menu-plegable.js" defer></script>
<link rel="stylesheet" href="/Azucreativo/config/scroll-final.css?v=3"><link rel="stylesheet" href="/Azucreativo/config/avisos-legibles.css?v=20261122">

<link rel="stylesheet" href="/Azucreativo/config/mobile-responsive.css?v=20261122logout2">
<script src="/Azucreativo/config/mobile-responsive.js?v=20261122" defer></script>
</head>
<body>
    <div id="toast-container" class="toast-container"></div>

    <div class="app-container">


    <aside class="sidebar">
    <?php $activo = 'compra'; include __DIR__ . '/../config/menu.php'; ?>
</aside>
            
        <main class="main-content">
            <header class="header-bar">
                <div class="header-left">
                    <span class="header-icon">🛒</span>
                    <h1 id="current-view-title" class="view-title">Módulo: Compra</h1>
                </div>
                <div class="header-user">
                    <span>Hola, <?= htmlspecialchars($_SESSION['usuario']) ?></span>
                    <a href="/Azucreativo/login/logout.php" class="btn-logout">Cerrar sesión</a>
                </div>
            </header>

            <div class="view-container">
                <section id="view-entity" class="view-section">
                    <div class="card form-card">
                        <form id="entity-form">
                            <div class="form-grid">
                                <div class="form-group"><label for="ID_Proveedor">Proveedor</label><select class="form-input" id="ID_Proveedor" required></select></div>
                                <div class="form-group"><label for="ID_Producto">Producto adquirido</label><select class="form-input" id="ID_Producto" required></select></div>
                                <div class="form-group"><label for="Cantidad">Cantidad adquirida</label><input class="form-input" type="number" min="1" step="1" id="Cantidad" value="1" required></div>
                                <div class="form-group"><label for="Precio_Unitario">Precio unitario</label><input class="form-input" type="number" min="0" step="0.01" id="Precio_Unitario" required></div>
                                <div class="form-group"><label for="Fecha_Compra">Fecha de compra</label><input class="form-input" type="date" id="Fecha_Compra" required></div>
                                <div class="form-group"><label for="Metodo_Pago">Método de pago</label><select class="form-input" id="Metodo_Pago"><option>Efectivo</option><option>Crédito</option><option>Transferencia</option></select></div>
                                <div class="form-group"><label for="Total_Compra">Total</label><input class="form-input" id="Total_Compra" readonly value="0.00"></div>
                            </div>
                            <label>Clave de autorización administrativa <input class="form-input" id="clave_autorizacion" type="password" inputmode="numeric" pattern="[0-9]{4}" minlength="4" maxlength="4" required autocomplete="off"></label><p>Al guardar, se registrará la compra, su detalle y una entrada de inventario por la cantidad adquirida.</p>
                            <div class="form-actions"><button type="button" id="limpiar_compra" class="btn btn-secondary">Limpiar</button><button type="submit" id="guardar_compra" class="btn btn-save">Guardar compra y actualizar inventario</button></div>
                        </form>
                    </div>
                        </form>
                    </div>

                    <div class="card table-card">
                        <div class="table-header">
                            <h3>Registros de la Tabla: <span id="table-name-display" class="highlight-text">Compra</span></h3>
                            <label for="filtro_proveedor">Filtrar por proveedor (ID): <input id="filtro_proveedor" type="number" min="1" class="form-input" placeholder="Todos"></label><span id="record-count" class="subtitle">0 registros</span>
                        </div>
                        <div class="table-wrapper">
                            <table class="data-table">
                                <thead id="entity-table-head"><tr><th>ID Compra</th><th>ID Proveedor</th><th>Fecha</th><th>Método</th><th>Total</th></tr></thead>
                                <tbody id="entity-table-body"></tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <script src="compra.js"></script>
</body>
</html>
