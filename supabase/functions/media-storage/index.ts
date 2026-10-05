// Server-only bridge. Laravel authorizes users; only Laravel holds the bridge
// token. Supabase service credentials stay inside the Edge runtime.
import { tokenHash } from './auth-hash.ts';

const base = Deno.env.get('SUPABASE_URL')! + '/storage/v1';
const key = Deno.env.get('SUPABASE_SERVICE_ROLE_KEY')!;
const json = (value: unknown, status = 200) => Response.json(value, { status });

export async function handle(req: Request): Promise<Response> {
  const token = req.headers.get('x-media-token') || '';
  const digest = new Uint8Array(await crypto.subtle.digest('SHA-256', new TextEncoder().encode(token)));
  const hash = Array.from(digest, b => b.toString(16).padStart(2, '0')).join('');
  if (!token || hash !== tokenHash) return json({ error: 'Unauthorized' }, 401);

  const url = new URL(req.url);
  const op = url.searchParams.get('op');
  const path = url.searchParams.get('path') || '';
  const headers = new Headers({ apikey: key, Authorization: `Bearer ${key}` });
  if (op === 'setup' && req.method === 'POST') {
    for (const [id, isPublic] of [['livecommerce-raw', false], ['livecommerce-media', true]] as const) {
      const found = await fetch(`${base}/bucket/${id}`, { headers });
      if (found.ok) continue;
      const created = await fetch(`${base}/bucket`, {
        method: 'POST', headers: { ...Object.fromEntries(headers), 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, name: id, public: isPublic, file_size_limit: 52428800 }),
      });
      if (!created.ok) return json({ error: 'Bucket setup failed' }, 502);
    }
    return json({ ready: true });
  }
  if (!/^[a-zA-Z0-9_/-]+\.[a-zA-Z0-9]+$/.test(path) || path.includes('..') || path.startsWith('/')) {
    return json({ error: 'Invalid object path' }, 400);
  }
  const bucket = /\/raw\.(mp4|mov|webm)$/.test(path) ? 'livecommerce-raw' : 'livecommerce-media';
  const object = `${bucket}/${path}`;
  let response: Response;
  if (op === 'sign' && req.method === 'POST') {
    headers.set('Content-Type', 'application/json');
    response = await fetch(`${base}/object/upload/sign/${object}`, { method: 'POST', headers, body: '{}' });
    if (!response.ok) return json({ error: 'Upload signing failed' }, 502);
    const data = await response.json();
    return json({ url: base + data.url });
  } else if (op === 'exists' && req.method === 'GET') {
    response = await fetch(`${base}/object/authenticated/${object}`, { method: 'HEAD', headers });
    if (response.ok) return json({ exists: true });
    if ([400, 404].includes(response.status)) return json({ exists: false });
    return json({ error: 'Storage unavailable' }, 502);
  } else if (op === 'read' && req.method === 'GET') {
    response = await fetch(`${base}/object/authenticated/${object}`, { headers });
  } else if (op === 'write' && req.method === 'PUT') {
    headers.set('Content-Type', req.headers.get('Content-Type') || 'application/octet-stream');
    headers.set('x-upsert', 'true');
    response = await fetch(`${base}/object/${object}`, { method: 'POST', headers, body: req.body });
  } else if (op === 'delete' && req.method === 'DELETE') {
    headers.set('Content-Type', 'application/json');
    response = await fetch(`${base}/object/${bucket}`, { method: 'DELETE', headers, body: JSON.stringify({ prefixes: [path] }) });
  } else {
    return json({ error: 'Unsupported operation' }, 405);
  }
  if (!response.ok) return json({ error: 'Storage operation failed' }, response.status === 404 ? 404 : 502);
  return new Response(response.body, { status: response.status, headers: { 'Content-Type': response.headers.get('Content-Type') || 'application/octet-stream' } });
}

Deno.serve(req => handle(req).catch(() => json({ error: 'Storage unavailable' }, 502)));
