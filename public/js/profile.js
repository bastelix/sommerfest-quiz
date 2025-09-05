/* global STORAGE_KEYS, getStored, setStored, clearStored, UIkit */
// Profile page logic for handling player names

let nameInput;
let eventUid;

const notify = (msg, status = 'primary') => {
  if (typeof UIkit !== 'undefined' && UIkit.notification) {
    UIkit.notification({ message: msg, status });
  } else {
    alert(msg);
  }
};

function generateRandomName() {
  const adjectives = [
    'Flinker', 'Lustiger', 'Mutiger', 'Schneller', 'Starker',
    'Schlauer', 'Kreativer', 'Tapferer', 'Fröhlicher', 'Geschickter'
  ];
  const animals = [
    'Fuchs', 'Panda', 'Tiger', 'Bär', 'Adler',
    'Löwe', 'Delfin', 'Eule', 'Wal', 'Drache'
  ];
  const adj = adjectives[Math.floor(Math.random() * adjectives.length)];
  const animal = animals[Math.floor(Math.random() * animals.length)];
  const number = Math.floor(Math.random() * 100);
  return `${adj}${animal}${number}`;
}

function saveName(e) {
  e?.preventDefault();
  const name = nameInput.value.trim();
  if (name) {
    setStored(STORAGE_KEYS.PLAYER_NAME, name);
  } else {
    clearStored(STORAGE_KEYS.PLAYER_NAME);
  }
  let uid = getStored(STORAGE_KEYS.PLAYER_UID);
  if (!uid) {
    uid = self.crypto?.randomUUID ? self.crypto.randomUUID() : Math.random().toString(36).slice(2);
    setStored(STORAGE_KEYS.PLAYER_UID, uid);
  }
  fetch('/api/players', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ event_uid: eventUid, player_name: name, player_uid: uid })
  }).catch(() => {});
  postSession('player', { name })
    .then(() => notify('Name gespeichert', 'success'))
    .catch(() => notify('Fehler beim Speichern', 'danger'));
  if (typeof returnUrl !== 'undefined' && returnUrl) {
    window.location.href = returnUrl;
  }
}

function deleteName(e) {
  e?.preventDefault();
  clearStored(STORAGE_KEYS.PLAYER_NAME);
  clearStored(STORAGE_KEYS.PLAYER_UID);
  nameInput.value = generateRandomName();
}

document.addEventListener('DOMContentLoaded', () => {
  nameInput = document.getElementById('playerName');
  const cfg = window.quizConfig || {};
  eventUid = cfg.event_uid || '';
  const params = new URLSearchParams(location.search);
  const uidParam = params.get('uid') || params.get('player_uid');
  if (uidParam) {
    setStored(STORAGE_KEYS.PLAYER_UID, uidParam);
  }
  const storedName = getStored(STORAGE_KEYS.PLAYER_NAME);
  nameInput.value = storedName || generateRandomName();
  if (!storedName && uidParam && cfg.collectPlayerUid) {
    fetch(`/api/players?event_uid=${encodeURIComponent(eventUid)}&player_uid=${encodeURIComponent(uidParam)}`)
      .then(r => r.ok ? r.json() : null)
      .then(data => {
        if (data && data.player_name) {
          nameInput.value = data.player_name;
          saveName();
        }
      })
      .catch(() => {});
  }
  if (typeof t === 'function') {
    nameInput.placeholder = t('label_player_name');
  }
  document.getElementById('save-name')?.addEventListener('click', saveName);
  document.getElementById('delete-name')?.addEventListener('click', deleteName);
});

