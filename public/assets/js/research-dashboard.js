/* แดชบอร์ดงานวิจัยและพัฒนานวัตกรรม (Chart.js 4)
 * - ข้อมูลเริ่มต้นจาก <script type="application/json" id="research-dashboard-data">
 * - ดึงข้อมูลล่าสุดทุก 30 วินาที (และเมื่อกลับมาที่แท็บ) แล้วอัปเดตการ์ด/กราฟโดยไม่ต้องรีเฟรชหน้า
 * - สีอ่านจาก CSS Variables ของชุดสีปัจจุบัน และอัปเดตเมื่อเปลี่ยนธีม (ui:themechange) */
(() => {
  'use strict';

  const source = document.getElementById('research-dashboard-data');
  if (!source) return;
  const config = JSON.parse(source.textContent || '{}');
  const number = new Intl.NumberFormat('th-TH');
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const POLL_MS = 30000;

  const css = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();
  const palette = () => ({
    series: [1, 2, 3, 4, 5, 6, 7].map((n) => css(`--chart-${n}`)),
    accent1: css('--accent-1'),
    accent2: css('--accent-2'),
    ink: css('--chart-ink'),
    grid: css('--chart-grid'),
    surface: css('--chart-surface'),
  });

  const updatedEl = document.querySelector('[data-research-updated]');
  const stamp = () => {
    if (updatedEl) updatedEl.textContent = `(ล่าสุด ${new Date().toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit', second: '2-digit' })} น.)`;
  };

  /** การ์ดและรายการตัวเลข (ทำงานได้แม้โหลด Chart.js ไม่สำเร็จ) */
  const renderNumbers = (stats) => {
    Object.entries(stats.cards || {}).forEach(([key, value]) => {
      const el = document.querySelector(`[data-research-card="${key}"]`);
      if (el) el.textContent = number.format(value);
    });
    [['research', 'data-status-count'], ['media', 'data-media-status-count']].forEach(([group, attr]) => {
      ((stats[group] || {}).status || []).forEach((s) => {
        const el = document.querySelector(`[${attr}="${s.key}"]`);
        if (el) el.textContent = number.format(s.count);
      });
    });
  };

  const charts = [];
  let current = config.stats || {};

  const setup = () => {
    if (typeof window.Chart === 'undefined') return;
    Chart.defaults.font.family = "'Sarabun', system-ui, sans-serif";
    Chart.defaults.font.size = 13;
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.boxWidth = 10;
    Chart.defaults.plugins.tooltip.padding = 10;
    Chart.defaults.plugins.tooltip.cornerRadius = 10;
    Chart.defaults.maintainAspectRatio = false;
    Chart.defaults.animation.duration = reduceMotion ? 0 : 400;
    const integerAxis = (stacked) => ({ beginAtZero: true, stacked, ticks: { precision: 0 }, grid: {}, border: { display: false } });
    const categoryAxis = (stacked) => ({ stacked, grid: { display: false }, border: { display: false } });
    const label = (unit) => (ctx) => {
      const v = ctx.chart.config.type === 'doughnut' ? ctx.parsed : ctx.parsed[ctx.chart.options.indexAxis === 'y' ? 'x' : 'y'];
      const name = ctx.chart.data.datasets.length > 1 ? `${ctx.dataset.label}` : (ctx.label || ctx.dataset.label);
      return ` ${name}: ${number.format(v)} ${unit}`;
    };

    // สัดส่วนตามสถานะ (โดนัท)
    const doughnut = (id, group) => {
      const el = document.getElementById(id);
      if (!el) return;
      charts.push({
        chart: new Chart(el, {
          type: 'doughnut',
          data: { labels: [], datasets: [{ data: [], borderWidth: 3, hoverOffset: 8, borderRadius: 6, spacing: 2 }] },
          options: { cutout: '68%', plugins: { legend: { display: false }, tooltip: { callbacks: { label: label('งาน') } } } },
        }),
        fill: (chart, stats, p) => {
          const all = (stats[group] || {}).status || [];
          const rows = all.filter((s) => s.count > 0);
          const keys = all.map((s) => s.key);
          chart.data.labels = rows.map((s) => s.label);
          chart.data.datasets[0].data = rows.map((s) => s.count);
          chart.data.datasets[0].backgroundColor = rows.map((s) => p.series[keys.indexOf(s.key) % 7]);
          chart.data.datasets[0].borderColor = p.surface;
        },
      });
    };
    doughnut('chart-research-status', 'research');
    doughnut('chart-media-status', 'media');

    // แท่ง: rows(stats) คืนรายการ, series = [[ชื่อชุดข้อมูล, ฟิลด์]] (มากกว่า 1 ชุด = แสดงคำอธิบาย และสีตามชุด)
    const bar = (id, rows, series, { horizontal = false, stacked = false, unit = 'งาน' } = {}) => {
      const el = document.getElementById(id);
      if (!el) return;
      const multi = series.length > 1;
      charts.push({
        chart: new Chart(el, {
          type: 'bar',
          data: { labels: [], datasets: series.map(([name]) => ({ label: name, data: [], borderRadius: 8, maxBarThickness: 40 })) },
          options: {
            indexAxis: horizontal ? 'y' : 'x',
            scales: horizontal
              ? { x: integerAxis(stacked), y: categoryAxis(stacked) }
              : { y: integerAxis(stacked), x: categoryAxis(stacked) },
            plugins: { legend: { display: multi, position: 'bottom' }, tooltip: { callbacks: { label: label(unit) } } },
          },
        }),
        fill: (chart, stats, p) => {
          const list = rows(stats) || [];
          chart.data.labels = list.map((r) => r.label);
          series.forEach(([, field], i) => {
            const ds = chart.data.datasets[i];
            ds.data = list.map((r) => r[field] || 0);
            // ใช้อาร์เรย์สีรายแท่งเสมอ (สีเดี่ยวถูกแคชตอนสร้างกราฟ ทำให้ไม่เปลี่ยนตามชุดสี)
            ds.backgroundColor = list.map((_, j) => p.series[(multi ? i : j) % 7]);
          });
        },
      });
    };
    bar('chart-research-types', (s) => (s.research || {}).types, [['จำนวนงาน', 'count']]);
    bar('chart-media-types', (s) => (s.media || {}).types, [['จำนวนสื่อ', 'count']]);
    bar('chart-research-disabilities', (s) => s.disabilities, [['งานวิจัย', 'count'], ['สื่อนวัตกรรม', 'media']], { horizontal: true, stacked: true });
    bar('chart-media-targets', (s) => (s.media || {}).targets, [['ตามแผน', 'planned'], ['ใช้จริง', 'actual']], { horizontal: true, unit: 'คน' });
  };

  const paint = (animate) => {
    const p = palette();
    if (typeof window.Chart !== 'undefined') Chart.defaults.color = p.ink;
    charts.forEach(({ chart, fill }) => {
      Object.values(chart.options.scales || {}).forEach((s) => {
        if (s.grid) s.grid.color = p.grid;
        if (s.ticks) s.ticks.color = p.ink;
      });
      fill(chart, current, p);
      chart.update(animate && !reduceMotion ? undefined : 'none');
    });
  };

  const refresh = async () => {
    if (!config.endpoint || document.hidden) return;
    try {
      const res = await fetch(config.endpoint, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      if (!res.ok) return;
      const body = await res.json();
      if (!body || !body.data) return;
      current = body.data;
      renderNumbers(current);
      paint(true);
      stamp();
    } catch (e) {
      // เครือข่ายขัดข้องชั่วคราว: ลองใหม่รอบถัดไป
    }
  };

  const start = () => {
    setup();
    renderNumbers(current);
    paint(false);
    stamp();
    window.setInterval(refresh, POLL_MS);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
    document.addEventListener('ui:themechange', () => paint(false));
  };

  // Chart.js โหลดแบบ defer ก่อนไฟล์นี้ (ลำดับเดียวกับในหน้า)
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();
