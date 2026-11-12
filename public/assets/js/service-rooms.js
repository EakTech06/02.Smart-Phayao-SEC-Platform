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
})();
