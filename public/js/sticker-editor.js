const basePath = window.basePath || '';
const withBase = (p) => basePath + p;
const apiFetch = window.apiFetch || ((p, o) => fetch(withBase(p), o));

(() => {
  const openBtn = document.getElementById('openStickerEditorBtn');
  const modal = window.UIkit ? UIkit.modal('#catalogStickerModal') : null;
  openBtn?.addEventListener('click', (e) => {
    e.preventDefault();
    modal?.show();
  });

  const preview = document.getElementById('catalogStickerPreview');
  const stage = document.getElementById('stickerStage');
  const tplSel = document.getElementById('catalogStickerTemplate');

  const textBox = document.getElementById('stickerTextBox');
  const textPrev = document.getElementById('stickerTextPreview');

  const qrBox = document.getElementById('stickerQrHandle');
  const qrImg = document.getElementById('qrPreview');
  const qrSize = document.getElementById('catalogStickerQrSizePct');

  const printHeader = document.getElementById('catalogStickerPrintHeader');
  const printSubheader = document.getElementById('catalogStickerPrintSubheader');
  const printCatalog = document.getElementById('catalogStickerPrintCatalog');
  const printDesc = document.getElementById('catalogStickerPrintDesc');
  const qrColor = document.getElementById('catalogStickerQrColor');
  const textColor = document.getElementById('catalogStickerTextColor');
  const headerSize = document.getElementById('catalogStickerHeaderFontSize');
  const subheaderSize = document.getElementById('catalogStickerSubheaderFontSize');
  const catalogSize = document.getElementById('catalogStickerCatalogFontSize');
  const descSize = document.getElementById('catalogStickerDescFontSize');
  const bgInput = document.getElementById('catalogStickerBg');
  const bgName = document.getElementById('catalogStickerBgName');
  const bgProgress = document.getElementById('stickerBgProgress');

  const descTop = document.getElementById('stickerDescTop');
  const descLeft = document.getElementById('stickerDescLeft');
  const descW = document.getElementById('stickerDescW');
  const descH = document.getElementById('stickerDescH');

  const qrTop = document.getElementById('stickerQrTop');
  const qrLeft = document.getElementById('stickerQrLeft');
  const qrSizePct = document.getElementById('stickerQrSize');

  const pdfCreateBtn = document.getElementById('catalogStickerPdfCreateBtn');

  let baseHeader = '';
  let baseSubheader = '';
  let baseCatalog = '';
  let baseDesc = '';

  const templates = {
    avery_l7165: { w: 99.1, h: 67.7, padding: 6.0, bg: null },
    avery_l7163: { w: 99.1, h: 38.1, padding: 5.0, bg: null },
    avery_l7651: { w: 63.5, h: 38.1, padding: 4.0, bg: null },
    avery_l7992: { w: 210.0, h: 41.0, padding: 6.0, bg: null },
    avery_j8165: { w: 199.6, h: 67.7, padding: 6.0, bg: null },
    avery_l7168: { w: 199.6, h: 143.5, padding: 6.0, bg: null }
  };

  const PX_PER_MM = 6;

  function setTemplateBg() {
    const tpl = templates[tplSel.value] || templates.avery_l7163;
    const widthPx = Math.round(tpl.w * PX_PER_MM);
    const heightPx = Math.round(tpl.h * PX_PER_MM);
    const padPx = Math.round(tpl.padding * PX_PER_MM);
    preview.style.width = `${widthPx}px`;
    preview.style.height = `${heightPx}px`;
    preview.style.padding = '0';
    stage.style.left = `${padPx}px`;
    stage.style.top = `${padPx}px`;
    stage.style.width = `${widthPx - padPx * 2}px`;
    stage.style.height = `${heightPx - padPx * 2}px`;
    if (tpl.bg) {
      const img = new Image();
      img.onload = () => {
        preview.style.backgroundImage = `url('${tpl.bg}')`;
        applyPositionsWhenVisible();
      };
      img.onerror = () => {
        console.error('Failed to load sticker background', tpl.bg);
        if (typeof window.notify === 'function') {
          window.notify(window.transImageLoadError || 'Hintergrundbild konnte nicht geladen werden', 'danger');
        }
        preview.style.backgroundImage = 'none';
        applyPositionsWhenVisible();
      };
      img.src = tpl.bg;
    } else {
      preview.style.backgroundImage = 'none';
      applyPositionsWhenVisible();
    }
  }

  const pctToPx = (p, total) => Math.round((p / 100) * total);
  const pxToPct = (px, total) => Math.max(0, Math.min(100, (px / total) * 100));

  function mmToPx(mm, axis = 'x') {
    const tpl = templates[tplSel.value] || templates.avery_l7163;
    const innerW = stage.clientWidth;
    const innerH = stage.clientHeight;
    const widthMm = tpl.w - 2 * tpl.padding;
    const heightMm = tpl.h - 2 * tpl.padding;
    return Math.round(axis === 'y' ? (mm / heightMm) * innerH : (mm / widthMm) * innerW);
  }

  function pxToMm(px, axis = 'x') {
    const tpl = templates[tplSel.value] || templates.avery_l7163;
    const innerW = stage.clientWidth;
    const innerH = stage.clientHeight;
    const widthMm = tpl.w - 2 * tpl.padding;
    const heightMm = tpl.h - 2 * tpl.padding;
    return axis === 'y' ? (px / innerH) * heightMm : (px / innerW) * widthMm;
  }

  const GRID_PCT = 1;
  const MIN_SIZE_MM = 10;

  function snap(pct) {
    return Math.round(pct / GRID_PCT) * GRID_PCT;
  }

  window.stickerMmToPx = mmToPx;
  window.stickerPxToMm = pxToMm;

  function applyPositionsFromInputs() {
    const innerW = stage.clientWidth;
    const innerH = stage.clientHeight;

    const minWPct = pxToPct(mmToPx(MIN_SIZE_MM, 'x'), innerW);
    const minHPct = pxToPct(mmToPx(MIN_SIZE_MM, 'y'), innerH);

    let tTopPct = parseFloat(descTop.value || '10');
    let tLeftPct = parseFloat(descLeft.value || '10');
    let tWPct = parseFloat(descW.value || '60');
    let tHPct = parseFloat(descH.value || '60');

    tWPct = Math.max(minWPct, Math.min(tWPct, 100));
    tHPct = Math.max(minHPct, Math.min(tHPct, 100));
    tLeftPct = Math.max(0, Math.min(tLeftPct, 100 - tWPct));
    tTopPct = Math.max(0, Math.min(tTopPct, 100 - tHPct));

    tLeftPct = snap(tLeftPct);
    tTopPct = snap(tTopPct);
    tWPct = snap(tWPct);
    tHPct = snap(tHPct);

    descTop.value = tTopPct.toFixed(2);
    descLeft.value = tLeftPct.toFixed(2);
    descW.value = tWPct.toFixed(2);
    descH.value = tHPct.toFixed(2);

    const tTop = pctToPx(tTopPct, innerH);
    const tLeft = pctToPx(tLeftPct, innerW);
    const tW = pctToPx(tWPct, innerW);
    const tH = pctToPx(tHPct, innerH);

    textBox.style.top = `${tTop}px`;
    textBox.style.left = `${tLeft}px`;
    textBox.style.width = `${tW}px`;
    textBox.style.height = `${tH}px`;

    const shortK = Math.min(innerW, innerH);
    const minQPct = pxToPct(mmToPx(MIN_SIZE_MM, 'x'), shortK);

    let qTopPct = parseFloat(qrTop.value || '10');
    let qLeftPct = parseFloat(qrLeft.value || '75');
    let qPct = parseFloat(qrSizePct.value || qrSize.value || '30');

    qPct = Math.max(minQPct, Math.min(qPct, 100));
    qTopPct = Math.max(0, Math.min(qTopPct, 100 - qPct));
    qLeftPct = Math.max(0, Math.min(qLeftPct, 100 - qPct));

    qTopPct = snap(qTopPct);
    qLeftPct = snap(qLeftPct);
    qPct = snap(qPct);

    qrTop.value = qTopPct.toFixed(2);
    qrLeft.value = qLeftPct.toFixed(2);
    qrSizePct.value = qPct.toFixed(2);
    qrSize.value = qrSizePct.value;

    const qTopPx = pctToPx(qTopPct, innerH);
    const qLeftPx = pctToPx(qLeftPct, innerW);
    const qSizePx = pctToPx(qPct, shortK);

    qrBox.style.top = `${qTopPx}px`;
    qrBox.style.left = `${qLeftPx}px`;
    qrBox.style.width = `${qSizePx}px`;
    qrBox.style.height = `${qSizePx}px`;
  }

  function applyPositionsWhenVisible() {
    const rect = preview.getBoundingClientRect();
    if (!rect.width || !rect.height) {
      requestAnimationFrame(applyPositionsWhenVisible);
    } else {
      applyPositionsFromInputs();
    }
  }

  function syncInputsFromLayout({ lockDescPos = false } = {}) {
    const rect = stage.getBoundingClientRect();
    const innerW = stage.clientWidth;
    const innerH = stage.clientHeight;
    const innerLeft = rect.left;
    const innerTop = rect.top;
    const tRect = textBox.getBoundingClientRect();
    const qRect = qrBox.getBoundingClientRect();

    if (!lockDescPos) {
      descTop.value = pxToPct(tRect.top - innerTop, innerH).toFixed(2);
      descLeft.value = pxToPct(tRect.left - innerLeft, innerW).toFixed(2);
    }
    descW.value = pxToPct(tRect.width, innerW).toFixed(2);
    descH.value = pxToPct(tRect.height, innerH).toFixed(2);

    qrTop.value = pxToPct(qRect.top - innerTop, innerH).toFixed(2);
    qrLeft.value = pxToPct(qRect.left - innerLeft, innerW).toFixed(2);

    const shortK = Math.min(innerW, innerH);
    qrSizePct.value = pxToPct(qRect.width, shortK).toFixed(2);
    qrSize.value = qrSizePct.value;
  }

  function makeDraggable(el) {
    let dragging = false;
    let startX = 0;
    let startY = 0;
    let startLeft = 0;
    let startTop = 0;

    function moveTo(nx, ny) {
      const maxX = stage.clientWidth - el.clientWidth;
      const maxY = stage.clientHeight - el.clientHeight;
      let x = nx;
      let y = ny;
      if (x < 0) x = 0; else if (x > maxX) x = maxX;
      if (y < 0) y = 0; else if (y > maxY) y = maxY;
      el.style.left = `${x}px`;
      el.style.top = `${y}px`;
    }

    const onDown = (ev) => {
      const e = ev.touches ? ev.touches[0] : ev;
      dragging = true;
      startX = e.clientX;
      startY = e.clientY;
      startLeft = el.offsetLeft;
      startTop = el.offsetTop;
      ev.preventDefault();
    };

    const onMove = (ev) => {
      if (!dragging) return;
      const e = ev.touches ? ev.touches[0] : ev;
      const dx = e.clientX - startX;
      const dy = e.clientY - startY;
      moveTo(startLeft + dx, startTop + dy);
    };

    const onUp = () => {
      if (!dragging) return;
      dragging = false;
      syncInputsFromLayout();
      debouncedSave();
    };

    el.addEventListener('mousedown', onDown);
    el.addEventListener('touchstart', onDown, { passive: false });
    window.addEventListener('mousemove', onMove);
    window.addEventListener('touchmove', onMove, { passive: false });
    window.addEventListener('mouseup', onUp);
    window.addEventListener('touchend', onUp);

    el.tabIndex = 0;
    el.addEventListener('keydown', (e) => {
      let dx = 0;
      let dy = 0;
      const step = e.shiftKey ? 10 : 1;
      if (e.key === 'ArrowLeft') dx = -step;
      if (e.key === 'ArrowRight') dx = step;
      if (e.key === 'ArrowUp') dy = -step;
      if (e.key === 'ArrowDown') dy = step;
      if (dx || dy) {
        moveTo(el.offsetLeft + dx, el.offsetTop + dy);
        syncInputsFromLayout();
        debouncedSave();
        e.preventDefault();
      }
    });

    const resizer = el.querySelector('.resize-handle');
    if (resizer) {
      let resizing = false;
      let sStart = { x: 0, y: 0 };
      let sOrig = { w: 0, h: 0 };
      let anchor = { x: 0, y: 0 };

      const onRDown = (ev) => {
        resizing = true;
        const e = ev.touches ? ev.touches[0] : ev;
        const r = el.getBoundingClientRect();
        const sRect = stage.getBoundingClientRect();
        const innerW = stage.clientWidth;
        const innerH = stage.clientHeight;
        sStart.x = e.clientX;
        sStart.y = e.clientY;
        sOrig.w = (r.width / innerW) * 100;
        sOrig.h = (r.height / innerH) * 100;
        sOrig.size = (r.width / Math.min(innerW, innerH)) * 100;
        anchor.x = r.left - sRect.left;
        anchor.y = r.top - sRect.top;
        ev.preventDefault();
      };

      const onRMove = (ev) => {
        if (!resizing) return;
        const e = ev.touches ? ev.touches[0] : ev;
        const dx = e.clientX - sStart.x;
        const dy = e.clientY - sStart.y;
        const innerW = stage.clientWidth;
        const innerH = stage.clientHeight;
        const maxWPct = 100 - (anchor.x / innerW) * 100;
        const maxHPct = 100 - (anchor.y / innerH) * 100;
        const minWPct = pxToPct(mmToPx(MIN_SIZE_MM, 'x'), innerW);
        const minHPct = pxToPct(mmToPx(MIN_SIZE_MM, 'y'), innerH);

        if (el.classList.contains('dragzone--square')) {
          const shortK = Math.min(innerW, innerH);
          const maxSizePct = pxToPct(Math.min(innerW - anchor.x, innerH - anchor.y), shortK);
          const minSizePct = pxToPct(mmToPx(MIN_SIZE_MM, 'x'), shortK);
          let dPct = Math.max((dx / shortK) * 100, (dy / shortK) * 100);
          let sizePct = sOrig.size + dPct;
          sizePct = Math.max(minSizePct, Math.min(sizePct, maxSizePct));
          sizePct = snap(sizePct);
          const sizePx = pctToPx(sizePct, shortK);
          el.style.width = `${sizePx}px`;
          el.style.height = `${sizePx}px`;
        } else {
          let newWPct = sOrig.w + (dx / innerW) * 100;
          let newHPct = sOrig.h + (dy / innerH) * 100;
          newWPct = Math.max(minWPct, Math.min(newWPct, maxWPct));
          newHPct = Math.max(minHPct, Math.min(newHPct, maxHPct));
          newWPct = snap(newWPct);
          newHPct = snap(newHPct);
          el.style.width = `${pctToPx(newWPct, innerW)}px`;
          el.style.height = `${pctToPx(newHPct, innerH)}px`;
        }
      };

      const onRUp = () => {
        if (!resizing) return;
        resizing = false;
        syncInputsFromLayout({ lockDescPos: el === textBox });
        debouncedSave();
      };

      resizer.addEventListener('mousedown', onRDown);
      resizer.addEventListener('touchstart', onRDown, { passive: false });
      window.addEventListener('mousemove', onRMove);
      window.addEventListener('touchmove', onRMove, { passive: false });
      window.addEventListener('mouseup', onRUp);
      window.addEventListener('touchend', onRUp);
    }
  }

  qrSize.addEventListener('input', () => {
    const innerW = stage.clientWidth;
    const innerH = stage.clientHeight;
    const shortK = Math.min(innerW, innerH);
    const minPct = pxToPct(mmToPx(MIN_SIZE_MM, 'x'), shortK);
    let pct = parseFloat(qrSize.value);
    pct = Math.max(minPct, Math.min(pct, 100));
    pct = snap(pct);
    const px = pctToPx(pct, shortK);
    qrBox.style.width = `${px}px`;
    qrBox.style.height = `${px}px`;
    syncInputsFromLayout();
  });
  qrSize.addEventListener('change', () => {
    syncInputsFromLayout();
    debouncedSave();
  });
  function updatePreviewText() {
    if (!textPrev) return;
    textPrev.innerHTML = '';
    if (printHeader.checked && baseHeader) {
      const el = document.createElement('div');
      el.textContent = baseHeader;
      el.style.fontSize = `${headerSize.value}px`;
      textPrev.appendChild(el);
    }
    if (printSubheader.checked && baseSubheader) {
      const el = document.createElement('div');
      el.textContent = baseSubheader;
      el.style.fontSize = `${subheaderSize.value}px`;
      textPrev.appendChild(el);
    }
    if (printCatalog.checked && baseCatalog) {
      const el = document.createElement('div');
      el.textContent = baseCatalog;
      el.style.fontSize = `${catalogSize.value}px`;
      textPrev.appendChild(el);
    }
    if (printDesc.checked && baseDesc) {
      const el = document.createElement('div');
      el.textContent = baseDesc;
      el.style.fontSize = `${descSize.value}px`;
      textPrev.appendChild(el);
    }
  }

  [printHeader, printSubheader, printCatalog, printDesc].forEach(cb => {
    cb?.addEventListener('change', () => {
      updatePreviewText();
      debouncedSave();
    });
  });

  qrColor?.addEventListener('change', debouncedSave);
  textColor?.addEventListener('change', debouncedSave);
  [headerSize, subheaderSize, catalogSize, descSize].forEach(inp => {
    inp?.addEventListener('input', updatePreviewText);
    ['change', 'mouseup', 'touchend'].forEach(ev => inp?.addEventListener(ev, debouncedSave));
  });
  if (bgInput) {
    bgInput.addEventListener('change', async () => {
      const file = bgInput.files && bgInput.files[0];
      if (!file) return;
      if (bgName) bgName.value = file.name;
      const uid = window.getActiveEventId ? window.getActiveEventId() : '';
      const uploadUrl = uid
        ? withBase(`/admin/sticker-background?event_uid=${encodeURIComponent(uid)}`)
        : withBase('/admin/sticker-background');
      try {
        bgProgress?.removeAttribute('hidden');
        bgProgress.value = 0;
        bgProgress.max = 100;
        const fd = new FormData();
        fd.append('file', file);
        const res = await fetch(uploadUrl, { method: 'POST', body: fd });
        if (!res.ok) throw new Error(await res.text());
        const uploadResp = await res.json();
        bgProgress.value = 100;
        setTimeout(() => {
          bgProgress?.setAttribute('hidden', 'hidden');
          bgProgress?.setAttribute('value', '0');
        }, 1000);
        if (uploadResp && uploadResp.stickerBgPath) {
          if (typeof window.notify === 'function') {
            window.notify(window.transImageReady || 'Hintergrundbild hochgeladen', 'success');
          }
          await loadStickerSettings();
          setTemplateBg();
        } else {
          throw new Error('Hintergrund konnte nicht hochgeladen werden.');
        }
      } catch (err) {
        const msg = (err && err.message) ? err.message : 'Hintergrund konnte nicht hochgeladen werden.';
        if (typeof window.notify === 'function') {
          window.notify(msg, 'danger');
        } else if (typeof UIkit !== 'undefined' && UIkit.notification) {
          UIkit.notification({ message: msg, status: 'danger' });
        } else {
          alert(msg);
        }
        bgProgress?.setAttribute('value', '0');
        setTimeout(() => bgProgress?.setAttribute('hidden', ''), 500);
      }
    });
  }

  let saveTimer = null;
  function debouncedSave() {
    clearTimeout(saveTimer);
    saveTimer = setTimeout(saveStickerSettings, 250);
  }

  async function loadStickerSettings() {
    const uid = window.getActiveEventId ? window.getActiveEventId() : '';
    const url = uid
      ? withBase(`/admin/sticker-settings?event_uid=${encodeURIComponent(uid)}`)
      : withBase('/admin/sticker-settings');
    try {
      const res = await fetch(url);
      if (!res.ok) throw new Error(await res.text());
      const data = await res.json();
      tplSel.value = data.stickerTemplate || 'avery_l7163';
      descTop.value = data.stickerDescTop ?? '10';
      descLeft.value = data.stickerDescLeft ?? '10';
      descW.value = data.stickerDescWidth ?? '60';
      descH.value = data.stickerDescHeight ?? '60';
      qrTop.value = data.stickerQrTop ?? '10';
      qrLeft.value = data.stickerQrLeft ?? '75';
      qrSize.value = data.stickerQrSizePct ?? '28';
      qrSizePct.value = qrSize.value;
      printHeader.checked = data.stickerPrintHeader ?? true;
      printSubheader.checked = data.stickerPrintSubheader ?? true;
      printCatalog.checked = data.stickerPrintCatalog ?? true;
      printDesc.checked = data.stickerPrintDesc ?? false;
      baseHeader = data.previewHeader || '';
      baseSubheader = data.previewSubheader || '';
      baseCatalog = data.previewCatalog || '';
      baseDesc = data.previewDesc || '';
      updatePreviewText();
      qrColor.value = '#' + (data.stickerQrColor ?? '000000');
      textColor.value = '#' + (data.stickerTextColor ?? '000000');
      headerSize.value = data.stickerHeaderFontSize ?? '12';
      subheaderSize.value = data.stickerSubheaderFontSize ?? '10';
      catalogSize.value = data.stickerCatalogFontSize ?? '11';
      descSize.value = data.stickerDescFontSize ?? '10';
      if (data.stickerBgPath) {
        const rawPath = `${data.stickerBgPath}?${Date.now()}`;
        const bgUrl = /^https?:\/\//.test(data.stickerBgPath) ? rawPath : withBase(rawPath);
        Object.keys(templates).forEach(k => {
          templates[k].bg = bgUrl;
        });
      } else {
        Object.keys(templates).forEach(k => {
          templates[k].bg = null;
        });
      }
    } catch (e) {
      const msg = window.transStickerLoadError || 'Sticker-Einstellungen konnten nicht geladen werden.';
      if (typeof window.notify === 'function') {
        window.notify(msg, 'danger');
      } else if (typeof UIkit !== 'undefined' && UIkit.notification) {
        UIkit.notification({ message: msg, status: 'danger' });
      } else {
        alert(msg);
      }
    }
    applyPositionsWhenVisible();
    qrImg.src = makeDemoQr();
  }

  async function saveStickerSettings() {
    const uid = window.getActiveEventId ? window.getActiveEventId() : '';
    const payload = {
      stickerTemplate: tplSel.value,
      stickerDescTop: descTop.value,
      stickerDescLeft: descLeft.value,
      stickerDescWidth: descW.value,
      stickerDescHeight: descH.value,
      stickerQrTop: qrTop.value,
      stickerQrLeft: qrLeft.value,
      stickerQrSizePct: qrSizePct.value,
      stickerPrintHeader: printHeader.checked,
      stickerPrintSubheader: printSubheader.checked,
      stickerPrintCatalog: printCatalog.checked,
      stickerPrintDesc: printDesc.checked,
      stickerQrColor: qrColor.value.replace('#', ''),
      stickerTextColor: textColor.value.replace('#', ''),
      stickerHeaderFontSize: headerSize.value,
      stickerSubheaderFontSize: subheaderSize.value,
      stickerCatalogFontSize: catalogSize.value,
      stickerDescFontSize: descSize.value
    };
    if (uid) {
      payload.event_uid = uid;
    }
    try {
      const res = await fetch(withBase('/admin/sticker-settings'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      if (!res.ok) throw new Error(await res.text());
    } catch (e) {
      const msg = window.transStickerSaveError || 'Sticker-Einstellungen konnten nicht gespeichert werden.';
      if (typeof window.notify === 'function') {
        window.notify(msg, 'danger');
      } else if (typeof UIkit !== 'undefined' && UIkit.notification) {
        UIkit.notification({ message: msg, status: 'danger' });
      } else {
        alert(msg);
      }
    }
  }

  function makeDemoQr() {
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 29 29"><rect width="29" height="29" fill="#fff"/><rect x="2" y="2" width="7" height="7"/><rect x="20" y="2" width="7" height="7"/><rect x="2" y="20" width="7" height="7"/><rect x="11" y="11" width="7" height="7"/></svg>`;
    return 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
  }

  makeDraggable(textBox);
  makeDraggable(qrBox);

  const pctInputs = [descTop, descLeft, descW, descH, qrTop, qrLeft, qrSizePct];
  pctInputs.forEach(inp => {
    inp?.addEventListener('input', () => {
      if (inp === qrSizePct) {
        qrSize.value = inp.value;
      }
      applyPositionsFromInputs();
    });
    ['change', 'mouseup', 'touchend'].forEach(ev => inp?.addEventListener(ev, debouncedSave));
  });

  tplSel.addEventListener('change', () => {
    setTemplateBg();
    applyPositionsFromInputs();
    debouncedSave();
  });

  if (window.UIkit?.util && modal?.$el) {
    UIkit.util.on(modal.$el, 'shown', async () => {
      await loadStickerSettings();
      setTemplateBg();
    });
  } else if (!window.UIkit) {
    loadStickerSettings().then(setTemplateBg);
  }

  pdfCreateBtn?.addEventListener('click', () => {
    saveStickerSettings();
    const params = new URLSearchParams({
      template: tplSel.value,
      print_header: printHeader.checked,
      print_subheader: printSubheader.checked,
      print_catalog: printCatalog.checked,
      print_desc: printDesc.checked,
      qr_color: qrColor.value.replace('#', ''),
      text_color: textColor.value.replace('#', ''),
      qr_size_pct: qrSizePct.value,
      desc_top: descTop.value,
      desc_left: descLeft.value,
      desc_width: descW.value,
      desc_height: descH.value,
      qr_top: qrTop.value,
      qr_left: qrLeft.value,
      header_size: headerSize.value,
      subheader_size: subheaderSize.value,
      catalog_size: catalogSize.value,
      desc_size: descSize.value
    });
    window.open(
      withBase(`/admin/reports/catalog-stickers.pdf?${params.toString()}`),
      '_blank'
    );
  });
})();
