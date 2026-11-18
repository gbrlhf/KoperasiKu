/* Progressive enhancement; does not authorize roles or submit transactions. */
(()=>{
 const configure=()=>{
  document.querySelectorAll('.table-responsive,.tw').forEach(el=>{el.tabIndex=0;el.setAttribute('role','region');el.setAttribute('aria-label','Tabel data, geser ke samping untuk kolom lainnya');});
  document.querySelectorAll('nav a[onclick]:not([href])').forEach(el=>{el.setAttribute('role','button');el.tabIndex=0;});
  document.querySelectorAll('nav a.on').forEach(el=>el.setAttribute('aria-current','page'));
  document.querySelectorAll('nav a:not(.on)').forEach(el=>el.removeAttribute('aria-current'));
  document.querySelectorAll('.tabs [role="tab"]').forEach(el=>el.setAttribute('aria-selected',el.classList.contains('on')?'true':'false'));
 };
 configure();const pg=document.getElementById('pg');if(pg)new MutationObserver(configure).observe(pg,{childList:true});
 let returnFocus=null;
 document.addEventListener('click',e=>{if(e.target.closest('[onclick*="M("],[onclick*="prc("],[onclick*="inst("]'))returnFocus=e.target.closest('button,a')||e.target;});
 document.addEventListener('keydown',e=>{
  if((e.key==='Enter'||e.key===' ')&&e.target.matches('nav a[role="button"]')){e.preventDefault();e.target.click();}
  const modal=document.querySelector('.manager-v2 .md.on');if(!modal)return;
  if(e.key==='Escape'){modal.classList.remove('on');returnFocus?.focus();}
  if(e.key==='Tab'){const targets=[...modal.querySelectorAll('button,input,select,textarea,a[href]')].filter(x=>!x.disabled);if(!targets.length)return;const first=targets[0],last=targets.at(-1);if(!modal.contains(document.activeElement)||(!e.shiftKey&&document.activeElement===last)){e.preventDefault();first.focus();}else if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus();}}
 });
 const search=document.querySelector('.manager-v2 .sr');
 if(search){search.placeholder='Cari pada tabel halaman ini…';search.setAttribute('aria-label','Cari pada tabel halaman ini');search.addEventListener('input',()=>{const q=search.value.trim().toLocaleLowerCase('id');let count=0;const rows=document.querySelectorAll('#pg tbody tr');rows.forEach(row=>{row.hidden=!row.textContent.toLocaleLowerCase('id').includes(q);if(!row.hidden)count++;});let msg=document.getElementById('ui-search-message');if(!msg){msg=document.createElement('div');msg.id='ui-search-message';msg.className='ui-search-empty';msg.setAttribute('role','status');pg.prepend(msg);}msg.hidden=!q;msg.textContent=rows.length?(count+' baris sesuai pencarian.'):'Halaman ini tidak memiliki tabel. Buka Transaksi, Piutang, atau Data untuk mencari.';});window.addEventListener('hashchange',()=>{search.value='';});}
})();

