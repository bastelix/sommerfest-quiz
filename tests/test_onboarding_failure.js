const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

const code = fs.readFileSync('public/js/onboarding.js', 'utf8');

function extract(name) {
  const start = code.indexOf(`const ${name} = async`);
  if (start === -1) return '';
  let i = code.indexOf('{', start);
  let depth = 0;
  for (; i < code.length; i++) {
    if (code[i] === '{') depth++;
    else if (code[i] === '}') {
      depth--;
      if (depth === 0) return code.slice(start, i + 2);
    }
  }
  return '';
}

let healthzCalls = 0;

const ctx = {
  fetch: async (url) => {
    if (url.includes('/api/tenants/')) {
      return {
        status: 500,
        ok: false,
        statusText: 'error',
        redirected: false,
        headers: { get: () => 'application/json' },
        text: async () => JSON.stringify({ error: 'fail' }),
        json: async () => ({ error: 'fail' })
      };
    }
    if (url.includes('/healthz')) {
      healthzCalls++;
      return {
        ok: true,
        headers: { get: () => 'application/json' },
        json: async () => ({ status: 'error', error: 'bad' })
      };
    }
    throw new Error('unexpected url');
  },
  addLog: () => {},
  withBase: p => p,
  wait: () => Promise.resolve(),
  window: { mainDomain: 'example.com', waitForTenantRetries: 5, waitForTenantDelay: 0 },
  URL
};

const onboardCode = extract('onboardTenant').replace('const onboardTenant', 'var onboardTenant');
const waitCode = extract('waitForTenant').replace('const waitForTenant', 'var waitForTenant');
vm.runInNewContext(onboardCode + '\n' + waitCode, ctx);

(async () => {
  await assert.rejects(() => ctx.onboardTenant('demo'), /fail/);
  await assert.rejects(() => ctx.waitForTenant('demo'), /bad/);
  assert.strictEqual(healthzCalls, 1);
  console.log('ok');
})().catch(err => { console.error(err); process.exit(1); });
