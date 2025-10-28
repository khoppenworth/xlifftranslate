(function(){
  const D=document,$=s=>D.querySelector(s),$$=s=>Array.from(D.querySelectorAll(s));
  const csrf = ()=> ($('input[name="csrf"]')?.value || '');
  const srcLang = ()=> ($('#sourceLang')?.value.trim() || '');
  const trgLang = ()=> ($('#targetLang')?.value.trim() || '');
  const provider = ()=> ($('#provider')?.value || '');
  const snack = (msg)=>{ const el=$('#snackbar'); if(!el) return; el.textContent=msg; el.classList.add('show'); setTimeout(()=>el.classList.remove('show'), 2200); };
  const overlay = { show(){ $('#overlay')?.classList.add('show'); }, hide(){ $('#overlay')?.classList.remove('show'); } };

  function visibleRows(){ return $$('tbody tr').filter(tr => tr.offsetParent !== null); }
  function rowToSrcHTML(tr){ const td=tr.querySelector('td.src'); return td?td.innerHTML:''; }
  function rowToSrcText(tr){ const td=tr.querySelector('td.src'); return td?td.textContent:''; }
  function rowToArea(tr){ return tr.querySelector('textarea.tgt'); }
  function rowToId(tr){ return tr.querySelector('.idcell')?.textContent.trim() || ''; }
  function getSelectedRows(){ return $$('tbody tr').filter(tr => tr.querySelector('.rowSel')?.checked); }

  async function callTranslate(texts, ids){
    const res = await fetch('translate.php',{method:'POST',headers:{'Content-Type':'application/json'},
      body: JSON.stringify({texts, ids, source:srcLang(), target:trgLang(), provider:provider(), csrf:csrf()})});
    let data; try{ data = await res.json(); }catch(e){ throw new Error('Bad JSON from server'); }
    if(!res.ok){ throw new Error(data?.error || `HTTP ${res.status}`); } return data;
  }

  async function translateRows(rows){
    if(!trgLang()){ snack('Set target language first'); return; }
    const ids = rows.map(rowToId);
    const texts = rows.map(r=>{ let html=rowToSrcHTML(r)||''; if(html.replace(/\s|<[^>]*>/g,'').length===0) html=rowToSrcText(r)||''; return html; });
    const areas = rows.map(rowToArea);
    if(texts.length!==areas.length){ snack('Row mapping mismatch; abort'); return; }
    if(texts.every(t=> (t||'').toString().trim()==='')){ snack('No source text'); return; }
    overlay.show();
    try{
      const { translations } = await callTranslate(texts, ids);
      if(!Array.isArray(translations) || translations.length!==areas.length){ snack('Server count mismatch'); return; }
      let updated=0; translations.forEach((val,i)=>{ if(areas[i]){ areas[i].value = (typeof val==='string')?val:''; if((areas[i].value||'').trim()!=='') updated++; }});
      snack(`Translated ${updated}/${areas.length}`);
    }catch(e){ snack('Error: '+e.message); }
    finally{ overlay.hide(); }
  }

  // Wire buttons already present in v6.0+
  $('#btnTranslateAll')?.addEventListener('click', ()=> translateRows(visibleRows()));
  $('#btnTranslateSel')?.addEventListener('click', ()=> {
    const sel=getSelectedRows(); if(sel.length===0){ snack('No rows selected'); return; } translateRows(sel);
  });
  D.addEventListener('click', (e)=>{ const btn=e.target.closest?.('.btn-row'); if(!btn) return; const tr=btn.closest('tr'); if(!tr) return; translateRows([tr]); });

})();