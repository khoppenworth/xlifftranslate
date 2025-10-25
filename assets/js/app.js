(function(){
  const D = document;
  const q = sel => D.querySelector(sel);
  const qa = sel => Array.from(D.querySelectorAll(sel));
  const byId = id => D.getElementById(id);
  const csrf = () => (q('input[name="csrf"]')?.value || '');

  // Theme toggle
  const themeToggle = byId('themeToggle');
  const savedTheme = localStorage.getItem('theme') || 'auto';
  setTheme(savedTheme);
  if (themeToggle) themeToggle.addEventListener('click', () => {
    const next = (localStorage.getItem('theme')||'auto') === 'dark' ? 'light' : 'dark';
    setTheme(next);
  });
  function setTheme(mode){
    localStorage.setItem('theme', mode);
    D.body.classList.remove('theme-auto','theme-dark','theme-light');
    D.body.classList.add(mode==='dark'?'theme-dark':(mode==='light'?'theme-light':'theme-auto'));
  }

  // Elements
  const sourceInput = byId('sourceLang');
  const targetInput = byId('targetLang');
  const providerSel = byId('provider');
  const table = byId('xliffTable');
  const tbody = table ? table.querySelector('tbody') : null;

  // Filter + Pager (from v4)
  const filterBox = byId('filterBox');
  const pageSizeSel = byId('pageSize');
  const prevPage = byId('prevPage');
  const nextPage = byId('nextPage');
  const pageInfo = byId('pageInfo');
  let rows = tbody ? qa('tr', tbody) : [];
  let filteredIdx = rows.map((_,i)=>i);
  let page = 0;
  function pageSize(){ return parseInt(pageSizeSel?.value || '100',10); }
  function applyFilter(){
    const f = (filterBox?.value || '').toLowerCase().trim();
    filteredIdx = [];
    rows.forEach((tr, i) => {
      const src = tr.querySelector('td.src')?.innerText.toLowerCase() || '';
      const tgt = tr.querySelector('textarea.tgt')?.value.toLowerCase() || '';
      const ok = !f || src.includes(f) || tgt.includes(f);
      tr.dataset.matches = ok ? '1' : '0';
      if (ok) filteredIdx.push(i);
    });
    page = 0; renderPage();
  }
  function renderPage(){
    if (!tbody) return;
    const total = filteredIdx.length;
    const psize = pageSize();
    const pages = Math.max(1, Math.ceil(total/psize));
    page = Math.max(0, Math.min(page, pages-1));
    rows.forEach(tr => tr.style.display = 'none');
    const start = page * psize;
    const end = Math.min(total, start + psize);
    for (let i=start; i<end; i++) rows[ filteredIdx[i] ].style.display = '';
    if (pageInfo) pageInfo.textContent = `Page ${page+1} / ${pages} · ${total} row(s)`;
  }
  if (filterBox) filterBox.addEventListener('input', applyFilter);
  if (pageSizeSel) pageSizeSel.addEventListener('change', renderPage);
  if (prevPage) prevPage.addEventListener('click', () => { page--; renderPage(); });
  if (nextPage) nextPage.addEventListener('click', () => { page++; renderPage(); });
  if (tbody) { applyFilter(); }

  // Clipboard modal fallback (from v4)
  const clipModal = byId('clipModal'), clipArea = byId('clipArea');
  const clipTitle = byId('clipTitle'), clipCopy = byId('clipCopy'), clipPaste = byId('clipPaste'), clipClose = byId('clipClose');
  function openModal(title, text, showPaste=false){
    clipTitle.textContent = title;
    clipArea.value = text || '';
    clipPaste.hidden = !showPaste;
    clipModal.hidden = false;
    clipArea.focus();
    clipArea.select();
  }
  function closeModal(){ clipModal.hidden = true; clipArea.value=''; }
  if (clipClose) clipClose.addEventListener('click', closeModal);
  if (clipCopy) clipCopy.addEventListener('click', async () => { try { await navigator.clipboard.writeText(clipArea.value); alert('Copied.'); } catch(e){} });
  async function copyText(text){ try { await navigator.clipboard.writeText(text); return true; } catch(e){ openModal('Copy', text, false); return false; } }
  async function readText(){ try { return await navigator.clipboard.readText(); } catch(e){ openModal('Paste below, then click \"Use as paste\"', '', true); return new Promise(res => { clipPaste.onclick = () => { const t=clipArea.value; closeModal(); res(t); }; }); } }

  // Selection incl. Shift and Drag
  const selAll = byId('selAll');
  if (selAll) selAll.addEventListener('change', () => qa('.rowSel').forEach(cb => cb.checked = selAll.checked));
  const btnSelectAll = byId('selectAll'), btnClearSel = byId('clearSel');
  if (btnSelectAll) btnSelectAll.addEventListener('click', () => { qa('.rowSel').forEach(cb => cb.checked = true); if (selAll) selAll.checked=true; });
  if (btnClearSel) btnClearSel.addEventListener('click', () => { qa('.rowSel').forEach(cb => cb.checked = false); if (selAll) selAll.checked=false; });

  let lastClickedIndex = null;
  qa('.rowSel').forEach((cb, idx) => {
    cb.addEventListener('click', (e) => {
      if (e.shiftKey && lastClickedIndex !== null) {
        const start = Math.min(lastClickedIndex, idx);
        const end = Math.max(lastClickedIndex, idx);
        const state = cb.checked;
        for (let i=start; i<=end; i++){ qa('.rowSel')[i].checked = state; }
      }
      lastClickedIndex = idx;
    });
  });
  let dragging = false, dragState = null;
  tbody?.addEventListener('mousedown', (e) => {
    const cb = e.target.closest('.rowSel'); if (!cb) return;
    dragging = true; dragState = !cb.checked; cb.checked = dragState; e.preventDefault();
  });
  tbody?.addEventListener('mouseover', (e) => { if (!dragging) return; const cb = e.target.closest('.rowSel'); if (cb) cb.checked = dragState; });
  D.addEventListener('mouseup', () => { dragging=false; dragState=null; });

  function getSelectedRows(){ return qa('tbody tr').filter(tr => (tr.querySelector('.rowSel')?.checked) && tr.style.display !== 'none'); }
  function rowsToSourceLines(rows){ return rows.map(r => r.querySelector('td.src')?.innerText ?? ''); }
  function rowsToTargetLines(rows){ return rows.map(r => r.querySelector('textarea.tgt')?.value ?? ''); }

  // Copy / Paste / Undo
  const btnCopySrc = byId('copySrc'), btnCopyTgt = byId('copyTgt'), btnPasteTgt = byId('pasteTgt'), undoBulk = byId('undoBulk');
  let lastBulkSnapshot = null;
  if (btnCopySrc) btnCopySrc.addEventListener('click', async () => { const rows = getSelectedRows(); if (!rows.length) return alert('Select rows.'); await copyText(rowsToSourceLines(rows).join('\n')); });
  if (btnCopyTgt) btnCopyTgt.addEventListener('click', async () => { const rows = getSelectedRows(); if (!rows.length) return alert('Select rows.'); await copyText(rowsToTargetLines(rows).join('\n')); });
  if (btnPasteTgt) btnPasteTgt.addEventListener('click', async () => {
    const rows = getSelectedRows(); if (!rows.length) return alert('Select rows first.');
    const text = await readText(); if (text===undefined) return;
    lastBulkSnapshot = rows.map(r => ({row:r, val:r.querySelector('textarea.tgt')?.value || ''})); undoBulk.disabled=false;
    const lines = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n');
    if (lines.length === 1 && rows.length > 1) rows.forEach(r => { const ta = r.querySelector('textarea.tgt'); if (ta) ta.value = lines[0]; });
    else rows.forEach((r,i)=>{ const ta = r.querySelector('textarea.tgt'); if (ta) ta.value = lines[i] ?? ''; });
    debouncedSave();
  });
  if (undoBulk) undoBulk.addEventListener('click', () => {
    if (!lastBulkSnapshot) return; lastBulkSnapshot.forEach(({row,val})=>{ const ta=row.querySelector('textarea.tgt'); if (ta) ta.value=val; });
    lastBulkSnapshot=null; undoBulk.disabled=true; debouncedSave();
  });

  // Hotkeys: Alt+T (row translate), Alt+Enter (translate + next)
  D.addEventListener('keydown', async (e) => {
    const ta = e.target.closest?.('textarea.tgt');
    if (!ta) return;
    if (e.altKey && (e.key === 't' || e.key === 'T')) {
      e.preventDefault(); await translateRow(ta);
    }
    if (e.altKey && e.key === 'Enter') {
      e.preventDefault(); await translateRow(ta); focusNextRow(ta);
    }
  });
  async function translateRow(textarea){
    const row = textarea.closest('tr');
    const srcCell = row.querySelector('td.src');
    const target = (targetInput?.value.trim()) || '';
    if (!srcCell) return;
    if (!target) { alert('Set target language first'); return; }
    const btn = row.querySelector('.btn-row');
    if (btn) { btn.disabled = true; btn.textContent = '…'; }
    try {
      const data = await sendTranslate([srcCell.innerHTML]);
      textarea.value = (data.translations && data.translations[0]) || '';
      debouncedSave();
    } catch (e){ alert('Translate failed: ' + e.message); }
    finally { if (btn){ btn.disabled=false; btn.textContent='↻'; } }
  }
  function focusNextRow(textarea){
    const tr = textarea.closest('tr'); if (!tr) return;
    const next = tr.nextElementSibling; if (!next) return;
    const ta = next.querySelector('textarea.tgt'); if (ta){ ta.focus(); ta.select(); }
  }

  // Keyboard shortcuts for select/copy/paste (when not in input)
  D.addEventListener('keydown', async (e) => {
    const inInput = ['INPUT','TEXTAREA','SELECT'].includes(e.target.tagName);
    const mod = e.ctrlKey || e.metaKey;
    if (mod && e.key.toLowerCase()==='a' && !inInput) { qa('.rowSel').forEach(cb => cb.checked = true); e.preventDefault(); renderPage(); }
    if (mod && e.key.toLowerCase()==='c' && !inInput) { const rows = getSelectedRows(); if (!rows.length) return; await copyText(rowsToTargetLines(rows).join('\n')); e.preventDefault(); }
    if (mod && e.key.toLowerCase()==='v' && !inInput) {
      const rows = getSelectedRows(); if (!rows.length) return;
      const text = await readText(); if (text===undefined) return;
      lastBulkSnapshot = rows.map(r => ({row:r, val:r.querySelector('textarea.tgt')?.value || ''})); undoBulk.disabled = false;
      const lines = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n');
      rows.forEach((r, i) => { const ta = r.querySelector('textarea.tgt'); if (ta) ta.value = lines[i] ?? ''; });
      e.preventDefault(); debouncedSave();
    }
  });

  // Translate API
  const btnAll = byId('autoTranslate');
  async function sendTranslate(texts) {
    const res = await fetch('translate.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({
        texts,
        source: (sourceInput?.value.trim()) || '',
        target: (targetInput?.value.trim()) || '',
        provider: providerSel?.value || '',
        csrf: csrf(),
      }),
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Translate failed');
    return data;
  }
  if (btnAll) {
    btnAll.addEventListener('click', async function(){
      const t = (targetInput?.value.trim()) || '';
      if (!t) { alert('Set a target language first'); return; }
      const areas = qa('tbody textarea.tgt');
      const sources = qa('tbody td.src');
      const texts = sources.map(td => td.innerHTML);
      btnAll.disabled = true; const old = btnAll.textContent; btnAll.textContent = 'Translating...';
      try {
        const data = await sendTranslate(texts);
        (data.translations || []).forEach((val,i)=>{ if (areas[i]) areas[i].value = val; });
        debouncedSave();
        alert('Translate complete.');
      } catch (e) { alert('Error: '+e.message); }
      finally { btnAll.disabled=false; btnAll.textContent = old; }
    });
  }

  // Autosave
  const btnSave = byId('saveEdits'); let saveTimer=null;
  function debouncedSave(){ if (saveTimer) clearTimeout(saveTimer); saveTimer=setTimeout(()=>doSave(), 600); }
  function doSave(){
    if (!q('#editForm')) return;
    const fd = new FormData(q('#editForm'));
    fetch('index.php', { method:'POST', body: fd }).catch(()=>{});
  }
  if (btnSave) btnSave.addEventListener('click',(e)=>{ e.preventDefault(); doSave(); alert('Saved.'); });

  // Google Sheets push/pull
  const pushBtn = byId('pushSheet'); const pullBtn = byId('pullSheet');
  if (pushBtn) pushBtn.addEventListener('click', async () => {
    const rowsSel = getSelectedRows();
    const useRows = rowsSel.length ? rowsSel : qa('tbody tr'); // if none selected, push all
    const ids = [], sources = [], targets = [];
    useRows.forEach(r => {
      ids.push(r.querySelector('.idcell')?.textContent || '');
      sources.push(r.querySelector('td.src')?.innerText || '');
      targets.push(r.querySelector('textarea.tgt')?.value || '');
    });
    const fd = new FormData();
    fd.append('csrf', csrf());
    ids.forEach(v => fd.append('ids[]', v));
    sources.forEach(v => fd.append('sources[]', v));
    targets.forEach(v => fd.append('targets[]', v));
    const resp = await fetch('sheets_push.php', { method:'POST', body: fd });
    const data = await resp.json();
    alert(data.ok ? 'Pushed to Google Sheet.' : 'Failed to push to Google Sheet. Check config and sharing.');
  });
  if (pullBtn) pullBtn.addEventListener('click', async () => {
    const resp = await fetch('sheets_pull.php?csrf=' + encodeURIComponent(csrf()));
    const data = await resp.json();
    if (!data.rows) { alert('Failed to pull. Check config.'); return; }
    const map = data.rows; // id => {source,target}
    qa('tbody tr').forEach(r => {
      const id = r.querySelector('textarea.tgt')?.dataset.id;
      if (id && map[id]) {
        const ta = r.querySelector('textarea.tgt');
        if (ta) ta.value = map[id].target || ta.value;
      }
    });
    debouncedSave();
    alert('Pulled from Google Sheet.');
  });

  // Drag-to-resize table headers
  function makeResizable(th){
    const resizer = document.createElement('div');
    resizer.className = 'col-resizer';
    th.style.position = 'relative';
    resizer.style.position = 'absolute';
    resizer.style.top = 0; resizer.style.right = 0; resizer.style.width = '6px'; resizer.style.cursor = 'col-resize';
    resizer.style.userSelect = 'none'; resizer.style.height = '100%';
    th.appendChild(resizer);
    let startX, startWidth;
    const mouseDown = (e) => {
      startX = e.pageX; startWidth = th.offsetWidth;
      document.addEventListener('mousemove', mouseMove);
      document.addEventListener('mouseup', mouseUp);
      e.preventDefault();
    };
    const mouseMove = (e) => {
      const newW = Math.max(40, startWidth + (e.pageX - startX));
      th.style.width = newW + 'px';
    };
    const mouseUp = () => {
      document.removeEventListener('mousemove', mouseMove);
      document.removeEventListener('mouseup', mouseUp);
    };
    resizer.addEventListener('mousedown', mouseDown);
  }
  qa('th[data-resizable]').forEach(makeResizable);

})();