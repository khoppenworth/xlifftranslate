(function(){
  const D=document,$=s=>D.querySelector(s),$$=s=>Array.from(D.querySelectorAll(s));
  const csrf = ()=> ($('input[name="csrf"]')?.value || '');
  const srcLang = ()=> ($('#sourceLang')?.value.trim() || '');
  const trgLang = ()=> ($('#targetLang')?.value.trim() || '');
  const provider = ()=> ($('#provider')?.value || '');
  const snack = (msg)=>{ const el=$('#snackbar'); if(!el) return; el.textContent=msg; el.classList.add('show'); setTimeout(()=>el.classList.remove('show'), 2600); };
  const overlay = { show(){ $('#overlay')?.classList.add('show'); }, hide(){ $('#overlay')?.classList.remove('show'); } };

  function stripXliffInline(html){ return (html||'').toString().replace(/<\/?g\b[^>]*>/gi,'').replace(/<x\b[^>]*\/>/gi,''); }
  function visibleRows(){ return $$('tbody tr').filter(tr => tr.offsetParent !== null); }
  function rowToArea(tr){ return tr.querySelector('textarea.tgt'); }
  function rowToId(tr){ return tr.querySelector('.idcell')?.textContent.trim() || ''; }
  function getSelectedRows(){ return $$('tbody tr').filter(tr => tr.querySelector('.rowSel')?.checked); }

  // Quick target dropdown (kept)
  (function ensureQuickTarget(){
    const targetInput = $('#targetLang');
    if (!targetInput || $('#langQuick')) return;
    const wrap = D.createElement('div');
    wrap.className = 'stack';
    wrap.innerHTML = `
      <label class="field"><span>Quick target</span>
        <select id="langQuick">
          <option value="">Choose…</option>
          <optgroup label="Common">
            <option value="fr">French (fr)</option>
            <option value="fr-FR">French (fr-FR)</option>
            <option value="es">Spanish (es)</option>
            <option value="es-ES">Spanish (es-ES)</option>
            <option value="en">English (en)</option>
            <option value="en-US">English (en-US)</option>
            <option value="en-GB">English (en-GB)</option>
            <option value="pt-BR">Portuguese (pt-BR)</option>
          </optgroup>
        </select>
      </label>`;
    targetInput.closest('.stack')?.appendChild(wrap);
    D.addEventListener('change', (e)=>{
      if (e.target?.id !== 'langQuick') return;
      const v = e.target.value || '';
      const prov = provider();
      if (prov==='libre' || prov==='google' || prov==='mymemory'){
        $('#targetLang').value = v ? v.split('-')[0].toLowerCase() : '';
      } else if (prov==='deepl'){
        let up = v.toUpperCase();
        if (['EN-GB','EN-US','PT-BR'].includes(up)) $('#targetLang').value = up;
        else $('#targetLang').value = up.substring(0,2);
      } else {
        const parts = v.split('-');
        $('#targetLang').value = parts[0].toLowerCase() + (parts[1] ? ('-'+parts[1].toUpperCase()) : '');
      }
      snack('Target set to ' + $('#targetLang').value);
    });
  })();

  $('#selAll')?.addEventListener('change', e=> $$('.rowSel').forEach(cb=>cb.checked=e.target.checked));
  (function ensureSelectClear(){
    const kbarL = $('.kbar .left');
    if (!kbarL) return;
    if (!$('#btnSelectAll')){
      const sel = D.createElement('button'); sel.id='btnSelectAll'; sel.className='btn'; sel.textContent='Select all';
      const clr = D.createElement('button'); clr.id='btnClearSel'; clr.className='btn'; clr.textContent='Clear';
      kbarL.appendChild(sel); kbarL.appendChild(clr);
      sel.addEventListener('click', ()=> $$('.rowSel').forEach(cb=> cb.checked=true));
      clr.addEventListener('click', ()=> $$('.rowSel').forEach(cb=> cb.checked=false));
    }
  })();

  async function parseJSONorText(res){
    try { return await res.json(); }
    catch(_){
      const t = await res.text();
      throw new Error(t?.slice(0,300) || 'Bad JSON from server');
    }
  }

  async function callTranslate(ids){
    const res = await fetch('translate.php',{method:'POST',headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ids, source:srcLang(), target:trgLang(), provider:provider(), csrf:csrf()})});
    const data = await parseJSONorText(res);
    if(!res.ok){ throw new Error(data?.error || ('HTTP '+res.status)); } return data;
  }
  async function saveTargets(changes){
    const res = await fetch('save_targets.php',{method:'POST',headers:{'Content-Type':'application/json'},
      body: JSON.stringify({csrf:csrf(), targets:changes})});
    const data = await parseJSONorText(res);
    if(!res.ok || data?.ok!==true) throw new Error(data?.error || ('HTTP '+res.status)); return data;
  }

  async function translateRows(rows){
    if(!trgLang()){ snack('Set target language first'); return; }
    const ids = rows.map(rowToId);
    const areas = rows.map(rowToArea);
    if(ids.length!==areas.length){ snack('Row mapping mismatch; abort'); return; }
    if(ids.length===0){ snack('Nothing selected'); return; }
    document.activeElement && document.activeElement.blur?.();
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

  $('#btnSave')?.addEventListener('click', async ()=>{
    const changes={}; $$('textarea.tgt').forEach(t=> changes[t.dataset.id]=t.value);
    try{ overlay.show(); await saveTargets(changes); snack('Saved'); }
    catch(err){ snack('Save failed: '+(err.message||err)); }
    finally{ overlay.hide(); }
  });

  $('#btnExportCSV')?.addEventListener('click', ()=>{
    const ids = getSelectedRows().map(tr => tr.querySelector('.idcell')?.textContent.trim()).filter(Boolean);
    const form = D.createElement('form'); form.method='POST'; form.action='export_csv.php';
    const add=(n,v)=>{ const i=D.createElement('input'); i.type='hidden'; i.name=n; i.value=v; form.appendChild(i); };
    add('csrf', csrf()); ids.forEach(id=> add('ids[]', id));
    $$('textarea.tgt').forEach(t=>{ const hid=D.createElement('input'); hid.type='hidden'; hid.name=`tgt[${t.dataset.id}]`; hid.value=t.value; form.appendChild(hid); });
    D.body.appendChild(form); form.submit(); setTimeout(()=>form.remove(), 1000);
  });

  D.addEventListener('click', async (e)=>{
    const a = e.target.closest?.('a[href^="export.php"]'); if(!a) return;
    e.preventDefault();
    const changes={}; $$('textarea.tgt').forEach(t=> changes[t.dataset.id]=t.value);
    try{ overlay.show(); await saveTargets(changes); window.location.href = a.href; }
    catch(err){ snack('Save before export failed: '+(err.message||err)); }
    finally{ overlay.hide(); }
  });

})();