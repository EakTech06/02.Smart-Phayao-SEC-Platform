/*
 * ช่องวันที่แบบ พ.ศ. (แทน <input type="date"> ของเบราว์เซอร์ซึ่งแสดงปี ค.ศ.)
 *
 * - ช่องเดิมถูกเปลี่ยนเป็น hidden และยังเก็บค่า YYYY-MM-DD (ค.ศ.) ส่งให้ server เหมือนเดิม
 * - ผู้ใช้เห็น/พิมพ์ วว/ดด/ปปปป (พ.ศ.) และเลือกจากปฏิทินได้ (พิมพ์ปี ค.ศ. ก็ได้ ระบบแปลงให้)
 * - JS อื่นที่อ่าน/ตั้งค่า .value ของช่องเดิม หรือฟัง change/input ยังทำงานได้ (ตั้ง .value แล้วช่องที่แสดงเปลี่ยนตาม)
 * - ไม่ต้องการแปลง: ใส่ data-native-date ที่ช่องนั้น
 */
(() => {
  'use strict';

  const MONTHS = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
    'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
  const DAYS = ['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส'];
  const BE = 543;
  const valueProp = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
  const pad = (n) => String(n).padStart(2, '0');

  /** YYYY-MM-DD → {y, m, d} (ค.ศ.) หรือ null */
  const parseIso = (s) => {
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s || '');
    if (!m) return null;
    const y = +m[1]; const mo = +m[2]; const d = +m[3];
    const dt = new Date(y, mo - 1, d);
    return dt.getFullYear() === y && dt.getMonth() === mo - 1 && dt.getDate() === d ? { y, m: mo, d } : null;
  };
  const toIso = (p) => `${p.y}-${pad(p.m)}-${pad(p.d)}`;
  const toThai = (p) => `${pad(p.d)}/${pad(p.m)}/${p.y + BE}`;

  /** วว/ดด/ปปปป (พ.ศ. หรือ ค.ศ.) → {y, m, d} ค.ศ. | null | undefined (ว่าง) */
  const parseThai = (s) => {
    const t = (s || '').trim();
    if (t === '') return undefined;
    const m = /^(\d{1,2})[/.\-\s](\d{1,2})[/.\-\s](\d{4})$/.exec(t) || /^(\d{2})(\d{2})(\d{4})$/.exec(t);
    if (!m) return null;
    let y = +m[3];
    if (y > 2400) y -= BE; // ปี พ.ศ.
    return parseIso(`${y}-${pad(+m[2])}-${pad(+m[1])}`);
  };
  const today = () => {
    const n = new Date();
    return { y: n.getFullYear(), m: n.getMonth() + 1, d: n.getDate() };
  };

  let openPicker = null;
  const closeOpen = () => {
    if (openPicker) openPicker.close();
  };
  document.addEventListener('mousedown', (e) => {
    if (openPicker && !openPicker.wrap.contains(e.target)) closeOpen();
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeOpen();
  });

  const enhance = (orig) => {
    if (orig.dataset.thaiDate || orig.hasAttribute('data-native-date')) return;
    orig.dataset.thaiDate = '1';
    const min = parseIso(orig.min);
    const max = parseIso(orig.max);
    const small = orig.classList.contains('form-control-sm');

    // ช่องที่ผู้ใช้เห็น
    const wrap = document.createElement('div');
    wrap.className = 'input-group thai-date' + (small ? ' input-group-sm' : '');
    const text = document.createElement('input');
    text.type = 'text';
    text.inputMode = 'numeric';
    text.autocomplete = 'off';
    text.maxLength = 10;
    text.placeholder = orig.placeholder || 'วว/ดด/ปปปป';
    text.className = orig.className;
    text.title = 'วัน/เดือน/ปี พ.ศ.';
    if (orig.id) {
      text.id = orig.id + '-th';
      document.querySelectorAll(`label[for="${CSS.escape(orig.id)}"]`).forEach((l) => { l.htmlFor = text.id; });
    }
    if (orig.required) text.required = true;
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-outline-secondary';
    btn.tabIndex = -1;
    btn.setAttribute('aria-label', 'เลือกวันที่จากปฏิทิน');
    btn.innerHTML = '<i class="bi bi-calendar3"></i>';
    const pop = document.createElement('div');
    pop.className = 'dropdown-menu thai-date-pop p-0 shadow'; // dropdown-menu: ไม่กระทบมุมโค้งของ input-group
    pop.setAttribute('role', 'dialog');

    orig.after(wrap); // ช่องเดิม (hidden) อยู่นอก input-group, .invalid-feedback ยังเป็น sibling ของ wrap
    wrap.append(text, btn, pop);
    valueProp.set.call(orig, valueProp.get.call(orig)); // คงค่าเดิม
    orig.type = 'hidden';

    const render = () => {
      const p = parseIso(valueProp.get.call(orig));
      text.value = p ? toThai(p) : '';
      text.classList.remove('thai-date-bad');
    };
    // JS อื่นตั้งค่า orig.value = '2020-01-01' → ช่องที่แสดงเปลี่ยนตาม
    Object.defineProperty(orig, 'value', {
      configurable: true,
      get() { return valueProp.get.call(this); },
      set(v) { valueProp.set.call(this, v); render(); },
    });
    // สถานะ readonly/disabled/class ของช่องเดิม (เช่น ล็อกเมื่อผู้ปกครองคือบิดา) → ช่องที่แสดง
    const mirror = () => {
      text.readOnly = orig.readOnly;
      text.disabled = orig.disabled;
      btn.disabled = orig.readOnly || orig.disabled;
      ['is-invalid', 'bg-body-tertiary'].forEach((c) => text.classList.toggle(c, orig.classList.contains(c)));
      wrap.classList.toggle('is-invalid', orig.classList.contains('is-invalid'));
    };
    new MutationObserver(mirror).observe(orig, { attributes: true, attributeFilter: ['readonly', 'disabled', 'class'] });
    mirror();
    render();

    const commit = (iso) => {
      if (valueProp.get.call(orig) === iso) return;
      valueProp.set.call(orig, iso);
      orig.dispatchEvent(new Event('input', { bubbles: true }));
      orig.dispatchEvent(new Event('change', { bubbles: true }));
    };
    const outOfRange = (p) => (min && toIso(p) < toIso(min)) || (max && toIso(p) > toIso(max));

    // พิมพ์: ใส่ / ให้อัตโนมัติ, ออกจากช่อง = ตรวจและแปลง
    text.addEventListener('input', () => {
      const digits = text.value.replace(/\D/g, '').slice(0, 8);
      if (/^\d+$/.test(text.value.replace(/\//g, '')) && text.value.length >= (text.dataset.prevLen || 0)) {
        let v = digits.slice(0, 2);
        if (digits.length > 2) v += '/' + digits.slice(2, 4);
        if (digits.length > 4) v += '/' + digits.slice(4);
        if ((digits.length === 2 || digits.length === 4) && text.value.length < 10) v += '/';
        text.value = v;
      }
      text.dataset.prevLen = text.value.length;
    });
    const finish = () => {
      const p = parseThai(text.value);
      if (p === undefined) {
        commit('');
        text.classList.remove('thai-date-bad');
      } else if (p === null || outOfRange(p)) {
        text.classList.add('thai-date-bad');
        text.title = p === null ? 'รูปแบบวันที่ไม่ถูกต้อง (วว/ดด/ปปปป พ.ศ.)' : 'วันที่อยู่นอกช่วงที่กำหนด';
        commit('');
      } else {
        commit(toIso(p));
        render();
        text.title = 'วัน/เดือน/ปี พ.ศ.';
      }
    };
    text.addEventListener('change', finish);
    text.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') finish();
      if (e.key === 'ArrowDown' && e.altKey) { e.preventDefault(); toggle(); }
    });

    // ปฏิทิน
    let view = null;
    const yearRange = () => {
      const t = today();
      const from = min ? min.y : t.y - 100;
      const to = max ? max.y : t.y + 10;
      return [from, to];
    };
    const draw = () => {
      const sel = parseIso(valueProp.get.call(orig));
      const t = today();
      const [yFrom, yTo] = yearRange();
      const first = new Date(view.y, view.m - 1, 1);
      const daysIn = new Date(view.y, view.m, 0).getDate();
      let html = '<div class="p-2">'
        + '<div class="d-flex align-items-center gap-1 mb-2">'
        + '<button type="button" class="btn btn-sm btn-light" data-nav="-1" aria-label="เดือนก่อน"><i class="bi bi-chevron-left"></i></button>'
        + '<select class="form-select form-select-sm" data-month aria-label="เดือน">'
        + MONTHS.map((n, i) => `<option value="${i + 1}"${i + 1 === view.m ? ' selected' : ''}>${n}</option>`).join('')
        + '</select><select class="form-select form-select-sm thai-date-year" data-year aria-label="ปี พ.ศ.">';
      for (let y = yTo; y >= yFrom; y -= 1) html += `<option value="${y}"${y === view.y ? ' selected' : ''}>${y + BE}</option>`;
      html += '</select><button type="button" class="btn btn-sm btn-light" data-nav="1" aria-label="เดือนถัดไป"><i class="bi bi-chevron-right"></i></button></div>'
        + '<div class="thai-date-grid">' + DAYS.map((d, i) => `<div class="thai-date-dow${i === 0 ? ' text-danger' : ''}">${d}</div>`).join('');
      for (let i = 0; i < first.getDay(); i += 1) html += '<div></div>';
      for (let d = 1; d <= daysIn; d += 1) {
        const p = { y: view.y, m: view.m, d };
        const cls = ['btn', 'btn-sm', 'thai-date-day'];
        if (sel && toIso(sel) === toIso(p)) cls.push('btn-primary');
        else if (toIso(t) === toIso(p)) cls.push('btn-outline-primary');
        else cls.push('btn-light');
        html += `<button type="button" class="${cls.join(' ')}" data-day="${d}"${outOfRange(p) ? ' disabled' : ''}>${d}</button>`;
      }
      html += '</div><div class="d-flex justify-content-between mt-2">'
        + '<button type="button" class="btn btn-sm btn-link px-0" data-today>วันนี้</button>'
        + '<button type="button" class="btn btn-sm btn-link px-0 text-danger" data-clear>ล้าง</button></div></div>';
      pop.innerHTML = html;
    };
    const picker = {
      wrap,
      close() {
        pop.classList.remove('show');
        if (openPicker === picker) openPicker = null;
      },
    };
    const toggle = () => {
      if (pop.classList.contains('show')) { picker.close(); return; }
      closeOpen();
      const p = parseIso(valueProp.get.call(orig)) || parseThai(text.value) || today();
      view = { y: p.y, m: p.m };
      draw();
      pop.classList.add('show');
      // เปิดขึ้นด้านบนถ้าด้านล่างไม่พอ
      pop.classList.toggle('thai-date-up', wrap.getBoundingClientRect().bottom + pop.offsetHeight > window.innerHeight
        && wrap.getBoundingClientRect().top > pop.offsetHeight);
      openPicker = picker;
    };
    btn.addEventListener('click', toggle);
    pop.addEventListener('click', (e) => {
      const el = e.target.closest('button');
      if (!el) return;
      if (el.dataset.nav) {
        view.m += +el.dataset.nav;
        if (view.m < 1) { view.m = 12; view.y -= 1; }
        if (view.m > 12) { view.m = 1; view.y += 1; }
        draw();
      } else if (el.dataset.day) {
        commit(toIso({ y: view.y, m: view.m, d: +el.dataset.day }));
        render();
        picker.close();
        text.focus();
      } else if (el.hasAttribute('data-today')) {
        commit(toIso(today()));
        render();
        picker.close();
      } else if (el.hasAttribute('data-clear')) {
        commit('');
        render();
        picker.close();
      }
    });
    pop.addEventListener('change', (e) => {
      if (e.target.matches('[data-month]')) view.m = +e.target.value;
      if (e.target.matches('[data-year]')) view.y = +e.target.value;
      draw();
    });
  };

  const run = (root) => root.querySelectorAll('input[type="date"]').forEach(enhance);
  run(document);
  // ช่องวันที่ที่ถูกเพิ่มทีหลัง (เช่น โคลนแถวในฟอร์ม)
  new MutationObserver((list) => list.forEach((m) => m.addedNodes.forEach((n) => {
    if (n.nodeType === 1) {
      if (n.matches('input[type="date"]')) enhance(n);
      else run(n);
    }
  }))).observe(document.body, { childList: true, subtree: true });
  window.ThaiDate = { enhance, parseThai, toThai };
})();
