// Zentrale Konfiguration des Quizzes
// Diese Werte können über die Admin-Oberfläche angepasst und anschließend
// als neue config.js heruntergeladen werden.
window.quizConfig = {
  // optionaler Pfad zu einem eigenen Logo
  logoPath: '',

  // Titel im Browser-Tab
  pageTitle: 'Modernes Quiz mit UIkit',

  // Überschrift und Untertitel auf der Startseite
  header: 'Sommerfest 2025',
  subheader: 'Willkommen beim Veranstaltungsquiz',

  // Farbschema der Anwendung
  backgroundColor: '#ffffff',
  buttonColor: '#1e87f0',

  // Falls "no", wird der Button "Antwort prüfen" ausgeblendet
  CheckAnswerButton: 'no',

  // QR-Code-Login aktivieren (true/false)
  QRUser: true,

  // Teilnahme auf bekannte Namen beschränken
  QRRestrict: false,

  // Wettkampfmodus aktivieren
  competitionMode: false,

  // Ergebnisübersicht für Teams anzeigen
  teamResults: true,

  // Rätselwort aktivieren und Begriff festlegen
  puzzleWordEnabled: true,
  puzzleWord: ''
};
