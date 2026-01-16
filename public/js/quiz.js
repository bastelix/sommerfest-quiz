/* global UIkit, Html5Qrcode, TeamNameClient, STORAGE_KEYS, setStored, getStored, clearStored */
// Hauptskript des Quizzes. Dieses File erzeugt dynamisch alle Fragen,
// wertet Antworten aus und speichert das Ergebnis im Browser.
// Der Code wird ausgeführt, sobald das DOM geladen ist.

const quizConfig = window.quizConfig || {};
if(!window.quizConfig){
  window.quizConfig = quizConfig;
}

const currentEventUid = (() => {
  if (typeof window.getActiveEventId === 'function') {
    return window.getActiveEventId();
  }
  return typeof window.activeEventId === 'string' ? window.activeEventId : '';
})();

const basePath = window.basePath || '';
const withBase = path => basePath + path;

let nameReservation = null;
let lastSuggestionFallback = false;
let quizStartedAt = null;
let activeSuggestionReservation = null;
let activeSuggestionFallback = false;

function rememberActiveSuggestionReservation(reservation){
  if(reservation){
    activeSuggestionReservation = reservation;
    activeSuggestionFallback = lastSuggestionFallback;
  }else{
    activeSuggestionReservation = null;
    activeSuggestionFallback = lastSuggestionFallback;
  }
}

function clearActiveSuggestionReservation(){
  activeSuggestionReservation = null;
  activeSuggestionFallback = false;
}

function restoreActiveSuggestionReservation(skippedReservation){
  if(activeSuggestionReservation && activeSuggestionReservation !== skippedReservation){
    nameReservation = activeSuggestionReservation;
    lastSuggestionFallback = activeSuggestionFallback;
  }else if(!activeSuggestionReservation){
    nameReservation = null;
    lastSuggestionFallback = activeSuggestionFallback;
  }else{
    nameReservation = null;
    lastSuggestionFallback = false;
  }
}

function releaseSpecificReservation(reservation){
  if(!reservation){
    return Promise.resolve();
  }
  const client = typeof TeamNameClient === 'object' && TeamNameClient ? TeamNameClient : null;
  if(client && typeof client.release === 'function'){
    return client.release({ reservation }).catch(() => {});
  }
  return Promise.resolve();
}

const fallbackSuggestion = () => `Gast-${Math.random().toString(36).slice(2, 7)}`;

async function getNameSuggestion(){
  const client = typeof TeamNameClient === 'object' && TeamNameClient ? TeamNameClient : null;
  if (client && typeof client.reserve === 'function') {
    try {
      const reservation = await client.reserve({ eventUid: currentEventUid });
      nameReservation = reservation || null;
      if (reservation && typeof reservation.name === 'string' && reservation.name) {
        lastSuggestionFallback = Boolean(reservation.fallback);
        return reservation.name;
      }
    } catch (error) {
      console.error('Team name reservation failed', error);
    }
  }
  lastSuggestionFallback = true;
  nameReservation = null;
  return fallbackSuggestion();
}

function getLastSuggestionWasFallback(){
  return lastSuggestionFallback;
}

function releaseNameReservation(){
  const reservation = nameReservation;
  nameReservation = null;
  lastSuggestionFallback = false;
  clearActiveSuggestionReservation();
  return releaseSpecificReservation(reservation);
}

async function releaseConfirmedTeamName(name){
  const candidate = typeof name === 'string' ? name.trim() : '';
  if (!candidate) {
    return false;
  }
  const client = typeof TeamNameClient === 'object' && TeamNameClient ? TeamNameClient : null;
  if (!client || typeof client.releaseByName !== 'function') {
    return false;
  }
  try {
    return await client.releaseByName({ eventUid: currentEventUid, name: candidate });
  } catch (error) {
    console.error('Team name release failed', error);
    return false;
  }
}

async function confirmNameReservationIfMatching(name){
  if (!nameReservation) {
    return false;
  }
  const client = typeof TeamNameClient === 'object' && TeamNameClient ? TeamNameClient : null;
  if (!client || typeof client.confirm !== 'function') {
    return false;
  }
  const normalizedInput = (name || '').trim().toLowerCase();
  const reserved = (nameReservation.name || '').toString().trim().toLowerCase();
  if (!normalizedInput || normalizedInput !== reserved) {
    return false;
  }
  try {
    const confirmed = await client.confirm((name || '').trim(), { reservation: nameReservation });
    if (confirmed) {
      nameReservation = null;
      lastSuggestionFallback = false;
      clearActiveSuggestionReservation();
      return true;
    }
  } catch (error) {
    console.error('Team name confirmation failed', error);
  }
  if (typeof client.release === 'function') {
    client.release({ reservation: nameReservation }).catch(() => {});
  }
  nameReservation = null;
  lastSuggestionFallback = false;
  clearActiveSuggestionReservation();
  return false;
}

function formatPuzzleTime(ts){
  const d = new Date(ts * 1000);
  const pad = n => n.toString().padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

window.formatPuzzleTime = formatPuzzleTime;

function insertSoftHyphens(text){
  return text ? text.replace(/\/-/g, '\u00AD') : '';
}

function normalizePoints(value, scorable = true){
  if(!scorable) return 0;
  if(value === undefined || value === null || value === '') return 1;
  const parsed = Number.parseInt(value, 10);
  if(Number.isNaN(parsed)) return 1;
  if(parsed < 0) return 0;
  if(parsed > 10000) return 10000;
  return parsed;
}

function updateTeamNameButton(){
  const btn = document.getElementById('teamNameBtn');
  if(!btn) return;
  const name = getStored(STORAGE_KEYS.PLAYER_NAME);
  btn.textContent = name || btn.getAttribute('data-placeholder') || 'Teamnamen eingeben';
}

async function promptTeamNameChange(existingName){
  const currentName = typeof existingName === 'string' ? existingName : getStored('quizUser') || '';
  const normalizedExisting = typeof currentName === 'string' ? currentName.trim() : '';
  await releaseNameReservation();
  return new Promise(resolve => {
    const modal = document.createElement('div');
    modal.setAttribute('uk-modal', '');
    modal.setAttribute('aria-modal', 'true');
    const dialog = document.createElement('div');
    dialog.className = 'uk-modal-dialog uk-modal-body';
    modal.appendChild(dialog);
    const title = document.createElement('h3');
    title.className = 'uk-modal-title uk-text-center';
    title.textContent = 'Teamname ändern';
    const sugg = document.createElement('span');
    sugg.className = 'uk-text-muted';
    sugg.textContent = ' (Vorschlag wird geladen …)';
    title.appendChild(sugg);
    dialog.appendChild(title);
    const input = document.createElement('input');
    input.id = 'team-name-input';
    input.className = 'uk-input';
    input.type = 'text';
    input.placeholder = 'Teamname wird geladen …';
    input.value = normalizedExisting || currentName || '';
    const applySuggestionBtn = document.createElement('button');
    applySuggestionBtn.id = 'team-name-apply-suggestion';
    applySuggestionBtn.type = 'button';
    applySuggestionBtn.className = 'uk-button uk-button-default uk-width-1-1 uk-margin-top';
    applySuggestionBtn.textContent = 'Vorschlag übernehmen';
    applySuggestionBtn.disabled = true;
    const refreshSuggestionBtn = document.createElement('button');
    refreshSuggestionBtn.id = 'team-name-refresh-suggestion';
    refreshSuggestionBtn.type = 'button';
    refreshSuggestionBtn.className = 'uk-button uk-button-default uk-width-1-1 uk-margin-top';
    refreshSuggestionBtn.textContent = 'Neuen Vorschlag laden';
    refreshSuggestionBtn.disabled = true;
    const btn = document.createElement('button');
    btn.id = 'team-name-submit';
    btn.className = 'uk-button uk-button-primary uk-width-1-1 uk-margin-top';
    btn.textContent = 'Weiter';
    dialog.appendChild(input);
    dialog.appendChild(applySuggestionBtn);
    dialog.appendChild(refreshSuggestionBtn);
    dialog.appendChild(btn);
    let suggestionApplied = false;
    let saved = false;
    let modalClosed = false;
    let modalClosing = false;
    let normalizedSuggestion = '';
    let normalizedSuggestionLower = '';
    let suggestionRequestId = 0;
    applySuggestionBtn.addEventListener('click', () => {
      if(!normalizedSuggestion){
        return;
      }
      input.value = normalizedSuggestion;
      suggestionApplied = true;
      if(typeof input.focus === 'function'){
        input.focus();
      }
      if(typeof input.select === 'function'){
        input.select();
      }
    });
    input.addEventListener('input', () => {
      const candidate = (input.value || '').trim().toLowerCase();
      if(!normalizedSuggestionLower || candidate !== normalizedSuggestionLower){
        suggestionApplied = false;
      }
    });
    async function loadSuggestion(autoApplyForMatchingInput){
      const requestId = ++suggestionRequestId;
      const previousSuggestionValue = normalizedSuggestion;
      const previousInputValue = (input.value || '').trim();
      refreshSuggestionBtn.disabled = true;
      applySuggestionBtn.disabled = true;
      input.placeholder = 'Teamname wird geladen …';
      sugg.textContent = ' (Vorschlag wird geladen …)';
      try{
        await releaseNameReservation();
      }catch(e){
        /* empty */
      }
      let suggestion;
      try{
        suggestion = await getNameSuggestion();
      }catch(e){
        if(modalClosed || modalClosing || requestId !== suggestionRequestId){
          const pendingReservation = nameReservation;
          releaseSpecificReservation(pendingReservation).catch(() => {});
          restoreActiveSuggestionReservation(pendingReservation);
          return;
        }
        handleSuggestionError();
        return;
      }
      const pendingReservation = nameReservation;
      if(modalClosed || modalClosing || requestId !== suggestionRequestId){
        releaseSpecificReservation(pendingReservation).catch(() => {});
        restoreActiveSuggestionReservation(pendingReservation);
        return;
      }
      const normalized = typeof suggestion === 'string' ? suggestion.trim() : '';
      normalizedSuggestion = normalized;
      normalizedSuggestionLower = normalized.toLowerCase();
      rememberActiveSuggestionReservation(pendingReservation);
      if(normalized){
        sugg.textContent = getLastSuggestionWasFallback()
          ? ` (Vorschlag: ${normalized} – Zufallsname)`
          : ` (Vorschlag: ${normalized})`;
      }else{
        sugg.textContent = ' (Kein Vorschlag verfügbar)';
      }
      input.placeholder = normalized || 'Teamname';
      if(autoApplyForMatchingInput && previousSuggestionValue && previousInputValue === previousSuggestionValue && normalized){
        input.value = normalized;
        suggestionApplied = true;
        if(typeof input.focus === 'function'){
          input.focus();
        }
        if(typeof input.select === 'function'){
          input.select();
        }
      }else{
        const currentLower = (input.value || '').trim().toLowerCase();
        if(!normalizedSuggestionLower || currentLower !== normalizedSuggestionLower){
          suggestionApplied = false;
        }
      }
      applySuggestionBtn.disabled = !normalized;
      refreshSuggestionBtn.disabled = false;
    }
    function handleSuggestionError(){
      normalizedSuggestion = '';
      normalizedSuggestionLower = '';
      sugg.textContent = ' (Vorschlag konnte nicht geladen werden)';
      input.placeholder = 'Teamname';
      applySuggestionBtn.disabled = true;
      refreshSuggestionBtn.disabled = false;
      suggestionApplied = false;
    }
    loadSuggestion(false).catch(() => {
      if(modalClosed){
        return;
      }
      handleSuggestionError();
    });
    refreshSuggestionBtn.addEventListener('click', () => {
      loadSuggestion(true).catch(() => {
        if(modalClosed){
          return;
        }
        handleSuggestionError();
      });
    });
    btn.addEventListener('click', async () => {
      const name = (input.value || '').trim();
      if(!name){
        return;
      }
      const usingSuggestion = suggestionApplied && nameReservation && typeof nameReservation.name === 'string'
        && name.toLowerCase() === nameReservation.name.toLowerCase();
      if(usingSuggestion){
        const confirmed = await confirmNameReservationIfMatching(name);
        if(!confirmed){
          if(typeof UIkit !== 'undefined' && UIkit.notification){
            UIkit.notification({
              message: 'Reservierter Teamname konnte nicht bestätigt werden. Bitte erneut versuchen.',
              status: 'danger',
              pos: 'top-center',
              timeout: 3000
            });
          }else{
            alert('Reservierter Teamname konnte nicht bestätigt werden. Bitte erneut versuchen.');
          }
          return;
        }
      }else{
        await releaseNameReservation();
      }
      if (normalizedExisting && name.toLowerCase() !== normalizedExisting.toLowerCase()) {
        await releaseConfirmedTeamName(normalizedExisting);
      }
      setStored('quizUser', name);
      setStored(STORAGE_KEYS.PLAYER_NAME, name);
      let uid = getStored(STORAGE_KEYS.PLAYER_UID);
      if(!uid){
        let cryptoSource = null;
        if(typeof self !== 'undefined' && self && self.crypto){
          cryptoSource = self.crypto;
        }else if(typeof globalThis !== 'undefined' && globalThis && globalThis.crypto){
          cryptoSource = globalThis.crypto;
        }
        if(cryptoSource && typeof cryptoSource.randomUUID === 'function'){
          uid = cryptoSource.randomUUID();
        }else{
          uid = Math.random().toString(36).slice(2);
        }
        setStored(STORAGE_KEYS.PLAYER_UID, uid);
      }
      fetch('/api/players', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          event_uid: currentEventUid,
          player_name: name,
          player_uid: uid
        })
      }).catch(() => {});
      try{
        await postSession('player', { name });
        saved = true;
        ui.hide();
      }catch(e){
        if(typeof UIkit !== 'undefined' && UIkit.notification){
          UIkit.notification({
            message: 'Teamname konnte nicht gespeichert werden. Bitte erneut versuchen.',
            status: 'danger',
            pos: 'top-center',
            timeout: 3000
          });
        }else{
          alert('Teamname konnte nicht gespeichert werden. Bitte erneut versuchen.');
        }
      }
    });
    input.addEventListener('keydown', ev => {
      if(ev.key === 'Enter'){
        ev.preventDefault();
        btn.click();
      }
    });
    document.body.appendChild(modal);
    const ui = UIkit.modal(modal, { bgClose: false, escClose: false });
    UIkit.util.on(modal, 'shown', () => {
      modalClosing = false;
      modalClosed = false;
      if(typeof input.focus === 'function') input.focus();
      if(typeof input.select === 'function') input.select();
    });
    UIkit.util.on(modal, 'hide', () => {
      modalClosing = true;
    });
    UIkit.util.on(modal, 'hidden', () => {
      modalClosed = true;
      if(!saved){
        releaseNameReservation();
      }
      modal.remove();
      updateTeamNameButton();
      resolve();
    });
    ui.show();
  });
}

async function promptTeamName(){
  const cfg = window.quizConfig || {};
  const competitionMode = Boolean(cfg.competitionMode);
  const existing = getStored('quizUser');
  if(existing){
    await releaseNameReservation();
    return new Promise(resolve => {
      const modal = document.createElement('div');
      modal.setAttribute('uk-modal', '');
      modal.setAttribute('aria-modal', 'true');
      const dialog = document.createElement('div');
      dialog.className = 'uk-modal-dialog uk-modal-body';
      modal.appendChild(dialog);
      const title = document.createElement('h3');
      title.className = 'uk-modal-title uk-text-center';
      const actionLabel = competitionMode ? 'den Namen ändern' : 'den Teamnamen zurücksetzen';
      title.textContent = `Ah - dich kenne ich. Du bist ${existing}. Willkommen zurück. Möchtest du fortfahren oder ${actionLabel}?`;
      dialog.appendChild(title);
      const btn = document.createElement('button');
      btn.id = 'team-name-submit';
      btn.className = 'uk-button uk-button-primary uk-width-1-1 uk-margin-top';
      btn.textContent = 'Weiter';
      const resetBtn = document.createElement('button');
      resetBtn.id = 'team-name-reset';
      resetBtn.className = 'uk-button uk-button-danger uk-width-1-1 uk-margin-top';
      resetBtn.textContent = competitionMode ? 'Name ändern' : 'Teamnamen zurücksetzen';
      dialog.appendChild(btn);
      dialog.appendChild(resetBtn);
      document.body.appendChild(modal);
      const ui = UIkit.modal(modal, { bgClose: false, escClose: false });
      let reopened = false;
      let reopenAction = null;
      UIkit.util.on(modal, 'hidden', () => {
        modal.remove();
        updateTeamNameButton();
        if(reopened && typeof reopenAction === 'function'){
          Promise.resolve(reopenAction()).then(resolve);
        }else if(!reopened){
          resolve();
        }
      });
      btn.addEventListener('click', () => { ui.hide(); });
      resetBtn.addEventListener('click', async () => {
        reopened = true;
        if(competitionMode){
          reopenAction = () => promptTeamNameChange(existing);
          ui.hide();
          return;
        }
        await releaseConfirmedTeamName(existing);
        clearStored('quizUser');
        clearStored(STORAGE_KEYS.PLAYER_UID);
        try{
          await postSession('player', { name: null });
        }catch(e){ /* empty */ }
        await releaseNameReservation();
        reopenAction = () => promptTeamName();
        ui.hide();
      });
      ui.show();
    });
  }

  await releaseNameReservation();
  return new Promise(resolve => {
    const modal = document.createElement('div');
    modal.setAttribute('uk-modal', '');
    modal.setAttribute('aria-modal', 'true');
    const dialog = document.createElement('div');
    dialog.className = 'uk-modal-dialog uk-modal-body';
    modal.appendChild(dialog);
    const title = document.createElement('h3');
    title.className = 'uk-modal-title uk-text-center';
    title.textContent = 'Teamname eingeben';
    const sugg = document.createElement('span');
    sugg.className = 'uk-text-muted';
    sugg.textContent = ' (Vorschlag wird geladen …)';
    title.appendChild(sugg);
    dialog.appendChild(title);
    const input = document.createElement('input');
    input.id = 'team-name-input';
    input.className = 'uk-input';
    input.type = 'text';
    input.placeholder = 'Teamname wird geladen …';
    input.value = '';
    let modalClosed = false;
    let userInteracted = false;
    const btn = document.createElement('button');
    btn.id = 'team-name-submit';
    btn.className = 'uk-button uk-button-primary uk-width-1-1 uk-margin-top';
    btn.textContent = 'Weiter';
    dialog.appendChild(input);
    dialog.appendChild(btn);
    let saved = false;
    input.addEventListener('input', () => {
      userInteracted = true;
    });
    const suggestionPromise = getNameSuggestion();
    suggestionPromise.then(suggestion => {
      if(modalClosed){
        return;
      }
      const normalized = typeof suggestion === 'string' ? suggestion.trim() : '';
      if(normalized){
        sugg.textContent = getLastSuggestionWasFallback()
          ? ` (Vorschlag: ${normalized} – Zufallsname)`
          : ` (Vorschlag: ${normalized})`;
      }else{
        sugg.textContent = ' (Kein Vorschlag verfügbar)';
      }
      input.placeholder = normalized || 'Teamname';
      if(!userInteracted){
        input.value = normalized || '';
      }
    }).catch(() => {
      if(modalClosed){
        return;
      }
      sugg.textContent = ' (Vorschlag konnte nicht geladen werden)';
      input.placeholder = 'Teamname';
      if(!userInteracted){
        input.value = '';
      }
    });
    btn.addEventListener('click', async () => {
      const name = (input.value || '').trim();
      if(!name){
        return;
      }
      const usingSuggestion = nameReservation && typeof nameReservation.name === 'string'
        && name.toLowerCase() === nameReservation.name.toLowerCase();
      if(usingSuggestion){
        const confirmed = await confirmNameReservationIfMatching(name);
        if(!confirmed){
          if(typeof UIkit !== 'undefined' && UIkit.notification){
            UIkit.notification({
              message: 'Reservierter Teamname konnte nicht bestätigt werden. Bitte erneut versuchen.',
              status: 'danger',
              pos: 'top-center',
              timeout: 3000
            });
          }else{
            alert('Reservierter Teamname konnte nicht bestätigt werden. Bitte erneut versuchen.');
          }
          return;
        }
      }else{
        await releaseNameReservation();
      }
      setStored('quizUser', name);
      setStored(STORAGE_KEYS.PLAYER_NAME, name);
      let uid = getStored(STORAGE_KEYS.PLAYER_UID);
      if(!uid){
        let cryptoSource = null;
        if(typeof self !== 'undefined' && self && self.crypto){
          cryptoSource = self.crypto;
        }else if(typeof globalThis !== 'undefined' && globalThis && globalThis.crypto){
          cryptoSource = globalThis.crypto;
        }
        if(cryptoSource && typeof cryptoSource.randomUUID === 'function'){
          uid = cryptoSource.randomUUID();
        }else{
          uid = Math.random().toString(36).slice(2);
        }
        setStored(STORAGE_KEYS.PLAYER_UID, uid);
      }
      fetch('/api/players', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          event_uid: currentEventUid,
          player_name: name,
          player_uid: uid
        })
      }).catch(() => {});
      try {
        await postSession('player', { name });
        saved = true;
        ui.hide();
      } catch (e) {
        if(typeof UIkit !== 'undefined' && UIkit.notification){
          UIkit.notification({
            message: 'Teamname konnte nicht gespeichert werden. Bitte erneut versuchen.',
            status: 'danger',
            pos: 'top-center',
            timeout: 3000
          });
        } else {
          alert('Teamname konnte nicht gespeichert werden. Bitte erneut versuchen.');
        }
      }
    });
    input.addEventListener('keydown', ev => {
      if(ev.key === 'Enter'){
        ev.preventDefault();
        btn.click();
      }
    });
    document.body.appendChild(modal);
    const ui = UIkit.modal(modal, { bgClose: false, escClose: false });
    UIkit.util.on(modal, 'shown', () => { input.focus(); });
    UIkit.util.on(modal, 'hidden', () => {
      modalClosed = true;
      modal.remove();
      if(!saved){
        releaseNameReservation();
      }
      updateTeamNameButton();
      resolve();
    });
    ui.show();
  });
}

async function runQuiz(questions, skipIntro){
  quizStartedAt = Math.floor(Date.now() / 1000);
  // Konfiguration laden und einstellen, ob der "Antwort prüfen"-Button
  // eingeblendet werden soll
  const cfg = window.quizConfig || {};
  const resolvedColors = typeof cfg.colors === 'object' && cfg.colors !== null
    ? { ...cfg.colors }
    : {};
  if (!resolvedColors.primary && typeof cfg.backgroundColor === 'string' && cfg.backgroundColor.trim() !== '') {
    resolvedColors.primary = cfg.backgroundColor.trim();
  }
  if (!resolvedColors.accent && typeof cfg.buttonColor === 'string' && cfg.buttonColor.trim() !== '') {
    resolvedColors.accent = cfg.buttonColor.trim();
  }
  if (Object.keys(resolvedColors).length > 0) {
    cfg.colors = resolvedColors;
  }
  const showCheck = cfg.CheckAnswerButton !== 'no';
  const SCORE_ALPHA = 1.0;
  const SCORE_FLOOR = 0.0;
  if(cfg.colors){
    if(cfg.colors.primary){
      document.documentElement.style.setProperty('--primary-color', cfg.colors.primary);
      document.documentElement.style.setProperty('--color-bg', cfg.colors.primary);
    }
    if(cfg.colors.accent){
      document.documentElement.style.setProperty('--accent-color', cfg.colors.accent);
    }
  }

  const countdownFeatureEnabled = parseCountdownFlag(cfg.countdownEnabled);
  const defaultCountdownRaw = normalizeCountdownValue(cfg.countdown);
  const defaultCountdownSeconds = defaultCountdownRaw !== null && defaultCountdownRaw > 0
    ? defaultCountdownRaw
    : null;

  const countdownState = {
    intervalId: null,
    autoAdvanceId: null,
    container: null,
    valueEl: null,
    secondsRemaining: 0,
    totalSeconds: 0,
    questionEl: null,
    scoreIndex: null,
    expired: false,
    previewEl: null,
    basePoints: null,
  };

  function parseCountdownFlag(value){
    if(typeof value === 'boolean') return value;
    if(typeof value === 'number') return value > 0;
    if(typeof value === 'string'){
      const normalized = value.trim().toLowerCase();
      if(normalized === '') return false;
      return ['1','true','yes','on'].includes(normalized);
    }
    return false;
  }

  function normalizeCountdownValue(value){
    if(value === null || value === undefined) return null;
    if(typeof value === 'string'){
      const trimmed = value.trim();
      if(trimmed === '') return null;
      value = trimmed;
    }
    const parsed = Number.parseInt(value, 10);
    if(Number.isNaN(parsed) || parsed < 0) return null;
    return parsed;
  }

  function stopCountdown(){
    if(countdownState.intervalId !== null){
      clearInterval(countdownState.intervalId);
      countdownState.intervalId = null;
    }
    if(countdownState.autoAdvanceId !== null){
      clearTimeout(countdownState.autoAdvanceId);
      countdownState.autoAdvanceId = null;
    }
    countdownState.container = null;
    countdownState.valueEl = null;
    countdownState.secondsRemaining = 0;
    countdownState.totalSeconds = 0;
    countdownState.questionEl = null;
    countdownState.scoreIndex = null;
    countdownState.expired = false;
    countdownState.previewEl = null;
    countdownState.basePoints = null;
  }

  function computePreviewPoints(basePoints, totalSeconds, remainingSeconds){
    if(!Number.isFinite(basePoints) || basePoints <= 0){
      return 0;
    }
    if(!Number.isFinite(totalSeconds) || totalSeconds <= 0){
      return Math.round(basePoints);
    }
    const safeRemaining = Math.max(0, Math.min(remainingSeconds ?? 0, totalSeconds));
    const ratio = totalSeconds > 0 ? safeRemaining / totalSeconds : 1;
    const multiplier = Math.max(Math.pow(ratio, SCORE_ALPHA), SCORE_FLOOR);
    return Math.round(basePoints * multiplier);
  }

  function updateTimerPreview(basePoints, totalSeconds, remainingSeconds){
    if(!countdownState.previewEl){
      return;
    }
    const previewPoints = computePreviewPoints(basePoints ?? 0, totalSeconds ?? 0, remainingSeconds ?? 0);
    countdownState.previewEl.textContent = `Maximal noch: ${previewPoints}`;
  }

  function getTimingSnapshot(questionEl){
    if(!questionEl) return null;
    const timerEl = questionEl.querySelector('.question-timer');
    if(!timerEl) return null;
    const totalRaw = timerEl.dataset.initialSeconds;
    const total = totalRaw ? parseInt(totalRaw, 10) : NaN;
    if(Number.isNaN(total) || total <= 0){
      return { totalTimeSec: Number.isNaN(total) ? null : total, timeLeftSec: null };
    }
    const remainingRaw = timerEl.dataset.remainingSeconds;
    let remaining = remainingRaw ? parseInt(remainingRaw, 10) : NaN;
    if(Number.isNaN(remaining)){
      remaining = 0;
    }
    remaining = Math.max(0, Math.min(remaining, total));
    return { totalTimeSec: total, timeLeftSec: remaining };
  }

  function updateAnswerEntry(idx, extraData = {}, questionEl = null){
    if(idx === null || idx === undefined){
      return;
    }
    const base = (answers[idx] && typeof answers[idx] === 'object') ? answers[idx] : {};
    if(extraData && typeof extraData === 'object'){
      Object.assign(base, extraData);
    }
    const snapshot = getTimingSnapshot(questionEl || countdownState.questionEl || null);
    if(snapshot){
      base.timeLeftSec = snapshot.timeLeftSec;
    } else if(!('timeLeftSec' in base)){
      base.timeLeftSec = null;
    }
    base.isCorrect = !!results[idx];
    answers[idx] = base;
  }

  function getQuestionCountdownSeconds(question){
    if(!countdownFeatureEnabled) return null;
    const qValue = normalizeCountdownValue(question?.countdown);
    if(qValue !== null){
      return qValue > 0 ? qValue : null;
    }
    return defaultCountdownSeconds;
  }

  function startCountdown(questionEl, questionData, scoreIndex){
    if(!countdownFeatureEnabled) return;
    const seconds = getQuestionCountdownSeconds(questionData);
    if(seconds === null || seconds <= 0){
      delete questionEl.dataset.timedOut;
      return;
    }
    stopCountdown();
    let timerEl = questionEl.querySelector('.question-timer');
    if(!timerEl){
      timerEl = document.createElement('div');
      timerEl.className = 'question-timer';
      timerEl.setAttribute('role', 'timer');
      const labelEl = document.createElement('span');
      labelEl.className = 'question-timer__label';
      labelEl.textContent = 'Zeitlimit:';
      const valueEl = document.createElement('span');
      valueEl.className = 'question-timer__value';
      timerEl.appendChild(labelEl);
      timerEl.appendChild(valueEl);
      const previewEl = document.createElement('span');
      previewEl.className = 'question-timer__preview';
      timerEl.appendChild(previewEl);
      questionEl.insertBefore(timerEl, questionEl.firstChild);
    }
    const valueEl = timerEl.querySelector('.question-timer__value');
    if(!valueEl){
      return;
    }
    let previewEl = timerEl.querySelector('.question-timer__preview');
    if(!previewEl){
      previewEl = document.createElement('span');
      previewEl.className = 'question-timer__preview';
      timerEl.appendChild(previewEl);
    }
    timerEl.classList.remove('question-timer--expired');
    timerEl.dataset.initialSeconds = String(seconds);
    timerEl.dataset.remainingSeconds = String(seconds);
    valueEl.textContent = `${seconds}s`;
    questionEl.classList.remove('question--timeout');
    questionEl.dataset.timedOut = '0';
    const feedbackEl = questionEl.querySelector('[data-role="feedback"]');
    if(feedbackEl){
      feedbackEl.classList.remove('question-feedback--timeout');
    }
    countdownState.container = timerEl;
    countdownState.valueEl = valueEl;
    countdownState.secondsRemaining = seconds;
    countdownState.totalSeconds = seconds;
    countdownState.questionEl = questionEl;
    countdownState.scoreIndex = scoreIndex;
    countdownState.expired = false;
    countdownState.previewEl = previewEl;
    countdownState.basePoints = scoreIndex !== null ? (questionPoints[scoreIndex] ?? 0) : null;
    updateTimerPreview(countdownState.basePoints, countdownState.totalSeconds, countdownState.secondsRemaining);
    countdownState.intervalId = window.setInterval(() => {
      if(!countdownState.questionEl || countdownState.questionEl !== questionEl){
        stopCountdown();
        return;
      }
      countdownState.secondsRemaining -= 1;
      if(countdownState.secondsRemaining <= 0){
        handleCountdownExpired(questionEl, scoreIndex);
      } else if(countdownState.valueEl){
        countdownState.valueEl.textContent = `${countdownState.secondsRemaining}s`;
      }
      if(countdownState.container){
        countdownState.container.dataset.remainingSeconds = String(Math.max(countdownState.secondsRemaining, 0));
      }
      updateTimerPreview(countdownState.basePoints, countdownState.totalSeconds, Math.max(countdownState.secondsRemaining, 0));
    }, 1000);
  }

  function handleCountdownExpired(questionEl, scoreIndex){
    if(countdownState.questionEl !== questionEl) return;
    if(countdownState.expired) return;
    countdownState.expired = true;
    if(countdownState.intervalId !== null){
      clearInterval(countdownState.intervalId);
      countdownState.intervalId = null;
    }
    if(countdownState.autoAdvanceId !== null){
      clearTimeout(countdownState.autoAdvanceId);
      countdownState.autoAdvanceId = null;
    }
    countdownState.secondsRemaining = 0;
    if(countdownState.valueEl){
      countdownState.valueEl.textContent = '0s';
    }
    if(countdownState.container){
      countdownState.container.classList.add('question-timer--expired');
      countdownState.container.dataset.remainingSeconds = '0';
    }
    updateTimerPreview(countdownState.basePoints, countdownState.totalSeconds, 0);
    if(scoreIndex !== null && results[scoreIndex] === true){
      countdownState.autoAdvanceId = window.setTimeout(() => {
        if(elements[current] === questionEl && current < totalQuestions + 1){
          next();
        }
      }, 800);
      return;
    }
    questionEl.dataset.timedOut = '1';
    questionEl.classList.add('question--timeout');
    const feedbackEl = questionEl.querySelector('[data-role="feedback"]');
    if(feedbackEl){
      feedbackEl.classList.remove('uk-text-success', 'uk-text-danger', 'question-feedback--timeout');
      feedbackEl.textContent = '⏱️ Zeit abgelaufen! Die Frage zählt nicht.';
      feedbackEl.classList.add('question-feedback--timeout');
    }
    if(scoreIndex !== null){
      if(results[scoreIndex] !== true){
        results[scoreIndex] = false;
        earnedPoints[scoreIndex] = 0;
      }
      updateAnswerEntry(scoreIndex, {}, questionEl);
    }
    countdownState.autoAdvanceId = window.setTimeout(() => {
      if(elements[current] === questionEl && current < totalQuestions + 1){
        next();
      }
    }, 1500);
  }

  const container = document.getElementById('quiz');
  const progress = document.getElementById('progress');
  const announcer = document.getElementById('question-announcer');
  // Vorhandene Inhalte entfernen (z.B. Katalogauswahl)
  if (container) container.textContent = '';


  // Hilfsfunktion zum Mischen von Arrays (Fisher-Yates)
  function shuffleArray(arr){
    const a = arr.slice();
    for(let i = a.length - 1; i > 0; i--){
      const j = Math.floor(Math.random() * (i + 1));
      [a[i], a[j]] = [a[j], a[i]];
    }
    return a;
  }

  // Fragen mischen, damit die Reihenfolge bei jedem Aufruf variiert
  const originalIndexMap = typeof WeakMap === 'function' ? new WeakMap() : new Map();
  if (Array.isArray(questions)) {
    let baseIndex = 0;
    questions.forEach(question => {
      if (question && typeof question === 'object' && question.type !== 'flip') {
        baseIndex += 1;
        try {
          originalIndexMap.set(question, baseIndex);
        } catch (e) { /* empty */ }
      }
    });
  }

  const shuffled = cfg.shuffleQuestions !== false ? shuffleArray(questions) : questions.slice();
  const scorableCounts = []; // Anzahl bewertbarer Fragen vor jeder Frage
  const questionPoints = [];
  const scorableIndexMap = [];
  let scorableIdx = 0;
  const questionElements = shuffled.map(q => {
    scorableCounts.push(scorableIdx);
    const isScorable = q.type !== 'flip';
    const idx = isScorable ? scorableIdx++ : null;
    if(idx !== null){
      questionPoints[idx] = normalizePoints(q.points, true);
      const mappedIndex = originalIndexMap.get(q);
      scorableIndexMap[idx] = Number.isFinite(mappedIndex) ? mappedIndex : (idx + 1);
    }
    return createQuestion(q, idx);
  });
  const questionCount = scorableIdx; // nur bewertbare Fragen
  const totalQuestions = questionElements.length; // alle Fragen inkl. flip

  let current = skipIntro ? 1 : 0;
  // Zu jedem Eintrag im Array ein DOM-Element erzeugen
  const elements = [createStart(), ...questionElements];
  // Speichert true/false für jede beantwortete (wertbare) Frage
  const results = new Array(questionCount).fill(false);
  const earnedPoints = new Array(questionCount).fill(0);
  const answers = new Array(questionCount).fill(null);
  const summaryEl = createSummary(); // Abschlussseite
  elements.push(summaryEl);
  let summaryShown = false;

  [STORAGE_KEYS.PLAYER_NAME, STORAGE_KEYS.CATALOG].forEach(k => {
    const v = getStored(k);
    if(v != null) setStored(k, v);
  });

  if(!getStored('quizUser') && !cfg.QRRestrict && !cfg.QRUser){
    if(cfg.randomNames){
      await promptTeamName();
    }
  }

  // Farben werden über CSS-Variablen gesetzt

  const headerEl = document.getElementById('quiz-header');
  if(headerEl){
    const name = getStored(STORAGE_KEYS.CATALOG_NAME) || '';
    const desc = getStored(STORAGE_KEYS.CATALOG_DESC) || '';
    const comment = getStored(STORAGE_KEYS.CATALOG_COMMENT);
    headerEl.innerHTML = '';
    if(skipIntro){
      headerEl.classList.add('uk-hidden');
    }else{
      const logoPath = cfg.logoPath;
      const logo = document.createElement('img');
      logo.id = 'quiz-logo';
      logo.className = 'logo-placeholder';
      if (logoPath) {
        logo.src = withBase(logoPath);
      } else {
        logo.src = withBase('/logo-160.svg');
        logo.srcset = withBase('/logo-160.svg') + ' 160w, ' + withBase('/logo-320.svg') + ' 320w';
        logo.sizes = '(max-width: 600px) 160px, 320px';
      }
      logo.alt = 'Logo';
      logo.width = 160;
      logo.height = 240;
      logo.loading = 'lazy';
      headerEl.appendChild(logo);
      if(name){
        const h1 = document.createElement('h1');
        h1.textContent = name;
        headerEl.appendChild(h1);
      }
      if(desc){
        const sub = document.createElement('p');
        sub.dataset.role = 'subheader';
        sub.textContent = desc;
        headerEl.appendChild(sub);
      }
      if(comment){
        const cBlock = document.createElement('div');
        cBlock.dataset.role = 'catalog-comment-block';
        cBlock.innerHTML = comment;
        headerEl.appendChild(cBlock);
      }
      headerEl.classList.remove('uk-hidden');
    }
  }

  elements.forEach((el, i) => {
    if (i !== current) el.classList.add('uk-hidden');
    container.appendChild(el);
    if (typeof UIkit !== 'undefined' && UIkit.scrollspy) {
      UIkit.scrollspy(el);
    }
  });
  progress.max = questionCount;
  showQuestion(current);


  // Zeigt das Element mit dem angegebenen Index an und aktualisiert den Fortschrittsbalken
  function showQuestion(i){
    stopCountdown();
    elements.forEach((el, idx) => el.classList.toggle('uk-hidden', idx !== i));
    if(i === 0){
      progress.classList.add('uk-hidden');
      progress.setAttribute('aria-valuenow', 0);
      if (announcer) announcer.textContent = '';
    } else if(i <= totalQuestions && i > 0){
      const scorableBefore = scorableCounts[i - 1];
      const isScorable = shuffled[i - 1].type !== 'flip';
      const displayNum = isScorable ? scorableBefore + 1 : scorableBefore;
      if(displayNum === 0){
        progress.classList.add('uk-hidden');
        progress.setAttribute('aria-valuenow', 0);
        if (announcer) announcer.textContent = '';
      } else {
        progress.classList.remove('uk-hidden');
        progress.value = displayNum;
        progress.setAttribute('aria-valuenow', displayNum);
        if (announcer) announcer.textContent = `Frage ${displayNum} von ${questionCount}`;
      }
    } else if(i === totalQuestions + 1){
      // Nach der letzten Frage Zusammenfassung anzeigen
      progress.value = questionCount;
      progress.setAttribute('aria-valuenow', questionCount);
      if (announcer) announcer.textContent = `Frage ${questionCount} von ${questionCount}`;
      progress.classList.add('uk-hidden');
      updateSummary();
    }
    if(i > 0 && i <= totalQuestions){
      const questionEl = elements[i];
      const questionData = shuffled[i - 1];
      const scoreIndex = questionData.type !== 'flip' ? scorableCounts[i - 1] : null;
      startCountdown(questionEl, questionData, scoreIndex);
    }
  }

  // Blendet die nächste Frage ein
  function next(){
    if(current === 0 && headerEl){
      headerEl.textContent = '';
      headerEl.classList.add('uk-hidden');
    }
    if(current < totalQuestions + 1){
      current++;
      showQuestion(current);
    }
  }

  // Wendet die konfigurierte Buttonfarbe an
  function styleButton(btn){
    if(cfg.colors && cfg.colors.accent){
      btn.style.backgroundColor = cfg.colors.accent;
      btn.style.borderColor = cfg.colors.accent;
      btn.style.color = '#fff';
    }
  }

  // Ermittelt das Ergebnis und schreibt es in localStorage
  async function updateSummary(){
    if(summaryShown) return;
    summaryShown = true;
    const score = results.filter(r => r).length;
    const pointsEarned = earnedPoints.reduce((sum, val) => sum + (Number.isFinite(val) ? val : 0), 0);
    const maxPointsTotal = questionPoints.reduce((sum, val) => sum + (Number.isFinite(val) ? val : 0), 0);
    let user = getStored(STORAGE_KEYS.PLAYER_NAME);
    if(!user && !cfg.QRRestrict && !cfg.QRUser){
      if(cfg.randomNames){
        await promptTeamName();
        user = getStored(STORAGE_KEYS.PLAYER_NAME);
      }
    }
    const p = summaryEl.querySelector('p');
    if(p){
      const maxText = maxPointsTotal > 0 ? `${pointsEarned} von ${maxPointsTotal} Punkten` : `${score} von ${questionCount} richtigen Antworten`;
      const extra = maxPointsTotal > 0 ? ` (${score} richtige Antworten)` : '';
      p.textContent = `${user} hat ${maxText} erreicht${extra}.`;
    }
    const heading = summaryEl.querySelector('h3');
    if(heading) heading.textContent = `🎉 Danke für die Teilnahme ${user}!`;
    const letter = cfg.puzzleWordEnabled ? getStored(STORAGE_KEYS.LETTER) : null;
    const letterEl = summaryEl.querySelector('#quiz-letter');
    if(letterEl){
      if(letter){
        letterEl.textContent = letter;
        letterEl.style.display = 'block';
      }else{
        letterEl.style.display = 'none';
      }
    }
    if(score === questionCount && typeof window.startConfetti === 'function'){
      window.startConfetti();
    }
    const rawCatalog = getStored(STORAGE_KEYS.CATALOG) || 'unknown';
    const normalizedCatalog = String(rawCatalog).toLowerCase();
    const wrong = [];
    results.forEach((value, idx) => {
      if (value) {
        return;
      }
      const mapped = scorableIndexMap[idx];
      const normalized = Number.isFinite(mapped) ? mapped : (idx + 1);
      wrong.push(normalized);
    });
    const normalizedAnswers = new Array(questionCount).fill(null);
    for (let i = 0; i < questionCount; i += 1) {
      const mapped = scorableIndexMap[i];
      const targetIndex = Number.isFinite(mapped) ? mapped - 1 : i;
      if (targetIndex >= 0 && targetIndex < questionCount) {
        normalizedAnswers[targetIndex] = answers[i] ?? null;
      }
    }
    const data = {
      name: user,
      catalog: rawCatalog,
      correct: score,
      total: questionCount,
      points: pointsEarned,
      maxPoints: maxPointsTotal,
      wrong,
      answers: normalizedAnswers,
      event_uid: currentEventUid
    };
    if(Number.isFinite(quizStartedAt)){
      data.startedAt = quizStartedAt;
    }
    if(cfg.collectPlayerUid){
      const uid = getStored(STORAGE_KEYS.PLAYER_UID);
      if(uid) data.player_uid = uid;
    }
    const puzzleSolved = getStored(STORAGE_KEYS.PUZZLE_SOLVED) === 'true';
    const puzzleTs = getStored(STORAGE_KEYS.PUZZLE_TIME);
    if(puzzleSolved && puzzleTs){
      data.puzzleTime = parseInt(puzzleTs, 10) || Math.floor(Date.now()/1000);
    }
    fetch(withBase('/results'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    }).catch(()=>{});
    const solved = JSON.parse(getStored(STORAGE_KEYS.QUIZ_SOLVED) || '[]')
      .map(s => String(s).toLowerCase());
    if(solved.indexOf(normalizedCatalog) === -1){
      solved.push(normalizedCatalog);
      setStored(STORAGE_KEYS.QUIZ_SOLVED, JSON.stringify(solved));
    }

    if(cfg.teamResults){
      let total = null;
      const dataEl = document.getElementById('catalogs-data');
      if(dataEl){
        try{
          const list = JSON.parse(dataEl.textContent);
          total = Array.isArray(list) ? list.length : null;
        }catch(e){
          total = null;
        }
      }
      if(total !== null && solved.length === total){
        const link = document.createElement('a');
        const summaryPath = '/summary' + (currentEventUid ? `?event_uid=${encodeURIComponent(currentEventUid)}` : '');
        link.href = withBase(summaryPath);
        link.className = 'uk-button uk-button-primary uk-margin-top';
        link.textContent = 'Ergebnisübersicht';
        styleButton(link);
        summaryEl.appendChild(link);
      }
    }

    if(cfg.puzzleWordEnabled){
      const attemptKey = 'puzzleAttempt-' + normalizedCatalog;
      const puzzleSolved = getStored(STORAGE_KEYS.PUZZLE_SOLVED) === 'true';
      const puzzleInfo = summaryEl.querySelector('#puzzle-info');
      if(puzzleSolved && puzzleInfo){
        const ts = parseInt(getStored(STORAGE_KEYS.PUZZLE_TIME) || '0', 10);
        if(ts){
          puzzleInfo.textContent = `Rätselwort gelöst: ${formatPuzzleTime(ts)}`;
        }
      }
      if(!puzzleSolved && !getStored(attemptKey)){
        const puzzleBtn = document.createElement('button');
        puzzleBtn.className = 'uk-button uk-button-primary uk-margin-top';
        puzzleBtn.textContent = 'Rätselwort überprüfen';
        styleButton(puzzleBtn);
        puzzleBtn.addEventListener('click', () => showPuzzleCheck(puzzleBtn, attemptKey));
        const endBtn = summaryEl.querySelector('#end-station-btn');
        if(endBtn){
          summaryEl.insertBefore(puzzleBtn, endBtn);
        } else {
          summaryEl.appendChild(puzzleBtn);
        }
      }
    }

    const remainingEl = summaryEl.querySelector('#quiz-remaining');
    if(remainingEl){
      try{
        const dataEl = document.getElementById('catalogs-data');
        const catalogs = dataEl ? JSON.parse(dataEl.textContent) : [];
        const solvedSet = new Set(
          JSON.parse(getStored(STORAGE_KEYS.QUIZ_SOLVED) || '[]')
            .map(s => String(s).toLowerCase())
        );
        const names = catalogs.filter(c => {
          const id = (c.slug || c.uid || c.sort_order).toString().toLowerCase();
          return !solvedSet.has(id);
        }).map(c => c.name || c.slug || c.sort_order);
        if(names.length){
          remainingEl.textContent = 'Auf zur nächsten Station. Es fehlen noch: ' + names.join(', ');
        } else {
          remainingEl.textContent = '';
        }
      }catch(e){
        remainingEl.textContent = '';
      }
    }

    if(cfg.photoUpload !== false){
      const photoBtn = document.createElement('button');
      photoBtn.className = 'uk-button uk-button-primary uk-margin-top';
      photoBtn.textContent = 'Beweisfoto einreichen';
      styleButton(photoBtn);
      photoBtn.addEventListener('click', () => {
        const name = getStored(STORAGE_KEYS.PLAYER_NAME) || '';
        const catalogName = getStored(STORAGE_KEYS.CATALOG) || 'unknown';
        showPhotoModal(name, catalogName);
      });
      summaryEl.appendChild(photoBtn);
    }
  }

  // Wählt basierend auf dem Fragetyp die passende Erzeugerfunktion aus
  function createQuestion(q, idx){
    if(q.type === 'sort') return createSortQuestion(q, idx);
    if(q.type === 'assign') return createAssignQuestion(q, idx);
    if(q.type === 'mc') return createMcQuestion(q, idx);
    if(q.type === 'swipe') return createSwipeQuestion(q, idx);
    if(q.type === 'photoText') return createPhotoTextQuestion(q, idx);
    if(q.type === 'flip') return createFlipQuestion(q, idx);
    return document.createElement('div');
  }

  // Erstellt das DOM für eine Sortierfrage
  function createSortQuestion(q, idx){
    const div = document.createElement('div');
    div.className = 'question';
    div.setAttribute('uk-scrollspy', 'cls: uk-animation-slide-bottom-small; target: > *; delay: 100');
    const h = document.createElement('h4');
    h.textContent = insertSoftHyphens(q.prompt);
    div.appendChild(h);
    const instr = document.createElement('p');
    instr.id = 'sort-desc-' + idx;
    instr.className = 'uk-hidden-visually';
    instr.textContent = 'Mit Pfeil nach oben oder unten verschiebst du den aktuellen Eintrag.'; // oder die andere Formulierung
    div.appendChild(instr);
    const ul = document.createElement('ul');
    ul.className = 'uk-list uk-list-divider sortable-list uk-margin';
    ul.setAttribute('aria-dropeffect', 'move');
    ul.setAttribute('aria-label', 'Sortierbare Liste');
    ul.setAttribute('aria-describedby', instr.id);
    const displayItems = shuffleArray(q.items);
    displayItems.forEach(text => {
      const li = document.createElement('li');
      li.draggable = true;
      li.setAttribute('role','listitem');
      li.tabIndex = 0;
      li.setAttribute('aria-grabbed','false');
      li.textContent = insertSoftHyphens(text);
      ul.appendChild(li);
    });
    div.appendChild(ul);
    const feedback = document.createElement('div');
    feedback.className = 'uk-margin-top question-feedback';
    feedback.dataset.role = 'feedback';
    feedback.setAttribute('role', 'alert');
    feedback.setAttribute('aria-live', 'polite');
    const footer = document.createElement('div');
    footer.className = 'uk-margin-top uk-flex uk-flex-between';
    const btn = document.createElement('button');
    btn.className = 'uk-button uk-button-primary';
    btn.textContent = 'Antwort prüfen';
    styleButton(btn);
    btn.addEventListener('click', () => checkSort(ul, q.items, feedback, idx));
    if(!showCheck) btn.classList.add('uk-hidden');
    const nextBtn = document.createElement('button');
    nextBtn.className = 'uk-button';
    nextBtn.textContent = 'Weiter';
    styleButton(nextBtn);
    nextBtn.addEventListener('click', () => {
      checkSort(ul, q.items, feedback, idx);
      next();
    });
    footer.appendChild(btn);
    footer.appendChild(nextBtn);
    div.appendChild(feedback);
    div.appendChild(footer);
    // Drag-&-Drop-Handler aktivieren
    setupSortHandlers(ul);
    return div;
  }

  // Drag-&-Drop sowie Tastaturnavigation für Sortierlisten
  function setupSortHandlers(ul){
    if (typeof Sortable !== 'undefined') {
      Sortable.create(ul, { animation: 150 });
    }
    ul.querySelectorAll('li').forEach(li => {
      li.addEventListener('keydown', e => {
        if(e.key === 'ArrowUp' && li.previousElementSibling){
          li.parentNode.insertBefore(li, li.previousElementSibling);
          li.focus();
        }else if(e.key === 'ArrowDown' && li.nextElementSibling){
          li.parentNode.insertBefore(li.nextElementSibling, li);
          li.focus();
        }
      });
    });
  }

  function renderFeedback(container, isCorrect, message){
    container.classList.remove('question-feedback--timeout');
    container.textContent = '';
    const div = document.createElement('div');
    div.setAttribute('uk-alert','');
    div.className = isCorrect ? 'uk-alert-success' : 'uk-alert-danger';
    const span = document.createElement('span');
    span.className = 'uk-hidden-visually';
    span.textContent = isCorrect ? 'Richtige Antwort' : 'Falsche Antwort';
    div.appendChild(span);
    div.append(' ' + message);
    container.replaceChildren(div);
  }

  // Prüft die Reihenfolge der Sortierfrage
  function checkSort(ul, right, feedback, idx){
    const container = ul.closest('.question');
    if(container && container.dataset.timedOut === '1'){
      return;
    }
    const currentOrder = Array.from(ul.querySelectorAll('li')).map(li => li.textContent.trim());
    const correct = JSON.stringify(currentOrder) === JSON.stringify(right);
    results[idx] = correct;
    if(idx !== null && idx !== undefined){
      earnedPoints[idx] = correct ? (questionPoints[idx] ?? 0) : 0;
    }
    updateAnswerEntry(idx, {}, container);
    renderFeedback(
      feedback,
      correct,
      correct
        ? '✅ Richtig sortiert!'
        : '❌ Leider falsch, versuche es nochmal!'
    );
  }

  // Erstellt das DOM für eine Zuordnungsfrage
  // Links werden die Begriffe gelistet, rechts die Dropzones für die Definitionen
  function createAssignQuestion(q, idx){
    const div = document.createElement('div');
    div.className = 'question';
    div.setAttribute('uk-scrollspy', 'cls: uk-animation-slide-bottom-small; target: > *; delay: 100');
    const h = document.createElement('h4');
    h.textContent = insertSoftHyphens(q.prompt);
    div.appendChild(h);
    const assignDesc = document.createElement('p');
    assignDesc.id = 'assign-desc-' + idx;
    assignDesc.className = 'uk-hidden-visually';
    assignDesc.textContent = 'Mit Pfeil nach oben oder unten kannst du Begriffe innerhalb der Liste verschieben.';
    div.appendChild(assignDesc);

    const grid = document.createElement('div');
    grid.className = 'uk-grid-small uk-child-width-1-2';
    grid.setAttribute('uk-grid','');
    div.appendChild(grid);

    const left = document.createElement('div');
    const termList = document.createElement('ul');
    termList.className = 'uk-list uk-list-striped terms';
    termList.setAttribute('aria-describedby', assignDesc.id);
    const leftTerms = shuffleArray(q.terms);
    div._initialLeftTerms = leftTerms.slice();
    leftTerms.forEach(t => {
      const li = document.createElement('li');
      li.draggable = true;
      li.setAttribute('role','listitem');
      li.tabIndex = 0;
      li.setAttribute('aria-grabbed','false');
      li.dataset.term = t.term;
      li.textContent = insertSoftHyphens(t.term);
      termList.appendChild(li);
    });
    left.appendChild(termList);
    grid.appendChild(left);

    const rightCol = document.createElement('div');
    const rightTerms = shuffleArray(q.terms);
    rightTerms.forEach(t => {
      const dz = document.createElement('div');
      dz.className = 'dropzone';
      dz.setAttribute('role','listitem');
      dz.tabIndex = 0;
      dz.setAttribute('aria-dropeffect', 'move');
      dz.dataset.term = t.term;
      dz.dataset.definition = t.definition;
      dz.setAttribute('aria-label', 'Dropzone f\u00fcr ' + t.definition);
      dz.setAttribute('aria-dropeffect', 'move');
      dz.textContent = insertSoftHyphens(t.definition);
      rightCol.appendChild(dz);
    });
    grid.appendChild(rightCol);

    const feedback = document.createElement('div');
    feedback.className = 'uk-margin-top question-feedback';
    feedback.dataset.role = 'feedback';
    feedback.setAttribute('role', 'alert');
    feedback.setAttribute('aria-live', 'polite');
    const footer = document.createElement('div');
    footer.className = 'uk-margin-top uk-flex uk-flex-between';
    const btn = document.createElement('button');
    btn.className = 'uk-button uk-button-primary';
    btn.textContent = 'Antwort prüfen';
    styleButton(btn);
    btn.addEventListener('click', () => checkAssign(div, feedback, idx));
    if(!showCheck) btn.classList.add('uk-hidden');
    const resetBtn = document.createElement('button');
    resetBtn.className = 'uk-button';
    resetBtn.textContent = 'Zurücksetzen';
    styleButton(resetBtn);
    resetBtn.addEventListener('click', () => resetAssign(div, feedback));
    const nextBtn = document.createElement('button');
    nextBtn.className = 'uk-button';
    nextBtn.textContent = 'Weiter';
    styleButton(nextBtn);
    nextBtn.addEventListener('click', () => {
      checkAssign(div, feedback, idx);
      next();
    });
    footer.appendChild(btn);
    footer.appendChild(resetBtn);
    footer.appendChild(nextBtn);
    div.appendChild(feedback);
    div.appendChild(footer);

    setupAssignHandlers(div);
    return div;
  }

  // Initialisiert Drag-&-Drop und Tastatursteuerung für die Zuordnungsfrage
  function setupAssignHandlers(div){
    const list = div.querySelector('.terms');
    const group = 'assign-' + Math.random().toString(36).substr(2,5);

    if (typeof Sortable !== 'undefined') {
      Sortable.create(list, {
        group: { name: group, pull: true, put: false },
        sort: false,
        animation: 150,
        onStart: evt => evt.item.setAttribute('aria-grabbed','true'),
        onEnd: evt => evt.item.setAttribute('aria-grabbed','false')
      });

      div.querySelectorAll('.dropzone').forEach(zone => {
        Sortable.create(zone, {
          group: { name: group, pull: false, put: true },
          sort: false,
          animation: 150,
            onAdd: evt => {
            const item = evt.item;
            zone.textContent = insertSoftHyphens(zone.dataset.definition) + ' \u2013 ' + item.textContent;
            zone.dataset.dropped = item.dataset.term;
            item.remove();
          }
        });
      });
    }

    div._selectedTerm = null;
    div.querySelectorAll('.terms li').forEach(li => {
      li.addEventListener('keydown', e => {
        if(e.key === 'Enter' || e.key === ' '){
          div._selectedTerm = li;
          li.setAttribute('aria-grabbed','true');
          e.preventDefault();
        }
      });
    });
    div.querySelectorAll('.dropzone').forEach(zone => {
      zone.addEventListener('keydown', e => {
        if((e.key === 'Enter' || e.key === ' ') && div._selectedTerm){
          zone.textContent = insertSoftHyphens(zone.dataset.definition) + ' \u2013 ' + div._selectedTerm.textContent;
          zone.dataset.dropped = div._selectedTerm.dataset.term;
          div._selectedTerm.style.visibility = 'hidden';
          div._selectedTerm.setAttribute('aria-grabbed','false');
          div._selectedTerm = null;
          e.preventDefault();
        }
      });
    });
  }

  // Überprüft, ob alle Begriffe korrekt zugeordnet wurden
  function checkAssign(div, feedback, idx){
    if(div.dataset.timedOut === '1'){
      return;
    }
    let allCorrect = true;
    div.querySelectorAll('.dropzone').forEach(zone => {
      const parts = zone.textContent.split(' \u2013 ');
      const dropped = parts.length > 1 ? parts[1].trim() : '';
      if(zone.dataset.term !== dropped) allCorrect = false;
    });
    results[idx] = allCorrect;
    if(idx !== null && idx !== undefined){
      earnedPoints[idx] = allCorrect ? (questionPoints[idx] ?? 0) : 0;
    }
    updateAnswerEntry(idx, {}, div);
    renderFeedback(
      feedback,
      allCorrect,
      allCorrect
        ? '✅ Alles richtig zugeordnet!'
        : '❌ Nicht alle Zuordnungen sind korrekt.'
    );
  }

  // Setzt die Zuordnungsfrage auf den Ausgangszustand zurück
  function resetAssign(div, feedback){
    if(div.dataset.timedOut === '1'){
      return;
    }
    const termList = div.querySelector('.terms');
    termList.textContent = '';
    div._initialLeftTerms.forEach(t => {
      const li = document.createElement('li');
      li.draggable = true;
      li.setAttribute('role','listitem');
      li.tabIndex = 0;
      li.setAttribute('aria-grabbed','false');
      li.dataset.term = t.term;
      li.textContent = insertSoftHyphens(t.term);
      termList.appendChild(li);
    });
    termList.querySelectorAll('li').forEach(li => {
      li.addEventListener('keydown', e => {
        if(e.key === 'Enter' || e.key === ' '){
          div._selectedTerm = li;
          li.setAttribute('aria-grabbed','true');
          e.preventDefault();
        }
      });
    });
    div.querySelectorAll('.dropzone').forEach(zone => {
      zone.textContent = insertSoftHyphens(zone.dataset.definition);
      delete zone.dataset.dropped;
    });
    feedback.textContent = '';
    feedback.classList.remove('question-feedback--timeout');
    div._selectedTerm = null;
  }

  // Prüft die Auswahl bei einer Multiple-Choice-Frage
  function checkMc(div, correctIndices, feedback, idx){
    if(div.dataset.timedOut === '1'){
      return;
    }
    const selected = Array.from(div.querySelectorAll('input[name="mc' + idx + '"]:checked'))
      .map(el => parseInt(el.value, 10))
      .sort((a, b) => a - b);
    const sortedCorrect = correctIndices.slice().sort((a, b) => a - b);
    const correct =
      selected.length === sortedCorrect.length &&
      selected.every((v, i) => v === sortedCorrect[i]);
    results[idx] = correct;
    if(idx !== null && idx !== undefined){
      earnedPoints[idx] = correct ? (questionPoints[idx] ?? 0) : 0;
    }
    renderFeedback(
      feedback,
      correct,
      correct ? '✅ Korrekt!' : '❌ Das ist nicht korrekt.'
    );
    updateAnswerEntry(idx, {}, div);
  }

  // Erstellt das DOM für eine Multiple-Choice-Frage
  function createMcQuestion(q, idx){
    const div = document.createElement('div');
    div.className = 'question';
    div.setAttribute('uk-scrollspy', 'cls: uk-animation-slide-bottom-small; target: > *; delay: 100');
    const h = document.createElement('h4');
    h.textContent = insertSoftHyphens(q.prompt);
    div.appendChild(h);

    const options = document.createElement('div');

    // Optionen zufällig anordnen
    const order = shuffleArray(q.options.map((_,i) => i));
    // Korrekte Antworten auf die neue Reihenfolge abbilden
    const correctIndices = (q.answers || [q.answer || 0]).map(a => order.indexOf(a));

    order.forEach((orig,i) => {
      const label = document.createElement('label');
      label.className = 'mc-option';
      const input = document.createElement('input');
      input.className = 'uk-checkbox';
      input.type = 'checkbox';
      input.name = 'mc' + idx;
      input.value = i;
      label.appendChild(input);
      label.appendChild(document.createTextNode(' ' + insertSoftHyphens(q.options[orig])));
      options.appendChild(label);
    });

    const feedback = document.createElement('div');
    feedback.setAttribute('role', 'alert');
    feedback.setAttribute('aria-live', 'polite');
    feedback.className = 'uk-margin-top question-feedback';
    feedback.dataset.role = 'feedback';

    const footer = document.createElement('div');
    footer.className = 'uk-margin-top uk-flex uk-flex-between';
    const checkBtn = document.createElement('button');
    checkBtn.className = 'uk-button uk-button-primary';
    checkBtn.textContent = 'Antwort prüfen';
    styleButton(checkBtn);
    checkBtn.addEventListener('click', () => {
      checkMc(div, correctIndices, feedback, idx);
    });
    if(!showCheck) checkBtn.classList.add('uk-hidden');
    const nextBtn = document.createElement('button');
    nextBtn.className = 'uk-button';
    nextBtn.textContent = 'Weiter';
    styleButton(nextBtn);
    nextBtn.addEventListener('click', () => {
      checkMc(div, correctIndices, feedback, idx);
      next();
    });
    footer.appendChild(checkBtn);
    footer.appendChild(nextBtn);

    div.appendChild(options);
    div.appendChild(feedback);
    div.appendChild(footer);

    return div;
  }

  function createSwipeQuestion(q, idx){
    const div = document.createElement('div');
    div.className = 'question';
    div.setAttribute('uk-scrollspy', 'cls: uk-animation-slide-bottom-small; target: > *; delay: 100');
    const h = document.createElement('h4');
    h.textContent = insertSoftHyphens(q.prompt);
    div.appendChild(h);

    const container = document.createElement('div');
    container.className = 'swipe-container';
    div.appendChild(container);

    const controls = document.createElement('div');
    controls.className = 'uk-margin-top uk-flex uk-flex-center';
    const leftBtn = document.createElement('button');
    leftBtn.className = 'uk-button uk-margin-right';
    leftBtn.textContent = '\u2B05 ' + (q.leftLabel || 'Nein');
    styleButton(leftBtn);
    leftBtn.addEventListener('click', () => manualSwipe('left'));
    leftBtn.addEventListener('keydown', e => {
      if(e.key === 'Enter' || e.key === ' '){
        e.preventDefault();
        manualSwipe('left');
      }
    });

    const rightBtn = document.createElement('button');
    rightBtn.className = 'uk-button';
    rightBtn.textContent = (q.rightLabel || 'Ja') + ' \u27A1';
    styleButton(rightBtn);
    rightBtn.addEventListener('click', () => manualSwipe('right'));
    rightBtn.addEventListener('keydown', e => {
      if(e.key === 'Enter' || e.key === ' '){
        e.preventDefault();
        manualSwipe('right');
      }
    });
    controls.appendChild(leftBtn);
    controls.appendChild(rightBtn);

    const leftStatic = document.createElement('div');
    leftStatic.textContent = '\u2B05 ' + insertSoftHyphens(q.leftLabel || 'Nein');
    leftStatic.style.position = 'absolute';
    leftStatic.style.left = '0';
    leftStatic.style.top = '50%';
    leftStatic.style.transform = 'translate(-50%, -50%) rotate(180deg)';
    leftStatic.style.writingMode = 'vertical-rl';
    leftStatic.style.pointerEvents = 'none';
    leftStatic.style.color = 'red';
    leftStatic.style.zIndex = '10';
    container.appendChild(leftStatic);

    const rightStatic = document.createElement('div');
    rightStatic.textContent = insertSoftHyphens(q.rightLabel || 'Ja') + ' \u27A1';
    rightStatic.style.position = 'absolute';
    rightStatic.style.right = '0';
    rightStatic.style.top = '50%';
    rightStatic.style.transform = 'translate(50%, -50%)';
    rightStatic.style.writingMode = 'vertical-rl';
    rightStatic.style.pointerEvents = 'none';
    rightStatic.style.color = 'green';
    rightStatic.style.zIndex = '10';
    container.appendChild(rightStatic);

    const label = document.createElement('div');
    label.style.position = 'absolute';
    label.style.top = '8px';
    label.style.left = '8px';
    label.style.fontWeight = 'bold';
    label.style.pointerEvents = 'none';
    container.appendChild(label);

    let cards = (q.cards || []).map(c => ({...c}));
    const resultsLocal = [];
    let startX=0,startY=0,offsetX=0,offsetY=0,dragging=false;
    const SWIPE_THRESHOLD = 80;
    const SWIPE_OUT_DISTANCE = 1000;
    const SWIPE_ANIM_MS = 300;

    function render(){
      container.querySelectorAll('.swipe-card').forEach(el => el.remove());
      cards.forEach((c,i) => {
        const card = document.createElement('div');
        card.className = 'swipe-card';
        card.style.position = 'absolute';
        card.style.left = '2rem';
        card.style.right = '2rem';
        card.style.top = '0';
        card.style.bottom = '0';
        card.style.background = 'white';
        card.style.borderRadius = '8px';
        card.style.boxShadow = '0 2px 6px rgba(0,0,0,0.2)';
        card.style.display = 'flex';
        card.style.alignItems = 'center';
        card.style.justifyContent = 'center';
        card.style.padding = '1rem';
        card.style.transition = 'transform 0.3s';
        card.style.touchAction = 'none';
        const off = (cards.length - i - 1) * 4;
        card.style.transform = `translate(0,-${off}px)`;
        card.style.zIndex = i;
        card.textContent = insertSoftHyphens(c.text);
        if(i === cards.length - 1){
          card.addEventListener('pointerdown', start);
          card.addEventListener('pointermove', move);
          card.addEventListener('pointerup', end);
          card.addEventListener('pointercancel', end);
        }
        container.appendChild(card);
      });
    }

    function point(e){ return { x: e.clientX, y: e.clientY }; }

    function start(e){
      if(div.dataset.timedOut === '1') return;
      if(!cards.length) return;
      const p = point(e);
      startX = p.x; startY = p.y;
      dragging = true;
      offsetX = 0; offsetY = 0;
      e.preventDefault();
    }

    function move(e){
      if(div.dataset.timedOut === '1') return;
      if(!dragging) return;
      const p = point(e);
      offsetX = p.x - startX;
      offsetY = p.y - startY;
      const card = container.querySelector('.swipe-card:last-child');
      if(card){
        const rot = offsetX / 10;
        card.style.transform = `translate(${offsetX}px,${offsetY}px) rotate(${rot}deg)`;
      }
      label.textContent = offsetX >= 0
        ? '\u27A1 ' + (q.rightLabel || 'Ja')
        : '\u2B05 ' + (q.leftLabel || 'Nein');
      label.style.color = offsetX >= 0 ? 'green' : 'red';
      e.preventDefault();
    }

    function end(){
      if(div.dataset.timedOut === '1') return;
      if(!dragging) return;
      dragging = false;
      if(Math.abs(offsetX) > SWIPE_THRESHOLD){
        handleSwipe(offsetX > 0 ? 'right' : 'left', offsetY);
      } else {
        const cardEl = container.querySelector('.swipe-card:last-child');
        if(cardEl){
          cardEl.style.transform = 'translate(0,0)';
        }
        offsetX = offsetY = 0;
        label.textContent = '';
      }
    }

    function handleSwipe(dir, yOffset){
      if(div.dataset.timedOut === '1') return;
      if(!cards.length) return;
      const cardEl = container.querySelector('.swipe-card:last-child');
      const card = cards[cards.length-1];
      const labelText = dir === 'right' ? (q.rightLabel || 'Ja') : (q.leftLabel || 'Nein');
      label.textContent = dir === 'right'
        ? '\u27A1 ' + (q.rightLabel || 'Ja')
        : '\u2B05 ' + (q.leftLabel || 'Nein');
      label.style.color = dir === 'right' ? 'green' : 'red';
      const correct = (dir === 'right') === !!card.correct;
      resultsLocal.push({text: card.text, label: labelText, correct});
      if(cardEl){
        cardEl.style.transform = `translate(${dir === 'right' ? SWIPE_OUT_DISTANCE : -SWIPE_OUT_DISTANCE}px,${yOffset || 0}px)`;
      }
      setTimeout(() => {
        cards.pop();
        offsetX = offsetY = 0;
        label.textContent = '';
        render();
        if(!cards.length){
          const swipeCorrect = resultsLocal.every(r => r.correct);
          results[idx] = swipeCorrect;
          if(idx !== null && idx !== undefined){
            earnedPoints[idx] = swipeCorrect ? (questionPoints[idx] ?? 0) : 0;
          }
          updateAnswerEntry(idx, {}, div);
          next();
        }
      }, SWIPE_ANIM_MS);
    }

    function manualSwipe(dir){
      if(div.dataset.timedOut === '1') return;
      handleSwipe(dir, 0);
    }

    div.appendChild(controls);
    const feedback = document.createElement('div');
    feedback.className = 'uk-margin-top question-feedback';
    feedback.dataset.role = 'feedback';
    feedback.setAttribute('role', 'status');
    feedback.setAttribute('aria-live', 'polite');
    div.appendChild(feedback);
    render();
    return div;
  }

  function createPhotoTextQuestion(q, idx){
    const div = document.createElement('div');
    div.className = 'question';
    div.setAttribute('uk-scrollspy', 'cls: uk-animation-slide-bottom-small; target: > *; delay: 100');
    const h = document.createElement('h4');
    h.textContent = insertSoftHyphens(q.prompt);
    div.appendChild(h);

    const text = document.createElement('input');
    text.type = 'text';
    text.className = 'uk-input uk-margin';
    text.placeholder = 'Teilnehmende eintragen';

    let photoPath = '';
    const uploadBtn = document.createElement('button');
    uploadBtn.className = 'uk-button uk-button-default uk-margin';
    uploadBtn.textContent = 'Foto aufnehmen';
    styleButton(uploadBtn);

    const feedback = document.createElement('div');
    feedback.className = 'uk-margin-small question-feedback';
    feedback.dataset.role = 'feedback';
    feedback.setAttribute('role','alert');
    feedback.setAttribute('aria-live','polite');

    uploadBtn.addEventListener('click', () => {
      if(div.dataset.timedOut === '1') return;
      const user = getStored(STORAGE_KEYS.PLAYER_NAME) || '';
      showPhotoModal(user, '', path => {
        photoPath = path || '';
        feedback.textContent = 'Foto gespeichert';
        feedback.classList.remove('uk-text-danger', 'question-feedback--timeout');
        feedback.classList.add('uk-text-success');
      }, !!q.consent);
    });

    const nextBtn = document.createElement('button');
    nextBtn.className = 'uk-button uk-button-primary';
    nextBtn.textContent = 'Weiter';
    styleButton(nextBtn);

    nextBtn.addEventListener('click', () => {
      if(div.dataset.timedOut === '1') return;
      if(!photoPath){
        feedback.textContent = 'Bitte Foto aufnehmen';
        feedback.classList.remove('uk-text-success', 'question-feedback--timeout');
        feedback.classList.add('uk-text-danger');
        return;
      }
      results[idx] = true;
      if(idx !== null && idx !== undefined){
        earnedPoints[idx] = questionPoints[idx] ?? 0;
      }
      updateAnswerEntry(idx, { text: text.value.trim(), photo: photoPath, consent: q.consent ? true : null }, div);
      next();
    });

    div.appendChild(uploadBtn);
    div.appendChild(text);
    div.appendChild(feedback);
    div.appendChild(nextBtn);
    return div;
  }

  function createFlipQuestion(q, idx){
    const div = document.createElement('div');
    div.className = 'question uk-text-center';
    div.setAttribute('uk-scrollspy', 'cls: uk-animation-slide-bottom-small; target: > *; delay: 100');
    const card = document.createElement('div');
    card.className = 'flip-card uk-margin';
    card.tabIndex = 0;
    const inner = document.createElement('div');
    inner.className = 'flip-card-inner';
    const front = document.createElement('div');
    front.className = 'flip-card-front';
    front.textContent = insertSoftHyphens(q.prompt);
    const back = document.createElement('div');
    back.className = 'flip-card-back';
    back.textContent = insertSoftHyphens(q.answer || '');
    inner.appendChild(front);
    inner.appendChild(back);
    card.appendChild(inner);
    const toggle = () => {
      if(div.dataset.timedOut === '1') return;
      card.classList.toggle('flipped');
    };
    card.addEventListener('click', toggle);
    card.addEventListener('keydown', e => { if(e.key==='Enter' || e.key===' ') { e.preventDefault(); toggle(); } });
    const nextBtn = document.createElement('button');
    nextBtn.className = 'uk-button uk-button-primary';
    nextBtn.textContent = 'Weiter';
    styleButton(nextBtn);
    nextBtn.addEventListener('click', () => { if(div.dataset.timedOut === '1') return; next(); });
    const feedback = document.createElement('div');
    feedback.className = 'uk-margin-small question-feedback';
    feedback.dataset.role = 'feedback';
    feedback.setAttribute('role','status');
    feedback.setAttribute('aria-live','polite');
    div.appendChild(card);
    div.appendChild(feedback);
    div.appendChild(nextBtn);
    return div;
  }

  // Startbildschirm mit Startknopf – ohne Statistik
  function createStart(){
    const div = document.createElement('div');
    div.className = 'question uk-text-center';
    div.setAttribute('uk-scrollspy', 'cls: uk-animation-slide-bottom-small; target: > *; delay: 100');

    if(cfg.QRUser && !getStored(STORAGE_KEYS.PLAYER_NAME)){
      const scanBtn = document.createElement('button');
      scanBtn.className = 'uk-button uk-button-primary uk-button-large';
      scanBtn.textContent = 'Name mit QR-Code scannen';
      styleButton(scanBtn);
      const modal = document.createElement('div');
      modal.id = 'quiz-qr-modal';
      modal.setAttribute('uk-modal', '');
      modal.setAttribute('aria-modal', 'true');
      modal.innerHTML = '<div class="uk-modal-dialog uk-modal-body">'+
        '<h3 class="uk-modal-title uk-text-center">Team-Check-in</h3>'+
        '<div id="qr-reader" class="uk-margin" style="max-width:320px;margin:0 auto;width:100%"></div>'+
        '<button id="qr-reader-flip" class="uk-button uk-button-default uk-width-1-1">Kamera wechseln</button>'+
        '<button id="qr-reader-stop" class="uk-button uk-button-primary uk-width-1-1 uk-margin-top">Abbrechen</button>'+
      '</div>';
      let scanner;
      let opener;
      let cameras = [];
      let camIndex = 0;
      const stopScanner = () => {
        if(scanner){
          scanner.stop().then(()=>scanner.clear()).catch(()=>{});
          scanner = null;
        }
        flipBtn.disabled = true;
      };
      const startCamera = async () => {
        const camId = cameras[camIndex].id;
        flipBtn.disabled = true;
        try{
          await scanner.start(camId, { fps: 10, qrbox: 250 }, text => {
            setStored(STORAGE_KEYS.PLAYER_NAME, text.trim());
            stopScanner();
            UIkit.modal(modal).hide();
            next();
          });
        }catch(err){
          console.error('QR scanner start failed.', err);
          document.getElementById('qr-reader').textContent = 'QR-Scanner konnte nicht gestartet werden.';
          showManualInput();
        }
        flipBtn.disabled = cameras.length < 2;
      };
      const startScanner = async () => {
        if(typeof Html5Qrcode === 'undefined'){
          document.getElementById('qr-reader').textContent = 'QR-Scanner nicht verfügbar.';
          showManualInput();
          return;
        }
        scanner = new Html5Qrcode('qr-reader');
        flipBtn.disabled = true;
        try{
          const cams = await Html5Qrcode.getCameras();
          if(!cams || !cams.length){
            document.getElementById('qr-reader').textContent = 'Keine Kamera gefunden.';
            return;
          }
          cameras = filterCameraOrientations(cams);
          flipBtn.disabled = cameras.length < 2;
          camIndex = 0;
          const backIdx = cameras.findIndex(c => /back|rear|environment/i.test(c.label));
          if(backIdx >= 0) camIndex = backIdx;
          await startCamera();
        }catch(err){
          console.error('Camera list error.', err);
          document.getElementById('qr-reader').textContent = 'Kamera konnte nicht initialisiert werden. Bitte erlaube den Kamerazugriff im Browser oder in den Geräteeinstellungen. Lade die Seite danach neu.';
          showManualInput();
        }
      };
      const flipBtn = modal.querySelector('#qr-reader-flip');
      const stopBtn = modal.querySelector('#qr-reader-stop');
      flipBtn.disabled = true;
      let manualInputRenderId = 0;
      let manualInputClosed = false;
      let manualInputClosing = false;
      async function showManualInput(){
        const container = document.getElementById('qr-reader');
        if(!container){
          return;
        }
        manualInputClosed = false;
        manualInputClosing = false;
        const renderId = ++manualInputRenderId;
        container.textContent = '';
        const hint = document.createElement('div');
        hint.className = 'uk-text-center uk-margin-small-bottom';
        hint.textContent = 'Vorschlag wird geladen …';
        container.appendChild(hint);
        const input = document.createElement('input');
        input.id = 'manual-team-name';
        input.className = 'uk-input';
        input.type = 'text';
        input.placeholder = 'Teamname wird geladen …';
        input.value = '';
        let userInteracted = false;
        const submit = document.createElement('button');
        submit.id = 'manual-team-submit';
        submit.className = 'uk-button uk-button-primary uk-width-1-1 uk-margin-top';
        submit.textContent = 'Weiter';
        container.appendChild(input);
        container.appendChild(submit);
        flipBtn.classList.add('uk-hidden');
        input.addEventListener('input', () => {
          userInteracted = true;
        });
        try{
          await releaseNameReservation();
        }catch(e){
          /* empty */
        }
        const suggestionPromise = getNameSuggestion();
        suggestionPromise.then(suggestion => {
          const pendingReservation = nameReservation;
          if(manualInputClosed || manualInputClosing || renderId !== manualInputRenderId || !container.isConnected){
            releaseSpecificReservation(pendingReservation).catch(() => {});
            restoreActiveSuggestionReservation(pendingReservation);
            return;
          }
          const normalized = typeof suggestion === 'string' ? suggestion.trim() : '';
          hint.textContent = normalized
            ? (getLastSuggestionWasFallback() ? `Vorschlag: ${normalized} (Zufall)` : `Vorschlag: ${normalized}`)
            : 'Kein Vorschlag verfügbar.';
          input.placeholder = normalized || 'Teamname eingeben';
          if(!userInteracted){
            input.value = normalized || '';
          }
          rememberActiveSuggestionReservation(pendingReservation);
        }).catch(() => {
          const pendingReservation = nameReservation;
          if(manualInputClosed || manualInputClosing || renderId !== manualInputRenderId || !container.isConnected){
            releaseSpecificReservation(pendingReservation).catch(() => {});
            restoreActiveSuggestionReservation(pendingReservation);
            return;
          }
          hint.textContent = 'Vorschlag konnte nicht geladen werden.';
          input.placeholder = 'Teamname eingeben';
          if(!userInteracted){
            input.value = '';
          }
        });
        const handleSubmit = async () => {
          const name = (input.value || input.placeholder || '').trim();
          if(name){
            const usingSuggestion = nameReservation && typeof nameReservation.name === 'string'
              && name.toLowerCase() === nameReservation.name.toLowerCase();
            if(usingSuggestion){
              const confirmed = await confirmNameReservationIfMatching(name);
              if(!confirmed){
                if(typeof UIkit !== 'undefined' && UIkit.notification){
                  UIkit.notification({
                    message: 'Reservierter Teamname konnte nicht bestätigt werden. Bitte erneut versuchen.',
                    status: 'danger',
                    pos: 'top-center',
                    timeout: 3000
                  });
                }else{
                  alert('Reservierter Teamname konnte nicht bestätigt werden. Bitte erneut versuchen.');
                }
                return;
              }
            }else{
              await releaseNameReservation();
            }
            setStored('quizUser', name);
            setStored(STORAGE_KEYS.PLAYER_NAME, name);
            stopScanner();
            UIkit.modal(modal).hide();
            next();
          }
        };
        submit.addEventListener('click', () => { handleSubmit(); });
        input.addEventListener('keydown', (ev) => {
          if(ev.key === 'Enter'){
            ev.preventDefault();
            handleSubmit();
          }
        });
        input.focus();
      }
      const trapFocus = (e) => {
        if(e.key === 'Tab'){
          e.preventDefault();
          stopBtn.focus();
        }
      };
      flipBtn.addEventListener('click', async () => {
        if(!scanner || cameras.length < 2) return;
        flipBtn.disabled = true;
        try{
          await scanner.stop();
          await scanner.clear();
          await new Promise(r => setTimeout(r, 100));
          scanner = new Html5Qrcode('qr-reader');
        }catch(e){
          console.warn('Fehler beim Stoppen oder Clearen:', e);
        }
        camIndex = (camIndex + 1) % cameras.length;
        await startCamera();
      });
      scanBtn.addEventListener('click', async (e) => {
        opener = e.currentTarget;
        UIkit.modal(modal).show();
        await startScanner();
      });
      UIkit.util.on(modal, 'shown', () => {
        stopBtn.focus();
        modal.addEventListener('keydown', trapFocus);
        manualInputClosed = false;
        manualInputClosing = false;
      });
      UIkit.util.on(modal, 'hide', () => {
        manualInputClosing = true;
      });
      UIkit.util.on(modal, 'hidden', () => {
        stopScanner();
        modal.removeEventListener('keydown', trapFocus);
        manualInputClosed = true;
        manualInputRenderId += 1;
        releaseNameReservation();
        if(opener){
          opener.focus();
        }
      });
      stopBtn.addEventListener('click', () => {
        UIkit.modal(modal).hide();
      });
      div.appendChild(scanBtn);
      document.body.appendChild(modal);
      return div;
    }

    const startBtn = document.createElement('button');
    startBtn.className = 'uk-button uk-button-primary uk-button-large uk-align-right';
    startBtn.textContent = 'Los geht\'s!';
    styleButton(startBtn);

    startBtn.addEventListener('click', async() => {
      if(cfg.QRRestrict){
        alert('Nur Registrierung per QR-Code erlaubt');
        return;
      }
      if(!getStored('quizUser')){
        if(cfg.randomNames){
          await promptTeamName();
        }
      }
      next();
    });
    div.appendChild(startBtn);
    return div;
  }

  // Abschlussbildschirm nach dem Quiz
  function createSummary(){
    const div = document.createElement('div');
    div.className = 'question uk-text-center';
    div.setAttribute('uk-scrollspy', 'cls: uk-animation-slide-bottom-small; target: > *; delay: 100');
    const h = document.createElement('h3');
    h.textContent = '🎉 Danke für die Teilnahme!';
    const p = document.createElement('p');
    const letter = document.createElement('div');
    letter.id = 'quiz-letter';
    letter.className = 'quiz-letter uk-margin-top';
    letter.style.display = 'none';
    const puzzleInfo = document.createElement('p');
    puzzleInfo.id = 'puzzle-info';
    puzzleInfo.className = 'uk-margin-top';
    const remainingInfo = document.createElement('p');
    remainingInfo.id = 'quiz-remaining';
    remainingInfo.className = 'uk-margin-top';
    div.appendChild(h);
    div.appendChild(p);
    div.appendChild(letter);
    div.appendChild(puzzleInfo);
    div.appendChild(remainingInfo);
    if(!cfg.competitionMode){
      const restart = document.createElement('a');
      const catalogSlug = getStored(STORAGE_KEYS.CATALOG) || '';
      const restartUrl = `/?event=${encodeURIComponent(currentEventUid)}&katalog=${encodeURIComponent(catalogSlug)}`;
      restart.href = withBase(restartUrl);
      restart.textContent = 'Neu starten';
      restart.className = 'uk-button uk-button-primary uk-margin-top';
      styleButton(restart);
        restart.addEventListener('click', () => {
          // keep player name across restarts but allow new quiz attempts
          clearStored(STORAGE_KEYS.QUIZ_SOLVED);
          const topbar = document.getElementById('topbar-title');
          if(topbar){
            topbar.textContent = topbar.dataset.defaultTitle || '';
          }
        });
      div.appendChild(restart);
    } else {
      const endBtn = document.createElement('button');
      endBtn.textContent = 'Station beenden';
      endBtn.id = 'end-station-btn';
      endBtn.className = 'uk-button uk-button-primary uk-margin-top';
      styleButton(endBtn);
      endBtn.addEventListener('click', () => {
        const name = getStored(STORAGE_KEYS.PLAYER_NAME);
        const hasValidName = name && name.trim();
        if(hasValidName){
          const params = new URLSearchParams();
          if(currentEventUid){
            params.set('event_uid', currentEventUid);
          }
          if(cfg.collectPlayerUid){
            const playerUid = getStored(STORAGE_KEYS.PLAYER_UID);
            if(playerUid){
              params.set('player_uid', playerUid);
            }
          }
          const resultPath = '/summary';
          const queryString = params.toString();
          const resultUrl = queryString ? `${resultPath}?${queryString}` : resultPath;
          window.location.href = withBase(resultUrl);
          return;
        }
        const fallbackUrl = withBase('/');
        window.close();
        setTimeout(() => {
          if(!window.closed){
            window.location.href = fallbackUrl;
          }
        }, 300);
      });
      div.appendChild(endBtn);
    }
    return div;
  }


  function showPuzzleCheck(btnEl, attemptKey){
    const modal = document.createElement('div');
    modal.setAttribute('uk-modal', '');
    modal.setAttribute('aria-modal', 'true');
    modal.innerHTML = '<div class="uk-modal-dialog uk-modal-body">' +
      '<h3 class="uk-modal-title uk-text-center">Rätselwort überprüfen</h3>' +
      '<input id="puzzle-input" class="uk-input" type="text" placeholder="Rätselwort eingeben">' +
      '<div id="puzzle-feedback" class="uk-margin-top uk-text-center"></div>' +
      '<button class="uk-button uk-button-primary uk-width-1-1 uk-margin-top">Überprüfen</button>' +
      '</div>';
    const input = modal.querySelector('#puzzle-input');
    const feedback = modal.querySelector('#puzzle-feedback');
    const btn = modal.querySelector('button');
    document.body.appendChild(modal);
    const ui = UIkit.modal(modal);
    UIkit.util.on(modal, 'hidden', () => { modal.remove(); });
    UIkit.util.on(modal, 'shown', () => { input.focus(); });
    function handleCheck(){
      const valRaw = (input.value || '').trim();
      const ts = Math.floor(Date.now()/1000);
        const user = getStored(STORAGE_KEYS.PLAYER_NAME) || '';
        const catalog = getStored(STORAGE_KEYS.CATALOG) || 'unknown';
        const data = { name: user, catalog, puzzleTime: ts, puzzleAnswer: valRaw };
        if(cfg.collectPlayerUid){
          const uid = getStored(STORAGE_KEYS.PLAYER_UID);
          if(uid) data.player_uid = uid;
        }
        fetch(withBase('/results?debug=1'), {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(data)
        })
      .then(async r => {
        if(!r.ok){
          throw new Error('HTTP ' + r.status);
        }
        try{
          return await r.json();
        }catch(e){
          return null;
        }
      })
      .then(data => {
        if(data){
          let debugTimer = null;
          if(data.normalizedAnswer !== undefined && data.normalizedExpected !== undefined){
            feedback.textContent = `Debug: ${data.normalizedAnswer} vs ${data.normalizedExpected}`;
            debugTimer = setTimeout(() => { feedback.textContent = ''; }, 3000);
          }
          if(data.success){
            if(debugTimer) clearTimeout(debugTimer);
            const msg = (data.feedback && data.feedback.trim())
              ? data.feedback
              : (cfg.puzzleFeedback && cfg.puzzleFeedback.trim())
                ? cfg.puzzleFeedback
                : 'Herzlichen Glückwunsch, das Rätselwort ist korrekt!';
            feedback.textContent = msg;
            feedback.className = 'uk-margin-top uk-text-center uk-text-success';
            setStored(STORAGE_KEYS.PUZZLE_SOLVED, 'true');
            setStored(STORAGE_KEYS.PUZZLE_TIME, String(ts));
            const infoEl = summaryEl.querySelector('#puzzle-info');
            if(infoEl){
              infoEl.textContent = `Rätselwort gelöst: ${formatPuzzleTime(ts)}`;
            }
            return;
          }
        }
        feedback.textContent = 'Das ist leider nicht korrekt. Viel Glück beim nächsten Versuch!';
        feedback.className = 'uk-margin-top uk-text-center uk-text-danger';
      })
      .catch(() => {
        feedback.textContent = 'Fehler bei der Überprüfung.';
        feedback.className = 'uk-margin-top uk-text-center uk-text-danger';
      })
      .finally(() => {
        input.disabled = true;
        if(attemptKey) setStored(attemptKey, 'true');
        if(btnEl){
          btnEl.disabled = true;
          btnEl.style.display = 'none';
        }

        btn.textContent = 'Schließen';
        btn.removeEventListener('click', handleCheck);
        btn.addEventListener('click', () => ui.hide());
      });
    }

    btn.addEventListener('click', handleCheck);
    ui.show();
  }

  function showPhotoModal(name, catalog, cb, requireConsent = true){
    const modal = document.createElement('div');
    modal.setAttribute('uk-modal', '');
    modal.setAttribute('aria-modal', 'true');
    modal.innerHTML =
      '<div class="uk-modal-dialog uk-modal-body">' +
        '<div class="uk-card qr-card uk-card-body uk-padding-small uk-width-1-1">' +
          '<p class="uk-text-small">Hinweis zum Hochladen von Gruppenfotos:<br>' +
            'Ich bestätige, dass alle auf dem Foto abgebildeten Personen vor der Aufnahme darüber informiert wurden, dass das Gruppenfoto zu Dokumentationszwecken erstellt und ggf. veröffentlicht wird. Alle Anwesenden hatten Gelegenheit, der Aufnahme zu widersprechen, indem sie den Aufnahmebereich verlassen oder dies ausdrücklich mitteilen konnten.' +
          '</p>' +
          '<div class="uk-margin-small-bottom">' +
            '<label class="uk-form-label" for="photo-input">Beweisfoto auswählen</label>' +
            '<div class="stacked-upload" uk-form-custom="target: true">' +
              '<input id="photo-input" type="file" accept="image/*" capture="environment" aria-label="Datei auswählen">' +
              '<input class="uk-input uk-width-1-1" type="text" placeholder="Keine Datei ausgewählt" disabled>' +
              '<button class="uk-button uk-button-default uk-width-1-1 uk-margin-small-top" type="button" tabindex="-1">Kamera öffnen</button>' +
            '</div>' +
          '</div>' +
          (requireConsent ?
            '<label class="uk-form-label uk-margin-small-bottom">' +
              '<input type="checkbox" id="consent-checkbox" class="uk-checkbox uk-margin-small-right">' +
              'Einverständnis aller abgebildeten Personen wurde eingeholt ' +
            '</label>' : '') +
          '<div id="photo-feedback" class="uk-margin-small uk-text-center"></div>' +
          '<button id="upload-btn" class="uk-button uk-button-primary uk-width-1-1" disabled>Hochladen</button>' +
        '</div>' +
      '</div>';

    const input = modal.querySelector('#photo-input');
    const feedback = modal.querySelector('#photo-feedback');
    const consent = modal.querySelector('#consent-checkbox');
    const btn = modal.querySelector('#upload-btn');
    document.body.appendChild(modal);
    const ui = UIkit.modal(modal);
    UIkit.util.on(modal, 'hidden', () => { modal.remove(); });

    function toggleBtn(){
      btn.disabled = !input.files.length || (requireConsent && !consent.checked);
    }
    input.addEventListener('change', toggleBtn);
    if(consent) consent.addEventListener('change', toggleBtn);

    btn.addEventListener('click', () => {
      const file = input.files && input.files[0];
      if(!file || (requireConsent && !consent.checked)) return;
      const fd = new FormData();
      fd.append('photo', file);
      fd.append('name', name);
        fd.append('catalog', catalog);
        fd.append('team', name);
        if(cfg.collectPlayerUid){
          const uid = getStored(STORAGE_KEYS.PLAYER_UID);
          if(uid) fd.append('player_uid', uid);
        }

      const originalChildren = Array.from(btn.childNodes).map(n => n.cloneNode(true));
      btn.disabled = true;
      const spinner = document.createElement('div');
      spinner.setAttribute('uk-spinner','');
      btn.replaceChildren(spinner);

      fetch(withBase('/photos'), { method: 'POST', body: fd })
        .then(async r => {
          if (!r.ok) {
            throw new Error(await r.text());
          }
          const ct = r.headers.get('Content-Type') || '';
          if (!ct.includes('application/json')) {
            throw new Error(await r.text());
          }
          return r.json();
        })
        .then(data => {
          feedback.textContent = 'Foto gespeichert';
          feedback.className = 'uk-margin-top uk-text-center uk-text-success';
          btn.disabled = true;
          input.disabled = true;
          if(consent) consent.disabled = true;
          setTimeout(() => {
            ui.hide();
            if (typeof UIkit !== 'undefined' && UIkit.notification) {
              UIkit.notification({
                message: 'Bild erfolgreich gespeichert',
                status: 'success',
                pos: 'top-center',
                timeout: 2000
              });
            } else {
              alert('Bild erfolgreich gespeichert');
            }
          }, 1000);
          if(typeof cb === 'function') cb(data.path);
        })
        .catch(e => {
          feedback.textContent = e.message || 'Fehler beim Hochladen';
          feedback.className = 'uk-margin-top uk-text-center uk-text-danger';
        })
        .finally(() => {
          btn.replaceChildren(...originalChildren);
        });
    });
    ui.show();
  }
}

function startQuiz(qs, skipIntro){
  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', () => runQuiz(qs, skipIntro));
  } else {
    runQuiz(qs, skipIntro);
  }
}

window.startQuiz = startQuiz;
if (window.quizQuestions && window.quizConfig?.autoStart) {
  startQuiz(window.quizQuestions, false);
}
