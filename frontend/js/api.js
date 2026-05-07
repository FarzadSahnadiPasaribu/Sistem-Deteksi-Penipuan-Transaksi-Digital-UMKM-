const API_BASE = (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')
  ? 'http://localhost:8000/api/v1'
  : `${window.location.origin}/api/v1`;

async function apiFetch(path, options = {}) {
  const res = await fetch(`${API_BASE}${path}`, {
    headers: { 'Content-Type': 'application/json', ...options.headers },
    ...options,
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({ detail: res.statusText }));
    throw new Error(err.detail || `HTTP ${res.status}`);
  }
  return res.json();
}

const API = {
  periksaBon: (data) => apiFetch('/transactions/analyze', { method: 'POST', body: JSON.stringify(data) }),
  listTransactions: (params = {}) => apiFetch(`/transactions/?${new URLSearchParams(params)}`),
  getTransaction: (id) => apiFetch(`/transactions/${id}`),
  updateStatus: (id, status) => apiFetch(`/transactions/${id}/status?new_status=${status}`, { method: 'PATCH' }),
  getSummary: () => apiFetch('/analytics/summary'),
  getTrend: (days = 30) => apiFetch(`/analytics/trend?days=${days}`),
  getTopFraud: (limit = 5) => apiFetch(`/analytics/top-fraud?limit=${limit}`),
  getRiskDistribution: () => apiFetch('/analytics/risk-distribution'),
  getModelInfo: () => apiFetch('/model/info'),
  retrain: () => apiFetch('/model/retrain/sync', { method: 'POST' }),
};
