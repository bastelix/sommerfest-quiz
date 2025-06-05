// Sortieren
let draggedSortItem;
document.querySelectorAll("#sortable li").forEach(li => {
  li.addEventListener('dragstart', e => draggedSortItem = li);
  li.addEventListener('dragover', e => e.preventDefault());
  li.addEventListener('drop', function() {
    if (draggedSortItem !== this) {
      this.parentNode.insertBefore(draggedSortItem, this.nextSibling);
    }
  });
});
window.checkSort = function() {
  const right = [
    "Datenquelle (Tabelle) erstellen",
    "Neues Dokument von Vorlage anlegen",
    "Serienbrieffunktion in Word starten",
    "Abgangsvermerk am Dokument anbringen"
  ];
  const current = Array.from(document.querySelectorAll('#sortable li')).map(li => li.textContent.trim());
  document.getElementById('sortFeedback').innerHTML =
    JSON.stringify(right) === JSON.stringify(current)
    ? '<div class="uk-alert-success" uk-alert>✅ Richtig sortiert!</div>'
    : '<div class="uk-alert-danger" uk-alert>❌ Leider falsch, versuche es nochmal!</div>';
};

// Zuordnen
let draggedTerm = null;
document.querySelectorAll("#terms li").forEach(li => {
  li.addEventListener('dragstart', e => {
    draggedTerm = li;
  });
});
document.querySelectorAll(".dropzone").forEach(zone => {
  zone.addEventListener('dragover', e => {
    e.preventDefault();
    zone.classList.add('over');
  });
  zone.addEventListener('dragleave', e => zone.classList.remove('over'));
  zone.addEventListener('drop', e => {
    zone.classList.remove('over');
    if (draggedTerm) {
      zone.innerHTML = draggedTerm.textContent;
      zone.dataset.dropped = draggedTerm.getAttribute('data-term');
      draggedTerm.style.visibility = "hidden";
      draggedTerm = null;
    }
  });
});
window.checkAssign = function() {
  let allCorrect = true;
  document.querySelectorAll(".dropzone").forEach(zone => {
    if (zone.dataset.term !== zone.dataset.dropped) allCorrect = false;
  });
  document.getElementById('assignFeedback').innerHTML =
    allCorrect
    ? '<div class="uk-alert-success" uk-alert>✅ Alles richtig zugeordnet!</div>'
    : '<div class="uk-alert-danger" uk-alert>❌ Nicht alle Zuordnungen sind korrekt.</div>';
};

// Multiple Choice
document.getElementById('mcForm').onsubmit = function(e){
  e.preventDefault();
  const v = document.querySelector('input[name="mc"]:checked');
  document.getElementById('mcFeedback').innerHTML =
    (v && v.value == "1")
    ? '<div class="uk-alert-success" uk-alert>✅ Korrekt! Die besitzende OE hat Schreibrechte.</div>'
    : '<div class="uk-alert-danger" uk-alert>❌ Das ist nicht korrekt.</div>';
}
