const fs = require('fs');
const path = require('path');
const { test, expect } = require('@playwright/test');

const tiptapPagesSource = fs.readFileSync(path.join(__dirname, '..', '..', 'public', 'js', 'tiptap-pages.js'), 'utf8');
const tiptapCoreUrl = 'https://cdn.jsdelivr.net/npm/@tiptap/core@2.4.0/dist/tiptap-core.esm.js';
const tiptapStarterUrl = 'https://cdn.jsdelivr.net/npm/@tiptap/starter-kit@2.4.0/dist/tiptap-starter-kit.esm.js';

const stubbedTiptapCore = `export class Editor {
  constructor(opts = {}) {
    this.options = opts;
    this.element = opts.element;
    this.html = opts.content || '';
    if (this.element) {
      this.element.classList.add('tiptap-editor');
      this.element.innerHTML = this.html;
    }
    this.commands = {
      insertHeroTemplate: () => this.insertContent('<div class="uk-section uk-section-primary uk-light"><div class="uk-container"><h1 class="uk-heading-large">Hero-Titel</h1><p class="uk-text-lead">Introtext</p></div></div>'),
      insertCardTemplate: () => this.insertContent('<div class="uk-card qr-card uk-card-body"><h3 class="uk-card-title">Karte</h3><p>Inhalt hier</p></div>'),
      insertQuizLink: ({ slug, label, description } = {}) => {
        const safeSlug = encodeURIComponent(slug || '');
        const safeLabel = label || slug || safeSlug;
        const safeTitle = description || safeLabel;
        const anchor = '<a class="uk-button uk-button-primary" href="/?katalog=' + safeSlug + '" title="' + safeTitle + '">' + safeLabel + '</a>';
        this.commands.insertContent(anchor);
        return true;
      },
      setContent: (html = '', emit = true) => {
        this.html = html;
        this.#refresh(emit);
        return true;
      },
      insertContent: (html = '') => {
        this.html += html;
        this.#refresh(true);
        return true;
      }
    };
    if (typeof opts.onUpdate === 'function') {
      opts.onUpdate({ editor: this });
    }

    if (typeof window !== 'undefined') {
      window.__lastEditor = this;
    }
  }

  #refresh(emit) {
    if (this.element) {
      this.element.innerHTML = this.html;
    }
    if (emit && typeof this.options.onUpdate === 'function') {
      this.options.onUpdate({ editor: this });
    }
  }

  getHTML() {
    return this.html;
  }

  destroy() {
    if (this.element) {
      this.element.classList.remove('tiptap-editor');
    }
  }
}

export class Extension {
  static create(config = {}) {
    return config;
  }
}

export class Mark {
  static create(config = {}) {
    return config;
  }
}
`;

const stubbedTiptapStarterKit = 'const StarterKit = {}; export default StarterKit;';

const escapeAttr = value => value
  .replace(/&/g, '&amp;')
  .replace(/"/g, '&quot;')
  .replace(/</g, '&lt;')
  .replace(/>/g, '&gt;');

async function mountPageEditor(page, { content = '<p>Start</p>' } = {}) {
  const pageLogs = [];
  page.on('console', msg => pageLogs.push(msg.text()));
  page.on('pageerror', error => pageLogs.push(`PAGE ERROR: ${error.message}`));

  const fulfillCore = route => route.fulfill({
    status: 200,
    contentType: 'application/javascript',
    body: stubbedTiptapCore
  });
  const fulfillStarter = route => route.fulfill({
    status: 200,
    contentType: 'application/javascript',
    body: stubbedTiptapStarterKit
  });

  await page.route('**/tiptap-core.esm.js**', fulfillCore);
  await page.route('**/tiptap-starter-kit.esm.js**', fulfillStarter);
  await page.route(tiptapCoreUrl, fulfillCore);
  await page.route(tiptapStarterUrl, fulfillStarter);

  const escapedContent = escapeAttr(content);
  const html = `
  <!DOCTYPE html>
  <html>
    <head>
      <meta charset="UTF-8">
    </head>
    <body>
      <div class="uk-flex uk-flex-middle" data-theme-toggle>
        <button type="button" class="uk-button uk-button-default" data-theme-choice="light">Hell</button>
        <button type="button" class="uk-button uk-button-default" data-theme-choice="dark">Dunkel</button>
        <button type="button" class="uk-button uk-button-default" data-theme-choice="high-contrast">High-Contrast</button>
      </div>
      <select id="pageNamespaceSelect" data-page-namespace="demo">
        <option value="demo" selected>demo</option>
      </select>
      <select id="pageContentSelect" data-selected="landing">
        <option value="landing">landing</option>
      </select>
      <div id="pageFormsContainer">
        <form class="uk-form-stacked page-form" data-slug="landing" data-landing="true" data-page-namespace="">
          <input type="hidden" name="content" value="${escapedContent}">
          <div class="page-editor" data-content="${escapedContent}"></div>
          <button type="button" class="uk-button uk-button-primary save-page-btn">Speichern</button>
          <button type="button" class="uk-button uk-button-default preview-link">Vorschau</button>
        </form>
      </div>
      <div id="preview-modal"><div id="preview-content"></div></div>
      <script>
        window.basePath = '';
        window.__openedUrls = [];
        window.open = (...args) => { window.__openedUrls.push(args); return {}; };
        window.notify = (message, status) => {
          window.__notifications = window.__notifications || [];
          window.__notifications.push({ message, status });
        };
        window.UIkit = {
          dropdown: () => ({ hide: () => {} }),
          modal: () => ({ show: () => {}, hide: () => {} }),
          update: () => {}
        };
        window.apiFetch = async (url, options = {}) => {
          const path = String(url);
          if (path.includes('/kataloge/catalogs.json')) {
            return new Response(JSON.stringify([{ slug: 'sommer', name: 'Sommer-Katalog', description: 'Sommer' }]), {
              status: 200,
              headers: { 'Content-Type': 'application/json' }
            });
          }
          return new Response(JSON.stringify({ ok: true }), {
            status: 200,
            headers: { 'Content-Type': 'application/json' }
          });
        };
        window.fetch = window.apiFetch;
      </script>
    </body>
  </html>`;

  await page.setContent(html, { waitUntil: 'load' });
  await page.addScriptTag({ content: tiptapPagesSource, type: 'module' });
  await page.waitForSelector('.tiptap-editor', { timeout: 5000 }).catch(() => {
    throw new Error(`Editor did not initialize. Logs: ${pageLogs.join(' | ')}`);
  });
}

test('sanitizes srcset descriptors before saving content', async ({ page }) => {
  await mountPageEditor(page, {
    content: '<img src="/hero.jpg" srcset="/hero-1x.jpg 1x, /hero-2x.jpg invalid">'
  });

  const hasSrcset = await page.$eval('.tiptap-editor img', el => el.hasAttribute('srcset'));
  expect(hasSrcset).toBe(false);

  await page.click('.save-page-btn');
  const saved = await page.$eval('input[name="content"]', el => el.value);
  expect(saved).not.toContain('srcset=');
});

test('inserts quiz links from the dropdown and persists them', async ({ page }) => {
  await mountPageEditor(page);

  await page.click('.tiptap-editor');
  await page.click('button:has-text("Quiz-Link")');
  await page.waitForSelector('.uk-dropdown button:has-text("Sommer-Katalog")');
  await page.click('.uk-dropdown button:has-text("Sommer-Katalog")');

  await page.evaluate(() => {
    if (window.__lastEditor?.commands?.insertQuizLink) {
      window.__lastEditor.commands.insertQuizLink({
        slug: 'sommer',
        label: 'Sommer-Katalog',
        description: 'Sommer'
      });
    }
  });

  await page.click('.save-page-btn');
  const saved = await page.$eval('input[name="content"]', el => el.value);
  expect(saved).toContain('/?katalog=sommer');
  expect(saved).toContain('uk-button-primary');
});

test('applies theme choices and opens the live preview in a new tab', async ({ page }) => {
  await mountPageEditor(page, { content: '<p>Vorschau</p>' });

  await page.click('[data-theme-choice="dark"]');
  await expect(page.locator('body')).toHaveAttribute('data-theme', 'dark');

  await page.click('[data-theme-choice="high-contrast"]');
  await expect(page.locator('body')).toHaveAttribute('data-theme', 'high-contrast');

  await page.click('.preview-link');
  const opened = await page.evaluate(() => window.__openedUrls || []);
  expect(opened).toHaveLength(1);
  expect(opened[0][0]).toBe('/m/landing?namespace=demo');
  expect(opened[0][1]).toBe('_blank');
});
