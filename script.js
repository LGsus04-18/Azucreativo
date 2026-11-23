// PÁGINA PRINCIPAL: Punto de Venta

let currentCart = [];
let db = { Producto: (window.CATALOGO_INICIAL || []).map(p => ({...p, ID_Producto:String(p.ID_Producto), Stock:Number(p.Stock)||0})) };

async function cargarCatalogo() {
    const grid = document.getElementById("pos-products-grid");
    try {
        const respuesta = await fetch("api/productos_categorias.php?accion=listar_productos", {cache:"no-store"});
        const datos = await respuesta.json();
        if (!respuesta.ok || !datos.ok) throw new Error(datos.mensaje || "No se pudieron consultar los productos");
        db.Producto = datos.datos.map(p => ({...p, ID_Producto:String(p.ID_Producto), Stock:Number(p.Stock)||0}));
        currentCart = currentCart.filter(i => db.Producto.some(p => p.ID_Producto === i.id));
        currentCart.forEach(i => {const p=db.Producto.find(p=>p.ID_Producto===i.id); i.qty=Math.min(i.qty,p.Stock);i.price=Number(p.Precio);});
        currentCart=currentCart.filter(i=>i.qty>0);
        renderPosCatalog();renderCart();
    } catch(e) { if (!db.Producto.length && grid) grid.textContent="No se pudo cargar el catálogo: "+e.message+". Solicita al administrador revisar diagnostico_catalogo.php"; showToast("No se pudo actualizar el catálogo: "+e.message,"error"); }
}


window.onload = function() {
    if (db.Producto.length) { renderPosCatalog(); renderCart(); }
    cargarCatalogo();
    cargarClientesCarrito();
    window.addEventListener("focus", cargarCatalogo);
};

// LOGICA DE PUNTO DE VENTA Y CARRITO
function escapeHtml(v) {return String(v ?? "").replace(/[&<>"']/g, c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));}
function renderPosCatalog() {
    const searchVal = document.getElementById('pos-search')?.value.toLowerCase() || '';
    const gridContainer = document.getElementById('pos-products-grid');
    if (!gridContainer) return;
    gridContainer.innerHTML = '';

    const filteredProducts = (db.Producto || []).filter(p => 
        (p.Nombre || '').toLowerCase().includes(searchVal) || 
        (p.Descripcion || p.Categoria || '').toLowerCase().includes(searchVal)
    );

    if (!filteredProducts.length) {gridContainer.textContent = "No hay productos registrados que coincidan con la búsqueda."; return;}
    filteredProducts.forEach(p => {
        const card = document.createElement('div');
        card.className = "product-card";
        card.innerHTML = `
            <div>
                <div class="product-card-top">
                    <span class="product-emoji">${p.icon || '🍬'}</span>
                    <span class="stock-badge">Stock: ${p.Stock}</span>
                </div>
                <h4 class="product-title">${escapeHtml(p.Nombre)}</h4>
                <div class="product-category">${escapeHtml(p.Descripcion || 'General')}</div>
            </div>
            <div class="product-card-bottom">
                <span class="product-price">$${parseFloat(p.Precio || 0).toFixed(2)}</span>
                <button onclick="addToCart('${p.ID_Producto}')" class="btn btn-pink btn-sm" ${p.Stock<=0 ? "disabled" : ""}>${p.Stock<=0 ? "Agotado" : "+ Agregar"}</button>
            </div>
        `;
        gridContainer.appendChild(card);
    });
}


// AQUÍ AGREGAS LA FUNCIÓN PARA LIMPIAR LA BÚSQUEDA
function clearPosSearch() {
    const searchInput = document.getElementById('pos-search');
    if (searchInput) {
        searchInput.value = ''; // Deja la barra en blanco
    }
    renderPosCatalog(); // Renderiza de nuevo todo el catálogo sin filtros
}

// ... abajo continúan las demás funciones (addToCart, renderCart, etc.)


function addToCart(productId) {
    const product = db.Producto.find(p => p.ID_Producto === String(productId));
    if (!product || product.Stock <= 0) return;

    const item = currentCart.find(i => i.id === String(productId));
    if (item) {
        if (item.qty < product.Stock) item.qty++;
    } else {
        currentCart.push({ id: product.ID_Producto, name: product.Nombre, price: parseFloat(product.Precio), qty: 1 });
    }

    renderCart();
}

function updateCartQty(productId, delta) {
    const item = currentCart.find(i => i.id === String(productId));
    if (!item) return;

    const product = db.Producto.find(p => p.ID_Producto === String(productId));
    if (!product) return;
    item.qty += delta;

    if (item.qty > product.Stock) item.qty = product.Stock;

    if (item.qty <= 0) {
        currentCart = currentCart.filter(i => i.id !== String(productId));
    }
    renderCart();
}

function emptyCart() {
    currentCart = [];
    renderCart();
}

function renderCart() {
    const container = document.getElementById('cart-items-list');
    const countBadge = document.getElementById('cart-item-count');
    if (!container) return;
    container.innerHTML = '';

    let subtotal = 0;
    let totalQty = 0;

    currentCart.forEach(item => {
        subtotal += item.price * item.qty;
        totalQty += item.qty;

        const row = document.createElement('div');
        row.className = 'cart-item-row';
        row.innerHTML = `
            <div>
                <strong>${escapeHtml(item.name)}</strong>
                <div class="subtitle">$${item.price.toFixed(2)} x ${item.qty}</div>
            </div>
            <div style="display: flex; align-items: center; gap: 0.25rem;">
                <button onclick="updateCartQty('${item.id}', -1)" class="btn btn-secondary btn-sm">-</button>
                <span style="font-weight:bold; padding: 0 0.25rem;">${item.qty}</span>
                <button onclick="updateCartQty('${item.id}', 1)" class="btn btn-secondary btn-sm">+</button>
            </div>
        `;
        container.appendChild(row);
    });

    const tax = 0; // El servidor registra el precio de Producto sin recargo adicional.
    const total = subtotal;

    if (countBadge) countBadge.innerText = `${totalQty} Items`;
    
    const elemSubtotal = document.getElementById('cart-subtotal');
    const elemTax = document.getElementById('cart-tax');
    const elemTotal = document.getElementById('cart-total');

    if (elemSubtotal) elemSubtotal.innerText = `$${subtotal.toFixed(2)}`;
    if (elemTax) elemTax.innerText = `$${tax.toFixed(2)}`;
    if (elemTotal) elemTotal.innerText = `$${total.toFixed(2)}`;

    calculateChange();
}

function actualizarMetodoPago() {
    const metodo = document.getElementById('cart-payment')?.value || 'Efectivo';
    const campos = document.getElementById('cart-cash-fields');
    if (campos) campos.style.display = metodo === 'Efectivo' ? '' : 'none';
    window.demoTarjeta?.actualizar();
    calculateChange();
}

function calculateChange() {
    const elemSubtotal = document.getElementById('cart-subtotal');
    if (!elemSubtotal) return;

    const subtotal = parseFloat(elemSubtotal.innerText.replace('$', '')) || 0;
    const total = subtotal;
    const cashInput = document.getElementById('cart-cash');
    const cash = cashInput ? parseFloat(cashInput.value) || 0 : 0;
    const changeBox = document.getElementById('cart-change');

    if (!changeBox) return;

    if (cash >= total && total > 0) {
        changeBox.innerText = `$${(cash - total).toFixed(2)}`;
        changeBox.style.color = '#15803d';
    } else if (total > 0) {
        changeBox.innerText = `Falta $${(total - cash).toFixed(2)}`;
        changeBox.style.color = '#b91c1c';
    } else {
        changeBox.innerText = `$0.00`;
        changeBox.style.color = '#6b7280';
    }
}

async function cargarClientesCarrito() {
    const selector = document.getElementById('cart-cliente');
    if (!selector) return;
    try {
        const response = await fetch('api/clientes_ventas.php?accion=listar_clientes', {credentials:'same-origin',cache:'no-store'});
        const result = await response.json();
        if (!response.ok || !result.ok) throw new Error(result.mensaje || 'No se pudieron cargar clientes');
        selector.replaceChildren(new Option('Cliente no registrado (venta de mostrador)', '0'));
        for (const c of result.datos) selector.add(new Option(`${c.Nombre} ${c.Apellido} (ID ${c.ID_Cliente})`, String(c.ID_Cliente)));
    } catch (error) { showToast('Clientes: '+error.message, 'error'); }
}

let cobrando = false;
async function processCheckout() {
    if (cobrando) return;
    if (!currentCart.length) return showToast('Agrega productos antes de cobrar.', 'error');
    const cliente = Number(document.getElementById('cart-cliente')?.value || 0);
    if (!Number.isInteger(cliente) || cliente < 0) return showToast('Selecciona un cliente válido.', 'error');
    const metodo = document.getElementById('cart-payment')?.value || 'Efectivo';
    const efectivoTexto = document.getElementById('cart-cash')?.value || '';
    const efectivo = Number(efectivoTexto);
    const total = currentCart.reduce((sum, item) => sum + item.price * item.qty, 0);
    if (metodo === 'Efectivo' && (efectivoTexto.trim() === '' || !Number.isFinite(efectivo) || efectivo < total)) return showToast('El efectivo recibido no cubre el total.', 'error');
    let tarjeta4 = null;
    try { if(metodo==='Tarjeta') tarjeta4=window.demoTarjeta.ultimos4(); } catch(e){return showToast(e.message,'error');}
    if (cliente === 0 && !confirm('¿Cobrar como cliente no registrado? Se generará un ticket sin crear una cuenta de acceso.')) return;
    const boton = document.getElementById('cart-checkout');
    cobrando = true;
    if (boton) boton.disabled = true;
    try {
        const response = await fetch('api/clientes_ventas.php', {
            method: 'POST', credentials: 'same-origin', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({accion:'guardar_venta',ID_Cliente:cliente,Metodo_Pago:metodo,Estado_Venta:'Pagada',tarjeta_ultimos4:tarjeta4,items:currentCart.map(i=>({ID_Producto:Number(i.id),Cantidad:i.qty}))})
        });
        const result = await response.json();
        if (!response.ok || !result.ok) throw new Error(result.mensaje || 'No se pudo registrar la venta');
        showToast(`Venta #${result.ID_Venta} registrada. Total: $${Number(result.Total).toFixed(2)}`, 'success');
        // El ticket se consulta al servidor usando el folio real de la venta.
        const ticketUrl = 'ticket.php?id=' + encodeURIComponent(result.ID_Venta) +
            (metodo === 'Efectivo' ? '&recibido=' + encodeURIComponent(efectivo.toFixed(2)) : '');
        const ventanaTicket = window.open(ticketUrl, '_blank', 'width=480,height=740');
        if (!ventanaTicket) showToast('Venta guardada. Habilita ventanas emergentes para imprimir el ticket: ' + ticketUrl, 'info');
        window.demoTarjeta?.limpiar();
        currentCart = [];
        const cashInput = document.getElementById('cart-cash');
        if (cashInput) cashInput.value = '';
        renderCart();
        await cargarCatalogo();
    } catch (error) {
        showToast('No se realizó el cobro: '+error.message, 'error');
        await cargarCatalogo();
    } finally {
        cobrando = false;
        if (boton) boton.disabled = false;
    }
}

function quickAddProductModal() {
    location.href = '/Azucreativo/uno/producto.php';
}

function showToast(msg, type = 'info') {
    const container = document.getElementById('toast-container');
    if (!container) return;
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerText = msg;
    container.appendChild(toast);
    setTimeout(() => toast.remove(), 2500);
}