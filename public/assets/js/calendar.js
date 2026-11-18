(() => {
  'use strict';

  /** ฟอร์มกิจกรรม: ซ่อนช่องเวลาเมื่อเลือก "ทั้งวัน" และซ่อนฝ่าย/งานเมื่อไม่ใช่ระดับฝ่าย */
  document.addEventListener('DOMContentLoaded', () => {
    const allDay = document.getElementById('f-is_all_day');
    const visibility = document.getElementById('f-visibility');
    const toggle = (selector, show) => document.querySelectorAll(selector).forEach((el) => el.classList.toggle('d-none', !show));

    const apply = () => {
      if (allDay) toggle('.js-time', !allDay.checked);
      if (visibility) toggle('.js-department', visibility.value === 'department');
    };
    if (allDay) allDay.addEventListener('change', apply);
    if (visibility) visibility.addEventListener('change', apply);
    apply();
  });
})();
