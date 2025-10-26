(function(){
  const D=document,$=s=>D.querySelector(s),$$=s=>Array.from(D.querySelectorAll(s));
  const csrf = ()=> ($('input[name="csrf"]')?.value || '');
  const srcLang = ()=> ($('#sourceLang')?.value.trim() || '');
  const trgLang = ()=> ($('#targetLang')?.value.trim() || '');
  const provider = ()=> ($('#provider')?.value || '');
  const snack = (msg)=>{ const el=$('#snackbar'); if(!el) return; el.textContent=msg; el.classList.add('show'); setTimeout(()=>el.classList.remove('show'), 2200); };
  const overlay = { show(){ $('#overlay')?.classList.add('show'); }, hide(){ $('#overlay')?.classList.remove('show'); } };

  function stripXliffInline(html){ return (html||'').toString().replace(/<\/?g\b[^>]*>/gi,'').replace(/<x\b[^>]*\/>/gi,''); }
  function visibleRows(){ return $$('tbody tr').filter(tr => tr.offsetParent !== null); }
  function rowToArea(tr){ return tr.querySelector('textarea.tgt'); }
  function rowToId(tr){ return tr.querySelector('.idcell')?.textContent.trim() || ''; }
  function getSelectedRows(){ return $$('tbody tr').filter(tr => tr.querySelector('.rowSel')?.checked); }

  async function callTranslate(ids){
    const res = await fetch('translate.php',{method:'POST',headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ids, source:srcLang(), target:trgLang(), provider:provider(), csrf:csrf()})});
    let data; try{ data = await res.json(); }catch(e){ throw new Error('Bad JSON from server'); }
    if(!res.ok){ throw new Error(data?.error || `HTTP ${res.status}`); } return data;
  }
  async function saveTargets(changes){
    const res = await fetch('save_targets.php',{method:'POST',headers:{'Content-Type':'application/json'},
      body: JSON.stringify({csrf:csrf(), targets:changes})});
    let j; try{ j=await res.json(); }catch(e){ throw new Error('Save failed'); }
    if(!res.ok || j?.ok!==true) throw new Error(j?.error || 'Save error'); return j;
  }

  async function translateRows(rows){
    if(!trgLang()){ snack('Set target language first'); return; }
    const ids = rows.map(rowToId);
    const areas = rows.map(rowToArea);
    if(ids.length!==areas.length){ snack('Row mapping mismatch; abort'); return; }
    overlay.show();
    try{
      const { translations } = await callTranslate(ids);
      if(!Array.isArray(translations) || translations.length!==areas.length){ snack('Server count mismatch'); return; }
      const changes = {};
      translations.forEach((val,i)=>{
        const clean = stripXliffInline(typeof val==='string' ? val : '');
        const ta = areas[i]; if(ta){ ta.value = clean; changes[ta.dataset.id] = clean; }
      });
      if(Object.keys(changes).length) await saveTargets(changes);
      snack(`Translated ${ids.length}/${ids.length}`);
    }catch(e){ snack('Error: '+e.message); }
    finally{ overlay.hide(); }
  }

  $('#btnTranslateAll')?.addEventListener('click', ()=> translateRows(visibleRows()));
  $('#btnTranslateSel')?.addEventListener('click', ()=> {
    const sel=getSelectedRows(); if(sel.length===0){ snack('No rows selected'); return; } translateRows(sel);
  });
  D.addEventListener('click', (e)=>{ const btn=e.target.closest?.('.btn-row'); if(!btn) return; const tr=btn.closest('tr'); if(!tr) return; translateRows([tr]); });

  D.addEventListener('click', async (e)=>{
    const a = e.target.closest?.('a[href^="export.php"]'); if(!a) return;
    e.preventDefault();
    const changes={}; $$('textarea.tgt').forEach(t=> changes[t.dataset.id]=t.value);
    try{ overlay.show(); await saveTargets(changes); window.location.href = a.href; }
    catch(err){ snack('Save before export failed: '+(err.message||err)); }
    finally{ overlay.hide(); }
  });

})();