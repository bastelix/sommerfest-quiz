// Hier sind alle Quizfragen hinterlegt.
// Jede Frage besitzt einen Typ (sort, assign, mc) sowie die benötigten Daten.
window.quizQuestions = [
  {
    // Sortier-Aufgabe: Items müssen in die richtige Reihenfolge gebracht werden
    type: 'sort',
    prompt: 'Bringe die Schritte zum Serienbrief in die richtige Reihenfolge:',
    items: [
      'Datenquelle (Tabelle) erstellen',
      'Neues Dokument von Vorlage anlegen',
      'Serienbrieffunktion in Word starten',
      'Abgangsvermerk am Dokument anbringen'
    ]
  },
  {
    // Zuordnungs-Aufgabe: Begriffe den Definitionen zuweisen
    type: 'assign',
    prompt: 'Ordne die Begriffe den Definitionen zu:',
    terms: [
      { term: 'Akte', definition: 'Sammlung von Vorgängen und Dokumenten' },
      { term: 'OE', definition: 'Organisationseinheit mit Rechten an der Akte' },
      { term: 'Snapshot', definition: 'PDF mit Status zum Zeichnungsprozess' }
    ]
  },
  {
    // Multiple-Choice-Frage: eine oder mehrere Antworten auswählen
    type: 'mc',
    prompt: 'Wer hat automatisch Schreibrechte an einer Akte?',
    options: [
      'Nur die Fachadministration',
      'Die besitzende OE',
      'Alle Nutzer'
    ],
    answers: [1]
  }
];
