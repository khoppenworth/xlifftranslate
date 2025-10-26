(function(){
  const D=document,$=s=>D.querySelector(s),$$=s=>Array.from(D.querySelectorAll(s));
  const csrf = ()=> ($('input[name="csrf"]')?.value || '');
  const srcLang = ()=> ($('#sourceLang')?.value.trim() || '');
  const trgLang = ()=> ($('#targetLang')?.value.trim() || '');
  const provider = ()=> ($('#provider')?.value || '');
  const snack = (msg)=>{ const el=$('#snackbar'); if(!el) return; el.textContent=msg; el.classList.add('show'); setTimeout(()=>el.classList.remove('show'), 2200); };
  const overlay = { show(){ $('#overlay')?.classList.add('show'); }, hide(){ $('#overlay')?.classList.remove('show'); } };

  const quick = D.createElement('div');
  quick.className='stack'; quick.innerHTML = `
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
  const targetInput = $('#targetLang');
  if (targetInput && !$('#langQuick')) targetInput.closest('.stack')?.appendChild(quick);
  D.addEventListener('change', (e)=>{
    if (e.target && e.target.id==='langQuick'){
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
    }
  });

  $('#selAll')?.addEventListener('change', e=> $$('.rowSel').forEach(cb=>cb.checked=e.target.checked));

  const kbar = $('.kbar .left');
  if (kbar && !$('#btnSelectAll')) {
    const sel = D.createElement('button'); sel.id='btnSelectAll'; sel.className='btn'; sel.textContent='Select all';
    const clr = D.createElement('button'); clr.id='btnClearSel'; clr.className='btn'; clr.textContent='Clear';
    kbar.appendChild(sel); kbar.appendChild(clr);
    sel.addEventListener('click', ()=> $$('.rowSel').forEach(cb=> cb.checked=true));
    clr.addEventListener('click', ()=> $$('.rowSel').forEach(cb=> cb.checked=false));
  }

  function visibleRows(){ return $$('tbody tr').filter(tr => tr.offsetParent !== null); }
  function rowToSrcHTML(tr){ const td=tr.querySelector('td.src'); return td?td.innerHTML:''; }
  function rowToSrcText(tr){ const td=tr.querySelector('td.src'); return td?td.textContent:''; }
  function rowToArea(tr){ return tr.querySelector('textarea.tgt'); }
  function getSelectedRows(){ return $$('tbody tr').filter(tr => tr.querySelector('.rowSel')?.checked); }

  async function callTranslate(texts){
    const res = await fetch('translate.php',{method:'POST',headers:{'Content-Type':'application/json'},
      body: JSON.stringify({texts,source:srcLang(),target:trgLang(),provider:provider(),csrf:csrf()})});
    let data; try{ data = await res.json(); }catch(e){ throw new Error('Bad JSON from server'); }
    if(!res.ok){ throw new Error(data?.error || `HTTP ${res.status}`); } return data;
  }

  async function translateRows(rows){
    if(!trgLang()){ snack('Set target language first'); return; }
    const texts = rows.map(r=>{ let html=rowToSrcHTML(r)||''; if(html.replace(/\s|<[^>]*>/g,'').length===0) html=rowToSrcText(r)||''; return html; });
    const areas = rows.map(rowToArea);
    if(texts.length!==areas.length){ snack('Row mapping mismatch; abort'); return; }
    if(texts.every(t=> (t||'').toString().trim()==='')){ snack('No source text'); return; }
    overlay.show();
    try{
      const { translations } = await callTranslate(texts);
      if(!Array.isArray(translations) || translations.length!==areas.length){ snack('Server count mismatch'); return; }
      let updated=0; translations.forEach((val,i)=>{ if(areas[i]){ areas[i].value = (typeof val==='string')?val:''; if((areas[i].value||'').trim()!=='') updated++; }});
      snack(`Translated ${updated}/${areas.length}`);
    }catch(e){ snack('Error: '+e.message); }
    finally{ overlay.hide(); }
  }

  $('#btnTranslateAll')?.addEventListener('click', ()=> translateRows(visibleRows()));
  $('#btnTranslateSel')?.addEventListener('click', ()=> {
    const sel=getSelectedRows(); if(sel.length===0){ snack('No rows selected'); return; } translateRows(sel);
  });

  D.addEventListener('click', (e)=>{
    const btn = e.target.closest?.('.btn-row'); if(!btn) return;
    const tr = btn.closest('tr'); if(!tr) return; translateRows([tr]);
  });

  D.addEventListener('keydown', async (ev)=>{
    if (ev.altKey && (ev.key==='t'||ev.key==='T')){ ev.preventDefault();
      const ta = D.activeElement?.closest?.('tr'); const tr = ta || $$('tbody tr')[0]; if(tr) translateRows([tr]); }
    if (ev.altKey && ev.key==='Enter'){ ev.preventDefault();
      const ta = D.activeElement?.closest?.('tr'); const tr = ta || $$('tbody tr')[0]; if(!tr) return;
      await translateRows([tr]); const rows = $$('tbody tr'); const i = rows.indexOf(tr); if(i>=0 && rows[i+1]) rows[i+1].querySelector('textarea.tgt')?.focus(); }
  });

  $('#btnSave')?.addEventListener('click', ()=>{
    const form = D.createElement('form'); form.method='POST'; form.action='index.php';
    const add=(n,v)=>{ const i=D.createElement('input'); i.type='hidden'; i.name=n; i.value=v; form.appendChild(i); };
    add('csrf', csrf()); add('action','save');
    $$('textarea.tgt').forEach(t=>{ const hidden=D.createElement('input'); hidden.type='hidden'; hidden.name = `target[${t.dataset.id}]`; hidden.value=t.value; form.appendChild(hidden); });
    D.body.appendChild(form); form.submit();
  });

  $('#btnExportCSV')?.addEventListener('click', ()=>{
    const ids = getSelectedRows().map(tr => tr.querySelector('.idcell')?.textContent.trim()).filter(Boolean);
    const form = D.createElement('form'); form.method='POST'; form.action='export_csv.php';
    const add=(n,v)=>{ const i=D.createElement('input'); i.type='hidden'; i.name=n; i.value=v; form.appendChild(i); };
    add('csrf', csrf()); ids.forEach(id=> add('ids[]', id));
    $$('textarea.tgt').forEach(t=>{ const hid=D.createElement('input'); hid.type='hidden'; hid.name=`tgt[${t.dataset.id}]`; hid.value=t.value; form.appendChild(hid); });
    D.body.appendChild(form); form.submit(); setTimeout(()=>form.remove(), 1000);
  });

})();