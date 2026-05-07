// ============================================================
// Navigasi
// ============================================================
const viewTitles = {
  dashboard: { title: 'Dashboard', sub: 'Ringkasan pemeriksaan bon transaksi' },
  periksa:   { title: 'Periksa Bon', sub: 'Periksa keaslian bon / struk dari e-commerce' },
  riwayat:   { title: 'Riwayat Pemeriksaan', sub: 'Semua bon yang pernah diperiksa' },
};

function showView(name) {
  document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  document.getElementById(`view-${name}`)?.classList.add('active');
  document.querySelector(`[data-view="${name}"]`)?.classList.add('active');
  const t = viewTitles[name] || {};
  document.getElementById('topbar-title').textContent = t.title || '';
  document.getElementById('topbar-subtitle').textContent = t.sub || '';
  if (name === 'dashboard') loadDashboard();
  if (name === 'riwayat') loadRiwayat(0);
}

function reloadCurrentView() {
  const active = document.querySelector('.view.active');
  if (!active) return;
  const name = active.id.replace('view-', '');
  if (name === 'dashboard') loadDashboard();
  if (name === 'riwayat') loadRiwayat(0);
}

document.querySelectorAll('.nav-item').forEach(item => {
  item.addEventListener('click', () => { if (item.dataset.view) showView(item.dataset.view); });
});

// ============================================================
// Dashboard
// ============================================================
let trendChart, riskChart;

async function loadDashboard() {
  try {
    const [summary, trend, risk] = await Promise.all([
      API.getSummary(), API.getTrend(30), API.getRiskDistribution()
    ]);
    renderSummary(summary);
    renderCharts(trend, risk);
    loadTopFraud();
  } catch (e) {
    showToast('Gagal memuat dashboard: ' + e.message, 'danger');
  }
}

function renderSummary(s) {
  document.getElementById('stat-total').textContent = s.total_transactions.toLocaleString('id-ID');
  document.getElementById('stat-fraud').textContent = s.total_fraud.toLocaleString('id-ID');
  document.getElementById('stat-normal').textContent = s.total_normal.toLocaleString('id-ID');
  document.getElementById('stat-blocked').textContent = s.total_blocked.toLocaleString('id-ID');
  document.getElementById('stat-rate').textContent = s.fraud_rate_pct + '%';
  document.getElementById('stat-amount').textContent = formatRupiah(s.total_amount_idr);
  document.getElementById('stat-fraud-amount').textContent = formatRupiah(s.fraud_amount_idr);

  const rb = s.risk_breakdown;
  document.getElementById('rb-berbahaya').textContent = rb.BERBAHAYA ?? rb.KRITIS ?? 0;
  document.getElementById('rb-berisiko').textContent = rb.BERISIKO ?? rb.TINGGI ?? 0;
  document.getElementById('rb-waspada').textContent = rb.WASPADA ?? rb.SEDANG ?? 0;
  document.getElementById('rb-aman').textContent = rb.AMAN ?? rb.RENDAH ?? 0;
}

function renderCharts(trend, risk) {
  if (trendChart) trendChart.destroy();
  trendChart = new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
      labels: trend.data.map(d => d.date.slice(5)),
      datasets: [
        { label: 'Valid', data: trend.data.map(d => d.normal), borderColor: '#16a34a', backgroundColor: 'rgba(22,163,74,.08)', fill: true, tension: .3, pointRadius: 2 },
        { label: 'Penipuan', data: trend.data.map(d => d.fraud), borderColor: '#dc2626', backgroundColor: 'rgba(220,38,38,.08)', fill: true, tension: .3, pointRadius: 2 },
      ],
    },
    options: { responsive: true, plugins: { legend: { position: 'top' }, tooltip: { mode: 'index' } }, scales: { y: { beginAtZero: true, grid: { color: '#f3f4f6' } }, x: { grid: { display: false } } } },
  });

  if (riskChart) riskChart.destroy();
  riskChart = new Chart(document.getElementById('riskChart'), {
    type: 'doughnut',
    data: {
      labels: risk.data.map(d => d.risk_level),
      datasets: [{ data: risk.data.map(d => d.count), backgroundColor: ['#16a34a','#2563eb','#d97706','#dc2626'], borderWidth: 2, borderColor: '#fff' }],
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } }, cutout: '65%' },
  });
}

async function loadTopFraud() {
  try {
    const data = await API.getTopFraud(5);
    const tbody = document.getElementById('top-fraud-body');
    if (!data.data.length) {
      tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;padding:20px;color:var(--gray-400)">Belum ada penipuan terdeteksi</td></tr>`;
      return;
    }
    tbody.innerHTML = data.data.map(t => `<tr>
      <td><code style="font-size:11px;color:var(--primary)">${t.transaction_id}</code></td>
      <td>${truncate(t.merchant_name, 22)}</td>
      <td style="font-weight:700">${formatRupiah(t.amount)}</td>
      <td>${scoreBar(t.fraud_score)}</td>
      <td>${riskBadge(t.risk_level)}</td>
    </tr>`).join('');
  } catch(e) {}
}

// ============================================================
// Parser Teks Bon
// ============================================================
function parseReceiptText() {
  const text = document.getElementById('receipt-text').value;
  if (!text.trim()) { showToast('Tempel teks bon terlebih dahulu', 'warning'); return; }

  // Ekstrak total
  const totalMatch = text.match(/total[^:]*:?\s*Rp\.?\s*([\d.,]+)/i) ||
                     text.match(/bayar[^:]*:?\s*Rp\.?\s*([\d.,]+)/i);
  if (totalMatch) {
    document.getElementById('f-total').value = totalMatch[1].replace(/[.,]/g, '').replace(/(\d+)(\d{3})/, '$1$2');
  }

  // Ekstrak subtotal
  const subtotalMatch = text.match(/subtotal[^:]*:?\s*Rp\.?\s*([\d.,]+)/i) ||
                        text.match(/harga[^:]*:?\s*Rp\.?\s*([\d.,]+)/i);
  if (subtotalMatch) {
    document.getElementById('f-subtotal').value = subtotalMatch[1].replace(/[.,]/g, '');
  }

  // Ekstrak diskon
  const discMatch = text.match(/diskon[^:]*:?\s*Rp\.?\s*([\d.,]+)/i) ||
                    text.match(/voucher[^:]*:?\s*Rp\.?\s*([\d.,]+)/i) ||
                    text.match(/potongan[^:]*:?\s*Rp\.?\s*([\d.,]+)/i);
  if (discMatch) {
    document.getElementById('f-discount').value = discMatch[1].replace(/[.,]/g, '');
  }

  // Ekstrak order ID
  const orderMatch = text.match(/(?:no\.?\s*pesanan|order\s*id|invoice)[:\s]+([A-Z0-9\-\/]+)/i);
  if (orderMatch) document.getElementById('f-order-id').value = orderMatch[1].trim();

  // Deteksi platform
  const textLow = text.toLowerCase();
  const platformSel = document.getElementById('f-platform');
  if (textLow.includes('shopee')) platformSel.value = 'Shopee';
  else if (textLow.includes('tokopedia')) platformSel.value = 'Tokopedia';
  else if (textLow.includes('lazada')) platformSel.value = 'Lazada';
  else if (textLow.includes('bukalapak')) platformSel.value = 'Bukalapak';
  else if (textLow.includes('tiktok')) platformSel.value = 'TikTok Shop';

  // Deteksi tanda bahaya
  const urgentWords = /segera|darurat|transfer sekarang|stok terakhir|habis|batas waktu|jangan sampai/i;
  if (urgentWords.test(text)) {
    document.getElementById('f-urgent').checked = true;
    showToast('⚠️ Terdeteksi kata-kata mendesak di bon ini!', 'warning');
  }

  const transferPribadi = /rekening pribadi|no\.?\s*rek|a\/n|atas nama|gopay|ovo|dana/i;
  const bukanGateway = /transfer ke|kirim ke|bayar ke/i;
  if (transferPribadi.test(text) && bukanGateway.test(text)) {
    document.getElementById('f-transfer-pribadi').checked = true;
  }

  // Platform tidak dikenal
  if (textLow.includes('whatsapp') || textLow.includes('wa.me') || textLow.includes('telegram')) {
    document.getElementById('f-platform-unverified').checked = true;
    platformSel.value = 'WhatsApp/Pribadi';
  }

  hitungDiskon();
  showToast('✅ Data berhasil diekstrak dari teks bon', 'success');
  showView('periksa');
}

function hitungDiskon() {
  const subtotal = parseFloat(document.getElementById('f-subtotal').value) || 0;
  const discount = parseFloat(document.getElementById('f-discount').value) || 0;
  const total = parseFloat(document.getElementById('f-total').value) || 0;
  const hint = document.getElementById('diskon-hint');

  if (subtotal > 0 && total > 0) {
    const pct = Math.round((1 - total / subtotal) * 100);
    if (pct >= 70) {
      hint.innerHTML = `<span style="color:var(--danger);font-weight:600">⚠️ Diskon ${pct}% — sangat mencurigakan!</span>`;
    } else if (pct >= 40) {
      hint.innerHTML = `<span style="color:var(--warning);font-weight:600">🔶 Diskon ${pct}% — perlu diperiksa lebih lanjut</span>`;
    } else if (pct > 0) {
      hint.innerHTML = `<span style="color:var(--gray-500)">Diskon ${pct}% dari subtotal</span>`;
    } else {
      hint.textContent = '';
    }
  }
}

// ============================================================
// Form Submit
// ============================================================
document.getElementById('form-periksa').addEventListener('submit', async (e) => {
  e.preventDefault();
  const btn = document.getElementById('btn-periksa');
  const total = parseFloat(document.getElementById('f-total').value);
  if (!total || total <= 0) { showToast('Isi total pembayaran terlebih dahulu', 'warning'); return; }

  setLoading(btn, true);
  document.getElementById('result-placeholder').style.display = 'none';
  document.getElementById('result-box').className = 'result-box';

  const subtotal = parseFloat(document.getElementById('f-subtotal').value) || total;
  const discount = parseFloat(document.getElementById('f-discount').value) || 0;
  const platformUnverified = document.getElementById('f-platform-unverified').checked;
  const platform = document.getElementById('f-platform').value;

  const payload = {
    platform: platform || undefined,
    seller_name: document.getElementById('f-seller').value || undefined,
    order_id: document.getElementById('f-order-id').value || undefined,
    description: document.getElementById('f-desc').value || undefined,
    total_amount: total,
    subtotal: subtotal,
    discount_amount: discount,
    item_count: parseInt(document.getElementById('f-item-count').value) || 1,
    is_cod: document.getElementById('f-cod').checked,
    is_transfer_pribadi: document.getElementById('f-transfer-pribadi').checked,
    platform_verified: !platformUnverified && !['WhatsApp/Pribadi', 'Lainnya', ''].includes(platform),
    seller_age_days: parseInt(document.getElementById('f-seller-age').value) || 365,
    has_urgent_words: document.getElementById('f-urgent').checked,
    price_ratio: parseFloat(document.getElementById('f-price-ratio').value) || 1.0,
  };

  try {
    const result = await API.periksaBon(payload);
    renderResult(result);
    showToast(
      result.is_fraud ? `🚨 Bon ini terindikasi ${result.risk_level}!` : '✅ Bon terlihat valid',
      result.is_fraud ? 'danger' : 'success'
    );
  } catch (e) {
    showToast('Error: ' + e.message, 'danger');
    document.getElementById('result-placeholder').style.display = '';
  } finally {
    setLoading(btn, false);
  }
});

function renderResult(r) {
  const box = document.getElementById('result-box');
  const pct = Math.round(r.fraud_score * 100);
  let cls, icon, title, titleColor;

  if (!r.is_fraud) {
    cls = 'aman'; icon = '✅'; title = 'BON TERLIHAT VALID'; titleColor = '#16a34a';
  } else if (r.risk_level === 'BERBAHAYA') {
    cls = 'bahaya'; icon = '🚨'; title = 'PENIPUAN BERBAHAYA!'; titleColor = '#dc2626';
  } else {
    cls = 'berisiko'; icon = '⚠️'; title = 'BON MENCURIGAKAN'; titleColor = '#d97706';
  }

  box.className = `result-box ${cls} show`;
  box.innerHTML = `
    <div class="result-header">
      <span class="result-icon">${icon}</span>
      <div>
        <div class="result-title" style="color:${titleColor}">${title}</div>
        <div class="result-sub">No. Bon: ${r.transaction_id} &nbsp;|&nbsp; Status: ${r.status}</div>
      </div>
      <div style="margin-left:auto">${riskBadge(r.risk_level)}</div>
    </div>

    <div class="result-metrics">
      <div class="result-metric">
        <div class="metric-val" style="color:${titleColor}">${pct}%</div>
        <div class="metric-lbl">Skor Risiko</div>
      </div>
      <div class="result-metric">
        <div class="metric-val">${formatRupiah(r.total_amount)}</div>
        <div class="metric-lbl">Nilai Transaksi</div>
      </div>
      <div class="result-metric">
        <div class="metric-val">${r.discount_pct}%</div>
        <div class="metric-lbl">Diskon</div>
      </div>
    </div>

    <div style="font-size:13px;font-weight:700;margin-bottom:8px;color:var(--gray-700)">
      📋 Temuan Pemeriksaan:
    </div>
    <ul class="reasons-list">
      ${r.explanation.map(e => `<li>${e}</li>`).join('')}
    </ul>

    ${r.is_fraud ? `
    <div style="margin-top:14px;padding:12px 14px;background:rgba(220,38,38,.08);border-radius:8px;font-size:13px;color:#7f1d1d">
      <strong>💡 Saran:</strong> Jangan lakukan pembayaran. Laporkan ke platform resmi atau hubungi bank Anda.
    </div>` : `
    <div style="margin-top:14px;padding:12px 14px;background:rgba(22,163,74,.08);border-radius:8px;font-size:13px;color:#14532d">
      <strong>💡 Tetap waspada:</strong> Selalu verifikasi ulang melalui aplikasi resmi sebelum membayar.
    </div>`}
  `;
  box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// ============================================================
// Demo
// ============================================================
function demoValid() {
  document.getElementById('f-platform').value = 'Tokopedia';
  document.getElementById('f-order-id').value = 'TKP-20240501-8821';
  document.getElementById('f-seller').value = 'Official Store Samsung';
  document.getElementById('f-desc').value = 'Samsung Galaxy A54 5G';
  document.getElementById('f-subtotal').value = 4500000;
  document.getElementById('f-discount').value = 450000;
  document.getElementById('f-total').value = 4050000;
  document.getElementById('f-item-count').value = 1;
  document.getElementById('f-seller-age').value = 1200;
  document.getElementById('f-price-ratio').value = '1.0';
  document.getElementById('f-transfer-pribadi').checked = false;
  document.getElementById('f-cod').checked = false;
  document.getElementById('f-urgent').checked = false;
  document.getElementById('f-platform-unverified').checked = false;
  hitungDiskon();
  showView('periksa');
}

function demoPenipuan() {
  document.getElementById('f-platform').value = 'WhatsApp/Pribadi';
  document.getElementById('f-order-id').value = 'WA-TOKO-001';
  document.getElementById('f-seller').value = 'Toko HP Murah Meriah';
  document.getElementById('f-desc').value = 'iPhone 15 Pro Max 256GB BNIB';
  document.getElementById('f-subtotal').value = 21000000;
  document.getElementById('f-discount').value = 18500000;
  document.getElementById('f-total').value = 2500000;
  document.getElementById('f-item-count').value = 1;
  document.getElementById('f-seller-age').value = 3;
  document.getElementById('f-price-ratio').value = '0.1';
  document.getElementById('f-transfer-pribadi').checked = true;
  document.getElementById('f-cod').checked = false;
  document.getElementById('f-urgent').checked = true;
  document.getElementById('f-platform-unverified').checked = true;
  hitungDiskon();
  showView('periksa');
}

function resetForm() {
  document.getElementById('form-periksa').reset();
  document.getElementById('receipt-text').value = '';
  document.getElementById('result-box').className = 'result-box';
  document.getElementById('result-placeholder').style.display = '';
  document.getElementById('diskon-hint').textContent = '';
}

// ============================================================
// Riwayat
// ============================================================
let riwayatPage = 0;
const PAGE_SIZE = 15;

async function loadRiwayat(page = 0) {
  riwayatPage = page;
  const tbody = document.getElementById('riwayat-body');
  tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:24px"><span class="spinner dark"></span></td></tr>`;

  try {
    const params = { skip: page * PAGE_SIZE, limit: PAGE_SIZE };
    const ff = document.getElementById('filter-fraud').value;
    const fr = document.getElementById('filter-risk').value;
    const fs = document.getElementById('filter-status').value;
    if (ff !== '') params.is_fraud = ff;
    if (fr) params.risk_level = fr;
    if (fs) params.status = fs;

    const data = await API.listTransactions(params);
    renderRiwayat(data);
  } catch (e) {
    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:var(--danger);padding:24px">Error: ${e.message}</td></tr>`;
  }
}

function renderRiwayat(data) {
  const tbody = document.getElementById('riwayat-body');
  document.getElementById('riwayat-page-info').textContent =
    `${data.skip + 1}–${Math.min(data.skip + data.limit, data.total)} dari ${data.total} bon`;

  if (!data.data.length) {
    tbody.innerHTML = `<tr><td colspan="7"><div style="text-align:center;padding:48px;color:var(--gray-400)">
      <div style="font-size:40px;margin-bottom:10px">📭</div>
      <div>Tidak ada data</div></div></td></tr>`;
    return;
  }

  tbody.innerHTML = data.data.map(t => `<tr>
    <td>
      <code style="font-size:11px;color:var(--primary)">${t.transaction_id}</code>
      <div style="font-size:11px;color:var(--gray-400)">${formatDate(t.created_at)}</div>
    </td>
    <td>${truncate(t.merchant_name, 22)}</td>
    <td style="font-weight:700">${formatRupiah(t.amount)}</td>
    <td>${scoreBar(t.fraud_score)}</td>
    <td>${riskBadge(t.risk_level)}</td>
    <td>${statusBadge(t.status)}</td>
    <td><button class="btn btn-outline btn-sm" onclick="showDetail('${t.transaction_id}')">Detail</button></td>
  </tr>`).join('');

  document.getElementById('btn-prev').disabled = riwayatPage === 0;
  document.getElementById('btn-next').disabled = (riwayatPage + 1) * PAGE_SIZE >= data.total;
}

document.getElementById('btn-prev').addEventListener('click', () => loadRiwayat(riwayatPage - 1));
document.getElementById('btn-next').addEventListener('click', () => loadRiwayat(riwayatPage + 1));
['filter-fraud','filter-risk','filter-status'].forEach(id => {
  document.getElementById(id).addEventListener('change', () => loadRiwayat(0));
});

// ============================================================
// Modal Detail
// ============================================================
async function showDetail(txId) {
  const modal = document.getElementById('modal-detail');
  const body = document.getElementById('modal-body');
  modal.style.display = 'flex';
  body.innerHTML = `<div style="text-align:center;padding:40px"><span class="spinner dark"></span></div>`;
  try {
    const t = await API.getTransaction(txId);
    const pct = Math.round(t.fraud_score * 100);
    body.innerHTML = `
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:18px">
        <div>
          <div style="font-size:16px;font-weight:800">${t.transaction_id}</div>
          <div style="font-size:13px;color:var(--gray-500)">${formatDate(t.created_at)}</div>
        </div>
        <div style="margin-left:auto;display:flex;gap:8px">${riskBadge(t.risk_level)} ${statusBadge(t.status)}</div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px;font-size:13px">
        ${dr('Toko / Penjual', t.merchant_name)}
        ${dr('Platform', t.description)}
        ${dr('Nilai Transaksi', formatRupiah(t.amount))}
        ${dr('Skor Risiko', pct + '%')}
      </div>

      <div style="background:var(--gray-50);border-radius:10px;padding:14px;margin-bottom:14px">
        <div style="font-weight:700;font-size:13px;margin-bottom:10px">Detail Skor Deteksi</div>
        ${sr('Isolation Forest', t.score_detail.isolation_forest)}
        ${sr('Local Outlier Factor', t.score_detail.local_outlier_factor)}
        ${sr('Aturan Bisnis', t.score_detail.rule_based)}
        <div style="border-top:1px solid var(--gray-200);margin-top:8px;padding-top:8px">
          ${sr('SKOR FINAL', t.fraud_score, true)}
        </div>
      </div>

      <div style="margin-bottom:16px">
        <div style="font-weight:700;font-size:13px;margin-bottom:8px">📋 Temuan</div>
        <ul class="reasons-list" style="background:var(--gray-50);border-radius:10px;padding:10px 10px 10px 28px">
          ${(t.explanation||[]).map(e=>`<li>${e}</li>`).join('')}
        </ul>
      </div>

      <div style="display:flex;gap:8px;justify-content:flex-end">
        <button class="btn btn-success btn-sm" onclick="updateStatus('${t.transaction_id}','AMAN')">✅ Tandai Aman</button>
        <button class="btn btn-danger btn-sm" onclick="updateStatus('${t.transaction_id}','DIBLOKIR')">🚫 Blokir</button>
        <button class="btn btn-outline btn-sm" onclick="closeModal()">Tutup</button>
      </div>`;
  } catch (e) {
    body.innerHTML = `<div style="color:var(--danger);padding:20px">Error: ${e.message}</div>`;
  }
}

function dr(label, value) {
  return `<div style="background:var(--gray-50);border-radius:8px;padding:10px 14px">
    <div style="font-size:11px;color:var(--gray-500);font-weight:600">${label}</div>
    <div style="font-weight:600;margin-top:2px">${value || '-'}</div>
  </div>`;
}

function sr(label, score, bold = false) {
  const pct = Math.round(score * 100);
  const color = pct >= 75 ? '#dc2626' : pct >= 50 ? '#d97706' : pct >= 30 ? '#2563eb' : '#16a34a';
  return `<div style="display:flex;align-items:center;gap:10px;margin-bottom:7px;${bold?'font-weight:800':''}">
    <span style="flex:1;font-size:13px">${label}</span>
    <div class="score-bar" style="flex:2"><div class="score-bar-fill" style="width:${pct}%;background:${color}"></div></div>
    <span style="font-size:13px;font-weight:700;color:${color};min-width:34px;text-align:right">${pct}%</span>
  </div>`;
}

async function updateStatus(txId, status) {
  try {
    await API.updateStatus(txId, status);
    showToast('Status diperbarui: ' + status, 'success');
    closeModal();
    loadRiwayat(riwayatPage);
  } catch (e) {
    showToast('Gagal: ' + e.message, 'danger');
  }
}

function closeModal() {
  document.getElementById('modal-detail').style.display = 'none';
}
document.getElementById('modal-detail').addEventListener('click', e => { if (e.target === document.getElementById('modal-detail')) closeModal(); });

// ============================================================
// Init
// ============================================================
showView('dashboard');
