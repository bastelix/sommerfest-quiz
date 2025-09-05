(function(){
  const eventUid = (window.quizConfig || {}).event_uid || '';

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
    return eventScoped.has(key) ? `${key}:${eventUid}` : key;
  }

  function getStored(key){
    key = mapKey(key);
    try{
      return (typeof sessionStorage !== 'undefined' && sessionStorage.getItem(key)) ||
             (typeof localStorage !== 'undefined' && localStorage.getItem(key));
    }catch(e){
      return null;
    }
  }

  function setStored(key, value){
    key = mapKey(key);
    try{
      if(typeof sessionStorage !== 'undefined') sessionStorage.setItem(key, value);
      if(typeof localStorage !== 'undefined') localStorage.setItem(key, value);
    }catch(e){ /* empty */ }
  }

  function clearStored(key){
    key = mapKey(key);
    try{
      if(typeof sessionStorage !== 'undefined') sessionStorage.removeItem(key);
      if(typeof localStorage !== 'undefined') localStorage.removeItem(key);
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
