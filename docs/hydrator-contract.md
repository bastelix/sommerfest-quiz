# ✅ Finaler CMS Hydrator-Contract (v1)

## 🎯 Ziel

* **Eine einzige, stabile Entry-Point-Definition**
* **CMS ≠ Marketing**
* **Preview, Frontend, SSR-Fallback identisch**
* Kein stilles Scheitern mehr

---

## 1️⃣ DOM-Contract (Pflicht)

### 📌 Root-Element (einzig gültig)

```html
<div
  data-page-root
  data-page-slug="calserver"
  data-page-namespace="calserver-neu"
  data-content-namespace="calserver-neu"
></div>
```

### Regeln

| Attribut                 | Pflicht    | Bedeutung                   |
| ------------------------ | ---------- | --------------------------- |
| `data-page-root`         | ✅          | **Hydration Entry Point**   |
| `data-page-slug`         | ✅          | Page Slug                   |
| `data-page-namespace`    | ✅          | Namespace für Design        |
| `data-content-namespace` | ⛔ optional | für Cross-Namespace Inhalte |

❌ **Verboten**

* `#page-render-target`
* `.marketing-page-content`
* Slug-basierte Selektoren

---

## 2️⃣ HTML `<html>` Contract

```html
<html
  lang="de"
  data-namespace="calserver-neu"
  data-theme="dark|light"
  data-base-path=""
>
```

### Regeln

* `data-theme` **kommt serverseitig aus Namespace-Design**
* JS darf es **nicht raten**, nur **respektieren**

---

## 3️⃣ JSON-Payload-Contract (format=json)

```json
{
  "namespace": "calserver-neu",
  "slug": "calserver",
  "blocks": [ ... ],
  "design": {
    "namespace": "calserver-neu",
    "appearance": { ... },
    "effects": { ... },
    "config": { ... }
  },
  "content": "<fallback-html>"
}
```

### Garantien

* `blocks` **oder** `content` vorhanden
* `design.namespace === pageNamespace`

---

## 4️⃣ FINAL: bootstrapHydrationContext()

```js
const bootstrapHydrationContext = () => {
  const root = document.documentElement;
  if (!root) return null;

  const pageRoot = document.querySelector('[data-page-root]');
  if (!pageRoot) {
    console.error('[CMS] Missing [data-page-root]');
    return null;
  }

  return {
    root,
    pageRoot,
    namespace: root.dataset.namespace || 'default',
    theme: root.dataset.theme || 'light',
    basePath: root.dataset.basePath || '',
    page: {
      slug: pageRoot.dataset.pageSlug,
      namespace: pageRoot.dataset.pageNamespace,
      contentNamespace: pageRoot.dataset.contentNamespace
    }
  };
};
```

➡️ **Kein Fallback mehr**
➡️ **Kein Marketing-Code**
➡️ **Fail-Fast**

---

## 5️⃣ FINAL: hydratePage()

```js
const hydratePage = async () => {
  const ctx = bootstrapHydrationContext();
  if (!ctx) return;

  const { root, pageRoot, basePath } = ctx;

  const payloadUrl = new URL(window.location.href);
  payloadUrl.searchParams.set('format', 'json');

  const res = await fetch(payloadUrl, {
    headers: { Accept: 'application/json' }
  });

  if (!res.ok) {
    console.error('[CMS] Payload fetch failed');
    return;
  }

  const payload = await res.json();
  if (!payload?.blocks && !payload?.content) {
    console.error('[CMS] Invalid payload');
    return;
  }

  const [
    designModule,
    rendererModule,
    effectsModule
  ] = await Promise.all([
    import(`${basePath}/js/components/namespace-design.js`),
    import(`${basePath}/js/components/block-renderer-matrix.js`),
    import(`${basePath}/js/effects/initEffects.js`)
  ]);

  // 1️⃣ Design anwenden
  if (payload.design) {
    designModule.registerNamespaceDesign(
      payload.design.namespace,
      payload.design
    );

    designModule.applyNamespaceDesign(
      root,
      payload.design.namespace,
      payload.design.appearance || {}
    );
  }

  // 2️⃣ Rendern
  let html = '';
  if (Array.isArray(payload.blocks) && payload.blocks.length > 0) {
    html = rendererModule.renderPage(payload.blocks, {
      rendererMatrix: rendererModule.RENDERER_MATRIX,
      context: 'frontend',
      appearance: payload.design?.appearance || {},
      basePath
    });
  }

  // 3️⃣ Fallback
  if (!html && payload.content) {
    html = payload.content;
  }

  pageRoot.innerHTML = html;

  // 4️⃣ Effects
  effectsModule?.initEffects?.(pageRoot, {
    namespace: payload.namespace,
    mode: 'frontend'
  });
};

document.addEventListener('DOMContentLoaded', hydratePage);
```

---

## 6️⃣ SSR / No-JS Fallback (verbindlich)

{% raw %}
```twig
<noscript>
  {{ content|raw }}
</noscript>
```
{% endraw %}

➡️ Seite **nie leer**
➡️ SEO & Accessibility safe

---

## 7️⃣ Was damit endgültig erledigt ist

✅ Marketing-Hydrator tot
✅ Slug-Heuristiken tot
✅ Doppelte Preview-Pfade tot
✅ „Warum rendert nix?“ tot
✅ Namespace-Design greift **immer**

---

## 🧭 Empfehlung als Nächstes (sehr sinnvoll)

1. `docs/hydrator-contract.md` anlegen (diesen Text)
2. Cypress Test:

   ```js
   cy.get('[data-page-root]').should('exist');
   ```
3. Entferne **alle** `.marketing-page-content` Referenzen
4. Versioniere:

   ```js
   data-hydrator="cms-v1"
   ```
