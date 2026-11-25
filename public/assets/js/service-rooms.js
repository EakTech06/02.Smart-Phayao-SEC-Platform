(() => {
  'use strict';

  /** ฟอร์มห้องบริการ: เปลี่ยนปีการศึกษา → แนะนำรหัสถัดไปของปีนั้น (ถ้าผู้ใช้ยังไม่ได้แก้รหัสเอง) */
  document.addEventListener('DOMContentLoaded', () => {
    const year = document.getElementById('f-academic_year_id');
    const code = document.getElementById('f-room_code');
    if (!year || !code) return;

    let suggested = code.dataset.auto === '1' ? code.value : null;
    year.addEventListener('change', () => {
      const next = year.selectedOptions[0] ? year.selectedOptions[0].dataset.nextCode : '';
      if (suggested !== null && code.value === suggested && next) {
        code.value = next;
        suggested = next;
      }
    });
    code.addEventListener('input', () => {
      code.value = code.value.toUpperCase();
    });
  });

  /** ครูประจำชั้นหลายคน: เพิ่ม/เอาออกแถว (เหลืออย่างน้อย 1 แถว, ไม่เกิน data-max) */
  document.addEventListener('DOMContentLoaded', () => {
    const list = document.querySelector('[data-teacher-list]');
    const add = document.querySelector('[data-teacher-add]');
    if (!list || !add) return;
    const max = parseInt(list.dataset.max || '5', 10);
    const rows = () => Array.from(list.querySelectorAll('[data-teacher-row]'));
    const yearSel = document.getElementById('f-academic_year_id');
    const hasRoom = (opt, year) => {
      if (!opt.dataset.rooms || year === '') return false;
      try {
        return Object.prototype.hasOwnProperty.call(JSON.parse(opt.dataset.rooms), year);
      } catch (err) {
        return false;
      }
    };

    const refresh = () => {
      const all = rows();
      const selects = all.map((row) => row.querySelector('select'));
      all.forEach((row, i) => {
        row.querySelector('[data-teacher-no]').textContent = String(i + 1);
        selects[i].setAttribute('aria-label', 'ครูประจำชั้นคนที่ ' + (i + 1));
      });
      // ซ่อน: ครูที่ถูกเลือกในแถวอื่นแล้ว และครูที่เป็นครูประจำชั้นห้องอื่นในปีการศึกษาที่เลือก
      // (ยกเว้นค่าที่เลือกค้างอยู่ในแถวนั้น เพื่อไม่ให้ค่าหาย ระบบจะแจ้งข้อผิดพลาดตอนบันทึก)
      const year = yearSel ? yearSel.value : '';
      selects.forEach((sel) => {
        const taken = new Set(selects.filter((s) => s !== sel && s.value !== '').map((s) => s.value));
        Array.from(sel.options).forEach((opt) => {
          const hide = opt.value !== '' && opt.value !== sel.value && (taken.has(opt.value) || hasRoom(opt, year));
          opt.hidden = hide;
          opt.disabled = hide;
        });
      });
      // เพิ่มได้เมื่อยังไม่ถึงจำนวนสูงสุด และยังมีครูให้เลือกเหลืออยู่
      const free = selects[0]
        ? Array.from(selects[0].options).filter((o) => o.value !== '' && !hasRoom(o, year)).length : 0;
      add.disabled = all.length >= max || all.length >= free;
    };

    add.addEventListener('click', () => {
      if (rows().length >= max) return;
      const clone = rows()[0].cloneNode(true);
      clone.querySelector('select').value = '';
      list.appendChild(clone);
      refresh();
      clone.querySelector('select').focus();
    });
    list.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-teacher-remove]');
      if (!btn) return;
      const row = btn.closest('[data-teacher-row]');
      if (rows().length > 1) {
        row.remove();
      } else {
        row.querySelector('select').value = '';
      }
      refresh();
    });
    list.addEventListener('change', (e) => {
      if (e.target.matches('select')) refresh();
    });
    if (yearSel) yearSel.addEventListener('change', refresh);
    refresh();
  });
})();
