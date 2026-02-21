const fs = require('fs');
const path = require('path');
const { test, expect } = require('@playwright/test');

const onboardingSource = fs.readFileSync(
  path.join(__dirname, '..', '..', 'public', 'js', 'onboarding.js'),
  'utf8'
);

/**
 * Build minimal onboarding HTML matching the template structure.
 * @param {object} opts - window-level overrides injected before onboarding.js runs
 */
function buildHtml(opts = {}) {
  const csrfToken = opts.csrfToken || 'test-csrf';
  const mainDomain = opts.mainDomain || 'example.com';
  return `<!DOCTYPE html>
<html><head><meta charset="UTF-8"></head><body>
<ul id="stepTimeline">
  <li class="timeline-step active" data-step="1">1. E-Mail</li>
  <li class="timeline-step" data-step="2">2. Subdomain</li>
  <li class="timeline-step" data-step="3">3. Impressum</li>
  <li class="timeline-step inactive" data-step="4">4. Tarif</li>
  <li class="timeline-step inactive" data-step="5">5. App erstellen</li>
</ul>
<button id="restartOnboarding">Neu starten</button>
<div id="step1">
  <input id="email" type="email" placeholder="E-Mail-Adresse">
  <button id="sendEmail">Bestätigung senden</button>
  <div id="emailStatus" hidden></div>
</div>
<div id="step2" hidden>
  <div id="verifiedHint" hidden></div>
  <input id="subdomain" type="text" placeholder="gewünschte Subdomain">
  <div id="subdomainStatus" hidden></div>
  <span id="subdomainPreview"></span>
  <button id="saveSubdomain">Subdomain speichern</button>
</div>
<div id="step3" hidden>
  <input id="imprintName" type="text" placeholder="Name">
  <input id="imprintStreet" type="text" placeholder="Straße">
  <input id="imprintZip" type="text" placeholder="PLZ">
  <input id="imprintCity" type="text" placeholder="Ort">
  <input id="imprintEmail" type="email" placeholder="E-Mail">
  <label><input id="useAsImprint" type="checkbox"> Als Impressum verwenden</label>
  <button id="saveImprint">Weiter</button>
</div>
<div id="step4" hidden></div>
<div id="step5" hidden>
  <ul id="task-status"></ul>
  <details id="task-log-details">
    <summary>Log</summary>
    <button id="copyTaskLog" hidden>Log kopieren</button>
    <ul id="task-log"></ul>
  </details>
</div>
<script>
  window.basePath = '';
  window.mainDomain = '${mainDomain}';
  window.csrfToken = '${csrfToken}';
</script>
</body></html>`;
}

/**
 * Set up the page with onboarding HTML and route mocks, then load onboarding.js.
 * @param {import('@playwright/test').Page} page
 * @param {object} opts
 * @param {object} opts.sessionResponse - JSON returned by GET /onboarding/session
 * @param {object} opts.routes - map of method+url patterns to response configs
 */
async function mountOnboarding(page, opts = {}) {
  const logs = [];
  page.on('console', msg => logs.push(msg.text()));
  page.on('pageerror', err => logs.push('PAGE ERROR: ' + err.message));

  const sessionResponse = opts.sessionResponse || {};
  const routes = opts.routes || {};

  // Intercept fetch calls from the page
  await page.route('**/onboarding/session', async (route, request) => {
    const method = request.method();
    if (method === 'GET') {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify(sessionResponse)
      });
    } else if (method === 'POST') {
      const handler = routes['POST /onboarding/session'];
      if (handler) {
        await route.fulfill(handler);
      } else {
        await route.fulfill({ status: 204, body: '' });
      }
    } else if (method === 'DELETE') {
      await route.fulfill({ status: 204, body: '' });
    } else {
      await route.continue();
    }
  });

  await page.route('**/onboarding/email', async (route, request) => {
    if (request.method() === 'POST') {
      const handler = routes['POST /onboarding/email'];
      if (handler) {
        await route.fulfill(handler);
      } else {
        await route.fulfill({ status: 204, body: '' });
      }
    } else {
      await route.continue();
    }
  });

  await page.route('**/onboarding/tenants/**', async (route) => {
    const handler = routes['GET /onboarding/tenants'];
    if (handler) {
      await route.fulfill(handler);
    } else {
      // 404 = subdomain available
      await route.fulfill({ status: 404, body: '' });
    }
  });

  // Catch any other requests
  for (const [key, handler] of Object.entries(routes)) {
    if (key.startsWith('ROUTE:')) {
      const pattern = key.slice('ROUTE:'.length);
      await page.route(pattern, async (route) => {
        await route.fulfill(handler);
      });
    }
  }

  await page.setContent(buildHtml(opts), { waitUntil: 'domcontentloaded' });
  await page.addScriptTag({ content: onboardingSource });
  // Allow DOMContentLoaded listeners to run
  await page.waitForTimeout(200);

  return { logs };
}

// ─── Tests ───────────────────────────────────────────────────────────────────

test.describe('Onboarding flow', () => {

  test('shows step 1 by default for a fresh session', async ({ page }) => {
    await mountOnboarding(page);

    await expect(page.locator('#step1')).not.toHaveAttribute('hidden', '');
    await expect(page.locator('#step2')).toHaveAttribute('hidden', '');
    await expect(page.locator('#step3')).toHaveAttribute('hidden', '');
  });

  test('sends email confirmation and shows status message', async ({ page }) => {
    await mountOnboarding(page, {
      routes: {
        'POST /onboarding/email': { status: 204, body: '' }
      }
    });

    await page.fill('#email', 'user@example.com');
    await page.click('#sendEmail');
    await expect(page.locator('#emailStatus')).not.toHaveAttribute('hidden', '');
    await expect(page.locator('#emailStatus')).toHaveText('E-Mail versendet. Bitte prüfe dein Postfach.');
  });

  test('rejects invalid email address', async ({ page }) => {
    await mountOnboarding(page);

    await page.fill('#email', 'not-an-email');
    await page.click('#sendEmail');
    await expect(page.locator('#emailStatus')).toHaveText('Ungültige E-Mail-Adresse.');
  });

  test('resumes at step 2 when session has verified email', async ({ page }) => {
    await mountOnboarding(page, {
      sessionResponse: {
        email: 'user@example.com',
        verified: true
      }
    });

    await expect(page.locator('#step1')).toHaveAttribute('hidden', '');
    await expect(page.locator('#step2')).not.toHaveAttribute('hidden', '');
    await expect(page.locator('#verifiedHint')).not.toHaveAttribute('hidden', '');
  });

  test('validates subdomain and advances to step 3', async ({ page }) => {
    await mountOnboarding(page, {
      sessionResponse: {
        email: 'user@example.com',
        verified: true
      },
      routes: {
        // 404 means subdomain is available
        'GET /onboarding/tenants': { status: 404, body: '' },
        'POST /onboarding/session': { status: 204, body: '' }
      }
    });

    await page.fill('#subdomain', 'my-quiz');
    await page.click('#saveSubdomain');

    // Subdomain should be marked available
    await expect(page.locator('#subdomainStatus')).toHaveText('Subdomain verfügbar.');
    // Should advance to step 3
    await expect(page.locator('#step3')).not.toHaveAttribute('hidden', '');
    await expect(page.locator('#step2')).toHaveAttribute('hidden', '');
  });

  test('shows error when subdomain is already taken', async ({ page }) => {
    await mountOnboarding(page, {
      sessionResponse: {
        email: 'user@example.com',
        verified: true
      },
      routes: {
        // 200 means subdomain exists = taken
        'GET /onboarding/tenants': {
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ uid: 'taken' })
        }
      }
    });

    await page.fill('#subdomain', 'taken');
    await page.click('#saveSubdomain');

    await expect(page.locator('#subdomainStatus')).toHaveText('Subdomain bereits vergeben.');
    // Should stay on step 2
    await expect(page.locator('#step2')).not.toHaveAttribute('hidden', '');
  });

  test('rejects invalid subdomain format', async ({ page }) => {
    await mountOnboarding(page, {
      sessionResponse: {
        email: 'user@example.com',
        verified: true
      }
    });

    await page.fill('#subdomain', 'AB');
    await page.click('#saveSubdomain');

    await expect(page.locator('#subdomainStatus')).toHaveText('Ungültige Subdomain.');
    await expect(page.locator('#subdomain')).toHaveClass(/uk-form-danger/);
  });

  test('saves imprint data and advances to step 4', async ({ page }) => {
    await mountOnboarding(page, {
      sessionResponse: {
        email: 'user@example.com',
        verified: true,
        subdomain: 'my-quiz'
      },
      routes: {
        'POST /onboarding/session': { status: 204, body: '' }
      }
    });

    // Should start at step 3 (verified + subdomain present, no imprint yet)
    await expect(page.locator('#step3')).not.toHaveAttribute('hidden', '');

    await page.fill('#imprintName', 'Firma GmbH');
    await page.fill('#imprintStreet', 'Musterstr. 1');
    await page.fill('#imprintZip', '12345');
    await page.fill('#imprintCity', 'Berlin');
    await page.fill('#imprintEmail', 'firma@example.com');
    await page.check('#useAsImprint');
    await page.click('#saveImprint');

    // Should advance to step 4
    await expect(page.locator('#step4')).not.toHaveAttribute('hidden', '');
    await expect(page.locator('#step3')).toHaveAttribute('hidden', '');
  });

  test('blocks imprint submission when fields are missing', async ({ page }) => {
    let alertMsg = '';
    page.on('dialog', async dialog => {
      alertMsg = dialog.message();
      await dialog.accept();
    });

    await mountOnboarding(page, {
      sessionResponse: {
        email: 'user@example.com',
        verified: true,
        subdomain: 'my-quiz'
      }
    });

    // Leave fields empty and click submit
    await page.click('#saveImprint');

    expect(alertMsg).toBe('Bitte alle Felder ausfüllen.');
    // Should stay on step 3
    await expect(page.locator('#step3')).not.toHaveAttribute('hidden', '');
  });

  test('subdomain preview updates on input', async ({ page }) => {
    await mountOnboarding(page, {
      sessionResponse: {
        email: 'user@example.com',
        verified: true
      }
    });

    await page.fill('#subdomain', 'MyQuiz');
    await expect(page.locator('#subdomainPreview')).toHaveText('myquiz');
  });

  test('timeline step navigation works for completed steps', async ({ page }) => {
    await mountOnboarding(page, {
      sessionResponse: {
        email: 'user@example.com',
        verified: true,
        subdomain: 'my-quiz',
        imprint: { done: true, name: 'Test', street: 'S', zip: '1', city: 'C', email: 'a@b.de' }
      }
    });

    // Should start at step 4
    await expect(page.locator('#step4')).not.toHaveAttribute('hidden', '');

    // Click step 2 in timeline to navigate back
    await page.click('.timeline-step[data-step="2"]');
    await expect(page.locator('#step2')).not.toHaveAttribute('hidden', '');

    // Click step 3
    await page.click('.timeline-step[data-step="3"]');
    await expect(page.locator('#step3')).not.toHaveAttribute('hidden', '');
  });

  test('restart button clears session and reloads', async ({ page }) => {
    let deleteCalled = false;

    await mountOnboarding(page, {
      sessionResponse: {
        email: 'user@example.com',
        verified: true
      }
    });

    // Track DELETE call
    await page.route('**/onboarding/session', async (route, request) => {
      if (request.method() === 'DELETE') {
        deleteCalled = true;
        await route.fulfill({ status: 204, body: '' });
      } else {
        await route.continue();
      }
    });

    // Listen for navigation (restart redirects to /onboarding)
    const navigationPromise = page.waitForURL('**/onboarding', { timeout: 5000 }).catch(() => {});
    await page.click('#restartOnboarding');
    await navigationPromise;

    expect(deleteCalled).toBe(true);
  });

  test('pre-fills email from session data', async ({ page }) => {
    await mountOnboarding(page, {
      sessionResponse: {
        email: 'stored@example.com'
      }
    });

    await expect(page.locator('#email')).toHaveValue('stored@example.com');
  });

  test('pre-fills imprint fields from session data', async ({ page }) => {
    await mountOnboarding(page, {
      sessionResponse: {
        email: 'user@example.com',
        verified: true,
        subdomain: 'my-quiz',
        imprint: {
          name: 'Firma GmbH',
          street: 'Musterstr. 1',
          zip: '12345',
          city: 'Berlin',
          email: 'firma@example.com',
          use_as_imprint: true
        }
      }
    });

    await expect(page.locator('#imprintName')).toHaveValue('Firma GmbH');
    await expect(page.locator('#imprintStreet')).toHaveValue('Musterstr. 1');
    await expect(page.locator('#imprintZip')).toHaveValue('12345');
    await expect(page.locator('#imprintCity')).toHaveValue('Berlin');
    await expect(page.locator('#imprintEmail')).toHaveValue('firma@example.com');
    await expect(page.locator('#useAsImprint')).toBeChecked();
  });

  test('marks completed timeline steps', async ({ page }) => {
    await mountOnboarding(page, {
      sessionResponse: {
        email: 'user@example.com',
        verified: true,
        subdomain: 'my-quiz'
      }
    });

    // Step 3 is active (verified + subdomain, no imprint)
    await expect(page.locator('.timeline-step[data-step="1"]')).toHaveClass(/completed/);
    await expect(page.locator('.timeline-step[data-step="2"]')).toHaveClass(/completed/);
    await expect(page.locator('.timeline-step[data-step="3"]')).toHaveClass(/active/);
  });
});
