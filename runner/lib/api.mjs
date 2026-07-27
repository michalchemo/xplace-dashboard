import { config } from './config.mjs';

async function call(method, path, body) {
  const { API_BASE, API_KEY } = config();
  const r = await fetch(`${API_BASE}${path}`, {
    method,
    headers: {
      'Authorization': `Bearer ${API_KEY}`,
      ...(body ? { 'Content-Type': 'application/json' } : {}),
    },
    body: body ? JSON.stringify(body) : undefined,
  });
  if (!r.ok) throw new Error(`${method} ${path} -> HTTP ${r.status}`);
  return r.json();
}

export const getDashboardStatus = () => call('GET', '/api/get_dashboard_status.php');
export const getApproved = () => call('GET', '/api/get_approved.php');
// More endpoints are added per job as phases land (BRISK-70+).
