/*
 * ใช้ธีมที่ผู้ใช้เลือกไว้ก่อนหน้าเว็บแสดงผล (โหลดแบบ blocking ใน <head> เพื่อไม่ให้หน้าจอกะพริบ)
 * ค่าเก็บใน localStorage: ui.theme (light|dark, ค่าเริ่มต้น light), ui.palette (ชุดสี), ui.sidebar (full|mini)
 */
(function () {
  'use strict';
  var PALETTES = ['cyber', 'neon', 'emerald', 'sunset', 'phayao'];
  var get = function (key) {
    try { return localStorage.getItem(key); } catch (e) { return null; }
  };
  var root = document.documentElement;
  var theme = get('ui.theme');
  var palette = get('ui.palette');
  root.setAttribute('data-bs-theme', theme === 'dark' ? 'dark' : 'light'); // ค่าเริ่มต้น: โหมดสว่าง
  root.setAttribute('data-palette', PALETTES.indexOf(palette) >= 0 ? palette : 'cyber');
  root.setAttribute('data-sidebar', get('ui.sidebar') === 'mini' ? 'mini' : 'full');
})();
