/* Menú accesible en pantallas pequeñas + desplazamiento horizontal de tablas extensas. */
(function(){'use strict';
function init(){
 var sidebar=document.querySelector('.app-container > .sidebar');
 if(sidebar&&!document.querySelector('.azu-mobile-open')){
  var open=document.createElement('button');open.type='button';open.className='azu-mobile-open';open.textContent='☰ Menú';open.setAttribute('aria-label','Abrir menú de navegación');open.setAttribute('aria-expanded','false');
  var overlay=document.createElement('div');overlay.className='azu-mobile-backdrop';overlay.setAttribute('aria-hidden','true');
  var oldToggle=sidebar.querySelector('.azu-menu-toggle');
  function setOpen(v){document.body.classList.toggle('azu-mobile-menu-open',v);open.setAttribute('aria-expanded',String(v));if(v){sidebar.classList.remove('azu-collapsed');}if(oldToggle){oldToggle.setAttribute('aria-expanded',String(v));}if(v){(oldToggle||sidebar.querySelector('a')||sidebar).focus?.();}else{open.focus();}}
  open.addEventListener('click',function(){setOpen(true)});overlay.addEventListener('click',function(){setOpen(false)});
  if(oldToggle)oldToggle.addEventListener('click',function(ev){if(matchMedia('(max-width:767px)').matches){ev.stopImmediatePropagation();ev.preventDefault();setOpen(false)}},true);
  sidebar.querySelectorAll('a').forEach(function(a){a.addEventListener('click',function(){if(matchMedia('(max-width:767px)').matches)setOpen(false)})});
  document.addEventListener('keydown',function(e){if(e.key==='Escape'&&document.body.classList.contains('azu-mobile-menu-open'))setOpen(false)});
  document.body.insertBefore(open,document.body.firstChild);document.body.appendChild(overlay);
  matchMedia('(min-width:768px)').addEventListener('change',function(e){if(e.matches){document.body.classList.remove('azu-mobile-menu-open');open.setAttribute('aria-expanded','false')}});
 }
 function wrapTables(){document.querySelectorAll('table').forEach(function(t){if(t.closest('.azu-table-scroll')||t.closest('.dataTables_scroll')||t.closest('.table-responsive'))return;var w=document.createElement('div');w.className='azu-table-scroll';w.setAttribute('role','region');w.setAttribute('aria-label','Tabla desplazable horizontalmente');w.setAttribute('tabindex','0');t.parentNode.insertBefore(w,t);w.appendChild(t)})}
 wrapTables();
 if(document.querySelector('.app-container')){var observer=new MutationObserver(function(){wrapTables()});observer.observe(document.querySelector('.app-container'),{childList:true,subtree:true})}
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
