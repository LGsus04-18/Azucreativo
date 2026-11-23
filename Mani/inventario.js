// Tabla Inventario: conectada a la base de datos Azucreativo_db (api/*.php)
const ENTITY_SCHEMAS = {
    Inventario: [
        { name: 'ID_Inventario', label: 'ID_Inventario (PK)', type: 'text', pk: true },
        { name: 'ID_Producto', label: 'ID_Producto (FK)', type: 'text' },
        { name: 'Cantidad', label: 'Cantidad', type: 'number' },
        { name: 'Fecha_Entrada', label: 'Fecha_Entrada', type: 'date' },
        { name: 'Fecha_Salida', label: 'Fecha_Salida', type: 'date' }
    ]
};

let db = { Inventario: [] };
let currentView = 'Inventario';
let editingIndex = null;
let productosMap = {};             // ID_Producto -> Nombre
let inventarioEditandoId = null;    // ID_Inventario que se está editando
let ultimosFiltrosInventario = {};  // últimos filtros usados

window.onload = function() {
    renderEntityForm(currentView);
    iniciarInventario();
};

function renderEntityForm(entityKey) {
    const container = document.getElementById('entity-form-fields');
    if (!container) return;
    container.innerHTML = '';
    
    const fields = ENTITY_SCHEMAS[entityKey] || [];

    fields.forEach(f => {
        const group = document.createElement('div');
        group.className = 'form-group';
        group.innerHTML = `
            <label>${f.label}</label>
            <input type="${f.type}" id="field-${f.name}" placeholder="${f.placeholder || (f.pk ? 'Automático' : '')}" class="form-input">
        `;
        container.appendChild(group);
    });
}

function renderEntityTable(entityKey, filteredData = null) {
    const head = document.getElementById('entity-table-head');
    const body = document.getElementById('entity-table-body');
    const countBadge = document.getElementById('record-count');
    
    if (!head || !body) return;
    head.innerHTML = '';
    body.innerHTML = '';

    const data = filteredData || db[entityKey] || [];
    if (countBadge) countBadge.innerText = `${data.length} registros`;

    const fields = ENTITY_SCHEMAS[entityKey] || [];
    if (fields.length === 0) return;

    // Encabezados
    let trHead = '<tr>';
    fields.forEach(f => {
        trHead += `<th>${f.name.toUpperCase()}</th>`;
    });
    trHead += '<th style="text-align: right;">ACCIONES</th></tr>';
    head.innerHTML = trHead;

    // Filas
    data.forEach((row, index) => {
        let trBody = '<tr>';
        fields.forEach(f => {
            let val = row[f.name] !== undefined ? row[f.name] : '-';
            if (entityKey === 'Inventario' && f.name === 'ID_Producto' && productosMap[val]) {
                val = productosMap[val];
            }
            trBody += `<td>${esc(val)}</td>`;
        });
        
        const esInv  = entityKey === 'Inventario';
        const onEdit = esInv ? `editarInventario(${Number(row.ID_Inventario)})` : `editRecord(${index})`;
        const onDel  = esInv ? `eliminarInventario(${Number(row.ID_Inventario)})` : `deleteRecord(${index})`;

        trBody += `
            <td style="text-align: right;">
                <button type="button" onclick="${onEdit}" class="action-btn" title="Editar">✏️</button>
                <button type="button" onclick="${onDel}" class="action-btn" title="Eliminar">🗑️</button>
            </td>
        </tr>`;
        body.innerHTML += trBody;
    });
}

function searchEntityRecord() {
    buscarInventario();
}

function clearEntityForm() {
    editingIndex = null;
    inventarioEditandoId = null;
    const fields = ENTITY_SCHEMAS[currentView] || [];
    fields.forEach(f => {
        const input = document.getElementById(`field-${f.name}`);
        if (input) input.value = '';
    });
    if (currentView === 'Inventario') {
        mostrarInventarioVacio();
        return;
    }
    renderEntityTable(currentView);
}


function saveEntityRecord() {
    guardarInventario();
}

function esc(t) {
    return String(t ?? '').replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));
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

async function cargarProductos() {
    try {
        const res = await fetch('api/productos.php');
        if (res.status === 401) { window.location = '/Azucreativo/login/login.php'; return; }
        if (!res.ok) throw new Error('Error ' + res.status);
        const productos = await res.json();

        productosMap = {};
        productos.forEach(p => { productosMap[p.ID_Producto] = p.Nombre; });

        const campo = document.getElementById('field-ID_Producto');
        if (campo) {
            const select = document.createElement('select');
            select.id = 'field-ID_Producto';
            select.className = 'form-input';
            select.innerHTML = '<option value="">-- Selecciona un producto --</option>' +
                productos.map(p => `<option value="${esc(p.ID_Producto)}">${esc(p.Nombre)}</option>`).join('');
            campo.replaceWith(select);
        }
    } catch (e) {
        showToast('No se pudo cargar la lista de productos', 'warning');
        console.error(e);
    }
}

function mostrarInventarioVacio() {
    db.Inventario = [];
    ultimosFiltrosInventario = {};
    renderEntityTable('Inventario', []);

    const body = document.getElementById('entity-table-body');
    const columnas = (ENTITY_SCHEMAS.Inventario || []).length + 1;
    if (body) {
        body.innerHTML = `<tr><td colspan="${columnas}" style="text-align:center; color:#6b7280; padding:1.5rem;">
            Escribe o elige un dato y presiona Buscar para ver registros.</td></tr>`;
    }
}

async function iniciarInventario() {
    mostrarInventarioVacio();
    await cargarProductos();
}

async function cargarInventario(filtros = {}, silencioso = false) {
    const hayFiltros = Object.keys(filtros).length > 0;
    ultimosFiltrosInventario = filtros;
    try {
        const res = await fetch('api/inventario.php?' + new URLSearchParams(filtros));
        if (res.status === 401) { window.location = '/Azucreativo/login/login.php'; return; }
        if (!res.ok) throw new Error('Error ' + res.status);
        const datos = await res.json();

        // Sin filtros: se guarda la lista completa. Con filtros: solo se muestran los resultados.
        if (!hayFiltros) db.Inventario = datos;

        // Si se consultó un solo registro por ID, mantener al día la copia local
        if (hayFiltros && filtros.id_inventario) {
            db.Inventario = db.Inventario.filter(r => String(r.ID_Inventario) !== String(filtros.id_inventario));
            if (datos.length) db.Inventario.push(datos[0]);
        }

        if (currentView === 'Inventario') {
            renderEntityTable('Inventario', hayFiltros ? datos : null);
        }
        if (hayFiltros && datos.length === 0 && !silencioso) {
            showToast('No se encontraron registros', 'warning');
        }
    } catch (e) {
        showToast('No se pudo cargar el inventario', 'warning');
        console.error(e);
    }
}

function buscarInventario() {
    const filtros = {};
    const idInv    = document.getElementById('field-ID_Inventario')?.value.trim();
    const producto = document.getElementById('field-ID_Producto')?.value.trim();
    const cantidad = document.getElementById('field-Cantidad')?.value.trim();
    const entrada  = document.getElementById('field-Fecha_Entrada')?.value;
    const salida   = document.getElementById('field-Fecha_Salida')?.value;

    if (idInv)    filtros.id_inventario = idInv;
    if (producto) filtros.id_producto = producto;
    if (cantidad) filtros.cantidad = cantidad;
    if (entrada)  filtros.fecha_entrada = entrada;
    if (salida)   filtros.fecha_salida = salida;

    // Sin datos en las barras no se consulta nada (así no se trae todo el inventario)
    if (Object.keys(filtros).length === 0) {
        showToast('Escribe o elige al menos un dato para buscar', 'warning');
        return;
    }

    cargarInventario(filtros);
}

async function guardarInventario() {
    const editando = inventarioEditandoId !== null;

    const registro = {
        ID_Producto:   document.getElementById('field-ID_Producto')?.value.trim(),
        Cantidad:      document.getElementById('field-Cantidad')?.value.trim(),
        Fecha_Entrada: document.getElementById('field-Fecha_Entrada')?.value,
        Fecha_Salida:  document.getElementById('field-Fecha_Salida')?.value
    };

    if (!registro.ID_Producto) {
        showToast('Escribe el ID_Producto', 'warning');
        return;
    }
    if (!registro.Cantidad) {
        showToast('Escribe la cantidad', 'warning');
        return;
    }

    if (editando) registro.ID_Inventario = inventarioEditandoId;

    try {
        const res = await fetch(editando ? 'api/actualizar_inventario.php' : 'api/guardar_inventario.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(registro)
        });
        if (res.status === 401) { window.location = '/Azucreativo/login/login.php'; return; }

        const r = await res.json();
        if (!res.ok) {
            showToast(r.error || 'No se pudo guardar', 'warning');
            console.error(r);
            return;
        }

        showToast(editando ? 'Registro actualizado en la base de datos' : 'Registro guardado en la base de datos', 'success');
        const idMostrar = editando ? inventarioEditandoId : r.id;

        // Vaciar los campos sin volver a pintar toda la lista
        editingIndex = null;
        inventarioEditandoId = null;
        (ENTITY_SCHEMAS.Inventario || []).forEach(f => {
            const input = document.getElementById(`field-${f.name}`);
            if (input) input.value = '';
        });

        // Mostrar solo el registro que se acaba de guardar o actualizar
        await cargarInventario({ id_inventario: idMostrar });
    } catch (e) {
        showToast('No se pudo guardar el registro', 'warning');
        console.error(e);
    }
}

async function editarInventario(id) {
    try {
        const res = await fetch('api/inventario.php?' + new URLSearchParams({ id_inventario: id }));
        if (res.status === 401) { window.location = '/Azucreativo/login/login.php'; return; }
        if (!res.ok) throw new Error('Error ' + res.status);
        const datos = await res.json();

        if (datos.length === 0) {
            showToast('El registro ya no existe', 'warning');
            return;
        }

        const r = datos[0];
        const poner = (campo, valor) => {
            const el = document.getElementById(`field-${campo}`);
            if (el) el.value = valor ?? '';
        };
        poner('ID_Inventario', r.ID_Inventario);
        poner('ID_Producto', r.ID_Producto);
        poner('Cantidad', r.Cantidad);
        poner('Fecha_Entrada', String(r.Fecha_Entrada ?? '').slice(0, 10));
        poner('Fecha_Salida', String(r.Fecha_Salida ?? '').slice(0, 10));

        inventarioEditandoId = Number(r.ID_Inventario);
        document.getElementById('entity-form')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        showToast(`Editando el registro ${r.ID_Inventario}. Modifica y presiona Guardar`, 'info');
    } catch (e) {
        showToast('No se pudo cargar el registro', 'warning');
        console.error(e);
    }
}

async function eliminarInventario(id) {
    if (!confirm('¿Eliminar este registro de la base de datos? Esta acción no se puede deshacer.')) return;

    try {
        const res = await fetch('api/eliminar_inventario.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ID_Inventario: id })
        });
        if (res.status === 401) { window.location = '/Azucreativo/login/login.php'; return; }

        const r = await res.json();
        if (!res.ok) {
            showToast(r.error || 'No se pudo eliminar', 'warning');
            console.error(r);
            return;
        }

        showToast('Registro eliminado de la base de datos', 'success');

        if (inventarioEditandoId === Number(id)) {
            inventarioEditandoId = null;
            (ENTITY_SCHEMAS.Inventario || []).forEach(f => {
                const input = document.getElementById(`field-${f.name}`);
                if (input) input.value = '';
            });
        }

        db.Inventario = db.Inventario.filter(x => String(x.ID_Inventario) !== String(id));
        if (Object.keys(ultimosFiltrosInventario).length > 0) {
            await cargarInventario(ultimosFiltrosInventario, true);
        } else {
            mostrarInventarioVacio();
        }
    } catch (e) {
        showToast('No se pudo eliminar el registro', 'warning');
        console.error(e);
    }
}
