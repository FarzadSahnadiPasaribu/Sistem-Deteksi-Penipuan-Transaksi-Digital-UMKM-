function formatRupiah(n) {
  if (n == null) return '-';
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n);
}

function formatDate(dt) {
  if (!dt) return '-';
  return new Date(dt).toLocaleString('id-ID', { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' });
}

function riskBadge(level) {
  const icon = { BERBAHAYA:'🚨', BERISIKO:'⚠️', WASPADA:'🔶', AMAN:'✅' };
  return `<span class="badge risk-${level}">${icon[level]||''} ${level}</span>`;
}

function statusBadge(status) {
  const icon = { DIBLOKIR:'🚫', DITINJAU:'🔍', AMAN:'✅' };
  return `<span class="badge status-${status}">${icon[status]||''} ${status}</span>`;
}

function scoreBar(score) {
  const pct = Math.round(score * 100);
  const color = pct >= 75 ? '#dc2626' : pct >= 50 ? '#d97706' : pct >= 30 ? '#2563eb' : '#16a34a';
  return `<div class="score-bar-wrap">
    <div class="score-bar"><div class="score-bar-fill" style="width:${pct}%;background:${color}"></div></div>
    <span class="score-text" style="color:${color}">${pct}%</span>
  </div>`;
}

function showToast(msg, type = 'info', dur = 4000) {
  const c = document.getElementById('toast-container');
  if (!c) return;
  const icons = { success:'✅', danger:'❌', warning:'⚠️', info:'ℹ️' };
  const t = document.createElement('div');
  t.className = `toast toast-${type}`;
  t.innerHTML = `<span>${icons[type]||''}</span><span>${msg}</span>`;
  c.appendChild(t);
  setTimeout(() => { t.style.opacity='0'; t.style.transition='opacity .3s'; }, dur - 300);
  setTimeout(() => t.remove(), dur);
}

function setLoading(btn, loading) {
  if (loading) {
    btn._orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<span class="spinner"></span> Memeriksa...`;
  } else {
    btn.disabled = false;
    btn.innerHTML = btn._orig || 'Submit';
  }
}

function truncate(s, n = 28) {
  if (!s) return '-';
  return s.length > n ? s.substring(0, n) + '…' : s;
}
