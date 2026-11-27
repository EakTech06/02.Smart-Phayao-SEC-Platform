/*
 * ส่วนติดต่อผู้ใช้ทั่วทั้งระบบ: สลับโหมดมืด/สว่าง, เลือกชุดสี, ย่อ/ขยายเมนูด้านข้าง, ช่องค้นหา, การ์ดเอียงตามเมาส์
 * ค่าที่ผู้ใช้เลือกเก็บใน localStorage (ui.theme, ui.palette, ui.sidebar) และใช้ก่อนแสดงผลโดย theme-init.js
 * เมื่อธีม/ชุดสีเปลี่ยนจะส่งเหตุการณ์ "ui:themechange" ให้ส่วนอื่น (เช่น กราฟ) อัปเดตสีทันที
 */
(() => {
  'use strict';

  const root = document.documentElement;
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const store = {
    get: (key) => { try { return localStorage.getItem(key); } catch (e) { return null; } },
    set: (key, value) => { try { localStorage.setItem(key, value); } catch (e) { /* private mode */ } },
  };

  /** เปลี่ยนค่าบน <html> แบบมีทรานซิชันสั้น ๆ แล้วแจ้งส่วนอื่น */
  const applyAttr = (attr, value) => {
    if (!reduceMotion.matches) {
      root.classList.add('theme-transition');
      window.clearTimeout(applyAttr.timer);
      applyAttr.timer = window.setTimeout(() => root.classList.remove('theme-transition'), 350);
    }
    root.setAttribute(attr, value);
    document.dispatchEvent(new CustomEvent('ui:themechange', {
      detail: { theme: root.getAttribute('data-bs-theme'), palette: root.getAttribute('data-palette') },
    }));
  };

  // ---------------------------------------------------------------- โหมดมืด/สว่าง
  const syncThemeButtons = () => {
    const dark = root.getAttribute('data-bs-theme') === 'dark';
    document.querySelectorAll('[data-theme-toggle]').forEach((btn) => {
      btn.setAttribute('aria-pressed', dark ? 'true' : 'false');
      btn.setAttribute('title', dark ? 'เปลี่ยนเป็นโหมดสว่าง' : 'เปลี่ยนเป็นโหมดมืด');
    });
  };
  document.addEventListener('click', (event) => {
    const btn = event.target.closest('[data-theme-toggle]');
    if (!btn) return;
    const next = root.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
    store.set('ui.theme', next);
    applyAttr('data-bs-theme', next);
    syncThemeButtons();
  });

  // ---------------------------------------------------------------- ชุดสี
  const syncPalette = () => {
    const current = root.getAttribute('data-palette');
    document.querySelectorAll('[data-palette-option]').forEach((opt) => {
      opt.setAttribute('aria-checked', opt.dataset.paletteOption === current ? 'true' : 'false');
    });
  };
  document.addEventListener('click', (event) => {
    const opt = event.target.closest('[data-palette-option]');
    if (!opt) return;
    store.set('ui.palette', opt.dataset.paletteOption);
    applyAttr('data-palette', opt.dataset.paletteOption);
    syncPalette();
  });

  // ---------------------------------------------------------------- เมนูด้านข้าง ย่อ/ขยาย
  const syncSidebar = () => {
    const mini = root.getAttribute('data-sidebar') === 'mini';
    // โหมดย่อแสดงเฉพาะไอคอน จึงใส่ title ให้เห็นชื่อเมนูเมื่อชี้
    document.querySelectorAll('.app-sidebar .nav-link[data-menu-label]').forEach((link) => {
      if (mini) link.setAttribute('title', link.dataset.menuLabel); else link.removeAttribute('title');
    });
    document.querySelectorAll('[data-sidebar-toggle]').forEach((btn) => {
      btn.setAttribute('aria-expanded', mini ? 'false' : 'true');
      const icon = btn.querySelector('.bi');
      if (icon) icon.className = mini ? 'bi bi-layout-sidebar' : 'bi bi-layout-sidebar-inset';
    });
  };
  document.addEventListener('click', (event) => {
    if (!event.target.closest('[data-sidebar-toggle]')) return;
    const next = root.getAttribute('data-sidebar') === 'mini' ? 'full' : 'mini';
    store.set('ui.sidebar', next);
    root.setAttribute('data-sidebar', next);
    syncSidebar();
    // กราฟ/ตารางที่คำนวณความกว้างไว้ให้ปรับตามพื้นที่ใหม่หลังทรานซิชันจบ
    window.setTimeout(() => window.dispatchEvent(new Event('resize')), 320);
  });

  // ---------------------------------------------------------------- ช่องค้นหา (เมนู + ส่งคำค้นไปหน้ารายการ)
  const initSearch = () => {
    const input = document.getElementById('global-search');
    const box = document.getElementById('global-search-results');
    const bar = document.getElementById('app-topbar');
    if (!input || !box) return;
    let targets = [];
    try { targets = JSON.parse(input.dataset.targets || '[]'); } catch (e) { targets = []; }
    const menu = [...document.querySelectorAll('.app-sidebar .nav-link[data-menu-label]')].map((a) => ({
      label: a.dataset.menuLabel, group: a.dataset.menuGroup, icon: a.dataset.menuIcon, url: a.href,
    }));
    const norm = (s) => s.toLowerCase().replace(/\s+/g, '');
    let items = [];
    let index = -1;

    const close = () => {
      box.classList.remove('show');
      input.setAttribute('aria-expanded', 'false');
      index = -1;
    };
    const highlight = (i) => {
      const links = box.querySelectorAll('.dropdown-item');
      links.forEach((el, n) => {
        el.classList.toggle('active', n === i);
        el.setAttribute('aria-selected', n === i ? 'true' : 'false');
      });
      index = i;
      if (links[i]) links[i].scrollIntoView({ block: 'nearest' });
    };
    const el = (tag, cls, text) => {
      const node = document.createElement(tag);
      if (cls) node.className = cls;
      if (text !== undefined) node.textContent = text;
      return node;
    };
    const addItem = (item) => {
      const a = el('a', 'dropdown-item');
      a.href = item.url;
      a.setAttribute('role', 'option');
      a.appendChild(el('i', 'bi bi-' + item.icon));
      const text = el('span', 'flex-grow-1');
      text.appendChild(document.createTextNode(item.label));
      if (item.hint) text.appendChild(el('small', 'd-block text-body-secondary', item.hint));
      a.appendChild(text);
      box.appendChild(a);
      items.push(item);
    };
    const render = () => {
      const q = input.value.trim();
      box.replaceChildren();
      items = [];
      const hits = q === '' ? menu.slice(0, 8) : menu.filter((m) => norm(m.label + m.group).includes(norm(q))).slice(0, 8);
      if (hits.length) {
        box.appendChild(el('div', 'result-group', q === '' ? 'เมนู' : 'เมนูที่ตรงกัน'));
        hits.forEach((m) => addItem({ ...m, hint: m.group }));
      }
      if (q !== '' && targets.length) {
        box.appendChild(el('div', 'result-group', 'ค้นหาข้อมูล'));
        targets.forEach((t) => addItem({ label: `${t.label}: “${q}”`, icon: t.icon, url: t.url + encodeURIComponent(q) }));
      }
      if (!items.length) box.appendChild(el('div', 'result-group py-3 text-center', 'ไม่พบเมนูที่ตรงกัน'));
      box.classList.add('show');
      input.setAttribute('aria-expanded', 'true');
      highlight(q !== '' && items.length ? 0 : -1);
    };

    input.addEventListener('focus', render);
    input.addEventListener('input', render);
    input.addEventListener('keydown', (event) => {
      if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        if (!items.length) return;
        const step = event.key === 'ArrowDown' ? 1 : -1;
        highlight((index + step + items.length) % items.length);
      } else if (event.key === 'Enter') {
        const target = items[index >= 0 ? index : 0];
        if (target) { event.preventDefault(); window.location.href = target.url; }
      } else if (event.key === 'Escape') {
        close();
        input.blur();
        if (bar) bar.classList.remove('search-open');
      }
    });
    document.addEventListener('click', (event) => {
      if (!event.target.closest('.topbar-search') && !event.target.closest('[data-search-open]')) {
        close();
        if (bar) bar.classList.remove('search-open');
      }
    });
    document.addEventListener('keydown', (event) => {
      const typing = /^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement?.tagName || '') || document.activeElement?.isContentEditable;
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        if (bar) bar.classList.add('search-open');
        input.focus();
      } else if (event.key === '/' && !typing) {
        event.preventDefault();
        input.focus();
      }
    });
    document.querySelectorAll('[data-search-open]').forEach((btn) => btn.addEventListener('click', () => {
      if (bar) bar.classList.add('search-open');
      input.focus();
    }));
  };

  // ---------------------------------------------------------------- การ์ดเอียงตามเมาส์ (3D tilt)
  const initTilt = () => {
    const fine = window.matchMedia('(hover: hover) and (pointer: fine)');
    const MAX = 7; // องศา
    document.querySelectorAll('[data-tilt]').forEach((card) => {
      let frame = 0;
      card.addEventListener('pointermove', (event) => {
        if (reduceMotion.matches || !fine.matches) return;
        const r = card.getBoundingClientRect();
        const x = (event.clientX - r.left) / r.width - 0.5;
        const y = (event.clientY - r.top) / r.height - 0.5;
        cancelAnimationFrame(frame);
        frame = requestAnimationFrame(() => {
          card.classList.add('is-tilting');
          card.style.setProperty('--rx', `${(-y * MAX).toFixed(2)}deg`);
          card.style.setProperty('--ry', `${(x * MAX).toFixed(2)}deg`);
        });
      });
      card.addEventListener('pointerleave', () => {
        cancelAnimationFrame(frame);
        card.classList.remove('is-tilting');
        card.style.setProperty('--rx', '0deg');
        card.style.setProperty('--ry', '0deg');
      });
    });
  };

  // ---------------------------------------------------------------- ตัวเลขนับขึ้น (การ์ดสถิติ)
  const initCountUp = () => {
    const nf = new Intl.NumberFormat('th-TH');
    document.querySelectorAll('[data-count]').forEach((node) => {
      const target = Number(node.dataset.count);
      if (!Number.isFinite(target)) return;
      if (reduceMotion.matches || target === 0) { node.textContent = nf.format(target); return; }
      const start = performance.now();
      const dur = 700;
      const tick = (now) => {
        const t = Math.min(1, (now - start) / dur);
        node.textContent = nf.format(Math.round(target * (1 - Math.pow(1 - t, 3))));
        if (t < 1) requestAnimationFrame(tick);
      };
      requestAnimationFrame(tick);
    });
  };

  document.addEventListener('DOMContentLoaded', () => {
    syncThemeButtons();
    syncPalette();
    syncSidebar();
    initSearch();
    initTilt();
    initCountUp();
  });
})();
