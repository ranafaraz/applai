const BASE_URL = process.env.CRM_BASE_URL ?? '';
const API_KEY  = process.env.CRM_API_KEY  ?? '';

if (!BASE_URL || !API_KEY) {
  console.error('ERROR: CRM_BASE_URL and CRM_API_KEY must be set in environment.');
  process.exit(1);
}

async function crmFetch(path: string, options: RequestInit = {}): Promise<unknown> {
  const url = `${BASE_URL.replace(/\/$/, '')}${path}`;

  const res = await fetch(url, {
    ...options,
    headers: {
      'X-Api-Key': API_KEY,
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      ...(options.headers ?? {}),
    },
  });

  const body = await res.json().catch(() => ({ error: 'Non-JSON response' }));

  if (!res.ok) {
    const msg = (body as Record<string, unknown>)?.error ?? res.statusText;
    throw new Error(`CRM API error ${res.status}: ${msg}`);
  }

  return body;
}

export const crm = {
  get:    (path: string)                  => crmFetch(path),
  post:   (path: string, data: unknown)   => crmFetch(path, { method: 'POST',   body: JSON.stringify(data) }),
  patch:  (path: string, data: unknown)   => crmFetch(path, { method: 'PATCH',  body: JSON.stringify(data) }),
  delete: (path: string)                  => crmFetch(path, { method: 'DELETE' }),

  // Calls the Social Studio API (/api/social/v1), derived from CRM_BASE_URL.
  // The GPT API base ends in /api/gpt/v1; swap the segment to reach social.
  postSocial: (path: string, data: unknown) =>
    crmFetchAbsolute(
      BASE_URL.replace(/\/$/, '').replace(/\/api\/gpt\/v1$/, '/api/social/v1') + path,
      { method: 'POST', body: JSON.stringify(data) },
    ),
};

async function crmFetchAbsolute(url: string, options: RequestInit = {}): Promise<unknown> {
  const res = await fetch(url, {
    ...options,
    headers: {
      'X-Api-Key': API_KEY,
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      ...(options.headers ?? {}),
    },
  });

  const body = await res.json().catch(() => ({ error: 'Non-JSON response' }));

  if (!res.ok) {
    const msg = (body as Record<string, unknown>)?.error ?? res.statusText;
    throw new Error(`CRM API error ${res.status}: ${msg}`);
  }

  return body;
}
