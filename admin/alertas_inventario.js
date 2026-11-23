(() => {
const api='/Azucreativo/admin/api_alertas_inventario.php';
const esc=s=>String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
async function cargar(){
 try {const r=await fetch(api,{credentials:'same-origin',cache:'no-store'});const d=await r.json();if(!r.ok||!d.ok)throw Error(d.mensaje||'Error');
 const n=d.conteos.bajos+d.conteos.agotados;
 const badge=document.getElementById('azu-bell-count');if(badge){badge.textContent=n;badge.hidden=n===0;}
 const panel=document.getElementById('azu-alertas-panel');if(!panel)return;
 panel.querySelector('#azu-counts').textContent=`Disponibles: ${d.conteos.disponibles} · Stock bajo: ${d.conteos.bajos} · Agotados: ${d.conteos.agotados}`;
 const tbody=panel.querySelector('tbody');tbody.innerHTML=d.productos.map(x=>`<tr><td>${esc(x.Nombre)}</td><td>${x.Stock}</td><td><input type="number" min="0" max="1000000" value="${x.Stock_Minimo}" data-minimo="${x.ID_Producto}" style="width:75px"></td><td>${x.estado==='agotado'?'🔴 Agotado':x.estado==='bajo'?'🟡 Stock bajo':'🟢 Disponible'}</td><td><button type="button" data-guardar="${x.ID_Producto}">Guardar</button></td></tr>`).join('');
 }catch(e){const panel=document.getElementById('azu-alertas-panel');if(panel)panel.querySelector('#azu-counts').textContent=e.message;}
}
document.addEventListener('click',async e=>{const b=e.target.closest('[data-guardar]');if(!b)return;const id=b.dataset.guardar;const input=document.querySelector(`[data-minimo="${id}"]`);const form=new FormData();form.set('accion','guardar_minimo');form.set('id',id);form.set('minimo',input.value);form.set('csrf',document.getElementById('azu-inv-csrf').value);try{const r=await fetch(api,{method:'POST',credentials:'same-origin',body:form});const d=await r.json();if(!r.ok||!d.ok)throw Error(d.mensaje);await cargar();}catch(err){alert(err.message);}});
document.addEventListener('DOMContentLoaded',()=>{cargar();setInterval(cargar,60000);});
})();
