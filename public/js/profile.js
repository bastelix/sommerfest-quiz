// Profile page logic for handling player names

let nameInput;
let nameKey;
let uidKey;
let eventUid;

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
    localStorage.setItem(nameKey, name);
  } else {
    localStorage.removeItem(nameKey);
  }
  let uid = localStorage.getItem(uidKey);
  if (!uid) {
    uid = self.crypto?.randomUUID ? self.crypto.randomUUID() : Math.random().toString(36).slice(2);
    localStorage.setItem(uidKey, uid);
  }
  fetch('/api/players', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ event_uid: eventUid, player_name: name, player_uid: uid })
  }).catch(() => {});
  if (typeof returnUrl !== 'undefined' && returnUrl) {
    window.location.href = returnUrl;
  }
}

function deleteName(e) {
  e?.preventDefault();
  localStorage.removeItem(nameKey);
  localStorage.removeItem(uidKey);
  nameInput.value = generateRandomName();
}

document.addEventListener('DOMContentLoaded', () => {
  nameInput = document.getElementById('playerName');
  eventUid = window.quizConfig?.event_uid || '';
  nameKey = `qr_player_name:${eventUid}`;
  uidKey = `qr_player_uid:${eventUid}`;
  const storedName = localStorage.getItem(nameKey);
  nameInput.value = storedName || generateRandomName();
  document.getElementById('save-name')?.addEventListener('click', saveName);
  document.getElementById('delete-name')?.addEventListener('click', deleteName);
});

