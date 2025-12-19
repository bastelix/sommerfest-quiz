/* global STORAGE_KEYS, getStored, setStored, clearStored, UIkit, TeamNameClient, deleteSession */
// Profile page logic for handling player names

let nameInput;
let currentEventUid;
let activeReservation = null;

const notify = (msg, status = 'primary') => {
  if (typeof UIkit !== 'undefined' && UIkit.notification) {
    UIkit.notification({ message: msg, status });
  } else {
    alert(msg);
  }
};

const fallbackSuggestion = () => `Gast-${Math.random().toString(36).slice(2, 7)}`;

function getTeamNameClient(){
  return typeof TeamNameClient === 'object' && TeamNameClient ? TeamNameClient : null;
}

async function requestNameSuggestion(){
  const client = getTeamNameClient();
  if (client && typeof client.reserve === 'function') {
    try {
      const reservation = await client.reserve({ eventUid: currentEventUid });
      activeReservation = reservation || null;
      if (reservation && typeof reservation.name === 'string' && reservation.name) {
        return reservation.name;
      }
    } catch (error) {
      console.error('Team name reservation failed', error);
    }
  }
  activeReservation = null;
  return fallbackSuggestion();
}

function releaseReservation(){
  if (activeReservation) {
    const client = getTeamNameClient();
    if (client && typeof client.release === 'function') {
      client.release({ reservation: activeReservation }).catch(() => {});
    }
  }
  activeReservation = null;
}

async function confirmReservationIfMatching(name){
  if (!activeReservation) {
    return false;
  }
  const client = getTeamNameClient();
  if (!client || typeof client.confirm !== 'function') {
    return false;
  }
  const normalizedInput = (name || '').trim().toLowerCase();
  const reserved = (activeReservation.name || '').toString().trim().toLowerCase();
  if (!normalizedInput || normalizedInput !== reserved) {
    return false;
  }
  try {
    const confirmed = await client.confirm((name || '').trim(), { reservation: activeReservation });
    if (confirmed) {
      activeReservation = null;
      return true;
    }
  } catch (error) {
    console.error('Team name confirmation failed', error);
  }
  releaseReservation();
  return false;
}

async function saveName(e) {
  e?.preventDefault();
  const name = nameInput.value.trim();
  if (!name) {
    clearStored('quizUser');
    clearStored(STORAGE_KEYS.PLAYER_NAME);
    releaseReservation();
    notify('Bitte gib einen Teamnamen ein.', 'warning');
    return;
  }

  const usingSuggestion = await confirmReservationIfMatching(name);
  if (!usingSuggestion && activeReservation) {
    releaseReservation();
  }

  let uid = getStored(STORAGE_KEYS.PLAYER_UID);
  if (!uid) {
    uid = self.crypto?.randomUUID ? self.crypto.randomUUID() : Math.random().toString(36).slice(2);
    setStored(STORAGE_KEYS.PLAYER_UID, uid);
  }

  let playerSaved = false;
  try {
    const response = await fetch('/api/players', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ event_uid: currentEventUid, player_name: name, player_uid: uid })
    });

    if (!response.ok) {
      if (response.status === 409 || response.status === 422) {
        let errorCode = '';
        try {
          const payload = await response.json();
          if (payload && typeof payload.error === 'string') {
            errorCode = payload.error;
          }
        } catch (parseError) {
          // ignore JSON parse failures for conflict responses
        }

        if (errorCode === 'name_taken') {
          notify('Dieser Name ist bereits vergeben.', 'warning');
        } else if (errorCode === 'name_blocked') {
          notify('Dieser Name ist nicht erlaubt.', 'warning');
        } else {
          notify('Fehler beim Speichern', 'danger');
        }
      } else {
        notify('Fehler beim Speichern', 'danger');
      }
      return;
    }

    playerSaved = true;
  } catch (error) {
    notify('Fehler beim Speichern', 'danger');
    return;
  }

  try {
    await postSession('player', { name });
  } catch (error) {
    if (playerSaved) {
      console.error('Failed to persist player name in session after saving to API', error);
    }
    notify('Fehler beim Speichern', 'danger');
    return;
  }

  setStored('quizUser', name);
  setStored(STORAGE_KEYS.PLAYER_NAME, name);
  notify('Name gespeichert', 'success');
  if (returnUrl) window.location.href = decodeURIComponent(returnUrl);
}

async function deleteName(e) {
  e?.preventDefault();
  releaseReservation();
  try {
    await deleteSession('player');
  } catch (error) {
    notify('Fehler beim Löschen', 'danger');
    return;
  }
  clearStored('quizUser');
  clearStored(STORAGE_KEYS.PLAYER_NAME);
  clearStored(STORAGE_KEYS.PLAYER_UID);
  nameInput.value = '';
  if (typeof nameInput.focus === 'function') {
    nameInput.focus();
  }
  notify('Name gelöscht', 'success');
}

document.addEventListener('DOMContentLoaded', async () => {
  nameInput = document.getElementById('playerName');
  const cfg = window.quizConfig || {};
  currentEventUid = window.getActiveEventId ? window.getActiveEventId() : '';
  const params = new URLSearchParams(location.search);
  const uidParam = params.get('uid') || params.get('player_uid');
  if (uidParam) {
    setStored(STORAGE_KEYS.PLAYER_UID, uidParam);
  }
  const storedName = getStored(STORAGE_KEYS.PLAYER_NAME);
  if (storedName) {
    nameInput.value = storedName;
  } else {
    nameInput.value = await requestNameSuggestion();
  }
  if (!storedName && uidParam && cfg.collectPlayerUid && currentEventUid) {
    fetch(`/api/players?event_uid=${encodeURIComponent(currentEventUid)}&player_uid=${encodeURIComponent(uidParam)}`)
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
