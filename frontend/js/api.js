/**
 * API Client - Sistem Deteksi Penipuan UMKM
 * Semua komunikasi ke backend FastAPI
 */

const API_BASE = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1'
  ? 'http://localhost:8000/api/v1'
  : `${window.location.origin}/api/v1`;

async function apiFetch(path, options = {}) {
  const url = `${API_BASE}${path}`;
  try {
    const res = await fetch(url, {
      headers: { 'Content-Type': 'application/json', ...options.headers },
      ...options,
    });
    if (!res.ok) {
      const err = await res.json().catch(() => ({ detail: res.statusText }));
      throw new Error(err.detail || `HTTP ${res.status}`);
    }
    return await res.json();
  } catch (e) {
    throw e;
  }
}

const API = {
  // Transactions
  analyzeTransaction: (data) =>
    apiFetch('/transactions/analyze', { method: 'POST', body: JSON.stringify(data) }),

  listTransactions: (params = {}) => {
    const qs = new URLSearchParams(params).toString();
    return apiFetch(`/transactions/?${qs}`);
  },

  getTransaction: (id) => apiFetch(`/transactions/${id}`),

  updateStatus: (id, status) =>
    apiFetch(`/transactions/${id}/status?new_status=${status}`, { method: 'PATCH' }),

  // Analytics
  getSummary: () => apiFetch('/analytics/summary'),
  getTrend: (days = 30) => apiFetch(`/analytics/trend?days=${days}`),
  getTopFraud: (limit = 10) => apiFetch(`/analytics/top-fraud?limit=${limit}`),
  getRiskDistribution: () => apiFetch('/analytics/risk-distribution'),
  getHourlyPattern: () => apiFetch('/analytics/hourly-pattern'),

  // Model
  getModelInfo: () => apiFetch('/model/info'),
  retrainModel: () => apiFetch('/model/retrain/sync', { method: 'POST' }),

  // Health
  health: () => fetch(`${API_BASE.replace('/api/v1', '')}/health`).then(r => r.json()),
};
