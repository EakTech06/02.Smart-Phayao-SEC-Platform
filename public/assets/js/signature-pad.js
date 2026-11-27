/** วาดลายเซ็นบน canvas (เมาส์ นิ้ว ปากกา) แล้วส่งเป็น PNG data URL + เปิดแท็บลายเซ็นจาก #signature */
(() => {
  if (location.hash === '#signature' && window.bootstrap) {
    const tab = document.getElementById('signature-tab');
    if (tab) {
      bootstrap.Tab.getOrCreateInstance(tab).show();
    }
  }

  const form = document.querySelector('[data-signature-form]');
  const canvas = document.querySelector('[data-signature-pad]');
  if (!form || !canvas) {
    return;
  }
  const value = form.querySelector('[data-signature-value]');
  const ctx = canvas.getContext('2d');
  let drawing = false;
  let dirty = false;
  let last = null;

  const setup = () => {
    // ขนาดจริงตามที่แสดง x devicePixelRatio (วาดใหม่หลังเปลี่ยนขนาดจะล้างภาพ)
    const ratio = window.devicePixelRatio || 1;
    const rect = canvas.getBoundingClientRect();
    if (rect.width === 0) {
      return;
    }
    canvas.width = Math.round(rect.width * ratio);
    canvas.height = Math.round(rect.height * ratio);
    ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.strokeStyle = '#0b1f5c';
    ctx.lineWidth = 2.6;
    dirty = false;
  };

  const point = (event) => {
    const rect = canvas.getBoundingClientRect();
    return { x: event.clientX - rect.left, y: event.clientY - rect.top, p: event.pressure || 0.5 };
  };

  canvas.addEventListener('pointerdown', (event) => {
    event.preventDefault();
    if (canvas.width === 0) {
      setup();
    }
    drawing = true;
    last = point(event);
    canvas.setPointerCapture(event.pointerId);
  });
  canvas.addEventListener('pointermove', (event) => {
    if (!drawing) {
      return;
    }
    const p = point(event);
    ctx.lineWidth = event.pointerType === 'pen' ? 1.4 + p.p * 2.6 : 2.6;
    ctx.beginPath();
    ctx.moveTo(last.x, last.y);
    ctx.lineTo(p.x, p.y);
    ctx.stroke();
    last = p;
    dirty = true;
  });
  const stop = () => { drawing = false; };
  canvas.addEventListener('pointerup', stop);
  canvas.addEventListener('pointercancel', stop);

  form.querySelector('[data-signature-clear]')?.addEventListener('click', setup);

  form.addEventListener('submit', (event) => {
    if (!dirty) {
      event.preventDefault();
      if (window.Swal) {
        Swal.fire({ icon: 'info', title: 'กรุณาวาดลายเซ็นก่อนบันทึก' });
      } else {
        window.alert('กรุณาวาดลายเซ็นก่อนบันทึก');
      }
      return;
    }
    value.value = canvas.toDataURL('image/png');
  });

  // แท็บที่ซ่อนอยู่มีขนาด 0: ตั้งค่าเมื่อแท็บแสดง
  document.getElementById('signature-tab')?.addEventListener('shown.bs.tab', setup);
  window.addEventListener('resize', () => { if (!dirty) { setup(); } });
  setup();
})();
