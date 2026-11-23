const API_CLIENTES_VENTAS = '../api/clientes_ventas.php';
let clientes = [];
let editingClientId = null;

window.addEventListener('DOMContentLoaded', () => {
    renderClientForm();
    loadClients();
});

function renderClientForm() {
    const container = document.getElementById('entity-form-fields');
    const fields = [
        ['Nombre', 'Nombre', 'text', true],
        ['Apellido', 'Apellido', 'text', true],
        ['Correo', 'Correo electrónico', 'email', false],
        ['Telefono', 'Teléfono', 'tel', false],
        ['Direccion', 'Dirección', 'text', false]
    ];
    container.innerHTML = fields.map(([name, label, type, required]) => `
        <div class="form-group"><label for="field-${name}">${label}${required ? ' *' : ''}</label>
        <input class="form-input" id="field-${name}" name="${name}" type="${type}" ${required ? 'required' : ''} maxlength="${name==='Correo'?150:255}"></div>
    `).join('') + `<div class="form-group"><label for="clave_cliente">Clave de autorización del administrador *</label><input class="form-input" id="clave_cliente" type="password" required autocomplete="off"></div>`;
}

async function apiRequest(action, payload = null, method = 'GET') {
    const url = new URL(API_CLIENTES_VENTAS, window.location.href);
    let options = { method, headers: { 'Accept': 'application/json' } };
    if (method === 'GET') url.searchParams.set('accion', action);
    else {
        options.headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify({ accion: action, ...(payload || {}) });
    }
    const response = await fetch(url, options);
    const result = await response.json();
    if (!response.ok || !result.ok) throw new Error(result.mensaje || 'No se pudo completar la operación.');
    return result;
}

async function loadClients() {
    try {
        const result = await apiRequest('listar_clientes');
        clientes = result.datos;
        renderClientTable(clientes);
    } catch (error) { showToast(error.message, 'error'); }
}

async function searchEntityRecord() {
    const q = document.getElementById('client-search').value.trim();
    if (!q) { await loadClients(); return; }
    try {
        const url = new URL(API_CLIENTES_VENTAS, window.location.href);
        url.searchParams.set('accion', 'buscar_clientes');
        url.searchParams.set('q', q);
        const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
        const result = await response.json();
        if (!response.ok || !result.ok) throw new Error(result.mensaje || 'No se pudo buscar.');
        clientes = result.datos;
        renderClientTable(clientes);
        if (!clientes.length) showToast('No se encontraron clientes.', 'info');
    } catch (error) { showToast(error.message, 'error'); }
}

function renderClientTable(data) {
    const head = document.getElementById('entity-table-head');
    const body = document.getElementById('entity-table-body');
    const count = document.getElementById('record-count');
    const columns = [['ID_Cliente','ID'],['Nombre','Nombre'],['Apellido','Apellido'],['Correo','Correo'],['Telefono','Teléfono'],['Direccion','Dirección']];
    head.innerHTML = `<tr>${columns.map(c=>`<th>${c[1]}</th>`).join('')}<th>ACCIONES</th></tr>`;
    body.innerHTML = data.map(row => `<tr>${columns.map(([key])=>`<td>${esc(row[key] ?? '—')}</td>`).join('')}<td style="white-space:nowrap;"><button type="button" class="action-btn" title="Editar" onclick="editClient(${Number(row.ID_Cliente)})">✏️</button> <button type="button" class="action-btn" title="Eliminar" onclick="deleteClient(${Number(row.ID_Cliente)})">🗑️</button></td></tr>`).join('') || '<tr><td colspan="7">No hay clientes registrados.</td></tr>';
    count.textContent = `${data.length} registros`;
}

async function saveEntityRecord() {
    const payload = { ID_Cliente: editingClientId || '', Nombre: val('Nombre'), Apellido: val('Apellido'), Correo: val('Correo'), Telefono: val('Telefono'), Direccion: val('Direccion'), clave_autorizacion: document.getElementById('clave_cliente').value };
    try {
        const result = await apiRequest('guardar_cliente', payload, 'POST');
        showToast(result.mensaje, 'success');
        clearEntityForm();
        await loadClients();
    } catch (error) { showToast(error.message, 'error'); }
}

function editClient(id) {
    const row = clientes.find(c => Number(c.ID_Cliente) === id);
    if (!row) return;
    editingClientId = id;
    ['Nombre','Apellido','Correo','Telefono','Direccion'].forEach(k => { document.getElementById(`field-${k}`).value = row[k] ?? ''; });
    document.getElementById('current-view-title').textContent = `Módulo: Cliente (editando #${id})`;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

async function deleteClient(id) {
    if (!confirm(`¿Eliminar el cliente #${id}? Si tiene ventas históricas, se conservarán. Los clientes anónimos ya anonimizados dejarán de mostrarse en la lista.`)) return;
    try {
        const result = await apiRequest('eliminar_cliente', { ID_Cliente: id, clave_autorizacion: prompt('Clave de autorización del administrador') || '' }, 'POST');
        showToast(result.mensaje, 'success');
        await loadClients();
    } catch (error) { showToast(error.message, 'error'); }
}

function clearEntityForm() {
    editingClientId = null;
    ['Nombre','Apellido','Correo','Telefono','Direccion'].forEach(k => { const el = document.getElementById(`field-${k}`); if (el) el.value = ''; });
    document.getElementById('current-view-title').textContent = 'Módulo: Cliente';
}
function val(name) { return document.getElementById(`field-${name}`).value.trim(); }
function esc(value) { return String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
function showToast(msg, type='info') {
    const container = document.getElementById('toast-container'); if (!container) { alert(msg); return; }
    const toast = document.createElement('div'); toast.className = `toast toast-${type}`; toast.textContent = msg; container.appendChild(toast); setTimeout(()=>toast.remove(), 3500);
}
