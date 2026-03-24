/**
 * Wiki Material – Vanilla JS enhancements
 * ────────────────────────────────────────
 * Handles:
 *  1. Sidebar section expand/collapse (tree navigation)
 *  2. Mobile sidebar toggle + overlay
 *  3. Code block copy-to-clipboard + language header
 *  4. Admonition collapsible toggle
 *
 * No external dependencies. Only operates within .wiki-layout.
 */
(function () {
  'use strict';

  /* ── Guard: only run inside a wiki page ── */
  var root = document.querySelector('.wiki-layout');
  if (!root) return;

  /* ═══════════════════════════════════════
     1. SIDEBAR EXPAND / COLLAPSE
     ═══════════════════════════════════════ */
  root.querySelectorAll('.wiki-sidebar__section-toggle').forEach(function (btn) {
    var target = btn.nextElementSibling;
    if (!target) return;

    // Auto-expand if section contains the active link
    var hasActive = target.querySelector('.wiki-sidebar__nav-link--active');
    if (hasActive) {
      btn.classList.add('is-expanded', 'has-active-child');
      target.classList.add('is-expanded');
    }

    btn.addEventListener('click', function () {
      var expanded = target.classList.toggle('is-expanded');
      btn.classList.toggle('is-expanded', expanded);
    });
  });


  /* ═══════════════════════════════════════
     2. MOBILE SIDEBAR TOGGLE
     ═══════════════════════════════════════ */
  var sidebar  = root.querySelector('.wiki-sidebar');
  var toggle   = root.querySelector('.wiki-sidebar-toggle');
  var overlay  = root.querySelector('.wiki-sidebar-overlay');

  function openSidebar()  {
    if (sidebar) sidebar.classList.add('is-open');
    if (overlay) overlay.classList.add('is-visible');
  }
  function closeSidebar() {
    if (sidebar) sidebar.classList.remove('is-open');
    if (overlay) overlay.classList.remove('is-visible');
  }

  if (toggle)  toggle.addEventListener('click', function () {
    sidebar && sidebar.classList.contains('is-open') ? closeSidebar() : openSidebar();
  });
  if (overlay) overlay.addEventListener('click', closeSidebar);


  /* ═══════════════════════════════════════
     3. CODE BLOCKS – Header + Copy button
     ═══════════════════════════════════════
     Wraps standalone <pre><code> blocks that are NOT already
     inside a .code-block wrapper, adding a header with language
     label and copy button. */
  var COPY_LABEL   = 'Kopieren';
  var COPIED_LABEL = 'Kopiert!';

  // SVG icons (inline to avoid extra requests)
  var ICON_COPY = '<svg class="code-block__copy-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';
  var ICON_CHECK = '<svg class="code-block__copy-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>';

  root.querySelectorAll('.wiki-article-content pre').forEach(function (pre) {
    // Skip if already wrapped
    if (pre.parentElement && pre.parentElement.classList.contains('code-block')) return;

    var code = pre.querySelector('code');
    var lang = '';

    // Detect language from class (e.g. "language-python", "hljs language-js")
    if (code) {
      var match = (code.className || '').match(/language-(\w+)/);
      if (match) lang = match[1];
    }

    // Build wrapper
    var wrapper = document.createElement('div');
    wrapper.className = 'code-block';

    // Header
    var header = document.createElement('div');
    header.className = 'code-block__header';

    var langSpan = document.createElement('span');
    langSpan.className = 'code-block__lang';
    langSpan.textContent = lang || 'code';

    var copyBtn = document.createElement('button');
    copyBtn.className = 'code-block__copy';
    copyBtn.type = 'button';
    copyBtn.innerHTML = ICON_COPY + ' ' + COPY_LABEL;
    copyBtn.setAttribute('aria-label', COPY_LABEL);

    header.appendChild(langSpan);
    header.appendChild(copyBtn);

    // Insert wrapper
    pre.parentNode.insertBefore(wrapper, pre);
    wrapper.appendChild(header);
    wrapper.appendChild(pre);

    // Copy handler
    copyBtn.addEventListener('click', function () {
      var text = code ? code.textContent : pre.textContent;
      navigator.clipboard.writeText(text).then(function () {
        copyBtn.innerHTML = ICON_CHECK + ' ' + COPIED_LABEL;
        copyBtn.classList.add('is-copied');
        setTimeout(function () {
          copyBtn.innerHTML = ICON_COPY + ' ' + COPY_LABEL;
          copyBtn.classList.remove('is-copied');
        }, 2000);
      });
    });
  });


  /* ═══════════════════════════════════════
     4. ADMONITION COLLAPSIBLE TOGGLE
     ═══════════════════════════════════════ */
  root.querySelectorAll('.admonition--collapsible .admonition__header').forEach(function (header) {
    var body = header.nextElementSibling;
    if (!body) return;

    // Set initial state if not set
    if (!header.hasAttribute('aria-expanded')) {
      header.setAttribute('aria-expanded', 'true');
    }

    header.addEventListener('click', function () {
      var isExpanded = header.getAttribute('aria-expanded') === 'true';
      header.setAttribute('aria-expanded', String(!isExpanded));
    });
  });

})();
