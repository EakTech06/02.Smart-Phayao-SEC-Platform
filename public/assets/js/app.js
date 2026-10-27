(() => {
  'use strict';

  document.addEventListener('DOMContentLoaded', () => 
  {
    const qr = document.getElementById('qrcode');
    if (qr && window.QRCode) {
      new QRCode(qr, { text: qr.dataset.uri, width: 200, height: 200, correctLevel: QRCode.CorrectLevel.M });
    }

    if (window.DataTable) {
      document.querySelectorAll('table.js-datatable').forEach((table) => {
        new DataTable(table, {
          pageLength: 25,
          language: {
            search: 'ค้นหา:',
            lengthMenu: 'แสดง _MENU_ รายการ',
            info: 'แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ',
            infoEmpty: 'ไม่มีข้อมูล',
            zeroRecords: 'ไม่พบข้อมูลที่ค้นหา',
            emptyTable: 'ไม่มีข้อมูล',
          },
        });
      });
    }

    document.querySelectorAll('form[data-confirm]').forEach((form) => {
      form.addEventListener('submit', (event) => {
        event.preventDefault();
        const message = form.dataset.confirm;
        if (window.Swal) {
          Swal.fire({
            title: message, icon: 'question', showCancelButton: true,
            confirmButtonText: 'ยืนยัน', cancelButtonText: 'ยกเลิก',
          }).then((result) => { if (result.isConfirmed) { form.submit(); } });
        } else if (window.confirm(message)) {
          form.submit();
        }
      });
    });

    const copyButton = document.getElementById('copy-codes');
    if (copyButton) {
      copyButton.addEventListener('click', async () => {
        const codes = [...document.querySelectorAll('#recovery-codes code')].map((el) => el.textContent.trim());
        await navigator.clipboard.writeText(codes.join('\n'));
        copyButton.innerHTML = '<i class="bi bi-check2 me-1"></i>คัดลอกแล้ว';
      });
    }

    document.querySelectorAll('[data-copy]').forEach((button) => {
      button.addEventListener('click', async () => {
        const target = document.querySelector(button.dataset.copy);
        if (!target) return;
        await navigator.clipboard.writeText(target.textContent.trim());
        button.innerHTML = '<i class="bi bi-check2 me-1"></i>คัดลอกแล้ว';
      });
    });

  });

  /** fetch helper ที่แนบ CSRF Token และอ่านผลแบบ JSend */
  window.api = async (url, options = {}) => {
    const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const response = await fetch(url, {
      credentials: 'same-origin',
      ...options,
      headers: { Accept: 'application/json', 'X-CSRF-Token': token, ...(options.headers || {}) },
    });
    return response.json();
  };
})();
  // Show / Hide Password with Eye Icon
  document.querySelectorAll('.toggle-password').forEach((button) => {
    button.addEventListener('click', () => {
      const targetId = button.dataset.target;
      const input = document.getElementById(targetId);
      if (!input) return;

      const icon = button.querySelector('bi'); // หรือไอคอนภายในปุ่ม
      if (input.type === 'password') {
        input.type = 'text';
        button.innerHTML = '<i class="bi bi-eye-slash"></i>';
      } else {
        input.type = 'password';
        button.innerHTML = '<i class="bi bi-eye"></i>';
      }
    });
  });