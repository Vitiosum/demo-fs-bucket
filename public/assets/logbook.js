// Progressive enhancement only: the page works without this file.
(() => {
  const MAX_SKETCH = 2 * 1024 * 1024;
  const form = document.querySelector('.entry-form');
  const errorBox = document.getElementById('form-error');

  const showError = (message) => {
    errorBox.textContent = message;
    errorBox.hidden = false;
  };

  const wax = (instance) => (/^\d+$/.test(instance) ? `wax--${Number(instance) % 5}` : 'wax--local');
  const waxMark = (instance) => {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('class', `wax-mark ${wax(instance)}`);
    svg.setAttribute('aria-hidden', 'true');
    const use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
    use.setAttribute('href', '#wax');
    svg.append(use);
    return svg;
  };
  const el = (tag, className, text) => {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  };

  // Remarks: characters left.
  const remarks = document.getElementById('remarks');
  const count = document.getElementById('remarks-count');
  const updateCount = () => { count.textContent = String(remarks.maxLength - remarks.value.length); };
  remarks?.addEventListener('input', updateCount);
  if (remarks) updateCount();

  // Sketch: show the chosen file, refuse oversized ones before uploading.
  const sketch = document.getElementById('sketch');
  const sketchName = document.getElementById('sketch-name');
  sketch?.addEventListener('change', () => {
    const file = sketch.files[0];
    sketchName.textContent = file ? `${file.name} (${(file.size / 1024).toFixed(0)} KB)` : 'Paste in a drawing or a photo';
    if (file && file.size > MAX_SKETCH) showError('The sketch weighs more than 2 MB. Attach a smaller image.');
    else errorBox.hidden = true;
  });

  // Seal the entry with fetch, keep the write trace for the next page.
  form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const file = sketch.files[0];
    if (file && file.size > MAX_SKETCH) {
      showError('The sketch weighs more than 2 MB. Attach a smaller image.');
      return;
    }
    const button = form.querySelector('.seal-button');
    button.disabled = true;
    form.classList.add('is-sealing');
    try {
      const response = await fetch('/entries', { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } });
      const data = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(data.error || 'The entry could not be sealed. Try again.');
      try { sessionStorage.setItem('logbook:sealed', JSON.stringify(data)); } catch { /* private mode: no trace, still fine */ }
      location.assign(`/?sealed=${encodeURIComponent(data.id)}#entry-${encodeURIComponent(data.id)}`);
    } catch (error) {
      showError(error.message);
      button.disabled = false;
      form.classList.remove('is-sealing');
    }
  });

  // After a seal: show how the entry was written, below deck.
  const sealed = new URLSearchParams(location.search).get('sealed');
  if (sealed) {
    let data = null;
    try {
      data = JSON.parse(sessionStorage.getItem('logbook:sealed') || 'null');
      sessionStorage.removeItem('logbook:sealed');
    } catch { data = null; }
    if (data && data.id === sealed && Array.isArray(data.ops)) {
      document.getElementById('seal-ops-title').textContent = data.instance === 'local'
        ? 'Your entry was written by this machine'
        : `Your entry was written by instance ${data.instance}`;
      const list = document.getElementById('seal-ops-list');
      for (const op of data.ops) {
        const li = el('li');
        li.append(el('span', 'op__call', op.call), el('span', 'op__path', op.path), el('span', 'op__detail', op.detail), el('span', 'op__ms', `${Number(op.ms).toFixed(2)} ms`));
        list.append(li);
      }
      document.getElementById('seal-ops').hidden = false;
    }
    history.replaceState(null, '', location.pathname + location.hash);
  }

  // Hail the watches: eight requests, each instance reports what it reads.
  const hail = document.getElementById('hail-button');
  hail?.addEventListener('click', async () => {
    const replies = document.getElementById('hail-replies');
    const summary = document.getElementById('hail-summary');
    replies.replaceChildren();
    summary.textContent = '';
    hail.disabled = true;
    const seen = new Map();
    try {
      for (let i = 0; i < 8; i += 1) {
        const response = await fetch('/api/hail', { cache: 'no-store', headers: { Accept: 'application/json' } });
        const data = await response.json();
        seen.set(data.instance, data);
        const li = el('li');
        const who = data.instance === 'local' ? 'This machine' : `Instance ${data.instance}, ${data.name}`;
        const last = data.last ? `, last by ${data.last.hand}` : '';
        li.append(waxMark(data.instance), el('span', '', `${who}: ${data.entries} entries${last}`), el('small', '', `${Number(data.ms).toFixed(1)} ms`));
        replies.append(li);
      }
      const counts = new Set([...seen.values()].map((d) => `${d.entries}/${d.last ? d.last.id : ''}`));
      if (seen.size > 1) {
        summary.textContent = counts.size === 1
          ? `${seen.size} instances answered, and ${seen.size === 2 ? 'both' : 'all'} of them read the same ${[...seen.values()][0].entries} entries from the same hold.`
          : `${seen.size} instances answered. An entry was sealed during the hail, so the counts moved; hail again.`;
      } else {
        const only = [...seen.keys()][0];
        summary.textContent = only === 'local'
          ? 'Only this machine is on deck. On Clever Cloud with two instances, both answer and read the same hold.'
          : `Only instance ${only} is on deck right now. Scale to two instances and hail again: both read the same hold.`;
      }
    } catch {
      summary.textContent = 'The hail went unanswered. Check that the app is running, then try again.';
    } finally {
      hail.disabled = false;
    }
  });
})();
