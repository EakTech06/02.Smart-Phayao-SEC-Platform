(() => {
  'use strict';

  /** หน้าข้อมูลบุคลากร: เลือกหลายรายการเพื่อสร้างบัญชี (ตาราง + Card List บนมือถือใช้ checkbox ชุดเดียวกัน) */
  document.addEventListener('DOMContentLoaded', () => {
    const boxes = [...document.querySelectorAll('input.js-bulk')];
    const selectAll = document.getElementById('select-all');
    const counter = document.getElementById('bulk-count');
    const submit = document.getElementById('bulk-submit');
    if (boxes.length === 0) return;

    const refresh = () => {
      const selected = new Set(boxes.filter((b) => b.checked).map((b) => b.value));
      if (counter) counter.textContent = String(selected.size);
      if (submit) submit.disabled = selected.size === 0;
      if (selectAll) {
        const unique = new Set(boxes.map((b) => b.value));
        selectAll.checked = selected.size > 0 && selected.size === unique.size;
        selectAll.indeterminate = selected.size > 0 && selected.size < unique.size;
      }
    };

    boxes.forEach((box) => {
      box.addEventListener('change', () => {
        boxes.filter((b) => b.value === box.value).forEach((b) => { b.checked = box.checked; });
        refresh();
      });
    });

    if (selectAll) {
      selectAll.addEventListener('change', () => {
        boxes.forEach((b) => { b.checked = selectAll.checked; });
        refresh();
      });
    }

    refresh();
  });
})();
