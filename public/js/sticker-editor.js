const basePath = window.basePath || '';
const withBase = (p) => basePath + p;

(() => {
  const pdfBtn = document.getElementById('catalogStickerPdfBtn');
  const modal = window.UIkit ? UIkit.modal('#catalogStickerModal') : null;
  pdfBtn?.addEventListener('click', (e) => {
    e.preventDefault();
    modal?.show();
  });

  const modalEl = document.getElementById('catalogStickerModal');
  const preview = document.getElementById('catalogStickerPreview');
  const tplSel = document.getElementById('catalogStickerTemplate');

  const textBox = document.getElementById('stickerTextBox');
  const textPrev = document.getElementById('stickerTextPreview');
  const textInput = document.getElementById('catalogStickerText');

  const qrBox = document.getElementById('stickerQrHandle');
  const qrImg = document.getElementById('qrPreview');
  const qrSize = document.getElementById('catalogStickerQrSizePct');

  const descTop = document.getElementById('stickerDescTop');
  const descLeft = document.getElementById('stickerDescLeft');
  const descW = document.getElementById('stickerDescW');
  const descH = document.getElementById('stickerDescH');

  const qrTop = document.getElementById('stickerQrTop');
  const qrLeft = document.getElementById('stickerQrLeft');
  const qrSizePct = document.getElementById('stickerQrSize');

  const saveBtn = document.getElementById('saveStickerBtn');

  const templates = {
    avery_l7163: { w: 99.1, h: 38.1, bg: null },
    avery_l7165: { w: 99.1, h: 67.7, bg: null }
  };

  function setTemplateBg() {
    const tpl = templates[tplSel.value] || templates.avery_l7163;
    preview.style.aspectRatio = `${tpl.w}/${tpl.h}`;
    if (tpl.bg) preview.style.backgroundImage = `url('${tpl.bg}')`;
    else preview.style.backgroundImage = 'none';
  }

  const pctToPx = (p, total) => Math.round((p / 100) * total);
  const pxToPct = (px, total) => Math.max(0, Math.min(100, (px / total) * 100));

  function applyPositionsFromInputs() {
    const rect = preview.getBoundingClientRect();

    const tTop = pctToPx(parseFloat(descTop.value || '10'), rect.height);
    const tLeft = pctToPx(parseFloat(descLeft.value || '10'), rect.width);
    const tW = pctToPx(parseFloat(descW.value || '60'), rect.width);
    const tH = pctToPx(parseFloat(descH.value || '60'), rect.height);

    textBox.style.top = `${tTop}px`;
    textBox.style.left = `${tLeft}px`;
    textBox.style.width = `${tW}px`;
    textBox.style.height = `${tH}px`;

    const qTopPx = pctToPx(parseFloat(qrTop.value || '10'), rect.height);
    const qLeftPx = pctToPx(parseFloat(qrLeft.value || '75'), rect.width);
    const qPct = parseFloat(qrSizePct.value || qrSize.value || '30');
    const qSizePx = pctToPx(qPct, Math.min(rect.width, rect.height));

    qrBox.style.top = `${qTopPx}px`;
    qrBox.style.left = `${qLeftPx}px`;
    qrBox.style.width = `${qSizePx}px`;
    qrBox.style.height = `${qSizePx}px`;
  }

  function syncInputsFromLayout() {
    const rect = preview.getBoundingClientRect();
    const tRect = textBox.getBoundingClientRect();
    const qRect = qrBox.getBoundingClientRect();

    descTop.value = pxToPct(tRect.top - rect.top, rect.height).toFixed(2);
    descLeft.value = pxToPct(tRect.left - rect.left, rect.width).toFixed(2);
    descW.value = pxToPct(tRect.width, rect.width).toFixed(2);
    descH.value = pxToPct(tRect.height, rect.height).toFixed(2);

    qrTop.value = pxToPct(qRect.top - rect.top, rect.height).toFixed(2);
    qrLeft.value = pxToPct(qRect.left - rect.left, rect.width).toFixed(2);

    const shortK = Math.min(rect.width, rect.height);
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
      const r = el.getBoundingClientRect();
      const w = r.width;
      const h = r.height;

      let left = absX - pRect.left;
      let top = absY - pRect.top;

      left = Math.max(0, Math.min(left, pRect.width - w));
      top = Math.max(0, Math.min(top, pRect.height - h));

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
        anchor.x = r.left - pRect.left;
        anchor.y = r.top - pRect.top;
        ev.preventDefault();
      };

      const onRMove = (ev) => {
        if (!resizing) return;
        const e = ev.touches ? ev.touches[0] : ev;
        const dx = e.clientX - sStart.x;
        const dy = e.clientY - sStart.y;
        const pRect = preview.getBoundingClientRect();
        let newW = Math.max(40, sOrig.w + dx);
        let newH = Math.max(40, sOrig.h + dy);
        newW = Math.min(newW, pRect.width - anchor.x);
        newH = Math.min(newH, pRect.height - anchor.y);
        el.style.width = `${Math.round(newW)}px`;
        el.style.height = `${Math.round(newH)}px`;
      };

      const onRUp = () => {
        if (!resizing) return;
        resizing = false;
        syncInputsFromLayout();
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
    const shortK = Math.min(rect.width, rect.height);
    const px = Math.round((parseInt(qrSize.value, 10) / 100) * shortK);
    qrBox.style.width = `${px}px`;
    qrBox.style.height = `${px}px`;
    syncInputsFromLayout();
    debouncedSave();
  });

  textInput.addEventListener('input', () => {
    textPrev.textContent = textInput.value.replace(/\n/g, '\n');
  });

  let saveTimer = null;
  function debouncedSave() {
    clearTimeout(saveTimer);
    saveTimer = setTimeout(saveStickerSettings, 250);
  }

  async function loadStickerSettings() {
    try {
      const res = await fetch(withBase('/admin/sticker-settings'));
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
      textInput.value = data.previewText || 'Beispiel-Text\nWeitere Zeile';
      textPrev.textContent = textInput.value;
    } catch (e) {
      // ignore
    }
    setTemplateBg();
    applyPositionsFromInputs();
    qrImg.src = makeDemoQr();
  }

  async function saveStickerSettings() {
    const payload = {
      stickerTemplate: tplSel.value,
      stickerDescTop: descTop.value,
      stickerDescLeft: descLeft.value,
      stickerDescWidth: descW.value,
      stickerDescHeight: descH.value,
      stickerQrTop: qrTop.value,
      stickerQrLeft: qrLeft.value,
      stickerQrSizePct: qrSizePct.value,
      previewText: textInput.value
    };
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

  if (window.UIkit?.util && modalEl?.$el) {
    UIkit.util.on(modalEl.$el, 'shown', () => loadStickerSettings());
  } else {
    loadStickerSettings();
  }

  saveBtn.addEventListener('click', saveStickerSettings);
})();

