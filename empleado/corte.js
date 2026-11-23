'use strict';
const $=id=>document.getElementById(id);
const money=n=>'$'+Number(n||0).toFixed(2);
async function api(accion,data=null){const r=await fetch('api.php'+(data===null?'?accion='+encodeURIComponent(accion):''),data===null?{credentials:'same-origin'}:{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({accion,...data})});const j=await r.json();if(!r.ok||!j.ok)throw Error(j.mensaje||'Error de conexión');return j;}
function msg(t){$('mensaje').textContent=t;}
async function resumen(){try{const j=await api('resumen');const d=j.datos;$('resumen').textContent=`Ventas: ${d.cantidad} · Efectivo: ${money(d.efectivo)} · Tarjeta: ${money(d.tarjeta)} · Transferencia: ${money(d.transferencia)} · Total: ${money(d.total)}`;}catch(e){msg(e.message);}}
$('cerrar').onclick=async()=>{const n=$('contado').value;if(n===''||Number(n)<0)return msg('Indica el efectivo contado.');const adminId=Number($('admin_corte').value);const pin=$('pin_corte').value;if(!Number.isInteger(adminId)||adminId<1||!/^[0-9]{4}$/.test(pin))return msg('Indica el ID del administrador y su PIN de autorización de 4 dígitos.');if(!confirm('¿Cerrar el corte con autorización administrativa? No podrás incluir de nuevo estas ventas.'))return;const b=$('cerrar');b.disabled=true;try{const j=await api('corte',{efectivo_contado:Number(n),admin_autorizador:adminId,clave_autorizacion:pin});$('resultado').textContent=`Corte #${j.corte} · ${j.ventas} ventas · Efectivo esperado: ${money(j.efectivo)} · Tarjeta: ${money(j.tarjeta)} · Transferencia: ${money(j.transferencia)} · Diferencia: ${money(j.diferencia)}`;$('contado').value='';$('pin_corte').value='';await resumen();}catch(e){msg(e.message);}finally{b.disabled=false;}};

resumen();
