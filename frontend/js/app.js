/**
 * App Controller — Sistem Deteksi Penipuan UMKM
 * Kelompok 6: Farrel, Farzad, Hafif, Arung
 */

// ============================================================
// Navigation
// ============================================================
function showView(name) {
  document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));

  const view = document.getElementById(`view-${name}`);
  const nav = document.querySelector(`[data-view="${name}"]`);

  if (view) view.classList.add('active');
  if (nav) nav.classList.add('active');

  // Update topbar
  const titles = {
    dashboard: { title: 'Dashboard', sub: 'Ringkasan deteksi penipuan transaksi UMKM' },
    analyze: { title: 'Analisis Transaksi', sub: 'Periksa transaksi apakah terindikasi penipuan' },
    history: { title: 'Riwayat Transaksi', sub: 'Semua transaksi yang telah dianalisis' },
    model: { title: 'Informasi Model AI', sub: 'Performa dan konfigurasi model deteksi' },
  };
  const info = titles[name] || {};
  document.getElementById('topbar-title').textContent = info.title || '';
  document.getElementById('topbar-subtitle').textContent = info.sub || '';

  // Load data per view
  if (name === 'dashboard') loadDashboard();
  if (name === 'history') loadHistory();
  if (name === 'model') loadModelInfo();
}

document.querySelectorAll('.nav-item').forEach(item => {
  item.addEventListener('click', () => {
    const view = item.dataset.view;
    if (view) showView(view);
  });
});

// ============================================================
// Dashboard
// ============================================================
let chartsInitialized = false;
let trendChart, riskChart, hourlyChart;

async function loadDashboard() {
  try {
    const [summary, trend, risk, hourly] = await Promise.all([
      API.getSummary(),
      API.getTrend(30),
      API.getRiskDistribution(),
      API.getHourlyPattern(),
    ]);

    renderSummaryCards(summary);
    renderCharts(trend, risk, hourly);
    loadTopFraud();
  } catch (e) {
    showToast('Gagal memuat dashboard: ' + e.message, 'danger');
  }
}

function renderSummaryCards(s) {
  document.getElementById('stat-total').textContent = s.total_transactions.toLocaleString('id-ID');
  document.getElementById('stat-fraud').textContent = s.total_fraud.toLocaleString('id-ID');
  document.getElementById('stat-normal').textContent = s.total_normal.toLocaleString('id-ID');
  document.getElementById('stat-blocked').textContent = s.total_blocked.toLocaleString('id-ID');
  document.getElementById('stat-rate').textContent = s.fraud_rate_pct + '%';
  document.getElementById('stat-amount').textContent = formatRupiah(s.total_amount_idr);
  document.getElementById('stat-fraud-amount').textContent = formatRupiah(s.fraud_amount_idr);

  // Risk breakdown
  const rb = s.risk_breakdown;
  document.getElementById('rb-kritis').textContent = rb.KRITIS || 0;
  document.getElementById('rb-tinggi').textContent = rb.TINGGI || 0;
  document.getElementById('rb-sedang').textContent = rb.SEDANG || 0;
  document.getElementById('rb-rendah').textContent = rb.RENDAH || 0;
}

function renderCharts(trend, risk, hourly) {
  // Tren chart
  const trendLabels = trend.data.map(d => d.date.slice(5));
  const trendNormal = trend.data.map(d => d.normal);
  const trendFraud = trend.data.map(d => d.fraud);

  if (trendChart) trendChart.destroy();
  trendChart = new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
      labels: trendLabels,
      datasets: [
        {
          label: 'Normal',
          data: trendNormal,
          borderColor: '#16a34a',
          backgroundColor: 'rgba(22,163,74,.08)',
          fill: true,
          tension: .3,
          pointRadius: 2,
        },
        {
          label: 'Fraud',
          data: trendFraud,
          borderColor: '#dc2626',
          backgroundColor: 'rgba(220,38,38,.08)',
          fill: true,
          tension: .3,
          pointRadius: 2,
        },
      ],
    },
    options: {
      responsive: true,
      plugins: {
        legend: { position: 'top' },
        tooltip: { mode: 'index' },
      },
      scales: {
        y: { beginAtZero: true, grid: { color: '#f3f4f6' } },
        x: { grid: { display: false } },
      },
    },
  });

  // Risk doughnut
  const riskLabels = risk.data.map(d => d.risk_level);
  const riskCounts = risk.data.map(d => d.count);
  const riskColors = ['#16a34a', '#2563eb', '#d97706', '#dc2626'];

  if (riskChart) riskChart.destroy();
  riskChart = new Chart(document.getElementById('riskChart'), {
    type: 'doughnut',
    data: {
      labels: riskLabels,
      datasets: [{
        data: riskCounts,
        backgroundColor: riskColors,
        borderWidth: 2,
        borderColor: '#fff',
      }],
    },
    options: {
      responsive: true,
      plugins: {
        legend: { position: 'bottom' },
      },
      cutout: '65%',
    },
  });

  // Hourly bar
  const hLabels = hourly.data.map(d => `${d.hour}:00`);
  const hNormal = hourly.data.map(d => d.normal);
  const hFraud = hourly.data.map(d => d.fraud);

  if (hourlyChart) hourlyChart.destroy();
  hourlyChart = new Chart(document.getElementById('hourlyChart'), {
    type: 'bar',
    data: {
      labels: hLabels,
      datasets: [
        { label: 'Normal', data: hNormal, backgroundColor: 'rgba(22,163,74,.7)', borderRadius: 4 },
        { label: 'Fraud', data: hFraud, backgroundColor: 'rgba(220,38,38,.7)', borderRadius: 4 },
      ],
    },
    options: {
      responsive: true,
      plugins: { legend: { position: 'top' } },
      scales: {
        y: { beginAtZero: true, grid: { color: '#f3f4f6' } },
        x: { grid: { display: false } },
      },
    },
  });
}

async function loadTopFraud() {
  try {
    const data = await API.getTopFraud(5);
    const tbody = document.getElementById('top-fraud-body');
    if (!data.data.length) {
      tbody.innerHTML = `<tr><td colspan="5" class="text-center" style="color:var(--gray-400);padding:20px;text-align:center">Belum ada transaksi fraud</td></tr>`;
      return;
    }
    tbody.innerHTML = data.data.map(t => `
      <tr>
        <td><code style="font-size:12px">${t.transaction_id}</code></td>
        <td>${t.merchant_name || '-'}</td>
        <td>${formatRupiah(t.amount)}</td>
        <td>${scoreBar(t.fraud_score)}</td>
        <td>${riskBadge(t.risk_level)}</td>
      </tr>
    `).join('');
  } catch (e) {}
}

// ============================================================
// Analyze Transaction Form
// ============================================================
document.getElementById('form-analyze').addEventListener('submit', async (e) => {
  e.preventDefault();
  const btn = document.getElementById('btn-analyze');
  const resultBox = document.getElementById('result-box');

  setLoading(btn, true, 'Menganalisis...');
  resultBox.className = 'result-box';

  const now = new Date();
  const payload = {
    merchant_name: document.getElementById('f-merchant').value || undefined,
    amount: parseFloat(document.getElementById('f-amount').value),
    recipient_name: document.getElementById('f-recipient').value || undefined,
    description: document.getElementById('f-desc').value || undefined,
    transaction_count_1h: parseInt(document.getElementById('f-count-1h').value) || 0,
    transaction_count_24h: parseInt(document.getElementById('f-count-24h').value) || 0,
    avg_amount_7d: parseFloat(document.getElementById('f-avg-7d').value) || 0,
    amount_deviation: parseFloat(document.getElementById('f-deviation').value) || 0,
    is_new_recipient: document.getElementById('f-new-recipient').checked,
    location_change: document.getElementById('f-location-change').checked,
    velocity_score: parseFloat(document.getElementById('f-velocity').value) || 0,
  };

  try {
    const result = await API.analyzeTransaction(payload);
    renderResult(result);
    showToast(
      result.is_fraud
        ? `⚠️ Terdeteksi FRAUD! Risk: ${result.risk_level}`
        : '✅ Transaksi terlihat normal',
      result.is_fraud ? 'danger' : 'success',
    );
  } catch (e) {
    showToast('Error: ' + e.message, 'danger');
  } finally {
    setLoading(btn, false);
  }
});

function renderResult(r) {
  const box = document.getElementById('result-box');
  const pct = Math.round(r.fraud_score * 100);

  let cls = 'safe', icon = '✅', title = 'Transaksi AMAN', titleColor = '#16a34a';
  if (r.is_fraud) {
    if (r.risk_level === 'KRITIS') { cls = 'fraud'; icon = '🚨'; title = 'PENIPUAN KRITIS!'; titleColor = '#dc2626'; }
    else { cls = 'warning'; icon = '⚠️'; title = 'TERINDIKASI PENIPUAN'; titleColor = '#d97706'; }
  }

  box.className = `result-box ${cls} show`;
  box.innerHTML = `
    <div class="result-header">
      <span class="result-icon">${icon}</span>
      <div>
        <div class="result-title" style="color:${titleColor}">${title}</div>
        <div class="result-subtitle">ID: ${r.transaction_id} | Status: ${r.status}</div>
      </div>
      <div style="margin-left:auto">${riskBadge(r.risk_level)}</div>
    </div>

    <div class="result-grid">
      <div class="result-metric">
        <div class="metric-value" style="color:${titleColor}">${pct}%</div>
        <div class="metric-label">Fraud Score</div>
      </div>
      <div class="result-metric">
        <div class="metric-value">${formatRupiah(r.amount)}</div>
        <div class="metric-label">Nominal Transaksi</div>
      </div>
      <div class="result-metric">
        <div class="metric-value">${r.risk_level}</div>
        <div class="metric-label">Level Risiko</div>
      </div>
    </div>

    <div style="margin:12px 0;background:rgba(0,0,0,.06);border-radius:8px;padding:14px">
      <div style="font-size:13px;font-weight:600;margin-bottom:8px;color:var(--gray-700)">Skor Detail Model</div>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;font-size:13px">
        <div>Isolation Forest: <strong>${Math.round(r.score_detail.isolation_forest * 100)}%</strong></div>
        <div>Local Outlier Factor: <strong>${Math.round(r.score_detail.local_outlier_factor * 100)}%</strong></div>
        <div>Rule-Based: <strong>${Math.round(r.score_detail.rule_based * 100)}%</strong></div>
      </div>
    </div>

    <div>
      <div style="font-size:13px;font-weight:700;margin-bottom:8px;color:var(--gray-700)">
        📋 Analisis Sistem:
      </div>
      <ul class="reasons-list">
        ${r.explanation.map(e => `<li>${e}</li>`).join('')}
      </ul>
    </div>
  `;
  box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// Reset form
document.getElementById('btn-reset').addEventListener('click', () => {
  document.getElementById('form-analyze').reset();
  document.getElementById('result-box').className = 'result-box';
});

// Auto-fill avg dan deviation dari amount
document.getElementById('f-amount').addEventListener('input', function() {
  const amount = parseFloat(this.value) || 0;
  const avg7d = document.getElementById('f-avg-7d');
  if (!avg7d.value && amount > 0) {
    avg7d.value = Math.round(amount * 0.9);
  }
  const avgVal = parseFloat(avg7d.value) || amount;
  if (avgVal > 0) {
    document.getElementById('f-deviation').value = ((Math.abs(amount - avgVal) / avgVal)).toFixed(4);
  }
});

// ============================================================
// Transaction History
// ============================================================
let historyPage = 0;
const PAGE_SIZE = 15;

async function loadHistory(page = 0) {
  historyPage = page;
  const tbody = document.getElementById('history-body');
  tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:24px"><span class="spinner dark"></span> Memuat...</td></tr>`;

  try {
    const filterFraud = document.getElementById('filter-fraud').value;
    const filterRisk = document.getElementById('filter-risk').value;
    const filterStatus = document.getElementById('filter-status').value;

    const params = { skip: page * PAGE_SIZE, limit: PAGE_SIZE };
    if (filterFraud !== '') params.is_fraud = filterFraud;
    if (filterRisk) params.risk_level = filterRisk;
    if (filterStatus) params.status = filterStatus;

    const data = await API.listTransactions(params);
    renderHistoryTable(data);
  } catch (e) {
    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:var(--danger);padding:24px">Error: ${e.message}</td></tr>`;
  }
}

function renderHistoryTable(data) {
  const tbody = document.getElementById('history-body');
  document.getElementById('history-page-info').textContent =
    `Menampilkan ${data.skip + 1}–${Math.min(data.skip + data.limit, data.total)} dari ${data.total} transaksi`;

  if (!data.data.length) {
    tbody.innerHTML = `<tr><td colspan="7"><div class="empty-state"><div class="empty-icon">📭</div><h3>Tidak Ada Transaksi</h3><p>Belum ada data transaksi yang sesuai filter.</p></div></td></tr>`;
    return;
  }

  tbody.innerHTML = data.data.map(t => `
    <tr>
      <td>
        <code style="font-size:11px;color:var(--primary)">${t.transaction_id}</code>
        <div style="font-size:11px;color:var(--gray-400)">${formatDate(t.created_at)}</div>
      </td>
      <td>${truncate(t.merchant_name, 20) || '<span style="color:var(--gray-400)">-</span>'}</td>
      <td style="font-weight:700">${formatRupiah(t.amount)}</td>
      <td>${scoreBar(t.fraud_score)}</td>
      <td>${riskBadge(t.risk_level)}</td>
      <td>${statusBadge(t.status)}</td>
      <td>
        <button class="btn btn-outline btn-sm" onclick="showTransactionDetail('${t.transaction_id}')">
          Detail
        </button>
      </td>
    </tr>
  `).join('');

  // Pagination buttons
  document.getElementById('btn-prev').disabled = historyPage === 0;
  document.getElementById('btn-next').disabled = (historyPage + 1) * PAGE_SIZE >= data.total;
}

document.getElementById('btn-prev').addEventListener('click', () => loadHistory(historyPage - 1));
document.getElementById('btn-next').addEventListener('click', () => loadHistory(historyPage + 1));

['filter-fraud', 'filter-risk', 'filter-status'].forEach(id => {
  document.getElementById(id).addEventListener('change', () => loadHistory(0));
});

// ============================================================
// Transaction Detail Modal
// ============================================================
async function showTransactionDetail(txId) {
  const modal = document.getElementById('modal-detail');
  const body = document.getElementById('modal-body');
  modal.style.display = 'flex';
  body.innerHTML = `<div style="text-align:center;padding:40px"><span class="spinner dark"></span></div>`;

  try {
    const t = await API.getTransaction(txId);
    body.innerHTML = renderDetailHTML(t);
  } catch (e) {
    body.innerHTML = `<div class="alert alert-danger">Error: ${e.message}</div>`;
  }
}

function renderDetailHTML(t) {
  const pct = Math.round(t.fraud_score * 100);
  return `
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px">
      <div>
        <div style="font-size:18px;font-weight:800">${t.transaction_id}</div>
        <div style="font-size:13px;color:var(--gray-500)">${formatDate(t.created_at)}</div>
      </div>
      <div style="margin-left:auto;display:flex;gap:8px">
        ${riskBadge(t.risk_level)} ${statusBadge(t.status)}
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">
      ${detailRow('Merchant', t.merchant_name)}
      ${detailRow('Penerima', t.recipient_name)}
      ${detailRow('Nominal', formatRupiah(t.amount))}
      ${detailRow('Rata-rata 7 hari', formatRupiah(t.avg_amount_7d))}
      ${detailRow('Jam Transaksi', t.hour + ':00')}
      ${detailRow('Hari', namaHari(t.day_of_week))}
      ${detailRow('Transaksi/1 jam', t.transaction_count_1h)}
      ${detailRow('Transaksi/24 jam', t.transaction_count_24h)}
      ${detailRow('Penerima Baru', t.is_new_recipient ? '⚠️ Ya' : '✅ Tidak')}
      ${detailRow('Perubahan Lokasi', t.location_change ? '⚠️ Ya' : '✅ Tidak')}
      ${detailRow('Velocity Score', (t.velocity_score * 100).toFixed(1) + '%')}
      ${detailRow('Amount Deviation', (t.amount_deviation * 100).toFixed(1) + '%')}
    </div>

    <div style="background:var(--gray-50);border-radius:10px;padding:16px;margin-bottom:16px">
      <div style="font-weight:700;margin-bottom:12px">Skor Deteksi Model</div>
      ${scoreRowHTML('Isolation Forest', t.score_detail.isolation_forest)}
      ${scoreRowHTML('Local Outlier Factor', t.score_detail.local_outlier_factor)}
      ${scoreRowHTML('Rule-Based', t.score_detail.rule_based)}
      <div style="border-top:1px solid var(--gray-200);margin-top:10px;padding-top:10px">
        ${scoreRowHTML('FRAUD SCORE FINAL', t.fraud_score, true)}
      </div>
    </div>

    <div style="margin-bottom:16px">
      <div style="font-weight:700;margin-bottom:8px">📋 Analisis Sistem</div>
      <ul class="reasons-list" style="background:var(--gray-50);border-radius:10px;padding:12px 12px 12px 32px">
        ${(t.explanation || []).map(e => `<li>${e}</li>`).join('')}
      </ul>
    </div>

    <div style="display:flex;gap:8px;justify-content:flex-end">
      <button class="btn btn-success btn-sm" onclick="updateTxStatus('${t.transaction_id}', 'APPROVED')">✅ Approve</button>
      <button class="btn btn-danger btn-sm" onclick="updateTxStatus('${t.transaction_id}', 'BLOCKED')">🚫 Block</button>
      <button class="btn btn-outline btn-sm" onclick="closeModal()">Tutup</button>
    </div>
  `;
}

function detailRow(label, value) {
  return `
    <div style="background:var(--gray-50);border-radius:8px;padding:10px 14px">
      <div style="font-size:11px;color:var(--gray-500);font-weight:600">${label}</div>
      <div style="font-size:14px;font-weight:600;margin-top:2px">${value || '-'}</div>
    </div>`;
}

function scoreRowHTML(label, score, bold = false) {
  const pct = Math.round(score * 100);
  let color = '#16a34a';
  if (pct >= 75) color = '#dc2626';
  else if (pct >= 50) color = '#d97706';
  else if (pct >= 30) color = '#2563eb';
  return `
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;${bold ? 'font-weight:800' : ''}">
      <span style="flex:1;font-size:13px">${label}</span>
      <div class="score-bar" style="flex:2"><div class="score-bar-fill" style="width:${pct}%;background:${color}"></div></div>
      <span style="font-size:13px;font-weight:700;color:${color};min-width:36px;text-align:right">${pct}%</span>
    </div>`;
}

async function updateTxStatus(txId, status) {
  try {
    await API.updateStatus(txId, status);
    showToast(`Status diperbarui: ${status}`, 'success');
    closeModal();
    loadHistory(historyPage);
  } catch (e) {
    showToast('Gagal update status: ' + e.message, 'danger');
  }
}

function closeModal() {
  document.getElementById('modal-detail').style.display = 'none';
}

document.getElementById('modal-detail').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});

// ============================================================
// Model Info
// ============================================================
async function loadModelInfo() {
  const container = document.getElementById('model-info-content');
  container.innerHTML = `<div style="text-align:center;padding:40px"><span class="spinner dark"></span> Memuat informasi model...</div>`;

  try {
    const info = await API.getModelInfo();
    renderModelInfo(info, container);
  } catch (e) {
    container.innerHTML = `<div class="alert alert-danger">Gagal memuat: ${e.message}</div>`;
  }
}

function renderModelInfo(info, container) {
  if (info.status !== 'ready') {
    container.innerHTML = `
      <div class="alert alert-warning">
        ⚠️ Model belum dilatih. Klik tombol "Latih Model" untuk memulai.
      </div>`;
    return;
  }

  const m = info.metrics || {};
  const weights = info.ensemble_weights || [0, 0, 0];

  container.innerHTML = `
    <div class="grid-2 mb-20">
      <div class="card">
        <div class="card-header"><div class="card-title">🤖 Informasi Model</div></div>
        <div class="card-body">
          <table style="width:100%;font-size:14px">
            ${infoRow('Jenis Model', info.model_type)}
            ${infoRow('Status', '<span class="badge badge-success">✅ Aktif</span>')}
            ${infoRow('Terlatih pada', info.trained_at ? new Date(info.trained_at).toLocaleString('id-ID') : '-')}
            ${infoRow('Contamination Rate', (info.contamination * 100).toFixed(1) + '%')}
          </table>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><div class="card-title">⚖️ Bobot Ensemble</div></div>
        <div class="card-body">
          ${weightRow('Isolation Forest', weights[0])}
          ${weightRow('Local Outlier Factor', weights[1])}
          ${weightRow('Rule-Based Logic', weights[2])}
        </div>
      </div>
    </div>

    <div class="card mb-20">
      <div class="card-header"><div class="card-title">📊 Metrik Performa Model</div></div>
      <div class="card-body">
        <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:16px;text-align:center">
          ${metricPill('Akurasi', m.accuracy)}
          ${metricPill('Precision', m.precision)}
          ${metricPill('Recall', m.recall)}
          ${metricPill('F1-Score', m.f1_score)}
          ${metricPill('ROC-AUC', m.roc_auc)}
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><div class="card-title">📚 Tentang Algoritma</div></div>
      <div class="card-body" style="font-size:14px;line-height:1.7;color:var(--gray-700)">
        <p style="margin-bottom:12px">
          Sistem menggunakan <strong>Ensemble Anomaly Detection</strong> yang menggabungkan tiga pendekatan:
        </p>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
          <div style="background:var(--primary-light);border-radius:10px;padding:14px">
            <div style="font-weight:700;color:var(--primary);margin-bottom:6px">🌲 Isolation Forest</div>
            <div style="font-size:13px">Mengisolasi anomali melalui pohon keputusan acak. Efektif untuk outlier global dengan nominal atau pola tidak biasa.</div>
          </div>
          <div style="background:var(--info-light);border-radius:10px;padding:14px">
            <div style="font-weight:700;color:var(--info);margin-bottom:6px">🔍 Local Outlier Factor</div>
            <div style="font-size:13px">Mendeteksi anomali berdasarkan kepadatan lokal. Efektif untuk outlier kontekstual yang berbeda dari tetangganya.</div>
          </div>
          <div style="background:var(--warning-light);border-radius:10px;padding:14px">
            <div style="font-weight:700;color:var(--warning);margin-bottom:6px">📋 Rule-Based Logic</div>
            <div style="font-size:13px">Aturan bisnis UMKM: jam transaksi, frekuensi, rasio nominal, dan perubahan lokasi mendadak.</div>
          </div>
        </div>
      </div>
    </div>
  `;
}

function infoRow(label, value) {
  return `<tr><td style="padding:8px 0;color:var(--gray-500);font-weight:500">${label}</td><td style="padding:8px 0;font-weight:600">${value || '-'}</td></tr>`;
}

function weightRow(label, weight) {
  const pct = Math.round(weight * 100);
  return `
    <div style="margin-bottom:14px">
      <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px">
        <span style="font-weight:600">${label}</span>
        <span style="font-weight:700;color:var(--primary)">${pct}%</span>
      </div>
      <div class="progress-bar"><div class="progress-fill" style="width:${pct}%"></div></div>
    </div>`;
}

function metricPill(label, value) {
  const pct = value ? Math.round(value * 100) : 0;
  let color = '#16a34a';
  if (pct < 70) color = '#dc2626';
  else if (pct < 85) color = '#d97706';
  return `
    <div>
      <div style="font-size:28px;font-weight:800;color:${color}">${pct}%</div>
      <div style="font-size:12px;color:var(--gray-500);font-weight:600">${label}</div>
    </div>`;
}

// Retrain button
document.getElementById('btn-retrain').addEventListener('click', async function() {
  if (!confirm('Latih ulang model? Proses ini membutuhkan beberapa menit.')) return;
  setLoading(this, true, 'Melatih...');
  try {
    const result = await API.retrainModel();
    showToast('✅ Model berhasil dilatih ulang!', 'success', 6000);
    loadModelInfo();
  } catch (e) {
    showToast('Gagal melatih ulang: ' + e.message, 'danger');
  } finally {
    setLoading(this, false);
  }
});

// ============================================================
// Quick Analyze demo buttons
// ============================================================
function fillDemoNormal() {
  document.getElementById('f-merchant').value = 'Toko Sembako Berkah';
  document.getElementById('f-amount').value = 250000;
  document.getElementById('f-recipient').value = 'Supplier Indofood';
  document.getElementById('f-desc').value = 'Pembelian stok barang bulanan';
  document.getElementById('f-count-1h').value = 1;
  document.getElementById('f-count-24h').value = 4;
  document.getElementById('f-avg-7d').value = 300000;
  document.getElementById('f-deviation').value = 0.17;
  document.getElementById('f-new-recipient').checked = false;
  document.getElementById('f-location-change').checked = false;
  document.getElementById('f-velocity').value = 0.05;
}

function fillDemoFraud() {
  document.getElementById('f-merchant').value = 'Toko Berkah Jaya';
  document.getElementById('f-amount').value = 85000000;
  document.getElementById('f-recipient').value = 'Rekening Tidak Dikenal';
  document.getElementById('f-desc').value = 'Transfer darurat';
  document.getElementById('f-count-1h').value = 12;
  document.getElementById('f-count-24h').value = 45;
  document.getElementById('f-avg-7d').value = 300000;
  document.getElementById('f-deviation').value = 283.0;
  document.getElementById('f-new-recipient').checked = true;
  document.getElementById('f-location-change').checked = true;
  document.getElementById('f-velocity').value = 0.95;
}

// ============================================================
// Init
// ============================================================
showView('dashboard');
