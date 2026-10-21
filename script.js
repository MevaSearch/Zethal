(function () {
  const KEY = 'zethal_settings';
  const SHORTCUTS = 'zethal_shortcuts';
  const DEFAULTS = {
    theme: 'system',
    capsule: true,
    compact: false,
    showShortcuts: true,
    perPage: 10,
    safe: 'moderate',
    newTab: false,
    stripTracking: true,
    aiSummary: true,
    hiddenDomains: []
  };

  let texts = null;

  function T(key, vars) {
    if (!texts) {
      try {
        texts = JSON.parse((document.getElementById('zethalText') || {}).textContent || '{}');
      } catch (e) {
        texts = {};
      }
    }
    let s = texts[key] || key;
    Object.entries(vars || {}).forEach(([k, v]) => { s = s.split('{' + k + '}').join(String(v)); });
    return s;
  }

  function read(key, fallback) {
    try {
      const v = JSON.parse(localStorage.getItem(key) || 'null');
      return v === null ? fallback : v;
    } catch (e) {
      return fallback;
    }
  }

  function write(key, value) {
    try {
      localStorage.setItem(key, JSON.stringify(value));
    } catch (e) {}
  }

  function load() {
    return Object.assign({}, DEFAULTS, read(KEY, {}));
  }

  function isDark(s) {
    return s.theme === 'dark' || (s.theme === 'system' && window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches);
  }

  function apply(s) {
    const root = document.documentElement;
    root.classList.toggle('dark', isDark(s));
    root.classList.toggle('compact', !!s.compact);
    root.classList.toggle('no-shortcuts', !s.showShortcuts);
    root.classList.toggle('no-capsule', !s.capsule);
  }

  apply(load());

  window.Zethal = {
    T,
    settings: load,
    save(s) {
      write(KEY, s);
      apply(s);
    }
  };

  if (window.matchMedia) {
    matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => apply(load()));
  }

  function svgIcon(name, size) {
    const ns = 'http://www.w3.org/2000/svg';
    const svg = document.createElementNS(ns, 'svg');
    svg.setAttribute('class', 'icon');
    svg.setAttribute('width', String(size || 20));
    svg.setAttribute('height', String(size || 20));
    svg.setAttribute('aria-hidden', 'true');
    const use = document.createElementNS(ns, 'use');
    use.setAttribute('href', (document.body.dataset.icons || 'zethal-icons.php') + '#i-' + name);
    svg.append(use);
    return svg;
  }

  function el(tag, cls, text) {
    const n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text !== undefined) n.textContent = text;
    return n;
  }

  function safeUrl(u) {
    try {
      const url = new URL(/^https?:\/\//i.test(u) ? u : 'https://' + u);
      return url.protocol === 'http:' || url.protocol === 'https:' ? url.href : '';
    } catch (e) {
      return '';
    }
  }

  function initShortcuts() {
    const wrap = document.getElementById('shortcuts');
    const add = document.getElementById('shortcutAdd');
    if (!wrap || !add) return;

    const render = () => {
      wrap.querySelectorAll('.shortcut:not(.shortcut-add)').forEach(n => n.remove());
      read(SHORTCUTS, []).slice(0, 11).forEach((s, i) => {
        const link = el('a', 'shortcut');
        link.href = s.url;
        link.rel = 'noopener noreferrer';
        const del = el('button', 'shortcut-del', '×');
        del.type = 'button';
        del.setAttribute('aria-label', T('shortcut_remove'));
        del.addEventListener('click', e => {
          e.preventDefault();
          const list = read(SHORTCUTS, []);
          list.splice(i, 1);
          write(SHORTCUTS, list);
          render();
        });
        link.append(el('span', 'shortcut-tile', (s.name || '?').trim().charAt(0).toUpperCase()), el('span', 'shortcut-label', s.name), del);
        wrap.insertBefore(link, add);
      });
    };

    add.addEventListener('click', () => {
      const name = (prompt(T('shortcut_name')) || '').trim().slice(0, 24);
      if (!name) return;
      const url = safeUrl((prompt(T('shortcut_url')) || '').trim());
      if (!url) return;
      const list = read(SHORTCUTS, []);
      list.push({ name, url });
      write(SHORTCUTS, list);
      render();
    });

    render();
  }

  function initVoice() {
    const Rec = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!Rec) return;
    document.querySelectorAll('[data-voice]').forEach(btn => {
      const form = btn.closest('form');
      const input = form && form.querySelector('input[type="search"]');
      if (!input) return;
      btn.hidden = false;
      btn.addEventListener('click', () => {
        const rec = new Rec();
        rec.lang = document.documentElement.lang || 'en';
        rec.interimResults = false;
        btn.classList.add('listening');
        rec.onresult = e => {
          input.value = e.results[0][0].transcript;
          form.requestSubmit();
        };
        rec.onend = () => btn.classList.remove('listening');
        rec.start();
      });
    });
  }

  function resizeImage(file, max) {
    return new Promise((resolve, reject) => {
      const url = URL.createObjectURL(file);
      const img = new Image();
      img.onload = () => {
        const scale = Math.min(1, max / Math.max(img.naturalWidth, img.naturalHeight));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(img.naturalWidth * scale);
        canvas.height = Math.round(img.naturalHeight * scale);
        canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
        const data = canvas.toDataURL('image/jpeg', 0.85).split(',')[1];
        resolve({ url, data });
      };
      img.onerror = () => {
        URL.revokeObjectURL(url);
        reject(new Error('invalid'));
      };
      img.src = url;
    });
  }

  function initImageSearch() {
    const triggers = document.querySelectorAll('[data-image-search]');
    if (!triggers.length) return;
    const endpoint = document.body.dataset.aiEndpoint || 'ai.php';
    let modal = null;
    let current = null;
    let lastFocus = null;

    const build = () => {
      modal = el('div', 'modal');
      modal.hidden = true;
      modal.setAttribute('role', 'dialog');
      modal.setAttribute('aria-modal', 'true');
      modal.setAttribute('aria-label', T('image_title'));

      const box = el('div', 'modal-box');
      const head = el('div', 'modal-head');
      const title = el('h2', 'modal-title');
      title.append(svgIcon('camera', 18), el('span', '', T('image_title')));
      const close = el('button', 'icon-btn modal-close');
      close.type = 'button';
      close.setAttribute('aria-label', T('close'));
      close.append(svgIcon('close', 16));
      head.append(title, close);

      const input = el('input');
      input.type = 'file';
      input.accept = 'image/jpeg,image/png,image/webp';
      input.hidden = true;

      const drop = el('div', 'dropzone');
      drop.tabIndex = 0;
      const preview = el('img', 'dropzone-preview');
      preview.alt = '';
      preview.hidden = true;
      const empty = el('div', 'dropzone-empty');
      const chooser = el('button', 'btn btn-ghost', T('image_choose'));
      chooser.type = 'button';
      empty.append(svgIcon('upload', 28), el('p', '', T('image_hint')), chooser);
      drop.append(preview, empty);

      const status = el('p', 'modal-status');
      status.setAttribute('role', 'status');
      const foot = el('div', 'modal-foot');
      const note = el('p', 'modal-note', T('image_privacy'));
      const go = el('button', 'btn', T('image_search'));
      go.type = 'button';
      go.disabled = true;
      foot.append(note, go);

      box.append(head, input, drop, status, foot);
      modal.append(box);
      document.body.append(modal);

      const reset = () => {
        if (current) URL.revokeObjectURL(current.url);
        current = null;
        preview.hidden = true;
        empty.hidden = false;
        go.disabled = true;
        status.textContent = '';
      };

      const take = async file => {
        if (!file || !/^image\/(jpeg|png|webp)$/.test(file.type)) {
          status.textContent = T('image_invalid');
          return;
        }
        try {
          reset();
          current = await resizeImage(file, 1024);
          current.name = file.name || '';
          preview.src = current.url;
          preview.hidden = false;
          empty.hidden = true;
          go.disabled = false;
        } catch (e) {
          status.textContent = T('image_invalid');
        }
      };

      const hide = () => {
        modal.hidden = true;
        document.documentElement.classList.remove('modal-open');
        reset();
        if (lastFocus) lastFocus.focus();
      };

      chooser.addEventListener('click', () => input.click());
      drop.addEventListener('click', e => { if (e.target === drop || e.target === preview) input.click(); });
      drop.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); } });
      input.addEventListener('change', () => take(input.files[0]));
      ['dragenter', 'dragover'].forEach(t => drop.addEventListener(t, e => { e.preventDefault(); drop.classList.add('over'); }));
      ['dragleave', 'drop'].forEach(t => drop.addEventListener(t, () => drop.classList.remove('over')));
      drop.addEventListener('drop', e => { e.preventDefault(); take(e.dataTransfer.files[0]); });
      document.addEventListener('paste', e => {
        if (modal.hidden) return;
        const item = [...(e.clipboardData || {}).items || []].find(i => i.type.startsWith('image/'));
        if (item) take(item.getAsFile());
      });
      close.addEventListener('click', hide);
      modal.addEventListener('click', e => { if (e.target === modal) hide(); });
      document.addEventListener('keydown', e => { if (e.key === 'Escape' && !modal.hidden) hide(); });

      go.addEventListener('click', async () => {
        if (!current) return;
        go.disabled = true;
        status.textContent = T('image_working');
        modal.classList.add('busy');
        try {
          const res = await fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ mode: 'image', mime: 'image/jpeg', image: current.data, filename: current.name })
          });
          const data = await res.json().catch(() => ({}));
          if (res.ok && data.query) {
            location.href = 'results.php?q=' + encodeURIComponent(data.query);
            return;
          }
          status.textContent = data.message || T('image_failed');
        } catch (e) {
          status.textContent = T('image_failed');
        }
        modal.classList.remove('busy');
        go.disabled = false;
      });

      modal.open = () => {
        lastFocus = document.activeElement;
        modal.hidden = false;
        document.documentElement.classList.add('modal-open');
        chooser.focus();
      };
    };

    triggers.forEach(btn => btn.addEventListener('click', () => {
      if (!modal) build();
      modal.open();
    }));
  }

  function initSlashFocus() {
    document.addEventListener('keydown', e => {
      if (e.key !== '/' || e.ctrlKey || e.metaKey || e.altKey) return;
      const tag = (document.activeElement || {}).tagName;
      if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;
      const input = document.querySelector('.searchbox input[type="search"]');
      if (input) {
        e.preventDefault();
        input.focus();
        input.select();
      }
    });
  }

  function initSettingsForm() {
    const form = document.getElementById('settingsForm');
    if (!form) return;
    const toast = document.getElementById('settingsToast');
    const currentLang = document.body.dataset.lang || 'en';
    const field = n => form.elements.namedItem(n);

    const fill = s => {
      field('lang').value = currentLang;
      field('theme').value = s.theme;
      field('perPage').value = String(s.perPage);
      field('safe').value = s.safe;
      ['capsule', 'compact', 'showShortcuts', 'newTab', 'stripTracking', 'aiSummary'].forEach(k => { field(k).checked = !!s[k]; });
      field('hiddenDomains').value = (s.hiddenDomains || []).join('\n');
    };

    const collect = () => ({
      theme: field('theme').value,
      capsule: field('capsule').checked,
      compact: field('compact').checked,
      showShortcuts: field('showShortcuts').checked,
      perPage: parseInt(field('perPage').value, 10) || DEFAULTS.perPage,
      safe: field('safe').value,
      newTab: field('newTab').checked,
      stripTracking: field('stripTracking').checked,
      aiSummary: field('aiSummary').checked,
      hiddenDomains: [...new Set(field('hiddenDomains').value.split(/[\s,]+/)
        .map(d => d.trim().toLowerCase().replace(/^https?:\/\//, '').replace(/^www\./, '').replace(/\/.*$/, ''))
        .filter(d => /^[a-z0-9.-]+\.[a-z]{2,}$/.test(d)))].slice(0, 200)
    });

    const notify = text => {
      toast.textContent = text;
      toast.classList.add('show');
      clearTimeout(notify.t);
      notify.t = setTimeout(() => toast.classList.remove('show'), 1800);
    };

    const setLang = code => {
      document.cookie = 'zethal_lang=' + encodeURIComponent(code) + '; path=/; max-age=31536000; SameSite=Lax';
    };

    fill(load());
    form.addEventListener('change', e => { if (e.target.name !== 'lang') apply(collect()); });
    form.addEventListener('submit', e => {
      e.preventDefault();
      window.Zethal.save(collect());
      if (field('lang').value !== currentLang) {
        setLang(field('lang').value);
        location.reload();
        return;
      }
      fill(load());
      notify(T('saved'));
    });
    document.getElementById('settingsReset').addEventListener('click', () => {
      window.Zethal.save(Object.assign({}, DEFAULTS));
      fill(load());
      notify(T('reset'));
    });
    document.getElementById('clearData').addEventListener('click', () => {
      if (!confirm(T('clear_confirm'))) return;
      try {
        localStorage.removeItem(KEY);
        localStorage.removeItem(SHORTCUTS);
      } catch (e) {}
      document.cookie = 'zethal_lang=; path=/; max-age=0; SameSite=Lax';
      apply(load());
      fill(load());
      notify(T('cleared'));
    });
  }

  function initMenu() {
    const menu = document.getElementById('sideMenu');
    const toggle = document.querySelector('[data-menu-toggle]');
    if (!menu || !toggle) return;
    const backdrop = document.querySelector('.side-menu-backdrop');
    const setOpen = open => {
      menu.hidden = !open;
      if (backdrop) backdrop.hidden = !open;
      toggle.setAttribute('aria-expanded', String(open));
      if (open) (menu.querySelector('a, button:not([data-menu-close])') || menu).focus();
      else toggle.focus();
    };
    toggle.addEventListener('click', () => setOpen(menu.hidden));
    document.querySelectorAll('[data-menu-close]').forEach(n => n.addEventListener('click', () => setOpen(false)));
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && !menu.hidden) setOpen(false); });
    menu.querySelectorAll('[data-image-search], [data-theme-toggle]').forEach(n => n.addEventListener('click', () => { if (n.hasAttribute('data-image-search')) setOpen(false); }));
    menu.querySelectorAll('[data-needs-query]').forEach(a => {
      a.addEventListener('click', e => {
        const form = document.querySelector('form.searchbox');
        const input = form && form.querySelector('input[type="search"]');
        if (!input) return;
        e.preventDefault();
        let typeField = form.querySelector('input[name="type"]');
        if (!typeField) {
          typeField = document.createElement('input');
          typeField.type = 'hidden';
          typeField.name = 'type';
          form.append(typeField);
        }
        typeField.value = a.dataset.needsQuery;
        if (input.value.trim()) {
          form.requestSubmit();
          return;
        }
        setOpen(false);
        input.focus();
      });
    });
  }

  function initBangSuggest() {
    const source = document.getElementById('zethalBangs');
    if (!source) return;
    let bangs = [];
    try {
      bangs = JSON.parse(source.textContent || '[]');
    } catch (e) {
      return;
    }
    document.querySelectorAll('form.searchbox').forEach(form => {
      const input = form.querySelector('input[type="search"]');
      if (!input) return;
      const list = el('ul', 'bang-suggest');
      list.setAttribute('role', 'listbox');
      list.id = 'bangSuggest' + Math.random().toString(36).slice(2, 8);
      list.hidden = true;
      form.append(list);
      input.setAttribute('aria-controls', list.id);
      input.setAttribute('aria-autocomplete', 'list');
      let items = [];
      let index = -1;
      let token = null;

      const close = () => {
        list.hidden = true;
        items = [];
        index = -1;
        input.setAttribute('aria-expanded', 'false');
      };

      const highlight = i => {
        index = i;
        [...list.children].forEach((li, n) => li.setAttribute('aria-selected', String(n === i)));
      };

      const insert = bang => {
        const pos = input.selectionStart ?? input.value.length;
        const before = input.value.slice(0, pos).replace(/!([a-z0-9]*)$/i, '!' + bang.code + ' ');
        input.value = before + input.value.slice(pos).replace(/^\S*\s?/, '');
        input.setSelectionRange(before.length, before.length);
        close();
        input.focus();
      };

      const open = () => {
        const pos = input.selectionStart ?? input.value.length;
        const m = input.value.slice(0, pos).match(/(?:^|\s)!([a-z0-9]*)$/i);
        if (!m) return close();
        token = m[1].toLowerCase();
        items = bangs
          .filter(b => b.code.startsWith(token) || b.name.toLowerCase().includes(token))
          .sort((a, b) => (b.code.startsWith(token) - a.code.startsWith(token)) || a.code.length - b.code.length)
          .slice(0, 8);
        if (!items.length) return close();
        list.replaceChildren();
        items.forEach((b, i) => {
          const li = el('li', 'bang-item');
          li.setAttribute('role', 'option');
          li.append(
            el('span', 'bang-avatar', b.name.charAt(0).toUpperCase()),
            el('span', 'bang-code', '!' + b.code),
            el('span', 'bang-name', b.name),
            el('span', 'bang-host', b.host)
          );
          li.addEventListener('mousedown', e => {
            e.preventDefault();
            insert(b);
          });
          li.addEventListener('mousemove', () => highlight(i));
          list.append(li);
        });
        list.append(el('li', 'bang-foot', T('bang_hint')));
        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
        highlight(0);
      };

      input.addEventListener('input', open);
      input.addEventListener('click', open);
      input.addEventListener('blur', () => setTimeout(close, 120));
      input.addEventListener('keydown', e => {
        if (list.hidden || !items.length) return;
        if (e.key === 'ArrowDown') {
          e.preventDefault();
          highlight((index + 1) % items.length);
        } else if (e.key === 'ArrowUp') {
          e.preventDefault();
          highlight((index - 1 + items.length) % items.length);
        } else if ((e.key === 'Enter' || e.key === 'Tab') && index >= 0 && token !== items[index].code) {
          e.preventDefault();
          insert(items[index]);
        } else if (e.key === 'Escape') {
          e.preventDefault();
          close();
        }
      });
    });
  }

  function initSidebarSpy() {
    const navLinks = [...document.querySelectorAll('.sidebar-nav a[href^="#"]')];
    const targets = navLinks
      .map(link => ({ link, el: document.querySelector(link.getAttribute('href')) }))
      .filter(t => t.el);
    if (!targets.length) return;

    const activationLine = 0.28;
    const reduced = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
    let current = null;
    let ticking = false;
    let locked = false;
    let lockTimer = 0;

    const scroller = () => {
      for (let el = targets[0].el.parentElement; el && el !== document.body && el !== document.documentElement; el = el.parentElement) {
        const oy = getComputedStyle(el).overflowY;
        if ((oy === 'auto' || oy === 'scroll') && el.scrollHeight > el.clientHeight + 1) return el;
      }
      return null;
    };

    const metrics = () => {
      const el = scroller();
      if (el) return { top: el.scrollTop, height: el.clientHeight, full: el.scrollHeight, offset: el.getBoundingClientRect().top };
      const doc = document.scrollingElement || document.documentElement;
      return { top: doc.scrollTop, height: window.innerHeight, full: doc.scrollHeight, offset: 0 };
    };

    const setActive = (target, updateHash) => {
      if (current === target) return;
      current = target;
      navLinks.forEach(a => {
        const on = a === target.link;
        a.classList.toggle('active', on);
        if (on) a.setAttribute('aria-current', 'true');
        else a.removeAttribute('aria-current');
      });
      if (updateHash) {
        const hash = target === targets[0] ? '' : '#' + target.el.id;
        if (location.hash !== hash) history.replaceState(history.state, '', location.pathname + location.search + hash);
      }
    };

    const update = () => {
      const m = metrics();
      const max = Math.max(0, m.full - m.height);
      const lineOffset = m.height * activationLine;
      const pos = targets.map(t => Math.max(0, t.el.getBoundingClientRect().top - m.offset + m.top - lineOffset));
      const firstClamped = pos.findIndex(p => p >= max - 2);
      if (firstClamped !== -1) {
        const start = firstClamped > 0 ? Math.min(pos[firstClamped - 1], max) : 0;
        const count = pos.length - firstClamped;
        for (let i = 0; i < count; i++) {
          pos[firstClamped + i] = firstClamped === 0 && i === 0 ? 0 : start + (max - start) * (i + 1) / count;
        }
      }
      let found = targets[0];
      targets.forEach((t, i) => { if (m.top >= pos[i] - 2) found = t; });
      setActive(found, true);
    };

    const lockFor = ms => {
      locked = true;
      clearTimeout(lockTimer);
      lockTimer = setTimeout(() => { locked = false; }, ms);
    };

    const onScroll = () => {
      if (locked) return lockFor(180);
      if (ticking) return;
      ticking = true;
      requestAnimationFrame(() => {
        update();
        ticking = false;
      });
    };

    const goToHash = smooth => {
      const target = targets.find(t => '#' + t.el.id === location.hash);
      if (!target) return false;
      lockFor(600);
      target.el.scrollIntoView({ block: 'start', behavior: smooth && !reduced ? 'smooth' : 'auto' });
      setActive(target, false);
      return true;
    };

    document.addEventListener('scroll', onScroll, { passive: true, capture: true });
    window.addEventListener('resize', onScroll);
    window.addEventListener('hashchange', () => goToHash(true));

    navLinks.forEach(a => a.addEventListener('click', e => {
      const target = targets.find(t => t.link === a);
      if (!target) return;
      e.preventDefault();
      setActive(target, true);
      lockFor(600);
      target.el.scrollIntoView({ block: 'start', behavior: reduced ? 'auto' : 'smooth' });
    }));

    if (!goToHash(false)) update();
    window.addEventListener('load', () => { if (!goToHash(false)) update(); });
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-theme-toggle]').forEach(btn => {
      btn.addEventListener('click', () => {
        const s = load();
        s.theme = isDark(s) ? 'light' : 'dark';
        window.Zethal.save(s);
      });
    });

    const homeBtn = document.querySelector('[data-submit-home]');
    if (homeBtn) {
      homeBtn.addEventListener('click', () => {
        const form = document.querySelector('.searchbox');
        const input = form.querySelector('input[type="search"]');
        if (input.value.trim()) form.requestSubmit();
        else input.focus();
      });
    }

    initShortcuts();
    initVoice();
    initImageSearch();
    initSlashFocus();
    initSettingsForm();
    initSidebarSpy();
    initMenu();
    initBangSuggest();
  });
})();
