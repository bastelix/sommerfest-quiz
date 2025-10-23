/* global STORAGE_KEYS, getStored, setStored */
(function(){
  const melodicNames = [
    'Sonnenklang', 'Mondmelodie', 'Sturmserenade', 'Himmelsklang', 'Seewindlied', 'Sternenchor',
    'Fliederduft', 'Traumtänzer', 'Herbstleuchten', 'Sommernacht', 'Funkelpfad', 'Lichtklang',
    'Wolkenflug', 'Morgenröte', 'Nebelmut', 'Blütenzauber', 'Schattenklang', 'Seelenruh',
    'Friedenshauch', 'Kristallschein', 'Sternquelle', 'Friedentropfen', 'Kometflug', 'Sommersanft',
    'Lichtersanft', 'Birkenflug', 'Frostkraft', 'Herbstkraft', 'Feuerkraft', 'Birkenquelle',
    'Fernenregen', 'Sternsonne', 'Abendrauschen', 'Talerfunken', 'Fernenmond', 'Meeresfunken',
    'Winterstille', 'Liedlicht', 'Seelenfeuer', 'Sturmfeuer', 'Fernenstern', 'Auenkraft',
    'Flügelrauschen', 'Fichtenglut', 'Sonnenregen', 'Melodieruf', 'Meereswelle', 'Flusssegen',
    'Tanzregen', 'Frostecho', 'Dufttraum', 'Silberstreif', 'Regentau', 'Sonnenwelle',
    'Sternmond', 'Abendmorgen', 'Abendschimmer', 'Winterlicht', 'Blütenkristall', 'Zauberseele',
    'Sonnenherz', 'Brunnenwind', 'Zauberflug', 'Herbstwelle', 'Duftsegen', 'Sonnenlicht',
    'Friedenstille', 'Sturmhauch', 'Feuerstreif', 'Frostlied', 'Wolkenkraft', 'Sommerlicht',
    'Goldwelle', 'Windtraum', 'Fliederwind', 'Liedklang', 'Sturmsegen', 'Silbertanz',
    'Fichtenruf', 'Seelenstreif', 'Flügeltropfen', 'Aromasegen', 'Fernenflug', 'Kometglanz',
    'Kristallmut', 'Silberfeuer', 'Traumstern', 'Fliedertöne', 'Liedtanz', 'Wiesenstille',
    'Wandersanft', 'Eichenglanz', 'Friedensegen', 'Frühlingswelle', 'Fliederfunken', 'Leuchtkraft',
    'Herbstklang', 'Blütensegen', 'Sturmklang', 'Brunnenglanz', 'Wolkenfeder', 'Duftstille',
    'Silbertropfen', 'Glanzlicht', 'Flügellicht', 'Glanzwind', 'Herbstfeuer', 'Flügelkristall',
    'Sonnenkristall', 'Morgensegen', 'Schattentöne', 'Brunnenreigen', 'Herbstreigen', 'Sternzeit',
    'Seelenzauber', 'Auenregen', 'Fichtenwind', 'Eichenflug', 'Schattensonne', 'Birkensegen',
    'Feuertraum', 'Seelenkraft', 'Duftpfad', 'Silberruf', 'Traumklänge', 'Sturmreigen',
    'Regenfeder', 'Tanzkraft', 'Lichtregen', 'Frühlingsreigen', 'Windzeit', 'Nebelseele',
    'Aromapfad', 'Meerestau', 'Klangherz', 'Sonnenfeuer', 'Eichenglut', 'Windpfad',
    'Fliedertropfen', 'Glückmut', 'Kometstrahl', 'Meereswind', 'Brunnentau', 'Wolkenmorgen',
    'Talerklänge', 'Elfenruf', 'Fichtensonne', 'Sternklang', 'Elfenlicht', 'Goldflug',
    'Liedzauber', 'Flusstraum', 'Sonnenzeit', 'Liedquelle', 'Klanglicht', 'Goldecho',
    'Duftzauber', 'Sternkristall', 'Frostflug', 'Friedenlicht', 'Winterregen', 'Sommerreigen',
    'Traumreigen', 'Seelenherz', 'Sternflug', 'Regenrauschen', 'Sternsegen', 'Glücktraum',
    'Regenglanz', 'Wolkenmut', 'Sonnenglut', 'Flügelmorgen', 'Brunnenpfad', 'Drachenstern',
    'Glückwelle', 'Fernenfeder', 'Glitzerlicht', 'Wiesenflug', 'Kristallmond', 'Regenlicht',
    'Blütenwind', 'Zaubersegen', 'Kometlicht', 'Brunnenlicht', 'Seelenflug', 'Kristallzauber',
    'Brunnentraum', 'Blütenzeit', 'Blütenherz', 'Melodiestille', 'Nebelflug', 'Aromatau',
    'Lichtzauber', 'Kometstille', 'Lichterwelle', 'Mondglanz', 'Schattentropfen', 'Elfenquelle',
    'Sturmstrahl', 'Traumkristall', 'Fliederstern', 'Glückhauch', 'Traumherz', 'Winterflug',
    'Tanztraum', 'Birkenlicht', 'Duftkraft', 'Lichterrauschen', 'Wiesenstrahl', 'Sterntöne',
    'Morgenherz', 'Glanzmorgen', 'Klangtanz', 'Talerecho', 'Klangwelle', 'Frühlingsmond',
    'Meeresreigen', 'Lichtglanz', 'Wintersegen', 'Feuerschimmer'
  ];

  const root = typeof window !== 'undefined' ? window : globalThis;
  const getKey = () => {
    if (typeof STORAGE_KEYS === 'object' && STORAGE_KEYS && STORAGE_KEYS.USED_NAMES) {
      return STORAGE_KEYS.USED_NAMES;
    }
    return 'USED_NAMES';
  };

  root.generatePlayerName = function(){
    const key = getKey();
    let used;
    try {
      used = JSON.parse((typeof getStored === 'function' && getStored(key)) || '[]');
    } catch (e) {
      used = [];
    }
    if (!Array.isArray(used)) {
      used = [];
    }
    const available = melodicNames.filter(n => !used.includes(n));
    let name;
    if(available.length){
      name = available[Math.floor(Math.random() * available.length)];
      used.push(name);
      if (typeof setStored === 'function') {
        try {
          setStored(key, JSON.stringify(used));
        } catch (e) { /* empty */ }
      }
    }else{
      name = 'Gast-' + Math.random().toString(36).substr(2,5);
    }
    return name;
  };
})();
