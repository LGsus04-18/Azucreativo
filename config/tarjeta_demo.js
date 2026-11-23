"use strict";
// Pago de simulación: el número completo nunca se envía al servidor ni se guarda.
const numerosEnMemoria = new WeakMap();
const digitos = valor => String(valor ?? '').replace(/\D/g, '').slice(0, 16);
function dibujarNumero(campo) {
  const numero = numerosEnMemoria.get(campo) || '';
  const mascara = '•'.repeat(Math.max(0, numero.length - 4)) + numero.slice(-4);
  campo.value = mascara.replace(/(.{4})(?=.)/g, '$1 ');
  try { campo.setSelectionRange(campo.value.length, campo.value.length); } catch (_) {}
}
function iniciarNumero(campo) {
  numerosEnMemoria.set(campo, '');
  // Una sola vía de entrada por teclado: evita que keydown + beforeinput dupliquen cifras.
  campo.addEventListener('keydown', e => {
    if (e.ctrlKey || e.metaKey || e.altKey || ['Tab','Enter','Escape','ArrowLeft','ArrowRight','Home','End'].includes(e.key)) return;
    if (!/^[0-9]$/.test(e.key) && !['Backspace','Delete'].includes(e.key)) {
      if(e.key.length===1)e.preventDefault();return;
    }
    e.preventDefault();
    const anterior = numerosEnMemoria.get(campo) || '';
    if (e.key === 'Backspace' || e.key === 'Delete') numerosEnMemoria.set(campo, anterior.slice(0,-1));
    else { numerosEnMemoria.set(campo, (anterior + e.key).slice(0,16)); campo.dataset.ultimoTeclado=e.key; }
    dibujarNumero(campo);
  });
  campo.addEventListener('paste', e => {
    e.preventDefault();
    numerosEnMemoria.set(campo, digitos(e.clipboardData?.getData('text') || ''));
    dibujarNumero(campo);
  });
  // Teclados móviles: beforeinput sin procesar de nuevo las teclas físicas.
  campo.addEventListener('beforeinput', e => {
    // Physical keyboards are handled in keydown; do not process twice.
    if (e.inputType === 'insertText' && e.isComposing) return;
    if (e.inputType === 'insertText' && e.data && campo.dataset.ultimoTeclado === e.data) { campo.dataset.ultimoTeclado=''; e.preventDefault(); return; }
    if (!e.cancelable || e.inputType === 'insertFromPaste') return;
    if (e.inputType === 'insertText' && e.data && /^[0-9]+$/.test(e.data)) {
      e.preventDefault();
      numerosEnMemoria.set(campo, ((numerosEnMemoria.get(campo)||'') + digitos(e.data)).slice(0,16));
      dibujarNumero(campo);
    } else if (e.inputType.startsWith('delete')) {
      e.preventDefault();
      numerosEnMemoria.set(campo, (numerosEnMemoria.get(campo)||'').slice(0,-1));
      dibujarNumero(campo);
    }
  });
  campo.addEventListener('input', () => {
    // Respaldo para pegado/autorrelleno que omita los eventos anteriores.
    if (!campo.value.includes('•')) numerosEnMemoria.set(campo, digitos(campo.value));
    dibujarNumero(campo);
  });
  campo.addEventListener('copy', e => e.preventDefault());
  campo.addEventListener('cut', e => e.preventDefault());
  campo.setAttribute('autocomplete','off');
  campo.setAttribute('aria-label','Número de tarjeta, solo se muestran los últimos cuatro dígitos');
  dibujarNumero(campo);
}
window.demoTarjeta = {
  actualizar() {
    const metodo=document.getElementById('metodo')||document.getElementById('cart-payment');
    const caja=document.getElementById('demo-card-fields');
    if(caja&&metodo)caja.hidden=metodo.value!=='Tarjeta';
  },
  ultimos4() {
    const campo=document.getElementById('demo-card-number');
    let numero=campo ? numerosEnMemoria.get(campo)||'' : '';
    // Restore the visible trailing digits if a browser has reset the field.
    if (campo && !numero && /^[0-9 ]{16,23}$/.test(campo.value)) numero=digitos(campo.value);
    const nombre=(document.getElementById('demo-card-name')?.value||'').trim();
    const vencimiento=(document.getElementById('demo-card-exp')?.value||'').trim();
    const cvv=(document.getElementById('demo-card-cvv')?.value||'').trim();
    if(numero.length!==16) throw Error('El número de tarjeta debe contener 16 dígitos.');
    if(!nombre) throw Error('Ingresa el nombre de la tarjeta.');
    if(!/^(0[1-9]|1[0-2])\/\d{2}$/.test(vencimiento)) throw Error('Ingresa el vencimiento en formato MM/AA (por ejemplo, 12/30).');
    if(!/^[0-9]{3,4}$/.test(cvv)) throw Error('Ingresa un código de seguridad de 3 o 4 dígitos.');
    return numero.slice(-4);
  },
  limpiar() {
    for(const id of ['demo-card-number','demo-card-name','demo-card-exp','demo-card-cvv']){
      const campo=document.getElementById(id);if(campo)campo.value='';
    }
    const numero=document.getElementById('demo-card-number');
    if(numero){numerosEnMemoria.set(numero,'');dibujarNumero(numero);}
  }
};
document.addEventListener('DOMContentLoaded',()=>{
  const numero=document.getElementById('demo-card-number');if(numero)iniciarNumero(numero);
  const metodo=document.getElementById('metodo')||document.getElementById('cart-payment');
  if(metodo){metodo.addEventListener('change',window.demoTarjeta.actualizar);window.demoTarjeta.actualizar();}
});
