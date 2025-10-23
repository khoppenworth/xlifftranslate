(function(){
  const btnAll = document.getElementById('autoTranslate');
  const sourceInput = document.getElementById('sourceLang');
  const targetInput = document.getElementById('targetLang');
  const table = document.getElementById('xliffTable');

  function qsa(sel, root=document){ return Array.from(root.querySelectorAll(sel)); }
  function getCsrf(){ const i = document.querySelector('input[name="csrf"]'); return i ? i.value : ''; }

  async function sendTranslate(texts) {
    const res = await fetch('translate.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({
        texts, source: (sourceInput?.value.trim()) || '',
        target: (targetInput?.value.trim()) || '',
        csrf: getCsrf(),
      }),
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Translate failed');
    return data;
  }

  if (btnAll) {
    btnAll.addEventListener('click', async function(){
      const target = (targetInput?.value.trim()) || '';
      if (!target) { alert('Please set a target language, e.g., fr or fr-FR'); return; }
      const areas = qsa('tbody textarea.tgt');
      const sources = qsa('tbody td.src');
      const texts = sources.map(td => td.innerHTML);

      btnAll.disabled = true;
      const old = btnAll.textContent;
      btnAll.textContent = 'Translating...';
      try {
        const data = await sendTranslate(texts);
        (data.translations || []).forEach((t, i) => { if (areas[i]) areas[i].value = t; });
        alert('Auto-translate complete.');
      } catch (e) { alert('Error: ' + e.message); }
      finally { btnAll.disabled = false; btnAll.textContent = old; }
    });
  }

  // Per-row translate
  qsa('.btn-row').forEach(b => {
    b.addEventListener('click', async () => {
      const row = b.closest('tr');
      const target = (targetInput?.value.trim()) || '';
      if (!target) { alert('Set target language first'); return; }
      const srcCell = row.querySelector('td.src');
      const tgtArea = row.querySelector('textarea.tgt');
      if (!srcCell || !tgtArea) return;
      const src = srcCell.innerHTML;
      b.disabled = true; const old = b.textContent; b.textContent = '…';
      try {
        const data = await sendTranslate([src]);
        tgtArea.value = (data.translations && data.translations[0]) || '';
      } catch (e) { alert('Row translate error: ' + e.message); }
      finally { b.disabled = false; b.textContent = old; }
    });
  });

  // Bulk selection via checkboxes
  const selAll = document.getElementById('selAll');
  if (selAll) selAll.addEventListener('change', () => {
    qsa('.rowSel').forEach(cb => { cb.checked = selAll.checked; });
  });

  const btnSelectAll = document.getElementById('selectAll');
  if (btnSelectAll) btnSelectAll.addEventListener('click', () => {
    qsa('.rowSel').forEach(cb => cb.checked = true);
    if (selAll) selAll.checked = true;
  });
  const btnClearSel = document.getElementById('clearSel');
  if (btnClearSel) btnClearSel.addEventListener('click', () => {
    qsa('.rowSel').forEach(cb => cb.checked = false);
    if (selAll) selAll.checked = false;
  });

  function getSelectedRows(){
    const rows = [];
    qsa('tbody tr').forEach(tr => {
      const cb = tr.querySelector('.rowSel');
      if (cb && cb.checked) rows.push(tr);
    });
    return rows;
  }

  // Copy helpers: source (text content) or target (textarea value)
  function rowsToSourceLines(rows){
    return rows.map(r => r.querySelector('td.src')?.innerText ?? '');
  }
  function rowsToTargetLines(rows){
    return rows.map(r => r.querySelector('textarea.tgt')?.value ?? '');
  }

  async function copyToClipboard(text){
    try {
      await navigator.clipboard.writeText(text);
      return true;
    } catch (e) {
      // Fallback: prompt user
      const ok = window.prompt('Copy the text below (Ctrl/Cmd + C), then click OK:', text);
      return !!ok;
    }
  }

  async function readFromClipboard(){
    try {
      return await navigator.clipboard.readText();
    } catch (e) {
      const t = window.prompt('Paste your lines here, then click OK:');
      return t || '';
    }
  }

  const btnCopySrc = document.getElementById('copySrc');
  if (btnCopySrc) btnCopySrc.addEventListener('click', async () => {
    const rows = getSelectedRows();
    if (!rows.length) { alert('Select one or more rows first.'); return; }
    const lines = rowsToSourceLines(rows).join('\n');
    const ok = await copyToClipboard(lines);
    if (ok) alert('Source copied to clipboard.');
  });

  const btnCopyTgt = document.getElementById('copyTgt');
  if (btnCopyTgt) btnCopyTgt.addEventListener('click', async () => {
    const rows = getSelectedRows();
    if (!rows.length) { alert('Select one or more rows first.'); return; }
    const lines = rowsToTargetLines(rows).join('\n');
    const ok = await copyToClipboard(lines);
    if (ok) alert('Target copied to clipboard.');
  });

  const btnPasteTgt = document.getElementById('pasteTgt');
  if (btnPasteTgt) btnPasteTgt.addEventListener('click', async () => {
    const rows = getSelectedRows();
    if (!rows.length) { alert('Select one or more rows first.'); return; }
    const text = await readFromClipboard();
    if (!text) return;
    const lines = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n');
    if (lines.length === 1 && rows.length > 1) {
      // Fill the same value down
      rows.forEach(r => { const ta = r.querySelector('textarea.tgt'); if (ta) ta.value = lines[0]; });
    } else {
      rows.forEach((r, i) => {
        const ta = r.querySelector('textarea.tgt');
        if (!ta) return;
        ta.value = lines[i] !== undefined ? lines[i] : '';
      });
      if (lines.length !== rows.length) {
        alert(`Pasted ${lines.length} line(s) over ${rows.length} row(s). Extra rows were left blank.`);
      }
    }
  });

})();