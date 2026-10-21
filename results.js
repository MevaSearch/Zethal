(function () {
  const body = document.body;
  const q = body.dataset.q || '';
  const type = body.dataset.type || 'all';
  const page = parseInt(body.dataset.page, 10) || 1;
  const endpoint = body.dataset.endpoint || 'api.php';
  const aiEndpoint = body.dataset.aiEndpoint || '';
  const T = window.Zethal ? window.Zethal.T : (k => k);
  const settings = window.Zethal ? window.Zethal.settings() : { perPage: 10, newTab: false, safe: 'moderate', stripTracking: true, aiSummary: true, hiddenDomains: [] };

  const list = document.getElementById('results');
  const meta = document.getElementById('resultsMeta');
  const pager = document.getElementById('pager');
  const panel = document.getElementById('panel');
  const aiCard = document.getElementById('aiCard');
  const aiText = document.getElementById('aiText');
  const aiLabel = document.getElementById('aiLabel');
  const aiNote = document.getElementById('aiNote');

  const TRACKING = /^(utm_[a-z]+|fbclid|gclid|dclid|gbraid|wbraid|msclkid|mc_cid|mc_eid|yclid|igshid|twclid|ref|ref_src|_hsenc|_hsmi|spm|si)$/i;

  function el(tag, cls, text) {
    const n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text !== undefined) n.textContent = text;
    return n;
  }

  function cleanUrl(u) {
    try {
      const url = new URL(u, location.href);
      if (url.protocol !== 'http:' && url.protocol !== 'https:') return '#';
      if (settings.stripTracking) {
        [...url.searchParams.keys()].forEach(k => { if (TRACKING.test(k)) url.searchParams.delete(k); });
      }
      return url.href;
    } catch (e) {
      return '#';
    }
  }

  function host(u) {
    try {
      return new URL(u).hostname.replace(/^www\./, '');
    } catch (e) {
      return '';
    }
  }

  function hidden(u) {
    const h = host(u);
    return (settings.hiddenDomains || []).some(d => h === d || h.endsWith('.' + d));
  }

  function link(cls, text, url) {
    const a = el('a', cls, text);
    a.href = cleanUrl(url);
    a.target = settings.newTab ? '_blank' : '_self';
    a.rel = 'noopener noreferrer';
    return a;
  }

  async function fetchResults() {
    const params = new URLSearchParams({ q, type, page: String(page), count: String(settings.perPage), safe: settings.safe });
    const res = await fetch(`${endpoint}?${params}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    if (res.status === 429) throw new Error('rate');
    if (!res.ok) throw new Error(String(res.status));
    const data = await res.json();
    return {
      results: Array.isArray(data.results) ? data.results : [],
      total: parseInt(data.total, 10) || 0,
      count: parseInt(data.count, 10) || settings.perPage,
      panel: data.panel || null
    };
  }

  function renderResult(r) {
    const item = el('article', 'result');
    const h = host(r.url);
    const top = el('div', 'result-top');
    top.append(el('span', 'result-avatar', (h[0] || '?').toUpperCase()), el('span', 'result-host', h + (r.date ? ' · ' + r.date : '')));
    item.append(top, link('result-title', r.title || h, r.url));
    if (r.description) item.append(el('p', 'result-desc', r.description));
    if (Array.isArray(r.sitelinks) && r.sitelinks.length) {
      const links = el('div', 'sitelinks');
      r.sitelinks.slice(0, 6).forEach(s => links.append(link('sitelink', s.title || host(s.url), s.url)));
      item.append(links);
    }
    return item;
  }

  function renderImages(results) {
    const grid = el('div', 'image-grid');
    results.forEach((r, i) => {
      const tile = link('image-tile', '', r.url);
      tile.style.setProperty('--shade', String((i * 7) % 18));
      if (r.thumbnail) {
        const img = el('img', 'image-thumb');
        img.src = r.thumbnail;
        img.alt = r.title || '';
        img.loading = 'lazy';
        img.decoding = 'async';
        img.referrerPolicy = 'no-referrer';
        tile.append(img);
      }
      tile.append(el('span', 'image-caption', r.title || ''));
      grid.append(tile);
    });
    list.append(grid);
  }

  function renderPanel(p) {
    panel.replaceChildren();
    if (!p || type !== 'all' || page !== 1) return;
    const card = el('div', 'panel-card');
    card.append(el('h2', 'panel-title', p.title || ''));
    if (p.subtitle) card.append(el('p', 'panel-sub', p.subtitle));
    if (p.text) card.append(el('p', 'panel-text', p.text));
    if (p.url) {
      const foot = el('p', 'panel-foot');
      foot.append(link('panel-link', T('read_more'), p.url));
      if (p.source) foot.append(el('span', 'panel-source', T('source', { name: p.source })));
      card.append(foot);
    }
    panel.append(card);
  }

  function renderPager(total, count) {
    pager.replaceChildren();
    const pages = Math.min(10, Math.ceil(total / count));
    if (pages <= 1) return;
    const first = Math.max(1, Math.min(page - 4, pages - 9));
    for (let i = first; i <= Math.min(pages, first + 9); i++) {
      const a = el('a', 'pager-link' + (i === page ? ' active' : ''), String(i));
      a.href = `results.php?q=${encodeURIComponent(q)}&type=${encodeURIComponent(type)}&page=${i}`;
      pager.append(a);
    }
  }

  async function renderSummary(sources) {
    if (!aiEndpoint || !settings.aiSummary || type !== 'all' || page !== 1 || !sources.length) return;
    aiCard.hidden = false;
    aiCard.classList.add('loading');
    aiText.textContent = '';
    try {
      const res = await fetch(aiEndpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ q, sources: sources.slice(0, 5).map(s => ({ title: s.title, description: s.description })) })
      });
      if (!res.ok) throw new Error(String(res.status));
      const data = await res.json();
      if (!data.answer) {
        aiCard.hidden = true;
        return;
      }
      const kind = data.kind === 'extractive' ? 'extractive' : 'ai';
      aiLabel.textContent = aiLabel.dataset[kind] || aiLabel.textContent;
      aiNote.textContent = aiNote.dataset[kind] || aiNote.textContent;
      aiText.textContent = data.answer;
    } catch (e) {
      aiCard.hidden = true;
    } finally {
      aiCard.classList.remove('loading');
    }
  }

  function skeleton() {
    list.replaceChildren();
    for (let i = 0; i < 5; i++) list.append(el('div', 'result skeleton'));
  }

  async function run() {
    if (!q) {
      meta.textContent = T('empty_query');
      return;
    }
    skeleton();
    try {
      const data = await fetchResults();
      const shown = data.results.filter(r => !hidden(r.url));
      list.replaceChildren();
      meta.textContent = shown.length ? T('count', { n: data.total.toLocaleString(document.documentElement.lang || 'en') }) : '';
      if (!shown.length) {
        list.append(el('p', 'empty', T('no_results', { q })));
        return;
      }
      if (type === 'images' || type === 'videos') renderImages(shown);
      else shown.forEach(r => list.append(renderResult(r)));
      renderPanel(data.panel);
      renderPager(data.total, data.count);
      renderSummary(shown);
    } catch (e) {
      list.replaceChildren(el('p', 'empty', e.message === 'rate' ? T('rate') : T('error')));
    }
  }

  run();
})();
