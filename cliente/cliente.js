"use strict";
const $=id=>document.getElementById(id),money=n=>'$'+Number(n||0).toFixed(2);
let productos=[],cart=new Map();
async function api(accion,data=null){const r=await fetch('api.php'+(data===null?'?accion='+encodeURIComponent(accion):''),data===null?{credentials:'same-origin'}:{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({accion,...data})});const j=await r.json();if(!r.ok||!j.ok)throw Error(j.mensaje||'Error de conexión');return j;}
async function cargarVentas(){
 const area=$('misVentas');if(!area)return;
 try{
  const j=await api('mis_ventas');area.replaceChildren();
  if(!j.datos.length){area.textContent='Aún no tienes compras.';return;}
  for(const venta of j.datos){
   const row=document.createElement('div');row.className='cart-line';row.style.flexWrap='wrap';
   const info=document.createElement('span');info.textContent='Venta #'+venta.ID_Venta+' · '+venta.Estado_Venta+' · '+money(venta.Total)+' · '+venta.Metodo_Pago;
   const a=document.createElement('a');a.href='../ticket.php?id='+encodeURIComponent(venta.ID_Venta);a.target='_blank';a.rel='noopener';a.textContent='Ver ticket';
   row.append(info,a);area.append(row);
  }
 }catch(e){area.textContent=e.message;}
}
$('actualizarVentas').onclick=cargarVentas;
function msg(t){$('mensaje').className='pos-alert';$('mensaje').textContent=t;}
async function cargar(){try{const j=await api('catalogo');productos=j.datos;render();}catch(e){msg(e.message);}}
function total(){return [...cart].reduce((s,[id,q])=>s+Number(productos.find(p=>Number(p.ID_Producto)===id)?.Precio||0)*q,0);}
function render(){const q=$('buscar').value.toLocaleLowerCase('es');$('productos').replaceChildren();for(const p of productos.filter(x=>x.Nombre.toLocaleLowerCase('es').includes(q))){const d=document.createElement('div');d.className='product-card';const top=document.createElement('div');const badge=document.createElement('div');badge.className='product-card-top';badge.textContent='🍬 · Stock: '+p.Stock;const title=document.createElement('h4');title.className='product-title';title.textContent=p.Nombre;const desc=document.createElement('p');desc.className='product-category';desc.textContent=p.Descripcion||'Dulces';const bottom=document.createElement('div');bottom.className='product-card-bottom';const price=document.createElement('strong');price.className='product-price';price.textContent=money(p.Precio);const b=document.createElement('button');b.className='btn btn-pink btn-sm';b.textContent=Number(p.Stock)>0?'+ Agregar':'Agotado';b.disabled=Number(p.Stock)<=0;b.onclick=()=>{const id=Number(p.ID_Producto),n=(cart.get(id)||0)+1;if(n>Number(p.Stock))return msg('Stock insuficiente.');cart.set(id,n);renderCart();};top.append(badge,title,desc);bottom.append(price,b);d.append(top,bottom);$('productos').append(d);}renderCart();}
function renderCart(){$('carrito').replaceChildren();for(const [id,q] of cart){const p=productos.find(x=>Number(x.ID_Producto)===id);if(!p)continue;const d=document.createElement('div');d.className='cart-line';const label=document.createElement('span');label.textContent=p.Nombre+' · '+q+' × '+money(p.Precio);const controls=document.createElement('span');for(const [text,delta] of [['−',-1],['+',1]]){const b=document.createElement('button');b.textContent=text;b.onclick=()=>{const n=q+delta;if(n>Number(p.Stock))return msg('Stock insuficiente.');if(n<1)cart.delete(id);else cart.set(id,n);renderCart();};controls.append(b);}d.append(label,controls);$('carrito').append(d);}$('total').textContent=money(total());}
$('buscar').oninput=render;
$('cobrar').onclick=async()=>{if(!cart.size)return msg('Agrega productos al carrito.');let tarjeta4=null;try{if($('metodo').value==='Tarjeta')tarjeta4=window.demoTarjeta.ultimos4();}catch(e){return msg(e.message);}if(!confirm('Esta página no procesa pagos bancarios. ¿Confirmas que el pago fue recibido/verificado y deseas registrar la venta como pagada?'))return;const b=$('cobrar');b.disabled=true;try{const j=await api('venta',{pago_confirmado:true,metodo:$('metodo').value,tarjeta_ultimos4:tarjeta4,items:[...cart].map(([id,cantidad])=>({id,cantidad}))});cart.clear();window.demoTarjeta?.limpiar();msg('Venta #'+j.folio+' registrada. Total '+money(j.total)+'. Inventario actualizado. ');const a=document.createElement('a');a.href='../ticket.php?id='+j.folio;a.target='_blank';a.rel='noopener';a.textContent='Ver ticket de venta';$('mensaje').append(a);window.open(a.href,'_blank','noopener');await cargar();await cargarVentas();}catch(e){msg(e.message);}finally{b.disabled=false;}};
cargar();
cargarVentas();
