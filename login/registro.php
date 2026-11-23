<?php
session_start();
if (isset($_SESSION['usuario_id'])) { header('Location: login.php'); exit; }
$error='';
$usuario=trim((string)($_POST['usuario']??''));
$correo=trim((string)($_POST['correo']??''));
$telefono=trim((string)($_POST['telefono']??''));
$direccion=trim((string)($_POST['direccion']??''));
if ($_SERVER['REQUEST_METHOD']==='POST') {
 $clave=(string)($_POST['password']??'');
 if (mb_strlen($usuario)<3 || mb_strlen($usuario)>50 || !filter_var($correo,FILTER_VALIDATE_EMAIL)) {
  $error='Ingresa un usuario de 3 a 50 caracteres y un correo válido.';
 } elseif (!preg_match('/^[0-9]{10}$/D',$telefono)) {
  $error='El número telefónico debe tener 10 dígitos.';
 } elseif (mb_strlen($direccion)<5 || mb_strlen($direccion)>255) {
  $error='Ingresa una dirección de entre 5 y 255 caracteres.';
 } elseif (strlen($clave)!==12) {
  $error='La contraseña debe contener exactamente 12 caracteres.';
 } else {
  require __DIR__.'/conexion.php';
  require_once __DIR__.'/esquema.php';
  try {
   prepararRoles($pdo);
   // La misma conexión permite una transacción para cuenta, cliente y vinculación.
   $pdo->beginTransaction();
   $q=$pdo->prepare("INSERT INTO login_db.usuarios (usuario,correo,password,rol) VALUES (?,?,?,'cliente')");
   $q->execute([$usuario,$correo,password_hash($clave,PASSWORD_DEFAULT)]);
   $idUsuario=(int)$pdo->lastInsertId();
   $q=$pdo->prepare('INSERT INTO azucreativo_db.Cliente (Nombre,Apellido,Correo,Telefono,Direccion) VALUES (?,?,?,?,?)');
   $q->execute([$usuario,'Web',$correo,$telefono,$direccion]);
   $idCliente=(int)$pdo->lastInsertId();
   $q=$pdo->prepare('INSERT INTO azucreativo_db.Cliente_Usuario_Web (ID_Usuario_Login,ID_Cliente) VALUES (?,?)');
   $q->execute([$idUsuario,$idCliente]);
   $pdo->commit();
   session_regenerate_id(true);
   $_SESSION['usuario_id']=$idUsuario;
   $_SESSION['usuario']=$usuario;
   $_SESSION['rol']='cliente';
   header('Location: /Azucreativo/cliente/catalogo.php');exit;
  } catch (Throwable $e) {
   if($pdo->inTransaction())$pdo->rollBack();
   error_log('Registro de cliente Azucreativo: '.$e->getMessage());
   $error=($e instanceof PDOException && (string)$e->getCode()==='23000')
    ? 'El usuario o correo ya está registrado. Utiliza uno diferente.'
    : 'No se pudo crear la cuenta. Verifica las tablas Cliente y Cliente_Usuario_Web y la conexión a las bases de datos.';
  }
 }
}
function valor(string $v):string{return htmlspecialchars($v,ENT_QUOTES,'UTF-8');}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Crear cuenta · Azucreativo</title><link rel="stylesheet" href="login.css"><link rel="stylesheet" href="/Azucreativo/config/avisos-legibles.css?v=20261122">
<link rel="stylesheet" href="/Azucreativo/config/mobile-responsive.css?v=20261122logout2">
<script src="/Azucreativo/config/mobile-responsive.js?v=20261122" defer></script>
</head>
<body><main class="shell"><section class="brand"><img class="logo" src="../img/logo-azucreativo.jpeg" alt="Logo Azucreativo"><div class="brand-copy"><span class="eyebrow">AZUCREATIVO</span><h1>Un mundo de dulces</h1><p>Regístrate para explorar nuestro catálogo.</p></div></section><section class="login-card"><div class="pill">Cuenta de cliente</div><h2>Crear cuenta</h2><p class="muted">Completa tus datos para registrarte como cliente.</p>
<?php if($error):?><div class="alert" role="alert"><?=valor($error)?></div><?php endif;?>
<form method="post"><label for="usuario">Usuario *</label><input id="usuario" name="usuario" maxlength="50" minlength="3" value="<?=valor($usuario)?>" required autocomplete="username">
<label for="correo">Correo electrónico *</label><input id="correo" type="email" name="correo" value="<?=valor($correo)?>" required autocomplete="email">
<label for="telefono">Número telefónico (10 dígitos) *</label><input id="telefono" type="tel" name="telefono" inputmode="numeric" pattern="[0-9]{10}" maxlength="10" value="<?=valor($telefono)?>" required autocomplete="tel" placeholder="5512345678">
<label for="direccion">Dirección *</label><input id="direccion" name="direccion" minlength="5" maxlength="255" value="<?=valor($direccion)?>" required autocomplete="street-address" placeholder="Calle, número, colonia, ciudad">
<label for="password">Contraseña (exactamente 12 caracteres) *</label><input id="password" type="password" name="password" minlength="12" maxlength="12" required autocomplete="new-password"><small>Debe contener exactamente 12 caracteres.</small>
<button type="submit">Crear cuenta de cliente</button></form><p class="register"><a href="login.php">Volver a iniciar sesión</a></p></section></main></body></html>
