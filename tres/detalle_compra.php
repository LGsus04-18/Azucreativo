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
    <title>Detalle_Compra - Dulcería Azucreativo</title>
    <link rel="stylesheet" href="detalle_compra.css">
    <link rel="stylesheet" href="/Azucreativo/config/menu-horizontal.css"><script src="/Azucreativo/config/menu-plegable.js" defer></script>
<link rel="stylesheet" href="/Azucreativo/config/scroll-final.css?v=3"><link rel="stylesheet" href="/Azucreativo/config/avisos-legibles.css?v=20261122">

<link rel="stylesheet" href="/Azucreativo/config/mobile-responsive.css?v=20261122logout2">
<script src="/Azucreativo/config/mobile-responsive.js?v=20261122" defer></script>
</head>
<body>
    <div id="toast-container" class="toast-container"></div>

    <div class="app-container">


    <aside class="sidebar">
    <?php $activo = 'detalle_compra'; include __DIR__ . '/../config/menu.php'; ?>
</aside>           

            <div class="view-container">
                <section id="view-entity" class="view-section">
                    <div class="card form-card">
                        <form id="entity-form" onsubmit="event.preventDefault(); saveEntityRecord();">
                            <div id="entity-form-fields" class="form-grid"></div>
                            <div class="form-actions">
                                <button type="button" onclick="searchEntityRecord()" class="btn btn-blue">Buscar</button>
                                <button type="button" onclick="clearEntityForm()" class="btn btn-secondary">Limpiar</button>
                                <button type="submit" class="btn btn-save">Guardar</button>
                            </div>
                        </form>
                    </div>

                    <div class="card table-card">
                        <div class="table-header">
                            <h3>Registros de la Tabla: <span id="table-name-display" class="highlight-text">Detalle_Compra</span></h3>
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

    <script src="detalle_compra.js"></script>
</body>
</html>
