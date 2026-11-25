/* ทะเบียนนักเรียน: แสดง/ซ่อนช่องตามตัวเลือก และกรองห้องบริการตามปีการศึกษา */
(() => {
  'use strict';

  const LEFT_STATUSES = ['graduated', 'transferred', 'discharged', 'deceased'];
  const toggle = (el, show) => el && el.classList.toggle('d-none', !show);

  // ประเภทเอกสารแสดงตน → ช่องเลขบัตร/เลข G
  document.querySelectorAll('[data-id-type]').forEach((select) => {
    const sync = () => {
      document.querySelectorAll('.id-field').forEach((el) => toggle(el, el.classList.contains('id-' + select.value)));
    };
    select.addEventListener('change', sync);
    sync();
  });

  // สถานะที่ออกจากการรับบริการแล้ว → วันที่/สาเหตุ
  document.querySelectorAll('[data-status]').forEach((select) => {
    const sync = () => document.querySelectorAll('.left-fields').forEach((el) => toggle(el, LEFT_STATUSES.includes(select.value)));
    select.addEventListener('change', sync);
    sync();
  });

  // checkbox "ที่อยู่ปัจจุบันตามทะเบียนบ้าน"
  document.querySelectorAll('[data-toggle-target]').forEach((box) => {
    const target = document.querySelector(box.dataset.toggleTarget);
    const sync = () => toggle(target, !box.checked);
    box.addEventListener('change', sync);
    sync();
  });

  // ห้องบริการแสดงเฉพาะของปีการศึกษาที่เลือก
  const year = document.querySelector('[data-enroll-year]');
  const room = document.querySelector('[data-room-select]');
  if (year && room) {
    const sync = () => {
      Array.from(room.options).forEach((opt) => {
        if (!opt.dataset.year) return;
        const match = opt.dataset.year === year.value;
        opt.hidden = !match;
        opt.disabled = !match;
        if (!match && opt.selected) room.value = '';
      });
    };
    year.addEventListener('change', sync);
    sync();
  }

  // เลือกทั้งหมด (หน้ายกยอดปีการศึกษา)
  document.querySelectorAll('[data-check-all]').forEach((master) => {
    const boxes = () => document.querySelectorAll('input[type="checkbox"][name="' + master.dataset.checkAll + '"]');
    master.addEventListener('change', () => boxes().forEach((b) => { b.checked = master.checked; }));
    const sync = () => {
      const list = Array.from(boxes());
      master.checked = list.length > 0 && list.every((b) => b.checked);
      master.indeterminate = !master.checked && list.some((b) => b.checked);
    };
    boxes().forEach((b) => b.addEventListener('change', sync));
    sync();
  });

  // ประเภทความพิการ "พิการซ้อน" → เลือกลักษณะความพิการ, ระดับเฉพาะการเห็น/การได้ยิน
  const disability = document.querySelector('[data-disability-type]');
  if (disability) {
    const level = document.querySelector('[data-disability-level]');
    const levelGroup = { blind: 'visual', low_vision: 'visual', deaf: 'hearing', hard_of_hearing: 'hearing' };
    const sync = () => {
      document.querySelectorAll('.multiple-fields').forEach((el) => toggle(el, disability.value === 'multiple'));
      if (level) {
        Array.from(level.options).forEach((opt) => {
          if (!opt.value) return;
          const ok = levelGroup[opt.value] === disability.value;
          opt.hidden = !ok;
          opt.disabled = !ok;
          if (!ok && opt.selected) level.value = '';
        });
      }
    };
    disability.addEventListener('change', sync);
    sync();
  }
})();
