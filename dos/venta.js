const API_CLIENTES_VENTAS = '../api/clientes_ventas.php';
let clientesDisponibles = [];
let productosDisponibles = [];
let carritoVenta = [];

window.addEventListener('DOMContentLoaded', async () => {
    renderSaleForm();
    await Promise.all([loadClientsForSale(), loadProductsForSale(), loadSales()]);
});

function renderSaleForm() {
    // El selector de mostrador ya está presente en el HTML: no reemplazarlo.
    const selector = document.getElementById('field-ID_Cliente');
    if (selector && !selector.querySelector('option[value="0"]')) {
        selector.insertAdjacentHTML('afterbegin', '<option value="0">Cliente no registrado (venta de mostrador)</option>');
    }
}
async function apiRequest(action, payload = null, method = 'GET') {
    const url = new URL(API_CLIENTES_VENTAS, window.location.href);
    const options = { method, headers: { 'Accept': 'application/json' } };
    if (method === 'GET') url.searchParams.set('accion', action);
    else { options.headers['Content-Type'] = 'application/json'; options.body = JSON.stringify({ accion: action, ...(payload || {}) }); }
    const response = await fetch(url, options); const result = await response.json();
    if (!response.ok || !result.ok) throw new Error(result.mensaje || 'No se pudo completar la operación.');
    return result;
}
async function loadClientsForSale() {
    try {
        const result = await apiRequest('listar_clientes'); clientesDisponibles = result.datos;
        const select = document.getElementById('field-ID_Cliente');
        select.innerHTML = '<option value="0">Cliente no registrado (venta de mostrador)</option>' + clientesDisponibles.map(c=>`<option value="${Number(c.ID_Cliente)}">#${Number(c.ID_Cliente)} — ${esc(c.Nombre)} ${esc(c.Apellido)}</option>`).join('');
        // Se puede vender sin clientes registrados.
    } catch (error) { showToast('No se pudieron cargar los clientes registrados. Puedes continuar cobrando como cliente no registrado. ' + error.message, 'error'); }
}
async function loadProductsForSale() {
    try {
        const result = await apiRequest('listar_productos'); productosDisponibles = result.datos;
        const select = document.getElementById('product-picker');
        select.innerHTML = '<option value="">Selecciona un producto</option>' + productosDisponibles.map(p=>`<option value="${Number(p.ID_Producto)}">#${Number(p.ID_Producto)} — ${esc(p.Nombre)} ($${Number(p.Precio).toFixed(2)}; stock: ${Number(p.Stock)})</option>`).join('');
        if (!productosDisponibles.length) select.innerHTML = '<option value="">No hay productos registrados en la base de datos</option>';
    } catch (error) { showToast(error.message, 'error'); }
}
function addProductToCart() {
    const id = Number(document.getElementById('product-picker').value);
    const quantity = Number(document.getElementById('product-quantity').value);
    const product = productosDisponibles.find(p => Number(p.ID_Producto) === id);
    if (!product) return showToast('Selecciona un producto válido.', 'error');
    if (!Number.isInteger(quantity) || quantity < 1) return showToast('La cantidad debe ser un número entero mayor que cero.', 'error');
    const existing = carritoVenta.find(i => i.ID_Producto === id);
    const requested = quantity + (existing ? existing.Cantidad : 0);
    if (requested > Number(product.Stock)) return showToast(`Stock insuficiente. Disponible: ${product.Stock}.`, 'error');
    if (existing) existing.Cantidad = requested; else carritoVenta.push({ ID_Producto: id, Cantidad: quantity });
    renderCart();
}
function removeCartItem(id) { carritoVenta = carritoVenta.filter(i => i.ID_Producto !== id); renderCart(); }
function renderCart() {
    const body = document.getElementById('sale-cart-body'); let total = 0;
    if (!carritoVenta.length) body.innerHTML = '<tr><td colspan="5">Agrega productos para comenzar.</td></tr>';
    else body.innerHTML = carritoVenta.map(item => {
        const p = productosDisponibles.find(x => Number(x.ID_Producto) === item.ID_Producto);
        const price = Number(p?.Precio || 0), subtotal = price * item.Cantidad; total += subtotal;
        return `<tr><td>${esc(p?.Nombre || `Producto #${item.ID_Producto}`)}</td><td>$${price.toFixed(2)}</td><td>${item.Cantidad}</td><td>$${subtotal.toFixed(2)}</td><td><button type="button" class="action-btn" onclick="removeCartItem(${item.ID_Producto})" title="Quitar">🗑️</button></td></tr>`;
    }).join('');
    document.getElementById('sale-total').textContent = total.toFixed(2);
}
async function saveEntityRecord() {
    const ID_Cliente = Number(document.getElementById('field-ID_Cliente').value);
    if (ID_Cliente < 0 || !Number.isInteger(ID_Cliente)) return showToast('Selecciona un cliente válido.', 'error');
    if (!carritoVenta.length) return showToast('Agrega al menos un producto a la venta.', 'error');
    if (ID_Cliente === 0 && !confirm('¿Cobrar como cliente no registrado? Se generará un ticket sin crear una cuenta de cliente.')) return;
    try {
        const result = await apiRequest('guardar_venta', { ID_Cliente, Metodo_Pago: document.getElementById('field-Metodo_Pago').value, Estado_Venta: document.getElementById('field-Estado_Venta').value, items: carritoVenta }, 'POST');
        showToast(`${result.mensaje} Ticket #${result.ID_Venta}; total $${Number(result.Total).toFixed(2)}.`, 'success');
        carritoVenta = []; renderCart(); document.getElementById('field-ID_Cliente').value = '0'; document.getElementById('field-Metodo_Pago').value = 'Efectivo'; document.getElementById('field-Estado_Venta').value = 'Pagada';
        await Promise.all([loadProductsForSale(), loadSales()]);
        document.getElementById('ticket-search').value = result.ID_Venta; await searchTicket();
    } catch (error) { showToast(error.message, 'error'); }
}
async function loadSales() {
    try { const result = await apiRequest('listar_ventas'); renderSalesTable(result.datos); }
    catch (error) { showToast(error.message, 'error'); }
}
function renderSalesTable(rows) {
    const head = document.getElementById('entity-table-head'), body = document.getElementById('entity-table-body'), count = document.getElementById('record-count');
    const cols = [['ID_Venta','Ticket'],['Cliente','Cliente'],['Fecha_Venta','Fecha'],['Metodo_Pago','Pago'],['Estado_Venta','Estado'],['Total','Total']];
    head.innerHTML = `<tr>${cols.map(c=>`<th>${c[1]}</th>`).join('')}<th>ACCIÓN</th></tr>`;
    body.innerHTML = rows.map(r=>`<tr>${cols.map(([key])=>`<td>${key==='Total'?'$'+Number(r[key]).toFixed(2):esc(r[key] ?? '—')}</td>`).join('')}<td><button type="button" class="action-btn" title="Consultar ticket" onclick="openTicket(${Number(r.ID_Venta)})">🔎</button></td></tr>`).join('') || '<tr><td colspan="7">No hay ventas registradas.</td></tr>';
    count.textContent = `${rows.length} registros`;
}
function openTicket(id) { document.getElementById('ticket-search').value = id; searchTicket(); }
async function searchTicket() {
    const q = document.getElementById('ticket-search').value.trim(); if (!q) return showToast('Escribe el número de ticket.', 'error');
    const target = document.getElementById('ticket-result'); target.innerHTML = 'Consultando ticket…';
    try {
        const url = new URL(API_CLIENTES_VENTAS, window.location.href); url.searchParams.set('accion','consultar_ticket'); url.searchParams.set('q',q);
        const response = await fetch(url, { headers:{'Accept':'application/json'} }); const result = await response.json();
        if (!response.ok || !result.ok) throw new Error(result.mensaje || 'No se encontró el ticket.');
        target.innerHTML = ticketHtml(result.venta, result.detalles);
    } catch(error) { target.innerHTML = ''; showToast(error.message, 'error'); }
}
function ticketHtml(v, details) {
    const lines = details.map(d=>`<tr><td>${esc(d.Producto)}</td><td>${d.Cantidad}</td><td>$${Number(d.Precio_Unitario).toFixed(2)}</td><td>$${Number(d.Subtotal).toFixed(2)}</td></tr>`).join('');
    return `<div class="card" style="padding:14px;"><h3>Ticket #${Number(v.ID_Venta)}</h3><p><strong>Cliente:</strong> ${esc(v.Nombre)} ${esc(v.Apellido)} &nbsp; <strong>Fecha:</strong> ${esc(v.Fecha_Venta)}</p><p><strong>Pago:</strong> ${esc(v.Metodo_Pago)} &nbsp; <strong>Estado:</strong> ${esc(v.Estado_Venta)}</p><div class="table-wrapper"><table class="data-table"><thead><tr><th>PRODUCTO</th><th>CANTIDAD</th><th>PRECIO</th><th>SUBTOTAL</th></tr></thead><tbody>${lines}</tbody></table></div><p style="text-align:right;font-weight:700;">Total: $${Number(v.Total).toFixed(2)}</p></div>`;
}
function clearEntityForm() { document.getElementById('field-ID_Cliente').value='0'; document.getElementById('field-Metodo_Pago').value='Efectivo'; document.getElementById('field-Estado_Venta').value='Pagada'; carritoVenta=[]; renderCart(); }
function esc(value) { return String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
function showToast(msg, type='info') { const container=document.getElementById('toast-container'); if(!container){alert(msg);return;} const toast=document.createElement('div');toast.className=`toast toast-${type}`;toast.textContent=msg;container.appendChild(toast);setTimeout(()=>toast.remove(),4000); }
