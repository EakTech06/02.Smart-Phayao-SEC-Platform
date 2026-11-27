/* สื่อนวัตกรรม: รวมจำนวนกลุ่มเป้าหมายในตาราง 9 ประเภทความพิการแบบทันที */
(() => {
  'use strict';

  const update = (name) => {
    const total = Array.from(document.querySelectorAll(`[data-target-input="${name}"]`))
      .reduce((sum, el) => sum + Math.max(0, parseInt(el.value, 10) || 0), 0);
    document.querySelectorAll(`[data-target-total="${name}"]`).forEach((el) => { el.textContent = String(total); });
  };

  document.addEventListener('input', (e) => {
    const name = e.target instanceof HTMLElement ? e.target.dataset.targetInput : undefined;
    if (name) update(name);
  });
})();
