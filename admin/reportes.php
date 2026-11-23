<?php
session_start();
if (!isset($_SESSION['usuario_id'])) { header('Location: /Azucreativo/login/login.php'); exit; }
if (($_SESSION['rol'] ?? '') !== 'administrador') { http_response_code(403); exit('Acceso exclusivo para administradores.'); }
require __DIR__ . '/../config/conexion_azucreativo.php';
function e($s){return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');}
// Folio de generación y nombre del administrador que realiza el reporte.
date_default_timezone_set('America/Mexico_City');
$generadoEn = new DateTimeImmutable('now');
$folioReporte = $generadoEn->format('Ymd_His');
$autorReporte = trim((string)($_SESSION['usuario'] ?? 'Administrador'));
$autorArchivo = preg_replace('/[^a-zA-Z0-9_-]+/', '_', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $autorReporte) ?: $autorReporte);
$autorArchivo = trim((string)$autorArchivo, '_-') ?: 'Administrador';
$autorArchivo = substr($autorArchivo, 0, 50);
$nombreReporte = 'Azucreativo_Reporte_'.$folioReporte.'_'.$autorArchivo;
$desde=$_GET['desde']??date('Y-m-01'); $hasta=$_GET['hasta']??date('Y-m-d');
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$desde)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$hasta)||!strtotime($desde)||!strtotime($hasta)||$desde>$hasta){$desde=date('Y-m-01');$hasta=date('Y-m-d');}
$agrupacion=$_GET['agrupacion']??'dia';
if(!in_array($agrupacion,['dia','semana','mes','anio'],true))$agrupacion='dia';
$periodosSQL=['dia'=>'DATE(v.Fecha_Venta)','semana'=>"DATE_FORMAT(v.Fecha_Venta,'%x-S%v')",'mes'=>"DATE_FORMAT(v.Fecha_Venta,'%Y-%m')",'anio'=>'YEAR(v.Fecha_Venta)'];
$periodoExp=$periodosSQL[$agrupacion];
$periodoTitulo=['dia'=>'día','semana'=>'semana','mes'=>'mes','anio'=>'año'][$agrupacion];
$ini=$desde.' 00:00:00';$fin=date('Y-m-d',strtotime($hasta.' +1 day')).' 00:00:00';
$base=" FROM Venta v WHERE v.Estado_Venta='Pagada' AND v.Fecha_Venta >= ? AND v.Fecha_Venta < ? ";
function getrows($pdo,$sql,$ini,$fin){$q=$pdo->prepare($sql);$q->execute([$ini,$fin]);return $q->fetchAll(PDO::FETCH_ASSOC);}
$error='';$resumen=['ventas'=>0,'ingresos'=>0,'ticket'=>0];$dias=[];$productos=[];$pagos=[];$vendedores=[];
try {
 $r=getrows($pdo,'SELECT COUNT(*) ventas, COALESCE(SUM(v.Total),0) ingresos,COALESCE(AVG(v.Total),0) ticket'.$base,$ini,$fin);$resumen=$r[0]??$resumen;
 $dias=getrows($pdo,'SELECT '.$periodoExp.' etiqueta,COUNT(*) ventas,ROUND(SUM(v.Total),2) total'.$base.' GROUP BY '.$periodoExp.' ORDER BY etiqueta',$ini,$fin);
 $productos=getrows($pdo,"SELECT p.Nombre etiqueta,SUM(d.Cantidad) unidades,ROUND(SUM(d.Subtotal),2) total FROM Detalle_Venta d JOIN Venta v ON v.ID_Venta=d.ID_Venta JOIN Producto p ON p.ID_Producto=d.ID_Producto WHERE v.Estado_Venta='Pagada' AND v.Fecha_Venta>=? AND v.Fecha_Venta<? GROUP BY p.ID_Producto,p.Nombre ORDER BY unidades DESC,total DESC",$ini,$fin);
 $pagos=getrows($pdo,'SELECT v.Metodo_Pago etiqueta,ROUND(SUM(v.Total),2) total'.$base.' GROUP BY v.Metodo_Pago ORDER BY total DESC',$ini,$fin);
 // Evita duplicar ventas: cada venta se clasifica con EXISTS/subconsulta, no con JOIN multiplicador.
 // Primero obtener ventas individuales: nunca multiplicar ventas con JOIN.
 $ventasVendedor=getrows($pdo,"SELECT v.ID_Venta,v.Total,(SELECT ve.ID_Usuario FROM Venta_Empleado ve WHERE ve.ID_Venta=v.ID_Venta LIMIT 1) usuario_vendedor,(SELECT ve.ID_Empleado FROM Venta_Empleado ve WHERE ve.ID_Venta=v.ID_Venta LIMIT 1) empleado_vendedor,(SELECT vc.ID_Venta FROM Venta_Cliente_Web vc WHERE vc.ID_Venta=v.ID_Venta LIMIT 1) venta_web".$base." ORDER BY v.ID_Venta",$ini,$fin);
 $nombres=[];
 try {
   // Conexión de lectura al catálogo de cuentas; no se supone que ID_Empleado sea el ID de usuario.
   $loginPdo=new PDO('mysql:host=localhost;port=3306;dbname=login_db;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
   foreach($loginPdo->query('SELECT id,usuario FROM usuarios') as $u){$nombres[(int)$u['id']]=$u['usuario'];}
 }catch(Throwable $loginError){error_log('Reportes: nombres de cuentas no disponibles: '.$loginError->getMessage());}
 $mapaVendedores=[];
 foreach($ventasVendedor as $v){
   $uid=$v['usuario_vendedor']!==null?(int)$v['usuario_vendedor']:0;
   if($uid>0){$nombre=isset($nombres[$uid])?('Empleado: '.$nombres[$uid]):('Empleado (usuario #'.$uid.')');$clave='u'.$uid;}
   elseif($v['empleado_vendedor']!==null){$eid=(int)$v['empleado_vendedor'];$nombre='Empleado #'.$eid;$clave='e'.$eid;}
   elseif($v['venta_web']!==null){$nombre='Cliente en línea';$clave='web';}
   else{$nombre='Sin vendedor identificado';$clave='sin';}
   if(!isset($mapaVendedores[$clave]))$mapaVendedores[$clave]=['etiqueta'=>$nombre,'ventas'=>0,'total'=>0];
   $mapaVendedores[$clave]['ventas']++;
   $mapaVendedores[$clave]['total']+=(float)$v['Total'];
 }
 $vendedores=array_values($mapaVendedores);
 usort($vendedores,static fn($a,$b)=>$b['total']<=>$a['total']);

} catch(Throwable $ex){error_log('Reportes Azucreativo: '.$ex->getMessage());$error='No fue posible consultar todos los reportes. Revisa las tablas Venta, Detalle_Venta, Producto, Venta_Empleado y Venta_Cliente_Web.';}
$estrella=$productos[0]??null;
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($nombreReporte)?></title><link rel="stylesheet" href="../styles.css"><link rel="stylesheet" href="../config/menu-horizontal.css"><script src="../config/menu-plegable.js" defer></script><link rel="stylesheet" href="../config/scroll-final.css?v=3"><style>
.report-content{padding:22px;max-width:1300px;width:100%;box-sizing:border-box}.report-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin:18px 0}.report-card,.report-panel{background:rgba(255,255,255,.96);border:1px solid #cce3f5;border-radius:15px;padding:17px;box-shadow:0 3px 14px #153c6112}.report-card b{display:block;font-size:1.55rem;color:#294c6a;margin-top:6px}.report-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,390px),1fr));gap:15px}.report-panel h2{font-size:1.15rem;color:#294c6a;margin:0 0 12px}.chart-row{display:grid;grid-template-columns:minmax(105px,1fr) minmax(110px,2fr) 90px;gap:9px;align-items:center;margin:10px 0;font-size:.9rem}.chart-label{overflow-wrap:anywhere}.chart-track{height:19px;background:#eaf3fc;border-radius:8px;overflow:hidden}.chart-bar{height:100%;background:#78add8;border-radius:8px;min-width:2px}.chart-value{text-align:right;font-variant-numeric:tabular-nums}.report-table{width:100%;border-collapse:collapse}.report-table td,.report-table th{border-bottom:1px solid #e3edf7;padding:8px;text-align:left}.report-filters{display:flex;flex-wrap:wrap;align-items:end;gap:12px}.report-filters label{display:grid;gap:5px}.report-filters input{padding:9px;border:1px solid #accce5;border-radius:8px}.report-filters button,.report-print{padding:10px 15px;background:#294c6a;color:white;border:0;border-radius:8px;cursor:pointer}.report-empty{color:#6c7b89}.report-error{background:#fff1ef;padding:15px;border-radius:9px;color:#8a2d23}@media print{.sidebar,.header-bar,.report-filters,.report-print{display:none!important}html,body,.app-container,.main-content{overflow:visible!important;height:auto!important;max-height:none!important}.report-content{padding:0}.report-panel,.report-card{break-inside:avoid}}

/* Impresión multipágina: incluir todas las tablas y gráficas, incluso las que no caben en pantalla */
.print-cover{display:none}
@page{size:auto;margin:13mm 12mm}
@media print{
 .print-cover{display:block!important;text-align:center;margin:0 0 15px;padding-bottom:10px;border-bottom:2px solid #294c6a;color:#294c6a}
 .print-cover h1{font-size:22pt;margin:0}.print-cover h2{font-size:15pt;margin:6px 0}.print-cover p{font-size:10pt;margin:4px}
 html,body{width:100%!important;height:auto!important;min-height:0!important;max-height:none!important;overflow:visible!important;position:static!important;display:block!important;background:white!important}
 body *{scroll-behavior:auto!important}
 .app-container,.main-content,.report-content,.report-grid,.report-cards,.report-panel,.report-card,.view-container,.view-section{position:static!important;display:block!important;height:auto!important;min-height:0!important;max-height:none!important;overflow:visible!important;transform:none!important;clip:auto!important;contain:none!important}
 .app-container,.main-content{width:100%!important;max-width:none!important;margin:0!important;padding:0!important}
 .report-content{width:100%!important;max-width:none!important;margin:0!important;padding:0!important}
 .report-grid{display:block!important}
 .report-cards{display:grid!important;grid-template-columns:repeat(4,minmax(0,1fr))!important;gap:6px!important}
 .report-card{display:block!important;padding:8px!important;font-size:9pt!important}
 .report-card b{font-size:13pt!important}
 .report-panel{display:block!important;margin:12px 0!important;padding:10px!important;break-inside:auto!important;page-break-inside:auto!important;box-shadow:none!important;border:1px solid #ccd7e1!important}
 .report-panel h2{break-after:avoid!important;page-break-after:avoid!important}
 .chart-row{break-inside:avoid!important;page-break-inside:avoid!important}
 .report-table{width:100%!important;font-size:9pt!important}
 .report-table thead{display:table-header-group!important}
 .report-table tr{break-inside:avoid!important;page-break-inside:avoid!important}
 .report-panel>div[style*="overflow-x"]{overflow:visible!important;width:100%!important}
 .sidebar,.header-bar,.report-filters,.report-print{display:none!important}
 .chart-track,.chart-bar{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important}
 a{color:inherit!important}
}
</style><link rel="stylesheet" href="/Azucreativo/config/avisos-legibles.css?v=20261122">

<link rel="stylesheet" href="/Azucreativo/config/mobile-responsive.css?v=20261122logout2">
<script src="/Azucreativo/config/mobile-responsive.js?v=20261122" defer></script>

<!-- Correccion de reportes: paneles y tablas sin recortes en pantallas estrechas. -->
<style id="azu-reportes-responsive-fix">
.report-content,.report-grid,.report-panel,.report-cards,.report-card{min-width:0;max-width:100%;box-sizing:border-box}
.report-panel{overflow:visible;overflow-wrap:anywhere}
.report-panel>div[style*="overflow-x"]{max-width:100%;overflow-x:auto!important;-webkit-overflow-scrolling:touch;overscroll-behavior-x:contain}
.report-table{width:100%;min-width:440px;table-layout:auto}
.report-table th,.report-table td{white-space:normal;overflow-wrap:anywhere;vertical-align:top}
@media(max-width:767px){
 html,body{overflow-x:hidden!important}
 body .app-container>.main-content{min-width:0!important;overflow-x:hidden!important}
 .report-content{width:100%!important;max-width:100%!important;padding:12px 4px 36px!important;margin:0!important}
 .report-grid{display:grid!important;grid-template-columns:minmax(0,1fr)!important;gap:12px!important}
 .report-cards{display:grid!important;grid-template-columns:repeat(auto-fit,minmax(min(100%,145px),1fr))!important;gap:10px!important}
 .report-card,.report-panel{min-width:0!important;width:100%!important;max-width:100%!important;padding:12px!important}
 .report-card b{overflow-wrap:anywhere;font-size:clamp(1.1rem,5vw,1.55rem)}
 .report-filters{display:grid!important;grid-template-columns:minmax(0,1fr)!important;align-items:stretch!important}
 .report-filters label,.report-filters input,.report-filters select,.report-filters button{width:100%!important;min-width:0!important}
 .chart-row{display:grid!important;grid-template-columns:minmax(0,1fr) minmax(0,1.2fr)!important;gap:5px 8px!important;align-items:center!important}
 .chart-label{min-width:0!important;overflow-wrap:anywhere!important;word-break:normal!important}
 .chart-track{min-width:0!important;width:100%!important}
 .chart-value{grid-column:1/-1!important;text-align:right!important;white-space:normal!important;overflow-wrap:anywhere!important;font-weight:600}
 .report-panel>div[style*="overflow-x"]{display:block!important;overflow-x:auto!important;max-width:100%!important}
 .report-table{min-width:440px!important;width:100%!important}
}
@media print{
 html,body{height:auto!important;overflow:visible!important}
 body .app-container,body .app-container>.main-content{height:auto!important;max-height:none!important;overflow:visible!important}
 .report-panel>div[style*="overflow-x"]{overflow:visible!important;max-width:none!important}
 .report-table{min-width:0!important;width:100%!important;table-layout:auto!important}
 .report-grid{display:block!important}
 .chart-row{grid-template-columns:minmax(0,1fr) minmax(0,1.5fr) 100px!important}
 .chart-value{grid-column:auto!important}
}
</style>
</head><body><div class="print-cover"><h1>AZUCREATIVO</h1><h2>Reporte completo de ventas</h2><p>Periodo: <?=e($desde)?> al <?=e($hasta)?> · Agrupación: <?=e($periodoTitulo)?> · Estado: Pagada</p><p>Folio: <?=e($folioReporte)?> · Generado: <?=e($generadoEn->format("d/m/Y H:i:s"))?> · Realizado por: <?=e($autorReporte)?></p></div><div class="app-container"><aside class="sidebar"><?php $activo='reportes';include __DIR__.'/../config/menu.php';?></aside><main class="main-content"><header class="header-bar"><div class="header-left"><h1 class="view-title">📊 Reportes de ventas</h1></div><div class="header-user"><a href="../login/logout.php">Cerrar sesión</a></div></header><div class="report-content"><form method="get" class="report-filters"><label>Desde<input type="date" name="desde" value="<?=e($desde)?>" required></label><label>Hasta<input type="date" name="hasta" value="<?=e($hasta)?>" required></label><label>Agrupar por<select name="agrupacion" style="padding:9px;border:1px solid #accce5;border-radius:8px"><?php foreach(['dia'=>'Día','semana'=>'Semana','mes'=>'Mes','anio'=>'Año'] as $valor=>$texto):?><option value="<?=e($valor)?>" <?=$agrupacion===$valor?'selected':''?>><?=e($texto)?></option><?php endforeach;?></select></label><button type="submit">Aplicar filtros</button><button class="report-print" type="button" onclick="imprimirReporte()">Imprimir / Guardar como PDF</button></form><p>Periodo: <?=e($desde)?> al <?=e($hasta)?> · Solo ventas con estado <b>Pagada</b>.</p><p><strong>Folio:</strong> <?=e($folioReporte)?> · <strong>Fecha y hora:</strong> <?=e($generadoEn->format("d/m/Y H:i:s"))?> · <strong>Realizado por:</strong> <?=e($autorReporte)?></p><p><small>Nombre sugerido al guardar: <code><?=e($nombreReporte)?>.pdf</code></small></p>
<?php if($error):?><p class="report-error"><?=e($error)?></p><?php else:?>
<div class="report-cards"><div class="report-card">Ventas realizadas<b><?=number_format((int)$resumen['ventas'])?></b></div><div class="report-card">Ingresos<b>$<?=number_format((float)$resumen['ingresos'],2)?></b></div><div class="report-card">Ticket promedio<b>$<?=number_format((float)$resumen['ticket'],2)?></b></div><div class="report-card">Producto estrella<b style="font-size:1.1rem"><?=e($estrella['etiqueta']??'Sin ventas')?></b><small><?=isset($estrella)?number_format((int)$estrella['unidades']).' unidades':'Sin datos'?></small></div></div>
<div class="report-grid">
<?php foreach ([['Ventas por '.$periodoTitulo,$dias,'moneda'],['Quién vendió',$vendedores,'moneda'],['Métodos de pago',$pagos,'moneda'],['Productos más vendidos',$productos,'unidades']] as [$titulo,$datos,$tipo]):?>
<section class="report-panel"><h2><?=e($titulo)?></h2><?php $max=0;foreach($datos as $d){$max=max($max,(float)$d[$tipo==='unidades'?'unidades':'total']);}?><?php if(!$datos):?><p class="report-empty">No hay datos en este periodo.</p><?php endif;?><?php foreach($datos as $d):$val=(float)$d[$tipo==='unidades'?'unidades':'total'];?><div class="chart-row"><span class="chart-label"><?=e($d['etiqueta'])?></span><div class="chart-track"><div class="chart-bar" style="width:<?= $max>0?round(100*$val/$max,2):0 ?>%"></div></div><span class="chart-value"><?= $tipo==='unidades'?number_format($val):'$'.number_format($val,2)?></span></div><?php endforeach;?></section>
<?php endforeach;?></div><section class="report-panel" style="margin-top:15px"><h2>Comparativo de ventas por <?=e($periodoTitulo)?></h2><div style="overflow-x:auto"><table class="report-table"><thead><tr><th>Periodo</th><th>Ventas</th><th>Ingresos</th></tr></thead><tbody><?php foreach($dias as $d):?><tr><td><?=e($d['etiqueta'])?></td><td><?=number_format((int)$d['ventas'])?></td><td>$<?=number_format((float)$d['total'],2)?></td></tr><?php endforeach;?></tbody></table></div></section><section class="report-panel" style="margin-top:15px"><h2>Rendimiento por vendedor</h2><div style="overflow-x:auto"><table class="report-table"><thead><tr><th>Vendedor</th><th>Ventas</th><th>Ingresos</th></tr></thead><tbody><?php foreach($vendedores as $d):?><tr><td><?=e($d['etiqueta'])?></td><td><?=number_format((int)$d['ventas'])?></td><td>$<?=number_format((float)$d['total'],2)?></td></tr><?php endforeach;?></tbody></table></div></section><section class="report-panel" style="margin-top:15px"><h2>Detalle de productos vendidos</h2><div style="overflow-x:auto"><table class="report-table"><thead><tr><th>Producto</th><th>Unidades</th><th>Ingresos</th></tr></thead><tbody><?php foreach($productos as $p):?><tr><td><?=e($p['etiqueta'])?></td><td><?=number_format((int)$p['unidades'])?></td><td>$<?=number_format((float)$p['total'],2)?></td></tr><?php endforeach;?></tbody></table></div></section><p class="report-empty">Si no se puede consultar login_db, aparecerá el ID del usuario en lugar del nombre. Las ventas sin asociación a empleado o cliente web se muestran como «Sin vendedor identificado». Los importes se calculan con las ventas registradas en MySQL; no son utilidades netas.</p><?php endif;?></div></main></div><script>
function imprimirReporte(){
  // El navegador utiliza el título del documento como nombre sugerido al guardar PDF.
  document.title = <?=json_encode($nombreReporte, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
  window.print();
}
</script></body></html>
