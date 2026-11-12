(() => {
  'use strict';

  /** หน้าเพิ่มผู้ใช้งาน: เลือกบุคลากร → เติมชื่อผู้ใช้เริ่มต้น (รหัสบุคลากร) และแสดงสรุปข้อมูล */
  document.addEventListener('DOMContentLoaded', () => {
    const select = document.getElementById('f-employee_id');
    const username = document.getElementById('f-username');
    const summary = document.getElementById('employee-summary');
    if (!select || !username) return;

    let autoValue = '';
    const apply = () => {
      const opt = select.selectedOptions[0];
      const data = opt && opt.value ? opt.dataset : null;
      if (username.value === '' || username.value === autoValue) {
        autoValue = data ? data.username : '';
        username.value = autoValue;
      }
      if (summary) {
        summary.textContent = data
          ? [data.position, data.department, data.email ? `อีเมลล็อกอิน: ${data.email}` : 'ไม่มีอีเมล (ล็อกอินด้วยชื่อผู้ใช้)']
            .filter(Boolean).join(' · ')
          : '';
      }
    };
    select.addEventListener('change', apply);
    apply();
  });

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
