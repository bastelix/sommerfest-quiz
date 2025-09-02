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

const ctx = {
  fetch: async (url) => {
    if (url.includes('/api/tenants/')) {
      return { status: 202, ok: true, redirected: false, text: async () => JSON.stringify({ status: 'queued' }) };
    }
    if (url.includes('/healthz')) {
      return { ok: true, headers: { get: () => 'application/json' }, json: async () => ({ status: 'ok' }) };
    }
    throw new Error('unexpected url');
  },
  addLog: () => {},
  withBase: p => p,
  wait: () => Promise.resolve(),
  window: { mainDomain: 'example.com', waitForTenantRetries: 2, waitForTenantDelay: 0 },
  URL
};

const onboardCode = extract('onboardTenant').replace('const onboardTenant', 'var onboardTenant');
const waitCode = extract('waitForTenant').replace('const waitForTenant', 'var waitForTenant');
vm.runInNewContext(onboardCode + '\n' + waitCode, ctx);

(async () => {
  await ctx.onboardTenant('demo');
  await ctx.waitForTenant('demo');
  console.log('ok');
})().catch(err => { console.error(err); process.exit(1); });
