/**
 * Grouped href suggestion dropdown for marketing menu editors.
 *
 * Replaces the native <datalist> with a custom dropdown that groups
 * suggestions by page path, shows block type + title metadata,
 * and filters by the currently active namespace.
 *
 * Usage:
 *   const suggest = window.hrefSuggest.create(internalLinks, resolveNamespaceFn);
 *   suggest.attach(inputElement);          // attach to an <input>
 *   suggest.detach(inputElement);          // clean up
 *   suggest.destroy();                     // remove everything
 *
 * Requires: href-suggest.css
 */
(function (global) {
  'use strict';

  /**
   * Build grouped option structure from flat internal links array.
   *
   * Groups:
   *   1. Pages – each page path becomes a group with its anchor children
   *   2. Standalone anchors (page === '') in a separate "Nur Anker" group
   *
   * @param {Array} options  - internal links from PHP
   * @param {string} namespace - current namespace to filter by (empty = show all)
   * @param {string} query - user typed filter string
   * @returns {Array<{groupLabel: string, namespace: string, items: Array}>}
   */
  function buildGroups(options, namespace, query) {
    const normalizedQuery = (query || '').trim().toLowerCase();
    const pageMap = new Map();   // page → {items}
    const standaloneItems = [];

    for (const opt of options) {
      // Namespace filter
      if (namespace && opt.namespace && opt.namespace !== namespace) {
        continue;
      }

      // Query filter – match against value, blockType, blockTitle
      if (normalizedQuery) {
        const haystack = [
          opt.value || '',
          opt.blockType || '',
          opt.blockTitle || '',
          opt.label || '',
        ].join(' ').toLowerCase();
        if (!haystack.includes(normalizedQuery)) {
          continue;
        }
      }

      const page = opt.page || '';
      if (page === '' && opt.group === 'Seiten-Anker') {
        standaloneItems.push(opt);
        continue;
      }

      if (!pageMap.has(page)) {
        pageMap.set(page, []);
      }
      pageMap.get(page).push(opt);
    }

    const groups = [];

    // Pages sorted alphabetically
    const sortedPages = Array.from(pageMap.keys()).sort();
    for (const page of sortedPages) {
      const items = pageMap.get(page);
      // Sort: page-path first, then anchors
      items.sort((a, b) => {
        if (a.group === 'Seitenpfade' && b.group !== 'Seitenpfade') return -1;
        if (a.group !== 'Seitenpfade' && b.group === 'Seitenpfade') return 1;
        return (a.value || '').localeCompare(b.value || '');
      });
      groups.push({
        groupLabel: page,
        namespace: items[0]?.namespace || '',
        items,
      });
    }

    // Standalone anchors
    if (standaloneItems.length > 0) {
      standaloneItems.sort((a, b) => (a.value || '').localeCompare(b.value || ''));
      groups.push({
        groupLabel: 'Nur Anker',
        namespace: standaloneItems[0]?.namespace || '',
        items: standaloneItems,
      });
    }

    return groups;
  }

  /**
   * Render the dropdown HTML from groups.
   */
  function renderDropdown(dropdown, groups) {
    dropdown.innerHTML = '';

    if (groups.length === 0) {
      const empty = document.createElement('div');
      empty.className = 'href-suggest__empty';
      empty.textContent = 'Keine Vorschläge gefunden.';
      dropdown.appendChild(empty);
      return;
    }

    let optionIndex = 0;
    for (const group of groups) {
      const header = document.createElement('div');
      header.className = 'href-suggest__group';
      header.textContent = group.groupLabel || 'Seitenübergreifend';
      if (group.namespace) {
        const nsBadge = document.createElement('span');
        nsBadge.className = 'href-suggest__ns';
        nsBadge.textContent = group.namespace;
        header.appendChild(nsBadge);
      }
      dropdown.appendChild(header);

      for (const item of group.items) {
        const row = document.createElement('div');
        row.className = 'href-suggest__option';
        row.dataset.index = String(optionIndex);
        row.dataset.value = item.value || '';

        const valueSpan = document.createElement('span');
        valueSpan.className = 'href-suggest__value';
        valueSpan.textContent = item.value || '';
        row.appendChild(valueSpan);

        const metaParts = [];
        if (item.blockType) {
          metaParts.push(item.blockType);
        }
        if (item.blockTitle) {
          metaParts.push(item.blockTitle);
        }

        if (metaParts.length > 0) {
          const metaSpan = document.createElement('span');
          metaSpan.className = 'href-suggest__meta';

          if (item.blockType) {
            const typeSpan = document.createElement('span');
            typeSpan.className = 'href-suggest__type';
            typeSpan.textContent = item.blockType;
            metaSpan.appendChild(typeSpan);
          }
          if (item.blockTitle) {
            metaSpan.appendChild(document.createTextNode(item.blockTitle));
          }

          row.appendChild(metaSpan);
        }

        dropdown.appendChild(row);
        optionIndex++;
      }
    }
  }

  /**
   * Create an href suggestion controller.
   *
   * @param {Array} internalLinks - options from data-internal-links
   * @param {Function} resolveNamespace - returns current namespace string
   * @returns {{ attach: Function, detach: Function, destroy: Function }}
   */
  function create(internalLinks, resolveNamespace) {
    const options = Array.isArray(internalLinks) ? internalLinks : [];
    const getNamespace = typeof resolveNamespace === 'function' ? resolveNamespace : () => '';

    const dropdown = document.createElement('div');
    dropdown.className = 'href-suggest';
    dropdown.hidden = true;
    dropdown.setAttribute('role', 'listbox');
    document.body.appendChild(dropdown);

    let activeInput = null;
    let activeIndex = -1;
    let allOptionElements = [];
    let isMouseInDropdown = false;

    function positionDropdown(input) {
      const rect = input.getBoundingClientRect();
      dropdown.style.position = 'fixed';
      dropdown.style.top = rect.bottom + 2 + 'px';
      dropdown.style.left = rect.left + 'px';
      dropdown.style.width = Math.max(rect.width, 280) + 'px';
    }

    function show(input) {
      activeInput = input;
      update();
      if (dropdown.hidden) {
        dropdown.hidden = false;
      }
    }

    function hide() {
      dropdown.hidden = true;
      activeIndex = -1;
      allOptionElements = [];
      isMouseInDropdown = false;
    }

    function update() {
      if (!activeInput) return;
      const query = activeInput.value || '';
      const namespace = getNamespace();
      const groups = buildGroups(options, namespace, query);
      renderDropdown(dropdown, groups);
      positionDropdown(activeInput);
      allOptionElements = Array.from(dropdown.querySelectorAll('.href-suggest__option'));
      activeIndex = -1;
      setActive(-1);
    }

    function selectOption(value) {
      if (!activeInput) return;
      activeInput.value = value;
      activeInput.dispatchEvent(new Event('input', { bubbles: true }));
      hide();
      activeInput.focus();
    }

    function setActive(index) {
      allOptionElements.forEach((el, i) => {
        el.dataset.active = i === index ? 'true' : 'false';
      });
      if (index >= 0 && allOptionElements[index]) {
        allOptionElements[index].scrollIntoView({ block: 'nearest' });
      }
    }

    // ── Event handlers ──────────────────────────────────────────

    function onFocus(e) {
      if (options.length === 0) return;
      show(e.target);
    }

    function onInput(e) {
      if (options.length === 0) return;
      show(e.target);
    }

    function onBlur() {
      // Delay to allow click on dropdown
      setTimeout(() => {
        if (!isMouseInDropdown) {
          hide();
        }
      }, 150);
    }

    function onKeydown(e) {
      if (dropdown.hidden) return;

      if (e.key === 'Escape') {
        e.preventDefault();
        hide();
        return;
      }

      if (e.key === 'ArrowDown') {
        e.preventDefault();
        activeIndex = Math.min(activeIndex + 1, allOptionElements.length - 1);
        setActive(activeIndex);
        return;
      }

      if (e.key === 'ArrowUp') {
        e.preventDefault();
        activeIndex = Math.max(activeIndex - 1, 0);
        setActive(activeIndex);
        return;
      }

      if (e.key === 'Enter' && activeIndex >= 0) {
        e.preventDefault();
        const el = allOptionElements[activeIndex];
        if (el) {
          selectOption(el.dataset.value);
        }
        return;
      }
    }

    // Dropdown mouse events
    dropdown.addEventListener('mouseenter', () => { isMouseInDropdown = true; });
    dropdown.addEventListener('mouseleave', () => { isMouseInDropdown = false; });
    dropdown.addEventListener('click', (e) => {
      const optionEl = e.target.closest('.href-suggest__option');
      if (optionEl) {
        selectOption(optionEl.dataset.value);
      }
    });
    dropdown.addEventListener('mousemove', (e) => {
      const optionEl = e.target.closest('.href-suggest__option');
      if (optionEl) {
        const idx = parseInt(optionEl.dataset.index, 10);
        if (!isNaN(idx)) {
          activeIndex = idx;
          setActive(activeIndex);
        }
      }
    });

    // Close on outside scroll (reposition)
    function onScroll() {
      if (!dropdown.hidden && activeInput) {
        positionDropdown(activeInput);
      }
    }
    window.addEventListener('scroll', onScroll, { passive: true, capture: true });

    // ── Public API ──────────────────────────────────────────────

    const inputListeners = new WeakMap();

    function attach(input) {
      if (!input || inputListeners.has(input)) return;

      // Remove any native datalist binding
      input.removeAttribute('list');

      const listeners = { onFocus, onInput, onBlur, onKeydown };
      input.addEventListener('focus', listeners.onFocus);
      input.addEventListener('input', listeners.onInput);
      input.addEventListener('blur', listeners.onBlur);
      input.addEventListener('keydown', listeners.onKeydown);
      inputListeners.set(input, listeners);
    }

    function detach(input) {
      if (!input) return;
      const listeners = inputListeners.get(input);
      if (!listeners) return;
      input.removeEventListener('focus', listeners.onFocus);
      input.removeEventListener('input', listeners.onInput);
      input.removeEventListener('blur', listeners.onBlur);
      input.removeEventListener('keydown', listeners.onKeydown);
      inputListeners.delete(input);
    }

    function destroy() {
      hide();
      window.removeEventListener('scroll', onScroll, { capture: true });
      dropdown.remove();
    }

    return { attach, detach, destroy };
  }

  // ── Parse helper ────────────────────────────────────────────────

  function parseInternalLinks(container) {
    try {
      const parsed = JSON.parse(container?.dataset?.internalLinks || '[]');
      return Array.isArray(parsed) ? parsed : [];
    } catch (error) {
      console.warn('[href-suggest] Failed to parse internal links', error);
      return [];
    }
  }

  global.hrefSuggest = { create, parseInternalLinks };
})(typeof window !== 'undefined' ? window : globalThis);
