/* ทะเบียนนักเรียน: แสดง/ซ่อนช่องตามตัวเลือก และกรองห้องบริการตามปีการศึกษา */
(() => {
  'use strict';

  const LEFT_STATUSES = ['graduated', 'transferred', 'discharged', 'deceased'];
  const toggle = (el, show) => el && el.classList.toggle('d-none', !show);

  // ประเภทเอกสารแสดงตน → ช่องเลขบัตร/เลข G
  document.querySelectorAll('[data-id-type]').forEach((select) => {
    const sync = () => {
      document.querySelectorAll('.id-field').forEach((el) => toggle(el, el.classList.contains('id-' + select.value)));
    };
    select.addEventListener('change', sync);
    sync();
  });

  // สถานะที่ออกจากการรับบริการแล้ว → วันที่/สาเหตุ
  document.querySelectorAll('[data-status]').forEach((select) => {
    const sync = () => document.querySelectorAll('.left-fields').forEach((el) => toggle(el, LEFT_STATUSES.includes(select.value)));
    select.addEventListener('change', sync);
    sync();
  });

  // checkbox "ที่อยู่ปัจจุบันตามทะเบียนบ้าน"
  document.querySelectorAll('[data-toggle-target]').forEach((box) => {
    const target = document.querySelector(box.dataset.toggleTarget);
    const sync = () => toggle(target, !box.checked);
    box.addEventListener('change', sync);
    sync();
  });

  const showOption = (opt, show) => {
    opt.hidden = !show;
    opt.disabled = !show;
  };

  // ประเภทการรับบริการ 2 ขั้น: กลุ่ม (ที่ศูนย์/นอกศูนย์) → ประเภท, "ที่หน่วยบริการ" → เลือกหน่วยบริการ
  const group = document.querySelector('[data-service-group]');
  const type = document.querySelector('[data-service-type]');
  if (group && type) {
    const unitField = document.querySelector('.enroll-unit-field');
    const unit = unitField ? unitField.querySelector('select') : null;
    const syncUnit = () => {
      const isUnit = type.value === 'service_unit';
      toggle(unitField, isUnit);
      if (!isUnit && unit) unit.value = '';
    };
    const syncGroup = () => {
      Array.from(type.options).forEach((opt) => {
        if (opt.dataset.group) showOption(opt, opt.dataset.group === group.value);
      });
      if (type.selectedOptions[0] && type.selectedOptions[0].disabled) type.value = '';
      type.options[0].textContent = group.value ? '-- เลือกประเภท --' : '-- เลือกกลุ่มก่อน --';
    };
    group.addEventListener('change', () => {
      syncGroup();
      syncUnit();
    });
    type.addEventListener('change', syncUnit);
    syncGroup();
  }

  // ห้องบริการแสดงเฉพาะของปีการศึกษาที่เลือก
  const year = document.querySelector('[data-enroll-year]');
  const room = document.querySelector('[data-room-select]');
  if (year && room) {
    const teacher = document.querySelector('[data-room-teacher]');
    const grade = document.querySelector('[data-room-grade]');

    // ห้องที่มีครูประจำชั้น → ครูผู้รับผิดชอบเหลือเฉพาะครูของห้อง (ห้องยังไม่มีครู/ไม่เลือกห้อง = เลือกได้ทุกคน)
    const syncTeachers = (pickDefault) => {
      if (!teacher) return;
      const opt = room.selectedOptions[0];
      const ids = opt && opt.dataset.teachers ? opt.dataset.teachers.split(',') : [];
      Array.from(teacher.options).forEach((o) => {
        if (o.value) showOption(o, ids.length === 0 || ids.includes(o.value));
      });
      if (ids.length > 0 && (pickDefault || !ids.includes(teacher.value))) {
        if (!ids.includes(teacher.value)) teacher.value = ids[0]; // ครูประจำชั้นหลัก
      }
    };
    const syncRooms = () => {
      Array.from(room.options).forEach((opt) => {
        if (!opt.dataset.year) return;
        const match = opt.dataset.year === year.value;
        showOption(opt, match);
        if (!match && opt.selected) room.value = '';
      });
      syncTeachers(false);
    };
    year.addEventListener('change', syncRooms);
    room.addEventListener('change', () => {
      syncTeachers(true);
      // ดึงระดับชั้นของห้อง
      const g = room.selectedOptions[0] ? room.selectedOptions[0].dataset.grade || '' : '';
      if (grade && g) grade.value = g;
    });
    // ตอนเปิดฟอร์ม: กรองครูตามห้องเดิม แต่ไม่เปลี่ยนค่าที่บันทึกไว้
    Array.from(room.options).forEach((opt) => {
      if (opt.dataset.year) showOption(opt, opt.dataset.year === year.value);
    });
    if (teacher && room.value) {
      const opt = room.selectedOptions[0];
      const ids = opt && opt.dataset.teachers ? opt.dataset.teachers.split(',') : [];
      Array.from(teacher.options).forEach((o) => {
        if (o.value) showOption(o, ids.length === 0 || ids.includes(o.value) || o.value === teacher.value);
      });
    }
  }

  // ผู้ปกครองคือบิดา/มารดา → คัดลอกข้อมูลมาใส่และล็อกช่อง (แก้ที่ข้อมูลบิดา/มารดาแล้วตามมาด้วย)
  const source = document.querySelector('[data-guardian-source]');
  if (source) {
    const FIELDS = ['title_name', 'first_name', 'last_name', 'birth_date', 'life_status', 'national_id', 'occupation', 'income_per_year', 'phone', 'line_id'];
    const RELATION = { father: 'บิดา', mother: 'มารดา' };
    const el = (rel, f) => document.getElementById('f-' + rel + '_' + f);
    const copy = () => {
      const src = source.value;
      FIELDS.forEach((f) => {
        const target = el('guardian', f);
        if (!target) return;
        const from = src ? el(src, f) : null;
        if (from) {
          target.value = from.value;
          if (f === 'national_id') target.placeholder = from.value ? '' : (from.placeholder || '');
        }
        target.readOnly = !!src && target.tagName !== 'SELECT';
        target.classList.toggle('bg-body-tertiary', !!src);
        if (target.tagName === 'SELECT') {
          Array.from(target.options).forEach((o) => { if (!o.selected) showOption(o, !src); });
        }
      });
      const note = document.getElementById('f-guardian_relation_note');
      if (note) {
        if (src) note.value = RELATION[src];
        note.readOnly = !!src;
        note.classList.toggle('bg-body-tertiary', !!src);
      }
    };
    source.addEventListener('change', () => {
      if (!source.value) {
        FIELDS.forEach((f) => {
          const t = el('guardian', f);
          if (t) { t.readOnly = false; t.classList.remove('bg-body-tertiary'); Array.from(t.options || []).forEach((o) => showOption(o, true)); }
        });
        const note = document.getElementById('f-guardian_relation_note');
        if (note) { note.readOnly = false; note.classList.remove('bg-body-tertiary'); }
        return;
      }
      copy();
    });
    ['father', 'mother'].forEach((rel) => FIELDS.forEach((f) => {
      const s = el(rel, f);
      if (s) s.addEventListener('input', () => { if (source.value === rel) copy(); });
      if (s && s.tagName === 'SELECT') s.addEventListener('change', () => { if (source.value === rel) copy(); });
    }));
    if (source.value) copy();
  }

  // อายุ: คำนวณจากวันเกิด (ปี เดือน)
  const birth = document.querySelector('[data-birth-date]');
  const age = document.querySelector('[data-age]');
  if (birth && age) {
    const calc = () => {
      if (!birth.value) { age.value = ''; return; }
      const b = new Date(birth.value + 'T00:00:00');
      const now = new Date();
      if (Number.isNaN(b.getTime()) || b > now) { age.value = '-'; return; }
      let y = now.getFullYear() - b.getFullYear();
      let m = now.getMonth() - b.getMonth();
      if (now.getDate() < b.getDate()) m -= 1;
      if (m < 0) { y -= 1; m += 12; }
      age.value = y + ' ปี ' + m + ' เดือน';
    };
    birth.addEventListener('change', calc);
    birth.addEventListener('input', calc);
  }

  // เลือกทั้งหมด (หน้ายกยอดปีการศึกษา)
  document.querySelectorAll('[data-check-all]').forEach((master) => {
    const boxes = () => document.querySelectorAll('input[type="checkbox"][name="' + master.dataset.checkAll + '"]');
    master.addEventListener('change', () => boxes().forEach((b) => { b.checked = master.checked; }));
    const sync = () => {
      const list = Array.from(boxes());
      master.checked = list.length > 0 && list.every((b) => b.checked);
      master.indeterminate = !master.checked && list.some((b) => b.checked);
    };
    boxes().forEach((b) => b.addEventListener('change', sync));
    sync();
  });

  // ประเภทความพิการ "พิการซ้อน" → เลือกลักษณะความพิการ, ระดับเฉพาะการเห็น/การได้ยิน
  const disability = document.querySelector('[data-disability-type]');
  if (disability) {
    const level = document.querySelector('[data-disability-level]');
    const levelGroup = { blind: 'visual', low_vision: 'visual', deaf: 'hearing', hard_of_hearing: 'hearing' };
    const sync = () => {
      document.querySelectorAll('.multiple-fields').forEach((el) => toggle(el, disability.value === 'multiple'));
      if (level) {
        Array.from(level.options).forEach((opt) => {
          if (!opt.value) return;
          const ok = levelGroup[opt.value] === disability.value;
          opt.hidden = !ok;
          opt.disabled = !ok;
          if (!ok && opt.selected) level.value = '';
        });
      }
    };
    disability.addEventListener('change', sync);
    sync();
  }
})();
