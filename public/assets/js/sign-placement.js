/**
 * หน้ากำหนดตำแหน่งลายเซ็นและลงนาม: แสดง PDF ด้วย pdf.js และกรอบลายเซ็น 4 ลำดับที่ลาก/ปรับขนาดได้
 * ตำแหน่งเก็บเป็นสัดส่วนของหน้าที่แสดง (0-1 จากมุมซ้ายบน) ส่งไปกับฟอร์มในช่อง boxes (JSON)
 */
(() => {
  const dataEl = document.getElementById('sign-data');
  if (!dataEl || !window.pdfjsLib) {
    return;
  }
  const data = JSON.parse(dataEl.textContent);
  const $ = (sel) => document.querySelector(sel);
  const stage = $('[data-sign-stage]');
  const canvas = $('[data-sign-canvas]');
  const layer = $('[data-sign-layer]');
  const loading = $('[data-sign-loading]');
  const pageInput = $('[data-sign-page-input]');
  const pageCountEl = $('[data-sign-page-count]');
  const previewBadge = $('[data-sign-preview-badge]');
  const previewExit = $('[data-sign-preview-exit]');
  const previewBtn = $('[data-sign-preview]');
  const boxInputs = [...document.querySelectorAll('[data-sign-boxes-form] input[name="boxes"], [data-sign-form] input[name="boxes"]')];

  pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.worker.min.js';

  const editable = new Set((data.editable || []).map(Number));
  const signed = new Set((data.signed || []).map(Number));
  const boxes = {};
  Object.entries(data.boxes || {}).forEach(([order, b]) => {
    boxes[Number(order)] = { page: Number(b.page), x: Number(b.x), y: Number(b.y), w: Number(b.w), h: Number(b.h) };
  });

  let doc = null;
  let previewDoc = null;
  let page = 1;
  let renderTask = null;
  const clamp = (v, min, max) => Math.min(Math.max(v, min), max);
  const round = (v) => Math.round(v * 100000) / 100000;

  const notify = (message) => {
    if (window.Swal) {
      Swal.fire({ icon: 'warning', title: message });
    } else {
      window.alert(message);
    }
  };

  /** ส่งตำแหน่งของลำดับที่แก้ได้ไปกับฟอร์ม + อัปเดตเลขหน้าในรายการด้านข้าง */
  const sync = () => {
    const out = {};
    editable.forEach((o) => {
      if (boxes[o]) {
        const b = boxes[o];
        out[o] = { page: b.page, x: round(b.x), y: round(b.y), w: round(b.w), h: round(b.h) };
      }
    });
    const json = JSON.stringify(out);
    boxInputs.forEach((input) => { input.value = json; });
    document.querySelectorAll('[data-sign-order]').forEach((li) => {
      const b = boxes[Number(li.dataset.signOrder)];
      const el = li.querySelector('[data-sign-order-page]');
      if (el && b) {
        el.textContent = String(b.page);
      }
    });
  };

  const current = () => previewDoc || doc;

  const drawBoxes = () => {
    layer.innerHTML = '';
    if (previewDoc) {
      return; // โหมดตัวอย่าง: แสดงผลจริงที่ประทับแล้ว
    }
    Object.entries(boxes).forEach(([key, b]) => {
      const order = Number(key);
      if (b.page !== page) {
        return;
      }
      const el = document.createElement('div');
      el.className = 'sign-box';
      el.dataset.order = String(order);
      el.style.setProperty('--sign-color', data.colors[order] || '#0d6efd');
      el.style.left = (b.x * 100) + '%';
      el.style.top = (b.y * 100) + '%';
      el.style.width = (b.w * 100) + '%';
      el.style.height = (b.h * 100) + '%';
      const label = document.createElement('span');
      label.className = 'sign-box-label';
      label.textContent = order + '. ' + (data.labels[order] || '');
      el.appendChild(label);
      if (signed.has(order)) {
        el.classList.add('is-signed');
        label.textContent += ' (ลงนามแล้ว)';
      } else if (editable.has(order)) {
        el.classList.add('is-editable');
        el.tabIndex = 0;
        el.setAttribute('role', 'group');
        el.setAttribute('aria-label', 'กรอบลายเซ็นลำดับที่ ' + order + ' ลากเพื่อย้าย ใช้ลูกศรเพื่อเลื่อน');
        const handle = document.createElement('span');
        handle.className = 'sign-box-handle';
        handle.setAttribute('aria-hidden', 'true');
        el.appendChild(handle);
        bindDrag(el, order, handle);
      } else {
        el.classList.add('is-locked');
      }
      layer.appendChild(el);
    });
  };

  /** ลากย้าย (ตัวกรอบ) / ปรับขนาด (มุมขวาล่าง) ด้วย Pointer Events + ปุ่มลูกศร */
  const bindDrag = (el, order, handle) => {
    let start = null;
    el.addEventListener('pointerdown', (event) => {
      if (event.button !== 0) {
        return;
      }
      event.preventDefault();
      const rect = layer.getBoundingClientRect();
      start = { mode: event.target === handle ? 'resize' : 'move', px: event.clientX, py: event.clientY, rect, box: { ...boxes[order] } };
      el.setPointerCapture(event.pointerId);
      el.classList.add('is-dragging');
    });
    el.addEventListener('pointermove', (event) => {
      if (!start) {
        return;
      }
      const dx = (event.clientX - start.px) / start.rect.width;
      const dy = (event.clientY - start.py) / start.rect.height;
      const b = boxes[order];
      if (start.mode === 'move') {
        b.x = clamp(start.box.x + dx, 0, 1 - b.w);
        b.y = clamp(start.box.y + dy, 0, 1 - b.h);
      } else {
        b.w = clamp(start.box.w + dx, data.minW, 1 - b.x);
        b.h = clamp(start.box.h + dy, data.minH, 1 - b.y);
      }
      el.style.left = (b.x * 100) + '%';
      el.style.top = (b.y * 100) + '%';
      el.style.width = (b.w * 100) + '%';
      el.style.height = (b.h * 100) + '%';
    });
    const end = () => {
      if (start) {
        start = null;
        el.classList.remove('is-dragging');
        sync();
      }
    };
    el.addEventListener('pointerup', end);
    el.addEventListener('pointercancel', end);
    el.addEventListener('keydown', (event) => {
      const step = event.shiftKey ? 0.01 : 0.0025;
      const b = boxes[order];
      const moves = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] };
      if (!moves[event.key]) {
        return;
      }
      event.preventDefault();
      b.x = clamp(b.x + moves[event.key][0], 0, 1 - b.w);
      b.y = clamp(b.y + moves[event.key][1], 0, 1 - b.h);
      el.style.left = (b.x * 100) + '%';
      el.style.top = (b.y * 100) + '%';
      sync();
    });
  };

  const render = async () => {
    const d = current();
    if (!d) {
      return;
    }
    page = clamp(page, 1, d.numPages);
    pageInput.value = String(page);
    pageInput.max = String(d.numPages);
    pageCountEl.textContent = String(d.numPages);
    const p = await d.getPage(page);
    const base = p.getViewport({ scale: 1 });
    const width = Math.max(240, stage.parentElement.clientWidth - 2);
    const scale = Math.min(width / base.width, 2.5);
    const viewport = p.getViewport({ scale });
    const ratio = window.devicePixelRatio || 1;
    canvas.width = Math.floor(viewport.width * ratio);
    canvas.height = Math.floor(viewport.height * ratio);
    canvas.style.width = Math.floor(viewport.width) + 'px';
    canvas.style.height = Math.floor(viewport.height) + 'px';
    stage.style.width = Math.floor(viewport.width) + 'px';
    stage.style.height = Math.floor(viewport.height) + 'px';
    if (renderTask) {
      renderTask.cancel();
    }
    renderTask = p.render({
      canvasContext: canvas.getContext('2d'),
      viewport,
      transform: ratio !== 1 ? [ratio, 0, 0, ratio, 0, 0] : null,
    });
    try {
      await renderTask.promise;
    } catch (e) {
      if (e && e.name === 'RenderingCancelledException') {
        return;
      }
      throw e;
    }
    drawBoxes();
  };

  const go = (n) => {
    page = n;
    render();
  };

  document.querySelector('[data-sign-prev]')?.addEventListener('click', () => go(page - 1));
  document.querySelector('[data-sign-next]')?.addEventListener('click', () => go(page + 1));
  pageInput.addEventListener('change', () => go(Number(pageInput.value) || 1));

  document.querySelectorAll('[data-sign-here]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const order = Number(btn.dataset.signHere);
      if (previewDoc) {
        exitPreview();
      }
      boxes[order].page = page;
      sync();
      drawBoxes();
      layer.querySelector('[data-order="' + order + '"]')?.focus();
    });
  });
  document.querySelectorAll('[data-sign-goto]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const order = Number(btn.dataset.signGoto);
      if (previewDoc) {
        exitPreview();
      }
      page = boxes[order].page;
      await render();
      const el = layer.querySelector('[data-order="' + order + '"]');
      if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        el.classList.add('is-flash');
        setTimeout(() => el.classList.remove('is-flash'), 1200);
      }
    });
  });

  const exitPreview = () => {
    previewDoc = null;
    previewBadge.classList.add('d-none');
    previewExit.classList.add('d-none');
    render();
  };
  previewExit.addEventListener('click', exitPreview);

  if (previewBtn) {
    previewBtn.addEventListener('click', async () => {
      const original = previewBtn.innerHTML;
      previewBtn.disabled = true;
      previewBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>กำลังสร้างตัวอย่าง';
      try {
        sync();
        const body = new FormData();
        body.append('_csrf', data.csrf);
        body.append('boxes', boxInputs[0] ? boxInputs[0].value : '{}');
        const res = await fetch(previewBtn.dataset.url, { method: 'POST', body, credentials: 'same-origin', headers: { Accept: 'application/pdf' } });
        const type = res.headers.get('Content-Type') || '';
        if (!res.ok || !type.includes('application/pdf')) {
          let message = 'สร้างตัวอย่างไม่สำเร็จ';
          try {
            const json = await res.json();
            message = json.error || (json.errors ? Object.values(json.errors).flat().join(' ') : message);
          } catch (e) { /* ไม่ใช่ JSON */ }
          notify(message);
          return;
        }
        const bytes = new Uint8Array(await res.arrayBuffer());
        previewDoc = await pdfjsLib.getDocument({ data: bytes, isEvalSupported: false }).promise;
        previewBadge.classList.remove('d-none');
        previewExit.classList.remove('d-none');
        await render();
      } catch (e) {
        notify('สร้างตัวอย่างไม่สำเร็จ: ' + e.message);
      } finally {
        previewBtn.disabled = false;
        previewBtn.innerHTML = original;
      }
    });
  }

  let resizeTimer = null;
  window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(render, 200);
  });

  sync();
  pdfjsLib.getDocument({ url: data.file, isEvalSupported: false, withCredentials: true }).promise
    .then((d) => {
      doc = d;
      loading.remove();
      const first = Math.min(...[...editable].map((o) => boxes[o]?.page || 1), d.numPages);
      page = Number.isFinite(first) ? first : 1;
      return render();
    })
    .catch((e) => {
      loading.textContent = 'เปิดเอกสารไม่สำเร็จ: ' + e.message;
    });
})();
