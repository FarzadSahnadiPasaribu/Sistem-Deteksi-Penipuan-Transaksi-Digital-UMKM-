/**
 * Utility helpers — format angka, tanggal, badge, toast, dll.
 */

// Format Rupiah
function formatRupiah(amount) {
  if (amount === null || amount === undefined) return '-';
  return new Intl.NumberFormat('id-ID', {
    style: 'currency', currency: 'IDR', maximumFractionDigits: 0,
  }).format(amount);
}

// Format tanggal Indonesia
function formatDate(dt) {
  if (!dt) return '-';
  return new Date(dt).toLocaleString('id-ID', {
    day: '2-digit', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  });
}

// Badge risk level
function riskBadge(level) {
  const icons = { KRITIS: '🚨', TINGGI: '⚠️', SEDANG: '🔶', RENDAH: '✅' };
  return `<span class="badge risk-${level}">${icons[level] || ''} ${level}</span>`;
}

// Badge status transaksi
function statusBadge(status) {
  const icons = { BLOCKED: '🚫', REVIEW: '🔍', APPROVED: '✅', PENDING: '⏳' };
  return `<span class="badge status-${status}">${icons[status] || ''} ${status}</span>`;
}

// Fraud score bar
function scoreBar(score) {
  const pct = Math.round(score * 100);
  let color = '#16a34a';
  if (pct >= 75) color = '#dc2626';
  else if (pct >= 50) color = '#d97706';
  else if (pct >= 30) color = '#2563eb';

  return `
    <div class="score-bar-wrap">
      <div class="score-bar">
        <div class="score-bar-fill" style="width:${pct}%;background:${color}"></div>
      </div>
      <span class="score-text" style="color:${color}">${pct}%</span>
    </div>`;
}

// Toast notification
const toastContainer = document.getElementById('toast-container');
function showToast(message, type = 'info', duration = 4000) {
  if (!toastContainer) return;
  const icons = { success: '✅', danger: '❌', warning: '⚠️', info: 'ℹ️' };
  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.innerHTML = `<span>${icons[type] || ''}</span><span>${message}</span>`;
  toastContainer.appendChild(toast);
  setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity .3s'; }, duration - 300);
  setTimeout(() => toast.remove(), duration);
}

// Loading state pada button
function setLoading(btn, loading, label = '') {
  if (loading) {
    btn._origHTML = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<span class="spinner"></span> ${label || 'Memproses...'}`;
  } else {
    btn.disabled = false;
    btn.innerHTML = btn._origHTML || label;
  }
}

// Nama hari
const HARI = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
function namaHari(idx) { return HARI[idx] || '-'; }

// Truncate teks
function truncate(str, n = 30) {
  if (!str) return '-';
  return str.length > n ? str.substring(0, n) + '...' : str;
}

// Debounce
function debounce(fn, ms = 300) {
  let t;
  return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}
