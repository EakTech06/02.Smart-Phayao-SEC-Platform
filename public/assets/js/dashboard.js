/* แดชบอร์ด: วาดกราฟจากข้อมูลใน <script type="application/json" id="dashboard-data"> (Chart.js 4)
 * สีทั้งหมดอ่านจาก CSS Variables ของชุดสีปัจจุบัน และอัปเดตทันทีเมื่อเปลี่ยนธีม/ชุดสี (เหตุการณ์ ui:themechange) */
(() => {
  'use strict';

  const source = document.getElementById('dashboard-data');
  if (!source || typeof window.Chart === 'undefined') return;
  const data = JSON.parse(source.textContent || '{}');
  const number = new Intl.NumberFormat('th-TH');
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  const css = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();
  const palette = () => ({
    series: [1, 2, 3, 4, 5, 6, 7].map((n) => css(`--chart-${n}`)),
    accent1: css('--accent-1'),
    accent2: css('--accent-2'),
    ink: css('--chart-ink'),
    grid: css('--chart-grid'),
    surface: css('--chart-surface'),
  });
  /** สีโปร่งใสจาก hex (#rrggbb) */
  const alpha = (hex, a) => {
    const m = /^#?([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i.exec(hex);
    return m ? `rgba(${parseInt(m[1], 16)}, ${parseInt(m[2], 16)}, ${parseInt(m[3], 16)}, ${a})` : hex;
  };
  /** ไล่สีแนวตั้งใต้เส้นกราฟ (area) */
  const fill = (color) => (ctx) => {
    const { chart } = ctx;
    if (!chart.chartArea) return alpha(color, 0.2);
    const g = chart.ctx.createLinearGradient(0, chart.chartArea.top, 0, chart.chartArea.bottom);
    g.addColorStop(0, alpha(color, 0.38));
    g.addColorStop(1, alpha(color, 0));
    return g;
  };

  Chart.defaults.font.family = "'Sarabun', system-ui, sans-serif";
  Chart.defaults.font.size = 13;
  Chart.defaults.plugins.legend.labels.usePointStyle = true;
  Chart.defaults.plugins.legend.labels.boxWidth = 10;
  Chart.defaults.plugins.tooltip.padding = 10;
  Chart.defaults.plugins.tooltip.cornerRadius = 10;
  Chart.defaults.plugins.tooltip.callbacks.label = (ctx) => {
    const v = ctx.chart.config.type === 'doughnut' ? ctx.parsed : ctx.parsed[ctx.chart.options.indexAxis === 'y' ? 'x' : 'y'];
    return ` ${ctx.dataset.label || ctx.label}: ${number.format(v ?? 0)}`;
  };
  Chart.defaults.maintainAspectRatio = false;
  Chart.defaults.animation.duration = reduceMotion ? 0 : 400;
  Chart.defaults.animation.easing = 'easeOutCubic';

  const charts = []; // [{ chart, paint(p) }]
  const integerAxis = () => ({ beginAtZero: true, ticks: { precision: 0 }, grid: {}, border: { display: false } });

  /** ใช้สีชุดปัจจุบันกับกราฟทั้งหมด */
  const paintAll = (update) => {
    const p = palette();
    Chart.defaults.color = p.ink;
    charts.forEach(({ chart, paint }) => {
      Object.values(chart.options.scales || {}).forEach((s) => {
        if (s.grid) s.grid.color = p.grid;
        if (s.ticks) s.ticks.color = p.ink;
      });
      if (chart.options.plugins?.legend?.labels) chart.options.plugins.legend.labels.color = p.ink;
      paint(chart, p);
      if (update) chart.update(reduceMotion ? 'none' : undefined);
    });
  };
  const add = (chart, paint) => charts.push({ chart, paint });

  // บุคลากรรายฝ่าย: แท่งแนวนอนแบบซ้อน (stacked) แยกตามประเภทการจ้าง (สีผูกกับลำดับประเภท)
  const personnel = document.getElementById('chart-personnel');
  const types = (data.types || []).map((t, i) => ({ ...t, index: i })).filter((t) => t.total > 0);
  if (personnel && types.length) {
    add(new Chart(personnel, {
      type: 'bar',
      data: {
        labels: data.departments,
        datasets: types.map((t) => ({
          label: t.label, data: t.data, borderWidth: { left: 0, right: 2, top: 0, bottom: 0 },
          borderSkipped: false, borderRadius: 6, barThickness: 22,
        })),
      },
      options: {
        indexAxis: 'y',
        interaction: { mode: 'index', axis: 'y', intersect: false },
        scales: { x: { ...integerAxis(), stacked: true }, y: { stacked: true, grid: { display: false }, border: { display: false } } },
        plugins: {
          legend: { position: 'top', align: 'start', labels: {} },
          tooltip: {
            filter: (item) => item.parsed.x > 0,
            footer: (items) => `รวม ${number.format(items.reduce((sum, i) => sum + i.parsed.x, 0))} คน`,
          },
        },
      },
    }), (chart, p) => chart.data.datasets.forEach((ds, i) => {
      ds.backgroundColor = p.series[types[i].index % 7];
      ds.borderColor = p.surface;
    }));
  }

  // สัดส่วนประเภทบุคลากร: โดนัท
  const typeChart = document.getElementById('chart-types');
  if (typeChart && types.length) {
    add(new Chart(typeChart, {
      type: 'doughnut',
      data: { labels: types.map((t) => t.label), datasets: [{ data: types.map((t) => t.total), borderWidth: 3, hoverOffset: 8, borderRadius: 6, spacing: 2 }] },
      options: { cutout: '72%', plugins: { legend: { display: false } } },
    }), (chart, p) => {
      chart.data.datasets[0].backgroundColor = types.map((t) => p.series[t.index % 7]);
      chart.data.datasets[0].borderColor = p.surface;
    });
  }

  const line = (label, values) => ({
    label, data: values, fill: true, borderWidth: 2.5, pointRadius: 4, pointHoverRadius: 6, pointBorderWidth: 2, tension: 0.35,
  });
  const paintLine = (ds, color, p) => {
    ds.borderColor = color;
    ds.pointBackgroundColor = color;
    ds.pointBorderColor = p.surface;
    ds.backgroundColor = fill(color);
  };

  // ไฟล์และกิจกรรมรายเดือน: กราฟพื้นที่ไล่สี (area)
  const trend = document.getElementById('chart-trend');
  if (trend && data.trend) {
    add(new Chart(trend, {
      type: 'line',
      data: { labels: data.trend.labels, datasets: [line('ไฟล์ที่อัปโหลด', data.trend.files), line('กิจกรรมในปฏิทิน', data.trend.events)] },
      options: {
        interaction: { mode: 'index', intersect: false },
        scales: { y: integerAxis(), x: { grid: { display: false }, border: { display: false } } },
        plugins: { legend: { position: 'top', align: 'start', labels: {} } },
      },
    }), (chart, p) => {
      paintLine(chart.data.datasets[0], p.series[0], p);
      paintLine(chart.data.datasets[1], p.series[1], p);
    });
  }

  // การเข้าสู่ระบบสำเร็จรายเดือน: แท่งไล่สี (แยกกราฟเพราะสเกลต่างจากไฟล์/กิจกรรมมาก)
  const logins = document.getElementById('chart-logins');
  if (logins && data.trend && data.trend.logins) {
    add(new Chart(logins, {
      type: 'bar',
      data: { labels: data.trend.labels, datasets: [{ label: 'เข้าสู่ระบบสำเร็จ', data: data.trend.logins, borderRadius: 8, maxBarThickness: 36 }] },
      options: {
        scales: { y: integerAxis(), x: { grid: { display: false }, border: { display: false } } },
        plugins: { legend: { display: false } },
      },
    }), (chart, p) => {
      chart.data.datasets[0].backgroundColor = (ctx) => {
        const area = ctx.chart.chartArea;
        if (!area) return p.accent1;
        const g = ctx.chart.ctx.createLinearGradient(0, area.bottom, 0, area.top);
        g.addColorStop(0, p.accent2);
        g.addColorStop(1, p.accent1);
        return g;
      };
      chart.data.datasets[0].hoverBackgroundColor = p.accent1;
    });
  }

  paintAll(true);
  document.addEventListener('ui:themechange', () => paintAll(true));
})();
