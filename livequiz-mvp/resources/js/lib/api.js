const jsonHeaders = {
  Accept: 'application/json',
  'Content-Type': 'application/json',
};

async function request(path, options = {}) {
  const token = window.localStorage.getItem('livequiz_token');
  const response = await fetch(`/api${path}`, {
    headers: {
      ...jsonHeaders,
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(options.headers || {}),
    },
    ...options,
  });

  const contentType = response.headers.get('content-type') || '';
  const payload = contentType.includes('application/json') ? await response.json() : await response.text();

  if (!response.ok) {
    const message = payload?.message || 'Запрос не выполнен';
    throw new Error(message);
  }

  return payload;
}

export const api = {
  get: (path) => request(path),
  post: (path, body = {}) => request(path, { method: 'POST', body: JSON.stringify(body) }),
  put: (path, body = {}) => request(path, { method: 'PUT', body: JSON.stringify(body) }),
  delete: (path) => request(path, { method: 'DELETE' }),
  csvUrl: (sessionId) => {
    const token = window.localStorage.getItem('livequiz_token');
    return `/api/sessions/${sessionId}/export.csv${token ? `?token=${encodeURIComponent(token)}` : ''}`;
  },
};

export const wsUrl = import.meta.env.VITE_WS_URL || 'ws://127.0.0.1:6001';
