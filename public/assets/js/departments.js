/* ข้อมูลฝ่าย/งาน: สวิตช์เปิด-ปิดการใช้งาน (ปิด = ถามยืนยันก่อน, ยกเลิก = สวิตช์กลับที่เดิม) */
(() => {
  'use strict';

  document.querySelectorAll('form[data-department-toggle]').forEach((form) => {
    const box = form.querySelector('input[type="checkbox"]');
    if (!box) return;
    box.addEventListener('change', () => {
      if (box.checked) {
        form.submit();
        return;
      }
      const n = parseInt(form.dataset.employees || '0', 10);
      const text = 'ฝ่าย/งานที่ปิดจะไม่แสดงในตัวเลือกของฟอร์มต่าง ๆ'
        + (n > 0 ? ` (บุคลากรที่สังกัดอยู่ ${n} คน ยังคงสังกัดเดิม)` : '');
      const revert = () => { box.checked = true; };
      if (window.Swal) {
        Swal.fire({
          title: 'ปิดการใช้งาน ' + form.dataset.name + '?', text, icon: 'question', showCancelButton: true,
          confirmButtonText: 'ปิดการใช้งาน', cancelButtonText: 'ยกเลิก',
        }).then((r) => (r.isConfirmed ? form.submit() : revert()));
      } else if (window.confirm('ปิดการใช้งาน ' + form.dataset.name + '?\n' + text)) {
        form.submit();
      } else {
        revert();
      }
    });
  });
})();
