const API_CLIENTES_VENTAS = '../api/clientes_ventas.php';
window.addEventListener('DOMContentLoaded', loadDetails);
async function apiRequest(action) {
    const url = new URL(API_CLIENTES_VENTAS, window.location.href); url.searchParams.set('accion', action);
    const response = await fetch(url, {headers:{'Accept':'application/json'}}); const result = await response.json();
    if (!response.ok || !result.ok) throw new Error(result.mensaje || 'No se pudieron cargar los detalles.'); return result;
}
async function loadDetails() {
    try { const result = await apiRequest('listar_detalles'); renderDetails(result.datos); }
    catch (error) { showToast(error.message, 'error'); }
}
function renderDetails(rows) {
    const head=document.getElementById('entity-table-head'), body=document.getElementById('entity-table-body'), count=document.getElementById('record-count');
    const cols=[['ID_DetalleVenta','ID detalle'],['ID_Venta','Ticket'],['ID_Producto','ID producto'],['Producto','Producto'],['Cantidad','Cantidad'],['Precio_Unitario','Precio unitario'],['Subtotal','Subtotal']];
    head.innerHTML=`<tr>${cols.map(c=>`<th>${c[1]}</th>`).join('')}<th>ACCIÓN</th></tr>`;
    body.innerHTML=rows.map(r=>`<tr>${cols.map(([key])=>`<td>${key==='Precio_Unitario'||key==='Subtotal'?'$'+Number(r[key]).toFixed(2):esc(r[key]??'—')}</td>`).join('')}<td><button type="button" class="action-btn" title="Consultar ticket" onclick="openTicket(${Number(r.ID_Venta)})">🔎</button></td></tr>`).join('')||'<tr><td colspan="8">No hay detalles de ventas registrados.</td></tr>';
    count.textContent=`${rows.length} registros`;
}
function openTicket(id) { document.getElementById('ticket-search').value=id; searchTicket(); }
async function searchTicket() {
    const q=document.getElementById('ticket-search').value.trim(); if(!q) return showToast('Escribe el número de ticket.', 'error');
    const target=document.getElementById('ticket-result'); target.textContent='Consultando…';
    try {
        const url=new URL(API_CLIENTES_VENTAS,window.location.href);url.searchParams.set('accion','consultar_ticket');url.searchParams.set('q',q);
        const response=await fetch(url,{headers:{'Accept':'application/json'}});const result=await response.json();
        if(!response.ok||!result.ok) throw new Error(result.mensaje||'No se encontró el ticket.');
        const v=result.venta;const lines=result.detalles.map(d=>`<tr><td>${esc(d.ID_DetalleVenta)}</td><td>${esc(d.Producto)}</td><td>${d.Cantidad}</td><td>$${Number(d.Precio_Unitario).toFixed(2)}</td><td>$${Number(d.Subtotal).toFixed(2)}</td></tr>`).join('');
        target.innerHTML=`<div class="card" style="padding:14px;"><h3>Ticket #${Number(v.ID_Venta)}</h3><p><strong>Cliente:</strong> ${esc(v.Nombre)} ${esc(v.Apellido)} | <strong>Fecha:</strong> ${esc(v.Fecha_Venta)}</p><p><strong>Método de pago:</strong> ${esc(v.Metodo_Pago)} | <strong>Estado:</strong> ${esc(v.Estado_Venta)}</p><div class="table-wrapper"><table class="data-table"><thead><tr><th>ID DETALLE</th><th>PRODUCTO</th><th>CANTIDAD</th><th>PRECIO UNITARIO</th><th>SUBTOTAL</th></tr></thead><tbody>${lines}</tbody></table></div><p style="text-align:right;font-weight:700;">Total de la venta: $${Number(v.Total).toFixed(2)}</p></div>`;
    } catch(error) { target.innerHTML=''; showToast(error.message,'error'); }
}
function esc(value){return String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
function showToast(msg,type='info'){const container=document.getElementById('toast-container');if(!container){alert(msg);return;}const toast=document.createElement('div');toast.className=`toast toast-${type}`;toast.textContent=msg;container.appendChild(toast);setTimeout(()=>toast.remove(),4000);}
