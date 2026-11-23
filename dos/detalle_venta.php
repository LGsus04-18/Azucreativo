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
    <title>Detalle_Venta - Dulcería Azucreativo</title>
    <link rel="stylesheet" href="detalle_venta.css">
    <link rel="stylesheet" href="/Azucreativo/config/menu-horizontal.css"><script src="/Azucreativo/config/menu-plegable.js" defer></script>
<link rel="stylesheet" href="/Azucreativo/config/scroll-final.css?v=3"><link rel="stylesheet" href="/Azucreativo/config/avisos-legibles.css?v=20261122">

<link rel="stylesheet" href="/Azucreativo/config/mobile-responsive.css?v=20261122logout2">
<script src="/Azucreativo/config/mobile-responsive.js?v=20261122" defer></script>
</head>
<body>
    <div id="toast-container" class="toast-container"></div>

    <div class="app-container">
        
    <aside class="sidebar">
    <?php $activo = 'detalle_venta'; include __DIR__ . '/../config/menu.php'; ?>
</aside>


        <main class="main-content">
            <header class="header-bar">
                <div class="header-left">
                    <span class="header-icon">📋</span>
                    <h1 id="current-view-title" class="view-title">Módulo: Detalle_Venta</h1>
                </div>
                <div class="header-user">
                    <span>Hola, <?= htmlspecialchars($_SESSION['usuario']) ?></span>
                    <a href="/Azucreativo/login/logout.php" class="btn-logout">Cerrar sesión</a>
                </div>
            </header>

            <div class="view-container">
                <section id="view-entity" class="view-section">
                    <div class="card form-card">
                        <h3 style="margin-top:0;">Consultar detalle de una venta</h3>
                        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;">
                            <input id="ticket-search" class="form-input" type="number" min="1" placeholder="Número de ticket / ID de venta" style="flex:1;min-width:220px;">
                            <button type="button" onclick="searchTicket()" class="btn btn-blue">Consultar ticket</button>
                            <button type="button" onclick="loadDetails()" class="btn btn-secondary">Mostrar todos</button>
                        </div>
                        <div id="ticket-result" style="margin-bottom:16px;"></div>

                    </div>

                    <div class="card table-card">
                        <div class="table-header">
                            <h3>Registros de la Tabla: <span id="table-name-display" class="highlight-text">Detalle_Venta</span></h3>
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

    <script src="detalle_venta.js"></script>
</body>
</html>
