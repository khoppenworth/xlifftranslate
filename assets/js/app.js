(function(){
  const D=document, $=sel=>D.querySelector(sel), $$=sel=>Array.from(D.querySelectorAll(sel));
  const csrf = ()=> ($('input[name="csrf"]')?.value || '');
  const srcLang = ()=> ($('#sourceLang')?.value.trim() || '');
  const trgLang = ()=> ($('#targetLang')?.value.trim() || '');
  const provider = ()=> ($('#provider')?.value || '');
  const tbl = $('#xliffTable');
  const btnAll = $('#autoTranslate');

  function visibleRows() { return $$('tbody tr').filter(tr => tr.style.display !== 'none'); }
  function rowToSrc(tr){ return tr.querySelector('td.src')?.innerHTML || ''; }
  function rowToArea(tr){ return tr.querySelector('textarea.tgt'); }

  async function callTranslate(texts){
    const res = await fetch('translate.php', { method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ texts, source: srcLang(), target: trgLang(), provider: provider(), csrf: csrf() }) });
    let data; try { data = await res.json(); } catch(e){ throw new Error('Bad JSON from server'); }
    if (!res.ok) throw new Error(data.error || 'Translate failed');
    return data;
  }

  // Translate ALL: only source cells; guard counts; skip empties server-side too.
  if (btnAll && !btnAll._bound) {
    btnAll._bound = true;
    btnAll.addEventListener('click', async ()=>{
      if (!trgLang()) { alert('Set a target language first.'); return; }
      const rows = visibleRows();
      const texts = rows.map(rowToSrc);
      const areas = rows.map(rowToArea);
      if (texts.length !== areas.length) { alert('Row mapping issue detected; aborting.'); return; }
      const old=btnAll.textContent; btnAll.disabled=true; btnAll.textContent='Translating…';
      try {
        const data = await callTranslate(texts);
        const out = data.translations || [];
        if (out.length !== areas.length) { alert('Server returned a mismatched count; nothing written.'); return; }
        out.forEach((t,i)=>{ if (areas[i]) areas[i].value = t; });
        alert('Translate complete.');
      } catch(e){ alert('Error: '+e.message); }
      finally { btnAll.disabled=false; btnAll.textContent=old; }
    });
  }

  // Per-row translate (Alt+T) using only source
  D.addEventListener('keydown', async (ev)=>{
    if (ev.altKey && (ev.key==='t' || ev.key==='T')) {
      const active = D.activeElement;
      const tr = active?.closest?.('tr') || $$('tbody tr')[0];
      if (!tr) return;
      ev.preventDefault();
      const src = rowToSrc(tr);
      try {
        const { translations } = await callTranslate([src]);
        const area = rowToArea(tr); if (area) area.value = (translations?.[0] || '');
      } catch(e){ alert('Error: '+e.message); }
    }
    if (ev.altKey && ev.key === 'Enter') {
      ev.preventDefault();
      const area = D.activeElement?.closest?.('tr')?.querySelector('textarea.tgt');
      if (!area) return;
      const tr = area.closest('tr'); const src = rowToSrc(tr);
      try {
        const { translations } = await callTranslate([src]);
        area.value = (translations?.[0] || '');
        // focus next row
        const rows = $$('tbody tr'); const idx = rows.indexOf(tr);
        if (idx>=0 && rows[idx+1]) rows[idx+1].querySelector('textarea.tgt')?.focus();
      } catch(e){ alert('Error: '+e.message); }
    }
  });

  // Export CSV: POST selected IDs; if none, export all
  const exportBtn = $('#exportCSV');
  if (exportBtn && !exportBtn._bound) {
    exportBtn._bound = true;
    exportBtn.addEventListener('click', ()=>{
      const ids = $$('tbody tr').filter(tr => tr.querySelector('.rowSel')?.checked).map(tr => tr.querySelector('.idcell')?.textContent.trim());
      const form = D.createElement('form'); form.method='POST'; form.action='export_csv.php';
      const add=(n,v)=>{ const i=D.createElement('input'); i.type='hidden'; i.name=n; i.value=v; form.appendChild(i); };
      add('csrf', csrf()); ids.forEach(id=> add('ids[]', id)); D.body.appendChild(form); form.submit(); form.remove();
    });
  }

  // Import CSV → import_csv.php
  const importInput = $('#importCSV');
  if (importInput && !importInput._bound) {
    importInput._bound = true;
    importInput.addEventListener('change', async ()=>{
      if (!importInput.files?.[0]) return;
      const fd = new FormData(); fd.append('csrf', csrf()); fd.append('csv', importInput.files[0]);
      importInput.disabled = true;
      try {
        const res = await fetch('import_csv.php', { method:'POST', body: fd });
        const j = await res.json();
        alert(j.ok ? `Updated: ${j.updated}, Skipped: ${j.skipped}` : (j.error || 'Import failed'));
        if (j.ok) location.reload();
      } catch(e){ alert('Error: '+e.message); }
      finally { importInput.disabled=false; importInput.value=''; }
    });
  }

  // Selection helpers
  $('#selAll')?.addEventListener('change', (e)=> { const on = e.target.checked; $$('.rowSel').forEach(cb=>cb.checked=on); });
  $('#clearSel')?.addEventListener('click', ()=> $$('.rowSel').forEach(cb=>cb.checked=false));
  $('#selectAll')?.addEventListener('click', ()=> $$('.rowSel').forEach(cb=>cb.checked=true));

  // Theme
  $('#themeToggle')?.addEventListener('click', ()=> D.body.classList.toggle('dark'));

})();