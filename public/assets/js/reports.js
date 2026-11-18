/* รายงานภาพรวม: ปุ่มพิมพ์ และแสดงช่องวันที่เฉพาะเมื่อเลือก "กำหนดเอง" */
(() => {
  'use strict';

  document.querySelectorAll('[data-action="print"]').forEach((button) => {
    button.addEventListener('click', () => window.print());
  });

  const period = document.getElementById('f-period');
  if (!period) return;
  const ranges = document.querySelectorAll('.custom-range');
  const sync = () => {
    const custom = period.value === 'custom';
    ranges.forEach((el) => {
      el.classList.toggle('d-none', !custom);
      el.querySelectorAll('input').forEach((input) => { input.disabled = !custom; });
    });
  };
  period.addEventListener('change', sync);
  sync();
})();
