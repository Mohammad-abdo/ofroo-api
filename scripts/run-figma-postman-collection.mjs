/**
 * Runs requests from OFROO Figma Postman collection against a live API.
 * Usage: node scripts/run-figma-postman-collection.mjs
 */
import { readFileSync, writeFileSync } from 'fs';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';

const __dirname = dirname(fileURLToPath(import.meta.url));
const COLLECTION = join(__dirname, '..', 'OFROO - Mobile User API (Figma Design Complete).postman_collection.json');

const BASE = 'http://127.0.0.1:8000';

const SKIP_NAMES = new Set(['Logout', 'Delete Account', 'Upload Avatar']);

function walkItems(items, folder, out) {
  for (const it of items) {
    const fp = folder ? `${folder} / ${it.name}` : it.name;
    if (it.item) walkItems(it.item, fp, out);
    if (it.request) out.push({ folder: fp, name: it.name, request: it.request });
  }
}

function resolveUrl(urlObj, accessToken) {
  const vars = {
    base_url: BASE,
    access_token: accessToken,
    id: '1',
  };
  if (urlObj?.variable) {
    for (const v of urlObj.variable) {
      if (v?.key) vars[v.key] = v.value ?? '1';
    }
  }
  let raw = urlObj?.raw || '';
  for (const [k, val] of Object.entries(vars)) {
    raw = raw.split(`{{${k}}}`).join(String(val));
  }
  // Replace Postman path params like :id — must NOT touch host port (:8000).
  try {
    const u = new URL(raw);
    const parts = u.pathname.split('/').map((seg) => {
      if (seg.startsWith(':')) {
        const key = seg.slice(1);
        return String(vars[key] ?? '1');
      }
      return seg;
    });
    u.pathname = parts.join('/') || '/';
    return u.toString();
  } catch {
    return raw;
  }
}

function substituteVars(str, accessToken) {
  if (str == null) return str;
  return String(str)
    .replace(/\{\{base_url\}\}/g, BASE)
    .replace(/\{\{access_token\}\}/g, accessToken)
    .replace(/\{\{id\}\}/g, '1');
}

/** Find JSON strings that look like numbers where strict clients expect numbers */
function analyzeTypes(obj, path = '', findings = []) {
  if (obj === null || obj === undefined) return findings;
  if (Array.isArray(obj)) {
    obj.forEach((item, i) => analyzeTypes(item, `${path}[${i}]`, findings));
    return findings;
  }
  if (typeof obj === 'object') {
    for (const [k, v] of Object.entries(obj)) {
      const p = path ? `${path}.${k}` : k;
      const idLike = /(^id$|_id$|_count$|^count$|order_index|^page$|per_page|total)/i.test(k);
      if (typeof v === 'string' && idLike && /^-?\d+(\.\d+)?$/.test(v)) {
        findings.push({ path: p, value: v, issue: 'string-looks-like-number' });
      }
      if (typeof v === 'string' && /^(price|amount|discount|lat|lng|latitude|longitude|rating)$/i.test(k) && /^-?\d+(\.\d+)?$/.test(v)) {
        findings.push({ path: p, value: v, issue: 'numeric-field-as-string' });
      }
      analyzeTypes(v, p, findings);
    }
  }
  return findings;
}

async function bootstrapToken() {
  const ts = Date.now();
  const email = `api_test_${ts}@example.com`;
  const phone = `+9665${String(ts).slice(-8)}`;
  const body = {
    name: 'API Test User',
    email,
    phone,
    password: 'password123',
    password_confirmation: 'password123',
    language: 'ar',
    city: 'Test City',
  };
  const r = await fetch(`${BASE}/api/mobile/auth/register`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify(body),
  });
  const text = await r.text();
  let json = null;
  try {
    json = JSON.parse(text);
  } catch {
    /* ignore */
  }
  const token = json?.token ?? json?.data?.token ?? null;
  return { token, registerStatus: r.status, registerBody: json ?? text.slice(0, 500) };
}

async function execRequest(entry, accessToken) {
  const { name, request } = entry;
  const method = request.method;
  const url = resolveUrl(request.url, accessToken);
  const headers = {};
  if (request.header) {
    for (const h of request.header) {
      if (!h?.key || h.disabled) continue;
      const v = substituteVars(h.value, accessToken);
      if (h.key.toLowerCase() === 'content-type' && request.body?.mode === 'formdata') continue;
      headers[h.key] = v;
    }
  }
  if (!headers.Accept) headers.Accept = 'application/json';

  let body;
  const mode = request.body?.mode;
  if (mode === 'raw' && request.body?.raw) {
    headers['Content-Type'] = headers['Content-Type'] || 'application/json';
    body = substituteVars(request.body.raw, accessToken);
  } else if (mode === 'formdata') {
    return {
      name,
      folder: entry.folder,
      method,
      url,
      skipped: true,
      skipReason: 'multipart/form-data (file) — not run',
    };
  }

  const opts = { method, headers };
  if (body !== undefined && ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
    opts.body = body;
  }

  let resText;
  let status;
  let contentType = '';
  try {
    const res = await fetch(url, opts);
    status = res.status;
    contentType = res.headers.get('content-type') || '';
    resText = await res.text();
  } catch (e) {
    return {
      name,
      folder: entry.folder,
      method,
      url,
      error: String(e.message || e),
    };
  }

  let json = null;
  let jsonError = null;
  try {
    if (contentType.includes('application/json') || resText.trim().startsWith('{') || resText.trim().startsWith('[')) {
      json = JSON.parse(resText);
    }
  } catch (e) {
    jsonError = e.message;
  }

  const typeFindings = json && typeof json === 'object' ? analyzeTypes(json) : [];

  return {
    name,
    folder: entry.folder,
    method,
    url,
    status,
    contentType,
    jsonValid: json !== null && !jsonError,
    jsonError,
    bodyPreview: resText.length > 1200 ? resText.slice(0, 1200) + '…' : resText,
    typeFindings: typeFindings.slice(0, 25),
    typeFindingsTruncated: typeFindings.length > 25,
  };
}

async function main() {
  const raw = readFileSync(COLLECTION, 'utf8');
  const col = JSON.parse(raw);
  const entries = [];
  walkItems(col.item, '', entries);

  const { token, registerStatus, registerBody } = await bootstrapToken();
  if (!token) {
    console.error('Bootstrap register failed:', registerStatus, registerBody);
    process.exit(1);
  }

  const results = [];
  for (const e of entries) {
    if (SKIP_NAMES.has(e.name)) {
      results.push({
        name: e.name,
        folder: e.folder,
        skipped: true,
        skipReason: 'Would revoke session or destructive',
      });
      continue;
    }
    results.push(await execRequest(e, token));
    await new Promise((r) => setTimeout(r, 30));
  }

  const sanitized = results.map((x) => {
    const o = { ...x };
    if (o.bodyPreview && /"token"\s*:/.test(o.bodyPreview)) {
      o.bodyPreview = '[redacted: response contained token]';
    }
    return o;
  });
  const outPath = join(__dirname, '..', 'MOBILE_API_FIGMA_POSTMAN_TEST_RESULTS.json');
  writeFileSync(
    outPath,
    JSON.stringify({ baseUrl: BASE, bootstrapRegisterStatus: registerStatus, results: sanitized }, null, 2),
    'utf8'
  );
  console.log('Wrote', outPath);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
