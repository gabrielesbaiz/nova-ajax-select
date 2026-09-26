(() => {
  'use strict';

  const calm = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const fmt = n => n.toLocaleString('en-US');

  /* theme ------------------------------------------------------ */
  const mode = document.getElementById('mode');
  const prefersDark = matchMedia('(prefers-color-scheme: dark)');
  const isDark = () => {
    const explicit = document.documentElement.dataset.theme;
    return explicit ? explicit === 'dark' : prefersDark.matches;
  };
  const syncLabel = () => { mode.textContent = isDark() ? 'Light' : 'Dark'; };
  mode.addEventListener('click', () => {
    document.documentElement.dataset.theme = isDark() ? 'light' : 'dark';
    syncLabel();
  });
  prefersDark.addEventListener('change', syncLabel);
  syncLabel();

  /* scroll progress -------------------------------------------- */
  const bar = document.getElementById('progress');
  let ticking = false;
  const paintBar = () => {
    const max = document.documentElement.scrollHeight - innerHeight;
    bar.style.width = (max > 0 ? Math.min(100, Math.max(0, (scrollY / max) * 100)) : 0) + '%';
    ticking = false;
  };
  addEventListener('scroll', () => { if (!ticking) { ticking = true; requestAnimationFrame(paintBar); } }, { passive: true });
  addEventListener('resize', paintBar, { passive: true });

  /* pages ------------------------------------------------------ */
  const home = document.getElementById('home');
  const shell = document.getElementById('docsShell');
  const docs = [...document.querySelectorAll('.doc')];
  const sideLinks = [...document.querySelectorAll('.sidenav a')];
  const navLinks = [...document.querySelectorAll('#nav a')];
  const toc = document.getElementById('toc');
  const rail = toc.closest('.onthispage');

  const titleOf = id => docs.find(d => d.id === id)?.dataset.title || 'NovaAjax Select';

  /* Rebuilt on every route change, so the rail always describes the page
     actually on screen. The palette reads the same array. */
  let sections = [];
  let railSpy = null;

  function buildRail(page) {
    if (railSpy) { railSpy.disconnect(); railSpy = null; }
    toc.innerHTML = '';
    sections = [...page.querySelectorAll('section[id]')].map(el => {
      const li = document.createElement('li');
      const a = document.createElement('a');
      a.href = '#' + el.id;
      a.textContent = (el.querySelector('h2')?.textContent || el.id).trim();
      a.addEventListener('click', e => {
        e.preventDefault();
        el.scrollIntoView({ behavior: calm ? 'auto' : 'smooth', block: 'start' });
      });
      li.appendChild(a); toc.appendChild(li);
      return { a, el };
    });

    /* Too few headings to earn a rail. */
    rail.style.display = sections.length < 3 ? 'none' : '';
    if (sections.length < 3) return;

    railSpy = new IntersectionObserver(es => {
      const vis = es.filter(e => e.isIntersecting)
                    .sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top);
      if (!vis.length) return;
      const hit = sections.find(s => s.el === vis[0].target);
      if (hit) sections.forEach(s => s.a.setAttribute('aria-current', String(s === hit)));
    }, { rootMargin: '-15% 0px -70% 0px' });
    sections.forEach(s => railSpy.observe(s.el));
  }

  function buildPager(page) {
    page.querySelector('.pager')?.remove();
    const prev = page.dataset.prev, next = page.dataset.next;
    if (!prev && !next) return;
    const nav = document.createElement('div');
    nav.className = 'pager';
    if (prev) nav.innerHTML += '<a href="#/' + prev + '"><span>Previous</span><b>' + titleOf(prev) + '</b></a>';
    if (next) nav.innerHTML += '<a class="next" href="#/' + next + '"><span>Next</span><b>' + titleOf(next) + '</b></a>';
    page.appendChild(nav);
  }

  function route() {
    /* Set by the inline bootstrap so a deep link does not flash the landing
       page. From here on the .hidden toggles below own the visibility. */
    document.documentElement.classList.remove('routed');

    const id = location.hash.replace('#/', '') || 'home';
    const doc = docs.find(d => d.id === id);

    home.classList.toggle('hidden', !!doc);
    shell.classList.toggle('hidden', !doc);
    docs.forEach(d => d.classList.toggle('hidden', d !== doc));

    sideLinks.forEach(a => a.setAttribute('aria-current', a.getAttribute('href') === '#/' + id ? 'page' : 'false'));
    navLinks.forEach(a => a.setAttribute('aria-current', String(a.getAttribute('href') === '#/' + id)));

    document.title = doc ? titleOf(id) + ' — NovaAjax Select' : 'NovaAjax Select';

    if (doc) {
      buildPager(doc);
      buildRail(doc);
    } else {
      sections = [];
      toc.innerHTML = '';
      startHome();
    }

    scrollTo({ top: 0, behavior: 'auto' });
    paintBar();
  }

  addEventListener('hashchange', route);

  /* landing-page animations ------------------------------------ */
  let homeStarted = false;
  function startHome() {
    if (homeStarted) return;
    homeStarted = true;

    const line = document.getElementById('posterLine');
    const words = ['Seven', 'thousand', 'options.', '<em>Twelve</em>', 'sent.'];
    line.innerHTML = words.map((w, i) => '<span class="w" style="--i:' + i + '">' + w + '</span>').join(' ');
    if (calm) [...line.children].forEach(s => { s.style.opacity = 1; s.style.transform = 'none'; });
    requestAnimationFrame(() => document.getElementById('hero').classList.add('run'));

    const tween = (ms, from, to, paint) => {
      if (calm) { paint(to); return; }
      const t0 = performance.now();
      const step = now => {
        const p = Math.min(1, (now - t0) / ms);
        paint(Math.round(from + (to - from) * (1 - Math.pow(1 - p, 3))));
        if (p < 1) requestAnimationFrame(step);
      };
      requestAnimationFrame(step);
    };

    const weigh = () => {
      const a = document.getElementById('wA'), b = document.getElementById('wB');
      tween(1300, 0, 341, v => { a.textContent = fmt(v) + ' kB'; });
      tween(1300, 0, 9, v => { b.textContent = (v / 10).toFixed(1) + ' kB'; });
    };

    const ledger = () => {
      const wrap = document.getElementById('ledRows');
      const count = document.getElementById('ledCount');
      const keep = [['Toronto','CA-ON'],['Montreal','CA-QC'],['Vancouver','CA-BC'],['Calgary','CA-AB'],['Ottawa','CA-ON'],['Halifax','CA-NS']];
      const cut = [['Vancouver','US-WA'],['Seattle','US-WA'],['Detroit','US-MI'],['Buffalo','US-NY'],['London','GB-LND'],['Paris','FR-IDF'],['Sydney','AU-NSW'],['Osaka','JP-27'],['Lagos','NG-LA'],['Mumbai','IN-MH'],['Santiago','CL-RM'],['Lisbon','PT-11']];

      /* Interleaved, so the strike-through reads as a filter and not a truncation. */
      const rows = [];
      let k = 0, c = 0;
      while (k < keep.length || c < cut.length) {
        if (c < cut.length) rows.push({ d: cut[c++], keep: false });
        if (c < cut.length) rows.push({ d: cut[c++], keep: false });
        if (k < keep.length) rows.push({ d: keep[k++], keep: true });
      }

      wrap.innerHTML = '';
      const els = rows.map(r => {
        const el = document.createElement('div');
        el.className = 'r';
        el.innerHTML = r.d[0] + '<span>' + r.d[1] + '</span>';
        wrap.appendChild(el);
        return { el, keep: r.keep };
      });

      if (calm) {
        els.forEach(e => e.el.classList.add(e.keep ? 'keep' : 'cut'));
        count.textContent = '12 rows';
        return;
      }
      els.forEach((e, n) => setTimeout(() => e.el.classList.add(e.keep ? 'keep' : 'cut'), 450 + n * 80));
      setTimeout(() => tween(1000, 7896, 12, v => { count.textContent = fmt(v) + ' rows'; }), 800);
    };

    const starters = { payload: weigh, filter: ledger };
    const once = new WeakSet();
    const io = new IntersectionObserver(es => {
      for (const e of es) {
        if (!e.isIntersecting || once.has(e.target)) continue;
        once.add(e.target);
        e.target.classList.add('seen');
        starters[e.target.id]?.();
      }
    }, { threshold: .25, rootMargin: '0px 0px -8% 0px' });
    home.querySelectorAll('.reveal').forEach(el => io.observe(el));
  }

  /* command palette -------------------------------------------- */
  const pal = document.getElementById('pal');
  const box = pal.querySelector('.box');
  const input = document.getElementById('palInput');
  const list = document.getElementById('palList');
  const openBtn = document.getElementById('openPal');

  const mac = /Mac|iPhone|iPad/.test(
    (navigator.userAgentData && navigator.userAgentData.platform) || navigator.platform || navigator.userAgent || ''
  );
  document.getElementById('kbd').textContent = mac ? '⌘K' : 'Ctrl K';
  openBtn.setAttribute('aria-keyshortcuts', mac ? 'Meta+K' : 'Control+K');

  const PAGES = [{ id: 'home', title: 'Overview' }, ...docs.map(d => ({ id: d.id, title: d.dataset.title }))];

  let rows = [], cursor = 0, lastFocus = null;

  const select = (scroll) => {
    rows.forEach((r, i) => r.li.setAttribute('aria-selected', String(i === cursor)));
    const active = rows[cursor];
    if (active) {
      input.setAttribute('aria-activedescendant', active.li.id);
      if (scroll) active.li.scrollIntoView({ block: 'nearest' });
    } else {
      input.removeAttribute('aria-activedescendant');
    }
  };

  const close = () => {
    if (pal.hidden) return;
    pal.hidden = true;
    input.removeAttribute('aria-activedescendant');
    /* Focus goes back where it came from, not blindly to the search button. */
    const back = lastFocus && document.contains(lastFocus) ? lastFocus : openBtn;
    lastFocus = null;
    back.focus();
  };
  const go = item => {
    close();
    if (item.kind === 'page') location.hash = '#/' + item.id;
    else document.getElementById(item.id)?.scrollIntoView({ behavior: calm ? 'auto' : 'smooth', block: 'start' });
  };

  const render = (q = '') => {
    const needle = q.trim().toLowerCase();
    /* Pages, plus the sections of whatever page is open. */
    const items = [
      ...PAGES.map(p => ({ label: p.title, hint: 'Page', kind: 'page', id: p.id })),
      ...sections.map(s => ({ label: s.a.textContent, hint: 'Section', kind: 'section', id: s.el.id })),
    ].filter(i => i.label.toLowerCase().includes(needle));

    list.innerHTML = ''; rows = []; cursor = 0;
    if (!items.length) {
      const none = document.createElement('li');
      none.className = 'none';
      none.textContent = 'Nothing matches “' + q + '”.';
      list.appendChild(none);
      return;
    }
    items.forEach((item, i) => {
      const li = document.createElement('li');
      li.id = 'pal-opt-' + i;
      li.setAttribute('role', 'option');
      const b = document.createElement('b'); b.textContent = item.label;
      const em = document.createElement('em'); em.textContent = item.hint;
      li.append(b, em);
      li.addEventListener('click', () => go(item));
      li.addEventListener('mousemove', () => { cursor = i; select(false); });
      list.appendChild(li);
      rows.push({ li, item });
    });
    select(false);
  };

  const show = () => {
    if (!pal.hidden) return;
    lastFocus = document.activeElement;
    pal.hidden = false;
    input.value = '';
    render();
    input.focus();
  };

  openBtn.addEventListener('click', show);
  input.addEventListener('input', () => render(input.value));
  pal.addEventListener('click', e => { if (e.target === pal) close(); });

  const FOCUSABLE = 'a[href],button:not([disabled]),input:not([disabled]),select,textarea,[tabindex]:not([tabindex="-1"])';

  /* Escape and the focus trap live on the overlay so they hold wherever
     focus is inside the dialog, not only while the input has it. */
  pal.addEventListener('keydown', e => {
    if (e.key === 'Escape') { e.preventDefault(); close(); return; }
    if (e.key !== 'Tab') return;
    const f = [...box.querySelectorAll(FOCUSABLE)];
    if (!f.length) { e.preventDefault(); return; }
    const first = f[0], last = f[f.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  });

  input.addEventListener('keydown', e => {
    if (e.key === 'ArrowDown' && rows.length) { e.preventDefault(); cursor = (cursor + 1) % rows.length; select(true); }
    else if (e.key === 'ArrowUp' && rows.length) { e.preventDefault(); cursor = (cursor - 1 + rows.length) % rows.length; select(true); }
    else if (e.key === 'Enter' && rows[cursor]) { e.preventDefault(); go(rows[cursor].item); }
  });
  addEventListener('keydown', e => {
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); pal.hidden ? show() : close(); }
    else if (e.key === '/' && pal.hidden && !/input|textarea/i.test(e.target.tagName)) { e.preventDefault(); show(); }
  });

  route();
})();
