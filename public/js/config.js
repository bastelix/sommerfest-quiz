// Zentrale Konfiguration des Quizzes
// Diese Werte können über die Admin-Oberfläche angepasst und anschließend
// als neue config.js heruntergeladen werden.
window.quizConfig = {
  // optionaler Pfad zu einem eigenen Logo
  logoPath: '',

  // Titel im Browser-Tab
  pageTitle: 'Modernes Quiz mit UIkit',


  // Farbschema der Anwendung
  backgroundColor: '#ffffff',
  buttonColor: '#1e87f0',
  colors: {
    primary: '#1e87f0',
    accent: '#222222',
    light: {
      primary: '#1e87f0',
      secondary: '#222222'
    },
    dark: {
      primary: '#0f172a',
      secondary: '#93c5fd'
    }
  },
  startTheme: 'light',

  // Falls "no", wird der Button "Antwort prüfen" ausgeblendet
  CheckAnswerButton: 'no',

  // QR-Code-Login aktivieren und Namen speichern (true/false)
  QRUser: true,

  // Teilnahme auf bekannte Namen beschränken
  QRRestrict: false,

  // Zufällige Teamnamen verwenden (sonst Eingabeaufforderung)
  randomNames: true,

  // Fragen zufällig mischen
  shuffleQuestions: true,

  // Wettkampfmodus aktivieren
  competitionMode: false,

  // Ergebnisübersicht für Teams anzeigen
  teamResults: true,

  // Beweisfotos aktivieren
  photoUpload: true,

  // Spieler-UIDs sammeln und serverseitig speichern
  collectPlayerUid: false,

  // Countdown pro Frage aktivieren und Standardwert festlegen
  countdownEnabled: false,
  countdown: 0,

  // Rätselwort aktivieren und Begriff festlegen
  puzzleWordEnabled: true,
  puzzleWord: '',
  puzzleFeedback: ''
};
