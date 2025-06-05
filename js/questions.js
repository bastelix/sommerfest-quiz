window.quizQuestions = [
  {
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
    type: 'assign',
    prompt: 'Ordne die Begriffe den Definitionen zu:',
    terms: [
      { term: 'Akte', definition: 'Sammlung von Vorg\u00e4ngen und Dokumenten' },
      { term: 'OE', definition: 'Organisationseinheit mit Rechten an der Akte' },
      { term: 'Snapshot', definition: 'PDF mit Status zum Zeichnungsprozess' }
    ]
  },
  {
    type: 'mc',
    prompt: 'Wer hat automatisch Schreibrechte an einer Akte?',
    options: [
      'Nur die Fachadministration',
      'Die besitzende OE',
      'Alle Nutzer'
    ],
    answer: 1
  }
];
