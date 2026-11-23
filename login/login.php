<?php
session_start();
if (isset($_SESSION['usuario_id'])) { header('Location: '.(($_SESSION['rol'] ?? 'administrador')==='cliente' ? '/Azucreativo/cliente/catalogo.php' : '/Azucreativo/index.php')); exit; }
$error = $_SESSION['error'] ?? ''; unset($_SESSION['error']);
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Acceso · Azucreativo</title><link rel="stylesheet" href="login.css"><link rel="stylesheet" href="/Azucreativo/config/avisos-legibles.css?v=20261122">

<link rel="stylesheet" href="/Azucreativo/config/mobile-responsive.css?v=20261122logout2">
<script src="/Azucreativo/config/mobile-responsive.js?v=20261122" defer></script>
</head><body>
<main class="shell"><section class="brand"><img class="logo" src="../img/logo-azucreativo.jpeg" alt="Logo Azucreativo"><div class="brand-copy"><span class="eyebrow">DULCERÍA AZUCREATIVO</span><h1>Tu rincón más dulce</h1><p>Descubre nuestros productos o administra tu negocio desde un solo lugar.</p></div></section>
<section class="login-card"><div class="pill">Bienvenido de nuevo</div><h2>Iniciar sesión</h2><p class="muted">Ingresa con tu cuenta de cliente o administrador.</p>
<?php if ($error): ?><div class="alert" role="alert"><?= htmlspecialchars($error,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
<form action="validar.php" method="post"><label for="login">Usuario o correo electrónico</label><input id="login" name="login" autocomplete="username" required placeholder="Tu usuario o correo"><label for="password">Contraseña</label><input id="password" type="password" name="password" autocomplete="current-password" required placeholder="Tu contraseña"><button type="submit">Entrar a Azucreativo</button></form>
<p class="register"><a href="../invitado/index.php" style="display:inline-block;padding:12px 18px;background:#2563eb;color:white;border-radius:10px;text-decoration:none;font-weight:bold">🛒 Comprar sin registrarse</a></p><p class="register">¿Eres cliente nuevo? <a href="registro.php">Crear cuenta</a></p><small>El acceso y los permisos se asignan según tu cuenta.</small></section></main></body></html>