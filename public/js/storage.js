(function(){
  const STORAGE_KEYS = {
    PLAYER_NAME: 'quizUser',
    PLAYER_UID: 'qr_player_uid',
    PLAYER_EMAIL: 'qr_player_email',
    PLAYER_EMAIL_CONSENT: 'qr_player_email_consent',
    CATALOG: 'quizCatalog',
    CATALOG_NAME: 'quizCatalogName',
    CATALOG_DESC: 'quizCatalogDesc',
    CATALOG_COMMENT: 'quizCatalogComment',
    CATALOG_UID: 'quizCatalogUid',
    CATALOG_SORT: 'quizCatalogSortOrder',
    LETTER: 'quizLetter',
    PUZZLE_SOLVED: 'puzzleSolved',
    PUZZLE_TIME: 'puzzleTime',
    QUIZ_SOLVED: 'quizSolved',
    USED_NAMES: 'usedNames',
    DARK_MODE: 'darkMode',
    BARRIER_FREE: 'barrierFree',
    QR_THEME: 'qr-theme',
    QR_CONTRAST: 'qr-contrast',
    TENANT_COLUMNS: 'tenantColumns',
    ADMIN_SIDEBAR: 'adminSidebarCollapsed',
    CALSERVER_COOKIE_CHOICES: 'calserverCookieChoices'
  };

  const eventScoped = new Set([
    STORAGE_KEYS.PLAYER_UID,
    STORAGE_KEYS.CATALOG,
    STORAGE_KEYS.CATALOG_NAME,
    STORAGE_KEYS.CATALOG_DESC,
    STORAGE_KEYS.CATALOG_COMMENT,
    STORAGE_KEYS.CATALOG_UID,
    STORAGE_KEYS.CATALOG_SORT,
    STORAGE_KEYS.LETTER,
    STORAGE_KEYS.PUZZLE_SOLVED,
    STORAGE_KEYS.PUZZLE_TIME,
    STORAGE_KEYS.QUIZ_SOLVED,
    STORAGE_KEYS.PLAYER_EMAIL,
    STORAGE_KEYS.PLAYER_EMAIL_CONSENT
  ]);

  function getActiveEventId(){
    if (typeof window.getActiveEventId === 'function') {
      return window.getActiveEventId();
    }
    return typeof window.activeEventId === 'string' ? window.activeEventId : '';
  }

  function normalizeEventUid(eventUid){
    if (typeof eventUid === 'string') {
      return eventUid.trim();
    }
    return eventUid ? String(eventUid).trim() : '';
  }

  function mapKeyForEvent(key, eventUid){
    const normalizedEvent = normalizeEventUid(eventUid);
    return eventScoped.has(key) ? `${key}:${normalizedEvent}` : key;
  }

  function mapKey(key){
    return mapKeyForEvent(key, getActiveEventId());
  }

  function readStorage(key){
    return (typeof sessionStorage !== 'undefined' && sessionStorage.getItem(key)) ||
           (typeof localStorage !== 'undefined' && localStorage.getItem(key));
  }

  function removeStorage(key){
    if(typeof sessionStorage !== 'undefined') sessionStorage.removeItem(key);
    if(typeof localStorage !== 'undefined') localStorage.removeItem(key);
  }

  function getStored(key){
    const mapped = mapKey(key);
    try{
      let val = readStorage(mapped);
      if(val === null){
        if(eventScoped.has(key)){
          const legacies = [`${key}:`, key];
          for(const legacy of legacies){
            val = readStorage(legacy);
            if(val !== null){
              setStored(key, val);
              try{ removeStorage(legacy); }catch(e){ /* empty */ }
              break;
            }
          }
        }else{
          const legacy = `${key}:${getActiveEventId()}`;
          val = readStorage(legacy);
          if(val !== null){
            setStored(key, val);
            try{ removeStorage(legacy); }catch(e){ /* empty */ }
          }
        }
      }
      return val;
    }catch(e){
      return null;
    }
  }

  function setStored(key, value){
    const mapped = mapKey(key);
    try{
      if(typeof sessionStorage !== 'undefined') sessionStorage.setItem(mapped, value);
      if(typeof localStorage !== 'undefined') localStorage.setItem(mapped, value);
    }catch(e){ /* empty */ }
  }

  function clearStored(key){
    const mapped = mapKey(key);
    try{
      removeStorage(mapped);
    }catch(e){ /* empty */ }
  }

  function getStoredForEvent(key, eventUid){
    const mapped = mapKeyForEvent(key, eventUid);
    try{
      return readStorage(mapped);
    }catch(e){
      return null;
    }
  }

  /*
   * Standardized storage keys:
   * - quizUser               – Spielername (global)
   * - qr_player_uid:<currentEventUid>   – Spieler-UID
   * - quizCatalog                – Aktueller Katalog-Slug
   * - quizCatalogName            – Katalognamen
   * - quizCatalogDesc            – Katalogbeschreibung
   * - quizCatalogComment         – Zusätzlicher Kommentar
   * - quizCatalogUid             – Katalog-UID
   * - quizCatalogSortOrder       – Sortierreihenfolge
   * - quizLetter                 – Buchstabe für Rätselwort
   * - puzzleSolved               – Flag, ob Rätsel gelöst
   * - puzzleTime                 – Zeitstempel des Rätsels
   * - quizSolved                 – Liste gelöster Fragen (JSON)
   * - usedNames                  – Für Zufallsnamen verwendete Namen
   * - darkMode                   – Aktiviertes Dunkelmodus-Flag
   * - barrierFree                – Barrierefrei-Flag
   * - qr-theme                   – Letztes Theme
   * - qr-contrast                – Letzter Kontrastmodus
   * - qr_player_email            – Spieler-E-Mail (eventbezogen)
   * - qr_player_email_consent    – Zustimmung zur Kontaktaufnahme (eventbezogen)
  * - tenantColumns              – Sichtbare Mandantenspalten (JSON)
  * - calserverCookieChoices     – Präferenzen für externe Inhalte auf der calServer-Seite (JSON)
  */

  const sessionName = globalThis.sessionPlayerName;
  if (typeof sessionName === 'string' && sessionName && !getStored(STORAGE_KEYS.PLAYER_NAME)) {
    setStored(STORAGE_KEYS.PLAYER_NAME, sessionName);
  }

  globalThis.STORAGE_KEYS = STORAGE_KEYS;
  globalThis.getStored = getStored;
  globalThis.setStored = setStored;
  globalThis.clearStored = clearStored;
  globalThis.getStoredForEvent = getStoredForEvent;
})();
