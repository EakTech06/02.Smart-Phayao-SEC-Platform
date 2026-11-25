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

  /** กระดิ่งแจ้งเตือน: อัปเดตจำนวนที่ยังไม่อ่านทุก 60 วินาที (หยุดเมื่อแท็บไม่ได้เปิดดู) */
  document.addEventListener('DOMContentLoaded', () => {
    const bell = document.getElementById('notification-bell');
    const badge = document.getElementById('notification-count');
    if (!bell || !badge) return;
    const refresh = async () => {
      if (document.hidden) return;
      try {
        const res = await window.api(bell.dataset.countUrl);
        const n = res && res.data ? Number(res.data.unread) : 0;
        badge.textContent = n > 99 ? '99+' : String(n);
        badge.classList.toggle('d-none', n === 0);
      } catch (e) { /* เครือข่ายขัดข้องชั่วคราว: ข้ามรอบนี้ */ }
    };
    setInterval(refresh, 60000);
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

/* เมนูด้านข้าง: จำหมวดที่ผู้ใช้ย่อไว้ (หมวดของหน้าปัจจุบันเปิดเสมอ) */
(() => {
  'use strict';
  const KEY = 'sidebar.collapsed';
  const read = () => {
    try { return JSON.parse(localStorage.getItem(KEY) || '[]'); } catch (e) { return []; }
  };
  const write = (list) => {
    try { localStorage.setItem(KEY, JSON.stringify(list)); } catch (e) { /* private mode */ }
  };
  document.addEventListener('DOMContentLoaded', () => {
    const collapsed = new Set(read());
    document.querySelectorAll('[data-sidebar-group]').forEach((btn) => {
      const key = btn.dataset.sidebarGroup;
      const target = document.querySelector(btn.dataset.bsTarget);
      if (!target) return;
      if (collapsed.has(key) && !btn.hasAttribute('data-keep-open')) {
        target.classList.remove('show');
        btn.classList.add('collapsed');
        btn.setAttribute('aria-expanded', 'false');
      }
      target.addEventListener('hidden.bs.collapse', () => { collapsed.add(key); write([...collapsed]); });
      target.addEventListener('shown.bs.collapse', () => { collapsed.delete(key); write([...collapsed]); });
    });
  });
})();
