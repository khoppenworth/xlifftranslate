(function(){
  const btn = document.getElementById('autoTranslate');
  if (!btn) return;
  btn.addEventListener('click', async function(){
    const source = document.getElementById('sourceLang').value.trim();
    const target = document.getElementById('targetLang').value.trim();
    if (!target) { alert('Please set a target language, e.g., fr or fr-FR'); return; }

    const areas = Array.from(document.querySelectorAll('tbody textarea'));
    const sources = Array.from(document.querySelectorAll('tbody td.mono'));
    const texts = sources.map(td => td.innerHTML);

    btn.disabled = true;
    btn.textContent = 'Translating...';

    try {
      const res = await fetch('translate.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({
          texts: texts,
          source: source,
          target: target,
          csrf: getCsrf(),
        })
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || 'Translate failed');
      const translated = data.translations || [];
      translated.forEach((t, i) => { if (areas[i]) areas[i].value = t; });
      alert(data.mock ? 'Used MOCK translations (no API key).' : 'Auto-translate complete.');
    } catch (e) {
      alert('Error: ' + e.message);
    } finally {
      btn.disabled = false;
      btn.textContent = 'Auto-translate all (Google)';
    }
  });

  function getCsrf(){
    const input = document.querySelector('input[name="csrf"]');
    return input ? input.value : '';
  }
})();
