/* แดชบอร์ด: วาดกราฟจากข้อมูลใน <script type="application/json" id="dashboard-data"> (Chart.js 4) */
(() => {
  'use strict';

  const source = document.getElementById('dashboard-data');
  if (!source || typeof window.Chart === 'undefined') return;
  const data = JSON.parse(source.textContent || '{}');

  // ชุดสีที่ผ่านการตรวจความแตกต่างสำหรับผู้มีภาวะตาบอดสี (ลำดับคงที่ ผูกกับชุดข้อมูล)
  const SERIES = ['#2a78d6', '#eb6834', '#1baf7a'];
  const INK = '#52514e';
  const GRID = 'rgba(0, 0, 0, 0.06)';
  const number = new Intl.NumberFormat('th-TH');

  Chart.defaults.font.family = "'Sarabun', system-ui, sans-serif";
  Chart.defaults.font.size = 13;
  Chart.defaults.color = INK;
  Chart.defaults.plugins.legend.labels.usePointStyle = true;
  Chart.defaults.plugins.legend.labels.boxWidth = 10;
  Chart.defaults.plugins.tooltip.callbacks.label = (ctx) =>
    ` ${ctx.dataset.label}: ${number.format(ctx.parsed[ctx.chart.options.indexAxis === 'y' ? 'x' : 'y'] ?? 0)}`;
  Chart.defaults.maintainAspectRatio = false;
  Chart.defaults.animation.duration = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 400;

  const integerAxis = { beginAtZero: true, ticks: { precision: 0 }, grid: { color: GRID }, border: { display: false } };

  // บุคลากรรายฝ่าย: แท่งแนวนอนแบบซ้อน (stacked) แยกตามประเภทการจ้าง
  const personnel = document.getElementById('chart-personnel');
  if (personnel && data.types) {
    const types = data.types.filter((t) => t.total > 0);
    new Chart(personnel, {
      type: 'bar',
      data: {
        labels: data.departments,
        datasets: types.map((t) => ({
          label: t.label,
          data: t.data,
          backgroundColor: t.color,
          borderColor: '#ffffff',
          borderWidth: { left: 0, right: 2, top: 0, bottom: 0 },
          borderSkipped: false,
          borderRadius: 4,
          barThickness: 22,
        })),
      },
      options: {
        indexAxis: 'y',
        interaction: { mode: 'index', axis: 'y', intersect: false },
        scales: {
          x: { ...integerAxis, stacked: true },
          y: { stacked: true, grid: { display: false }, border: { display: false } },
        },
        plugins: {
          legend: { position: 'top', align: 'start' },
          tooltip: {
            filter: (item) => item.parsed.x > 0,
            footer: (items) => `รวม ${number.format(items.reduce((sum, i) => sum + i.parsed.x, 0))} คน`,
          },
        },
      },
    });
  }

  const line = (color, label, values) => ({
    label,
    data: values,
    borderColor: color,
    backgroundColor: color,
    borderWidth: 2,
    pointRadius: 4,
    pointHoverRadius: 6,
    pointBorderColor: '#ffffff',
    pointBorderWidth: 2,
    tension: 0.25,
  });
  const lineOptions = (legend) => ({
    interaction: { mode: 'index', intersect: false },
    scales: { y: integerAxis, x: { grid: { display: false }, border: { display: false } } },
    plugins: { legend: { display: legend, position: 'top', align: 'start' } },
  });

  const trend = document.getElementById('chart-trend');
  if (trend && data.trend) {
    new Chart(trend, {
      type: 'line',
      data: {
        labels: data.trend.labels,
        datasets: [line(SERIES[0], 'ไฟล์ที่อัปโหลด', data.trend.files), line(SERIES[1], 'กิจกรรมในปฏิทิน', data.trend.events)],
      },
      options: lineOptions(true),
    });
  }

  // การเข้าสู่ระบบแยกกราฟ เพราะสเกลต่างจากจำนวนไฟล์/กิจกรรมมาก (ไม่ใช้กราฟสองแกน)
  const logins = document.getElementById('chart-logins');
  if (logins && data.trend && data.trend.logins) {
    new Chart(logins, {
      type: 'line',
      data: { labels: data.trend.labels, datasets: [line(SERIES[0], 'เข้าสู่ระบบสำเร็จ', data.trend.logins)] },
      options: lineOptions(false),
    });
  }
})();
