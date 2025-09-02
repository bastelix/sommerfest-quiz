const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

const code = fs.readFileSync('public/js/onboarding.js', 'utf8');
const match = code.match(/async function finalizeTenant\(\) {([\s\S]*?)\n  }\n\n  window.addEventListener/);
if (!match) {
  throw new Error('finalizeTenant not found');
}
const finalizeCode = 'async function finalizeTenant() {' + match[1] + '\n  }';

function createCtx(map) {
  const ctx = {
    sessionId: 'sess',
    tenantFinalizing: false,
    sessionData: { subdomain: 'tenant', email: 'user@example.com', imprint: {} },
    isValidSubdomain: () => true,
    isValidEmail: () => true,
    withBase: p => p,
    showStep: s => { ctx.step = s; },
    isAllowed: () => true,
    window: {
      mainDomain: 'example.com',
      history: { replaceState() {} },
      location: {
        href: 'https://example.com/onboarding?session_id=sess',
        toString() { return this.href; }
      }
    },
    document: { getElementById: () => null },
    alert: msg => { ctx.alertMsg = msg; },
    fetch: async (url, opts) => {
      const fn = map[url];
      if (!fn) throw new Error('unexpected url ' + url);
      return fn(url, opts);
    },
    console,
    URL,
    setTimeout,
    escape: u => encodeURI(u)
  };
  return ctx;
}

async function successScenario() {
  const ctx = createCtx({
    '/onboarding/checkout/sess': () => Promise.resolve({ ok: true, json: () => Promise.resolve({ paid: true, plan: 'basic' }) }),
    '/tenants': () => Promise.resolve({ ok: true, status: 201, headers: { get: () => 'application/json' }, json: () => Promise.resolve({ queued: true }) }),
    '/api/tenants/tenant/onboard': () => Promise.resolve({ ok: true, headers: { get: () => 'application/json' }, json: () => Promise.resolve({}), text: () => Promise.resolve('') }),
    'https://tenant.example.com/healthz': () => Promise.resolve({ ok: true, headers: { get: () => 'application/json' }, json: () => Promise.resolve({ status: 'ok' }) }),
    '/tenant-welcome': () => Promise.resolve({ ok: true }),
    '/onboarding/session': () => Promise.resolve({ ok: true })
  });
  const fn = vm.runInNewContext('(' + finalizeCode + ')', ctx);
  await fn();
  assert.strictEqual(ctx.window.location.href, 'https://tenant.example.com/');
}

async function failureScenario() {
  const ctx = createCtx({
    '/onboarding/checkout/sess': () => Promise.resolve({ ok: true, json: () => Promise.resolve({ paid: true, plan: 'basic' }) }),
    '/tenants': () => Promise.resolve({ ok: true, status: 201, headers: { get: () => 'application/json' }, json: () => Promise.resolve({ queued: true }) }),
    '/api/tenants/tenant/onboard': () => Promise.resolve({ ok: false, status: 500, headers: { get: () => 'text/plain' }, text: () => Promise.resolve('fail') })
  });
  const fn = vm.runInNewContext('(' + finalizeCode + ')', ctx);
  await fn();
  assert.strictEqual(ctx.alertMsg, 'Fehler: fail');
  assert.strictEqual(ctx.step, 4);
}

(async () => {
  await successScenario();
  await failureScenario();
  console.log('ok');
})().catch(err => { console.error(err); process.exit(1); });
