(function(){
  const STORAGE_KEYS = {
    PLAYER_NAME: 'qr_player_name',
    PLAYER_UID: 'qr_player_uid',
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
    USED_NAMES: 'usedNames'
  };

  const eventScoped = new Set([STORAGE_KEYS.PLAYER_NAME, STORAGE_KEYS.PLAYER_UID]);

  function mapKey(key){
    const uid = (window.quizConfig || {}).event_uid || '';
    return eventScoped.has(key) ? `${key}:${uid}` : key;
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
      if(val === null && eventScoped.has(key)){
        const legacy = `${key}:`;
        val = readStorage(legacy);
        if(val !== null){
          setStored(key, val);
          try{ removeStorage(legacy); }catch(e){ /* empty */ }
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

  /*
   * Standardized storage keys:
   * - qr_player_name:<eventUid>  – Spielername
   * - qr_player_uid:<eventUid>   – Spieler-UID
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
   */

  globalThis.STORAGE_KEYS = STORAGE_KEYS;
  globalThis.getStored = getStored;
  globalThis.setStored = setStored;
  globalThis.clearStored = clearStored;
})();
