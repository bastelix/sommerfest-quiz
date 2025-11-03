const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

const code = fs.readFileSync('public/js/onboarding.js', 'utf8');
const match = code.match(/async function finalizeTenant\(\) {([\s\S]*?)\n  }\n\n  window.addEventListener/);
if (!match) {
  throw new Error('finalizeTenant not found');
}
const finalizeCode = 'async function finalizeTenant() {' + match[1] + '\n  }';

function createCtx(map, overrides = {}) {
  const defaultLocation = {
    href: 'https://example.com/onboarding?session_id=sess',
    protocol: 'https:',
    hostname: 'example.com',
    port: '',
    origin: 'https://example.com',
    toString() { return this.href; }
  };
  const location = Object.assign(defaultLocation, overrides.window?.location || {});
  const windowDefaults = {
    mainDomain: 'example.com',
    history: { replaceState() {} },
    location
  };
  const windowObj = Object.assign(windowDefaults, overrides.window || {});
  windowObj.location = location;

  const ctx = {
    sessionId: 'sess',
    tenantFinalizing: false,
    sessionData: { subdomain: 'tenant', email: 'user@example.com', imprint: {} },
    isValidSubdomain: () => true,
    isValidEmail: () => true,
    withBase: p => p,
    showStep: s => { ctx.step = s; },
    isAllowed: () => true,
    window: windowObj,
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

  if (overrides.ctx) {
    Object.assign(ctx, overrides.ctx);
  }

  return ctx;
}

async function successScenario() {
  const ctx = createCtx({
    '/onboarding/checkout/sess': () => Promise.resolve({ ok: true, json: () => Promise.resolve({ paid: true, plan: 'basic' }) }),
    '/tenants': () => Promise.resolve({ ok: true, status: 201, headers: { get: () => 'application/json' }, json: () => Promise.resolve({ created: true }) }),
    '/api/tenants/tenant/onboard': () => Promise.resolve({
      ok: true,
      headers: { get: () => 'application/json' },
      text: () => Promise.resolve('{"status":"completed"}')
    }),
    'https://tenant.example.com/healthz': () => Promise.resolve({ ok: true, headers: { get: () => 'application/json' }, json: () => Promise.resolve({ status: 'ok' }) }),
    '/tenant-welcome': () => Promise.resolve({ ok: true }),
    '/onboarding/session': () => Promise.resolve({ ok: true })
  });
  ctx.window.waitForTenantDelay = 0;
  const fn = vm.runInNewContext('(' + finalizeCode + ')', ctx);
  await fn();
  assert.strictEqual(ctx.window.location.href, 'https://tenant.example.com/');
}

async function transientScenario() {
  let attempts = 0;
  const ctx = createCtx({
    '/onboarding/checkout/sess': () => Promise.resolve({ ok: true, json: () => Promise.resolve({ paid: true, plan: 'basic' }) }),
    '/tenants': () => Promise.resolve({ ok: true, status: 201, headers: { get: () => 'application/json' }, json: () => Promise.resolve({ created: true }) }),
    '/api/tenants/tenant/onboard': () => Promise.resolve({
      ok: true,
      headers: { get: () => 'application/json' },
      text: () => Promise.resolve('{"status":"completed"}')
    }),
    'https://tenant.example.com/healthz': () => {
      attempts += 1;
      if (attempts === 1) {
        return Promise.reject(new Error('Failed to fetch'));
      }
      if (attempts === 2) {
        return Promise.resolve({
          ok: false,
          status: 503,
          headers: { get: () => 'text/plain' },
          text: () => Promise.resolve('Service Unavailable')
        });
      }
      return Promise.resolve({ ok: true, headers: { get: () => 'application/json' }, json: () => Promise.resolve({ status: 'ok' }) });
    },
    '/tenant-welcome': () => Promise.resolve({ ok: true }),
    '/onboarding/session': () => Promise.resolve({ ok: true })
  });
  ctx.window.waitForTenantDelay = 0;
  ctx.window.waitForTenantRetries = 5;
  const fn = vm.runInNewContext('(' + finalizeCode + ')', ctx);
  await fn();
  assert.strictEqual(ctx.window.location.href, 'https://tenant.example.com/');
  assert.ok(attempts >= 3);
}

async function failureScenario() {
  const ctx = createCtx({
    '/onboarding/checkout/sess': () => Promise.resolve({ ok: true, json: () => Promise.resolve({ paid: true, plan: 'basic' }) }),
    '/tenants': () => Promise.resolve({ ok: true, status: 201, headers: { get: () => 'application/json' }, json: () => Promise.resolve({ created: true }) }),
    '/api/tenants/tenant/onboard': () => Promise.resolve({ ok: false, status: 500, headers: { get: () => 'text/plain' }, text: () => Promise.resolve('fail') })
  });
  const fn = vm.runInNewContext('(' + finalizeCode + ')', ctx);
  await fn();
  assert.strictEqual(ctx.alertMsg, 'Fehler: fail');
  assert.strictEqual(ctx.step, 4);
}

async function singleContainerScenario() {
  let healthzCalls = 0;
  const waitDelays = [];
  const realSetTimeout = setTimeout;
  const routes = {
    '/onboarding/checkout/sess': () => Promise.resolve({ ok: true, json: () => Promise.resolve({ paid: true, plan: 'basic' }) }),
    '/tenants': () => Promise.resolve({ ok: true, status: 201, headers: { get: () => 'application/json' }, json: () => Promise.resolve({ created: true }) }),
    '/api/tenants/tenant/onboard': () => Promise.resolve({
      ok: true,
      headers: { get: () => 'application/json' },
      text: () => Promise.resolve('{"status":"completed","mode":"single-container"}')
    }),
    '/tenant-welcome': () => Promise.resolve({ ok: true }),
    '/onboarding/session': () => Promise.resolve({ ok: true })
  };
  const ctx = createCtx(routes, {
    window: {
      location: {
        protocol: 'http:',
        href: 'http://example.com:8080/onboarding?session_id=sess',
        origin: 'http://example.com:8080',
        hostname: 'example.com',
        port: '8080'
      },
      waitForTenantDelay: 50
    },
    ctx: {
      setTimeout: (fn, delay, ...args) => {
        waitDelays.push(delay);
        return realSetTimeout(fn, delay, ...args);
      },
      fetch: async (url, opts) => {
        if (url === 'http://tenant.example.com:8080/healthz') {
          healthzCalls += 1;
          throw new Error('health-check should be skipped in single-container mode');
        }
        const fn = routes[url];
        if (!fn) {
          throw new Error('unexpected url ' + url);
        }
        return fn(url, opts);
      }
    }
  });
  const fn = vm.runInNewContext('(' + finalizeCode + ')', ctx);
  await fn();
  const positiveWaits = waitDelays.filter(delay => typeof delay === 'number' && delay > 0);
  assert.strictEqual(positiveWaits.length, 0, 'wait loop should be skipped for single-container mode');
  assert.strictEqual(healthzCalls, 0);
  assert.strictEqual(ctx.window.location.href, 'http://tenant.example.com:8080/');
}

(async () => {
  await successScenario();
  await transientScenario();
  await failureScenario();
  await singleContainerScenario();
  console.log('ok');
})().catch(err => { console.error(err); process.exit(1); });
