<?php
session_start();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: login.php'); exit; }
require 'conexion.php';
require_once __DIR__.'/esquema.php';
$login=trim($_POST['login']??''); $password=$_POST['password']??'';
if ($login==='' || $password==='') { $_SESSION['error']='Completa todos los campos.'; header('Location: login.php'); exit; }
try { prepararRoles($pdo);
$stmt=$pdo->prepare('SELECT id, usuario, password, rol, activo FROM usuarios WHERE usuario=? OR correo=? LIMIT 1');
$stmt->execute([$login,$login]); $u=$stmt->fetch();
} catch (Throwable $e) { error_log('Azucreativo login: '.$e->getMessage()); $_SESSION['error']='No se pudo consultar la base de datos de usuarios. Revisa login_db y la configuración de conexión.'; header('Location: login.php'); exit; }
if ($u && (int)$u['activo']===1 && password_verify($password,$u['password'])) {
 session_regenerate_id(true); $_SESSION['usuario_id']=(int)$u['id']; $_SESSION['usuario']=$u['usuario']; $_SESSION['rol']=$u['rol'];
 header('Location: '.($u['rol']==='administrador'?'/Azucreativo/index.php':($u['rol']==='empleado'?'/Azucreativo/empleado/index.php':'/Azucreativo/cliente/catalogo.php'))); exit;
}
$_SESSION['error']='Usuario o contraseña incorrectos.'; header('Location: login.php'); exit;
