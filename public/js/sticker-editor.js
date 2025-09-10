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

  function setTemplateBg() {
    const tpl = templates[tplSel.value] || templates.avery_l7163;
    preview.style.aspectRatio = `${tpl.w}/${tpl.h}`;
    preview.style.padding = `${(tpl.padding / tpl.w) * 100}%`;
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

  function applyPositionsFromInputs() {
    const rect = preview.getBoundingClientRect();
    const tpl = templates[tplSel.value] || templates.avery_l7163;
    const pad = (tpl.padding / tpl.w) * rect.width;
    const innerW = rect.width - 2 * pad;
    const innerH = rect.height - 2 * pad;

    const tTop = pctToPx(parseFloat(descTop.value || '10'), innerH);
    const tLeft = pctToPx(parseFloat(descLeft.value || '10'), innerW);
    const tW = pctToPx(parseFloat(descW.value || '60'), innerW);
    const tH = pctToPx(parseFloat(descH.value || '60'), innerH);

    textBox.style.top = `${tTop}px`;
    textBox.style.left = `${tLeft}px`;
    textBox.style.width = `${tW}px`;
    textBox.style.height = `${tH}px`;

    const qTopPx = pctToPx(parseFloat(qrTop.value || '10'), innerH);
    const qLeftPx = pctToPx(parseFloat(qrLeft.value || '75'), innerW);
    const qPct = parseFloat(qrSizePct.value || qrSize.value || '30');
    const qSizePx = pctToPx(qPct, Math.min(innerW, innerH));

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
    const rect = preview.getBoundingClientRect();
    const tpl = templates[tplSel.value] || templates.avery_l7163;
    const pad = (tpl.padding / tpl.w) * rect.width;
    const innerW = rect.width - 2 * pad;
    const innerH = rect.height - 2 * pad;
    const innerLeft = rect.left + pad;
    const innerTop = rect.top + pad;
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
  }

  function makeDraggable(el) {
    let dragging = false;
    let start = { x: 0, y: 0 };
    let orig = { x: 0, y: 0 };

    const onDown = (ev) => {
      const e = ev.touches ? ev.touches[0] : ev;
      dragging = true;
      const r = el.getBoundingClientRect();
      orig.x = r.left;
      orig.y = r.top;
      start.x = e.clientX;
      start.y = e.clientY;
      ev.preventDefault();
    };

    const onMove = (ev) => {
      if (!dragging) return;
      const e = ev.touches ? ev.touches[0] : ev;
      const dx = e.clientX - start.x;
      const dy = e.clientY - start.y;
      moveTo(orig.x + dx, orig.y + dy);
    };

    const onUp = () => {
      if (!dragging) return;
      dragging = false;
      syncInputsFromLayout();
      debouncedSave();
    };

    function moveTo(absX, absY) {
      const pRect = preview.getBoundingClientRect();
      const tpl = templates[tplSel.value] || templates.avery_l7163;
      const pad = (tpl.padding / tpl.w) * pRect.width;
      const innerLeft = pRect.left + pad;
      const innerTop = pRect.top + pad;
      const innerW = pRect.width - 2 * pad;
      const innerH = pRect.height - 2 * pad;
      const r = el.getBoundingClientRect();
      const w = r.width;
      const h = r.height;

      let left = absX - innerLeft;
      let top = absY - innerTop;

      left = Math.max(0, Math.min(left, innerW - w));
      top = Math.max(0, Math.min(top, innerH - h));

      el.style.left = `${Math.round(left)}px`;
      el.style.top = `${Math.round(top)}px`;
    }

    el.addEventListener('mousedown', onDown);
    el.addEventListener('touchstart', onDown, { passive: false });
    window.addEventListener('mousemove', onMove);
    window.addEventListener('touchmove', onMove, { passive: false });
    window.addEventListener('mouseup', onUp);
    window.addEventListener('touchend', onUp);

    el.tabIndex = 0;
    el.addEventListener('keydown', (e) => {
      const r = el.getBoundingClientRect();
      let dx = 0;
      let dy = 0;
      const step = e.shiftKey ? 10 : 1;
      if (e.key === 'ArrowLeft') dx = -step;
      if (e.key === 'ArrowRight') dx = step;
      if (e.key === 'ArrowUp') dy = -step;
      if (e.key === 'ArrowDown') dy = step;
      if (dx || dy) {
        moveTo(r.left + dx, r.top + dy);
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
        sStart.x = e.clientX;
        sStart.y = e.clientY;
        sOrig.w = r.width;
        sOrig.h = r.height;
        const pRect = preview.getBoundingClientRect();
        const tpl = templates[tplSel.value] || templates.avery_l7163;
        const pad = (tpl.padding / tpl.w) * pRect.width;
        anchor.x = r.left - (pRect.left + pad);
        anchor.y = r.top - (pRect.top + pad);
        ev.preventDefault();
      };

      const onRMove = (ev) => {
        if (!resizing) return;
        const e = ev.touches ? ev.touches[0] : ev;
        const dx = e.clientX - sStart.x;
        const dy = e.clientY - sStart.y;
        const pRect = preview.getBoundingClientRect();
        const tpl = templates[tplSel.value] || templates.avery_l7163;
        const pad = (tpl.padding / tpl.w) * pRect.width;
        const innerW = pRect.width - 2 * pad;
        const innerH = pRect.height - 2 * pad;
        let newW = Math.max(40, sOrig.w + dx);
        let newH = Math.max(40, sOrig.h + dy);
        newW = Math.min(newW, innerW - anchor.x);
        newH = Math.min(newH, innerH - anchor.y);
        el.style.width = `${Math.round(newW)}px`;
        el.style.height = `${Math.round(newH)}px`;
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
    const rect = preview.getBoundingClientRect();
    const tpl = templates[tplSel.value] || templates.avery_l7163;
    const pad = (tpl.padding / tpl.w) * rect.width;
    const innerW = rect.width - 2 * pad;
    const innerH = rect.height - 2 * pad;
    const shortK = Math.min(innerW, innerH);
    const px = Math.round((parseInt(qrSize.value, 10) / 100) * shortK);
    qrBox.style.width = `${px}px`;
    qrBox.style.height = `${px}px`;
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
  headerSize?.addEventListener('input', () => { updatePreviewText(); debouncedSave(); });
  subheaderSize?.addEventListener('input', () => { updatePreviewText(); debouncedSave(); });
  catalogSize?.addEventListener('input', () => { updatePreviewText(); debouncedSave(); });
  descSize?.addEventListener('input', () => { updatePreviewText(); debouncedSave(); });
  if (bgInput) {
    bgInput.addEventListener('change', async () => {
      const file = bgInput.files && bgInput.files[0];
      if (!file) return;
      if (bgName) bgName.value = file.name;
      const uid = (window.quizConfig || {}).event_uid || '';
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
    const uid = (window.quizConfig || {}).event_uid || '';
    const url = uid
      ? withBase(`/admin/sticker-settings?event_uid=${encodeURIComponent(uid)}`)
      : withBase('/admin/sticker-settings');
    try {
      const res = await fetch(url);
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
      // ignore
    }
    applyPositionsWhenVisible();
    qrImg.src = makeDemoQr();
  }

  async function saveStickerSettings() {
    const uid = (window.quizConfig || {}).event_uid || '';
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
      await fetch(withBase('/admin/sticker-settings'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
    } catch (e) {
      // ignore
    }
  }

  function makeDemoQr() {
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 29 29"><rect width="29" height="29" fill="#fff"/><rect x="2" y="2" width="7" height="7"/><rect x="20" y="2" width="7" height="7"/><rect x="2" y="20" width="7" height="7"/><rect x="11" y="11" width="7" height="7"/></svg>`;
    return 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
  }

  makeDraggable(textBox);
  makeDraggable(qrBox);

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

