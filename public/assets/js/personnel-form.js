(() => {
  'use strict';

  /** ฟอร์มข้อมูลบุคลากร: ช่องคำนวณอายุ/อายุราชการ/อายุงาน และรายการข้อมูลการศึกษาหลายระดับ */
  document.addEventListener('DOMContentLoaded', () => {
    // ระยะเวลา "x ปี y เดือน" จากช่อง data-duration-from ถึงวันนี้ (หรือถึงช่อง data-duration-to ถ้ามีค่าและไม่เกินวันนี้)
    const parse = (v) => (v ? new Date(v + 'T00:00:00') : null);
    document.querySelectorAll('[data-duration-from]').forEach((out) => {
      const from = document.getElementById(out.dataset.durationFrom);
      const to = out.dataset.durationTo ? document.getElementById(out.dataset.durationTo) : null;
      if (!from) return;
      const calc = () => {
        const start = parse(from.value);
        if (!start || Number.isNaN(start.getTime())) { out.value = ''; return; }
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        let end = to ? parse(to.value) : null;
        if (!end || Number.isNaN(end.getTime()) || end > today) end = today;
        if (start > end) { out.value = '-'; return; }
        let y = end.getFullYear() - start.getFullYear();
        let m = end.getMonth() - start.getMonth();
        if (end.getDate() < start.getDate()) m -= 1;
        if (m < 0) { y -= 1; m += 12; }
        out.value = y + ' ปี ' + m + ' เดือน';
      };
      [from, to].filter(Boolean).forEach((el) => {
        el.addEventListener('change', calc);
        el.addEventListener('input', calc);
      });
      calc();
    });

    // วันหมดอายุใบประกอบวิชาชีพครู: หมดอายุ/ใกล้หมดอายุแสดงข้อความสีแดง (ตรงกับ PersonnelService::licenseStatus)
    const licenseNote = document.querySelector('[data-license-status]');
    const licenseInput = document.getElementById('f-license_expiry_date');
    if (licenseNote && licenseInput) {
      const warn = Number(licenseNote.dataset.warnDays || 90);
      const update = () => {
        const d = parse(licenseInput.value);
        licenseNote.classList.remove('text-danger', 'text-success');
        if (!d || Number.isNaN(d.getTime())) { licenseNote.textContent = ''; return; }
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const days = Math.round((d - today) / 86400000);
        if (days < 0) {
          licenseNote.textContent = 'ใบประกอบวิชาชีพครูหมดอายุแล้ว ' + Math.abs(days) + ' วัน กรุณาต่ออายุ';
        } else if (days <= warn) {
          licenseNote.textContent = days === 0 ? 'ใบประกอบวิชาชีพครูหมดอายุวันนี้' : 'ใบประกอบวิชาชีพครูจะหมดอายุในอีก ' + days + ' วัน';
        } else {
          licenseNote.textContent = 'ใบประกอบวิชาชีพครูยังไม่หมดอายุ';
        }
        licenseNote.classList.add(days <= warn ? 'text-danger' : 'text-success');
      };
      licenseInput.addEventListener('change', update);
      licenseInput.addEventListener('input', update);
    }

    // ข้อมูลการศึกษา
    const list = document.querySelector('[data-edu-list]');
    const addBtn = document.querySelector('[data-edu-add]');
    if (!list) return;
    const max = Number(list.dataset.eduMax || 20);
    const noMajor = (list.dataset.eduNoMajor || '').split(',');

    // ต่ำกว่าปริญญาตรี: ปิดช่องสาขาวิชา (ฝั่ง server ล้างค่าให้อยู่แล้ว)
    const applyLevel = (row) => {
      const level = row.querySelector('[data-edu-level]');
      const major = row.querySelector('[data-edu-major]');
      const note = row.querySelector('[data-edu-major-note]');
      const off = noMajor.includes(level.value);
      major.disabled = off;
      if (off) major.value = '';
      note.classList.toggle('d-none', !off);
    };

    // ตั้งชื่อ educations[i][...] ใหม่ตามลำดับ และปิดปุ่มเพิ่มเมื่อครบจำนวน
    const renumber = () => {
      const rows = list.querySelectorAll('[data-edu-row]');
      rows.forEach((row, i) => {
        row.querySelectorAll('[name^="educations["]').forEach((el) => {
          el.name = el.name.replace(/^educations\[\d+\]/, 'educations[' + i + ']');
        });
      });
      if (addBtn) addBtn.disabled = rows.length >= max;
    };

    const bind = (row) => {
      row.querySelector('[data-edu-level]').addEventListener('change', () => applyLevel(row));
      row.querySelector('[data-edu-remove]').addEventListener('click', () => {
        if (list.querySelectorAll('[data-edu-row]').length > 1) {
          row.remove();
        } else {
          // แถวสุดท้าย: ล้างค่าแทนการลบ (แถวว่างไม่ถูกบันทึก)
          row.querySelectorAll('input, select').forEach((el) => { el.value = ''; });
          applyLevel(row);
        }
        renumber();
      });
      applyLevel(row);
    };

    list.querySelectorAll('[data-edu-row]').forEach(bind);
    if (addBtn) {
      addBtn.addEventListener('click', () => {
        const rows = list.querySelectorAll('[data-edu-row]');
        if (rows.length >= max) return;
        const clone = rows[rows.length - 1].cloneNode(true);
        clone.querySelectorAll('input, select').forEach((el) => { el.value = ''; el.disabled = false; });
        list.appendChild(clone);
        bind(clone);
        renumber();
        clone.querySelector('[data-edu-level]').focus();
      });
    }
    renumber();
  });
})();
