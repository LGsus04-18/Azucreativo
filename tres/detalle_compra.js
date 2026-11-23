const API='api/modulo.php?entity=detalle';
const fields=["ID_DetalleCompra", "ID_Compra", "ID_Producto", "Cantidad", "Precio_Unitario", "Subtotal"];
const pk=fields[0];
const $=id=>document.getElementById(id);
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
let rows=[];
function toast(msg){const c=$('toast-container');c.textContent=msg;setTimeout(()=>{c.textContent=''},4500)}
function renderEntityForm(){
 $('entity-form-fields').innerHTML=fields.map((f,i)=>{
 const type=f==='Correo'?'email':f==='Fecha_Compra'?'date':['Cantidad','Precio_Unitario','Subtotal','Total_Compra'].includes(f)?'number':'text';
 const input=f==='Metodo_Pago'?'<select class="form-input" id="field-'+f+'"><option>Efectivo</option><option>Crédito</option><option>Transferencia</option></select>':'<input class="form-input" id="field-'+f+'" type="'+type+'" '+(type==='number'?'min="0" step="any" ':'')+(i===0?'readonly placeholder="Automático"':'')+'>';
 return '<div class="form-group"><label>'+esc(f)+'</label>'+input+'</div>'}).join('');
 if($('field-Fecha_Compra'))$('field-Fecha_Compra').value=new Date().toLocaleDateString('en-CA');
 if($('field-Subtotal'))$('field-Subtotal').readOnly=true;
 ['Cantidad','Precio_Unitario'].forEach(f=>{if($('field-'+f))$('field-'+f).addEventListener('input',()=>{$('field-Subtotal').value=((+$('field-Cantidad').value||0)*(+$('field-Precio_Unitario').value||0)).toFixed(2)})});
}
function renderEntityTable(){
 $('entity-table-head').innerHTML='<tr>'+fields.map(f=>'<th>'+esc(f)+'</th>').join('')+'<th>ACCIONES</th></tr>';
 $('entity-table-body').innerHTML=rows.map((r,i)=>'<tr>'+fields.map(f=>'<td>'+esc(r[f])+'</td>').join('')+'<td><button type="button" class="btn btn-blue" data-edit="'+i+'">Editar</button></td></tr>').join('');
 $('record-count').textContent=rows.length+' registros';
 $('entity-table-body').querySelectorAll('[data-edit]').forEach(b=>b.addEventListener('click',()=>{const r=rows[+b.dataset.edit];fields.forEach(f=>$('field-'+f).value=r[f]??'');window.scrollTo({top:0,behavior:'smooth'})}));
}
async function request(url,options){const res=await fetch(url,options);const data=await res.json();if(!res.ok)throw Error(data.error||'Error del servidor');return data}
async function load(filters={}){try{const q=new URLSearchParams(filters);rows=await request(API+'&'+q);renderEntityTable()}catch(e){toast(e.message)}}
async function searchEntityRecord(){const q={};fields.forEach(f=>{const v=$('field-'+f).value.trim();if(v&&f!=='Subtotal')q[f]=v});await load(q)}
function clearEntityForm(){renderEntityForm();load()}
async function saveEntityRecord(){try{const data={};fields.forEach(f=>data[f]=$('field-'+f).value.trim());await request(API,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});toast('Registro guardado en MySQL');clearEntityForm()}catch(e){toast(e.message)}}
window.addEventListener('DOMContentLoaded',()=>{renderEntityForm();load()});
