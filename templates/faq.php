<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>FAQ</title>
  <link rel="stylesheet" href="/css/uikit.min.css">
  <link rel="stylesheet" href="/css/dark.css">
  <style>
    body {
      padding-top: 48px;
    }
    .topbar {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      z-index: 1000;
    }
    .theme-switch {
      display: inline-block;
      margin-left: 8px;
    }
    .theme-switch input {
      height: 0;
      width: 0;
      visibility: hidden;
    }
    .theme-switch-label {
      cursor: pointer;
      text-indent: -9999px;
      width: 40px;
      height: 20px;
      background: #ccc;
      display: block;
      border-radius: 100px;
      position: relative;
    }
    .theme-switch-label:after {
      content: '';
      position: absolute;
      top: 2px;
      left: 2px;
      width: 16px;
      height: 16px;
      background: #fff;
      border-radius: 90px;
      transition: 0.3s;
    }
    .theme-switch input:checked + .theme-switch-label {
      background: #1e87f0;
    }
    .theme-switch input:checked + .theme-switch-label:after {
      left: calc(100% - 2px);
      transform: translateX(-100%);
    }
  </style>
</head>
<body class="uk-background-muted uk-padding">
  <div class="uk-navbar-container topbar" uk-navbar>
    <div class="uk-navbar-left">
      <a href="/" class="uk-icon-button" uk-icon="arrow-left" title="Zurück" aria-label="Zurück"></a>
    </div>
    <div class="uk-navbar-right">
      <div class="theme-switch">
        <input type="checkbox" id="theme-toggle" aria-label="Design wechseln">
        <label for="theme-toggle" class="theme-switch-label">Design wechseln</label>
      </div>
    </div>
  </div>
  <div class="uk-container uk-container-small">
    <h1 class="uk-heading-divider">FAQ</h1>
    <p class="uk-text-lead">Auf dieser Seite findest du Antworten auf häufig gestellte Fragen rund um die Anwendung und Administration des Quiz.</p>

    <h2 id="anwendung" class="uk-heading-bullet">Anwendung</h2>
    <p>Dieser Abschnitt erklärt, wie du das Quiz nutzt, welche Funktionalitäten es bietet und was bei der Durchführung zu beachten ist.</p>
    <ul class="uk-accordion" uk-accordion>
      <li>
        <a class="uk-accordion-title" href="#">Wie starte ich das Quiz?</a>
        <div class="uk-accordion-content">
          <p>
            Öffne einfach die <code>index.html</code> im Browser oder rufe die gehostete Version auf. Danach folgst du den Anweisungen auf dem Bildschirm.
          </p>
        </div>
      </li>
      <li>
        <a class="uk-accordion-title" href="#">Benötige ich eine Internetverbindung?</a>
        <div class="uk-accordion-content">
          <p>Nein, alle Dateien liegen lokal vor. Eine Verbindung wird nur benötigt, wenn du das Quiz im Netz bereitstellst.</p>
        </div>
      </li>
      <li>
        <a class="uk-accordion-title" href="#">Welche Fragetypen gibt es?</a>
        <div class="uk-accordion-content">
          <p>Es stehen die Typen Sortieren, Zuordnen und Multiple Choice zur Verfügung.</p>
        </div>
      </li>
      <li>
        <a class="uk-accordion-title" href="#">Wie wird meine Punktzahl berechnet?</a>
        <div class="uk-accordion-content">
          <p>Jede korrekt beantwortete Frage bringt einen Punkt. Am Ende wird die Summe angezeigt.</p>
        </div>
      </li>
      <li>
        <a class="uk-accordion-title" href="#">Kann ich Fragen überspringen?</a>
        <div class="uk-accordion-content">
          <p>Nein, alle Fragen müssen der Reihe nach beantwortet werden, bevor das Ergebnis erscheint.</p>
        </div>
      </li>
      <li>
        <a class="uk-accordion-title" href="#">Werden meine Ergebnisse gespeichert?</a>
        <div class="uk-accordion-content">
          <p>Ja, die erzielten Punkte werden anonym im <code>localStorage</code> deines Browsers gespeichert.</p>
        </div>
      </li>
      <li>
        <a class="uk-accordion-title" href="#">Kann ich die Statistik herunterladen?</a>
        <div class="uk-accordion-content">
          <p>Auf der Startseite lässt sich eine Datei <code>statistical.log</code> exportieren, die alle bisherigen Ergebnisse enthält.</p>
        </div>
      </li>
      <li>
        <a class="uk-accordion-title" href="#">Wie funktioniert Drag & Drop beim Sortieren?</a>
        <div class="uk-accordion-content">
          <p>Halte ein Element mit der Maus oder dem Finger gedrückt und ziehe es an die gewünschte Position.</p>
        </div>
      </li>
      <li>
        <a class="uk-accordion-title" href="#">Kann ich das Quiz auf dem Smartphone spielen?</a>
        <div class="uk-accordion-content">
          <p>Ja, das Layout ist responsiv und passt sich auch kleinen Bildschirmen an.</p>
        </div>
      </li>
      <li>
        <a class="uk-accordion-title" href="#">Wie kann ich meine Antworten überprüfen?</a>
        <div class="uk-accordion-content">
          <p>Bei jeder Frage gibt es den Button <strong>Antwort prüfen</strong>, sofern er nicht vom Administrator deaktiviert wurde.</p>
        </div>
      </li>
    </ul>

    <h2 id="administration" class="uk-heading-bullet uk-margin-large-top">Administration</h2>
    <p>Hier erfährst du, wie das Quiz angepasst, Fragen bearbeitet und das Design verändert werden können.</p>
    <ul class="uk-accordion" uk-accordion>
      <li>
        <a class="uk-accordion-title" href="#">Wie gelange ich zur Admin-Oberfläche?</a>
        <div class="uk-accordion-content">
          <p>
            Rufe die Datei <code>admin.html</code> im Browser auf. Dort findest du alle Optionen zur Anpassung des Quizzes.
          </p>
        </div>
      </li>
      <li>
        <a class="uk-accordion-title" href="#">Wie ändere ich die Farben und Texte?</a>
        <div class="uk-accordion-content">
          <p>Im Tab "Veranstaltung konfigurieren" lassen sich Logo, Überschrift, Farben und weitere Einstellungen anpassen.</p>
        </div>
      </li>
      <li>
        <a class="uk-accordion-title" href="#">Kann ich eigene Fragen hinzufügen?</a>
        <div class="uk-accordion-content">
          <p>Ja, unter "Fragen bearbeiten" kannst du neue Fragen anlegen oder bestehende bearbeiten und löschen.</p>
        </div>
      </li>
      <li>
        <a class="uk-accordion-title" href="#">Wie speichere ich meine Änderungen?</a>
        <div class="uk-accordion-content">
          <p>Nach dem Klick auf <strong>Speichern</strong> wird eine neue <code>config.js</code> sowie eine JSON-Datei f&uuml;r den ausgew&auml;hlten Fragenkatalog erstellt. Kopiere die Dateien anschlie&szlig;end in die Ordner <code>js/</code> bzw. <code>kataloge/</code>.</p>
        </div>
      </li>
      <li>
        <a class="uk-accordion-title" href="#">Warum werden die Dateien nicht automatisch gespeichert?</a>
        <div class="uk-accordion-content">
          <p>Da das Quiz rein im Browser läuft, hat es keinen Schreibzugriff auf das Dateisystem. Deshalb müssen die geänderten Dateien manuell ersetzt werden.</p>
        </div>
      </li>
      <li>
        <a class="uk-accordion-title" href="#">Wie kann ich das Layout anpassen?</a>
        <div class="uk-accordion-content">
          <p>Du kannst eigene CSS-Dateien einbinden oder die vorhandenen UIkit-Klassen nutzen, um das Erscheinungsbild zu verändern.</p>
        </div>
      </li>
      <li>
        <a class="uk-accordion-title" href="#">Lassen sich Fragen importieren oder exportieren?</a>
        <div class="uk-accordion-content">
          <p>Aktuell müssen Fragen manuell in der Admin-Oberfläche erstellt werden. Ein automatischer Import ist nicht vorgesehen.</p>
        </div>
      </li>
      <li>
        <a class="uk-accordion-title" href="#">Wie setze ich das Quiz auf die Anfangswerte zurück?</a>
        <div class="uk-accordion-content">
          <p>Im Admin-Bereich gibt es die Buttons <strong>Zurücksetzen</strong>, die alle Eingaben auf ihre Ausgangswerte bringen.</p>
        </div>
      </li>
      <li>
        <a class="uk-accordion-title" href="#">Kann ich den Button "Antwort prüfen" ausblenden?</a>
        <div class="uk-accordion-content">
          <p>Ja, im Konfigurations-Tab findest du die Option <em>Antwort-Prüfen-Button anzeigen</em>. Setze sie auf "Nein".</p>
        </div>
      </li>
      <li>
        <a class="uk-accordion-title" href="#">Welche Software wird für das Hosting benötigt?</a>
        <div class="uk-accordion-content">
          <p>Ein einfacher HTTP-Server reicht aus. Im Repository liegt ein kleines Node.js-Skript (<code>server.js</code>) bei, mit dem sich die Dateien lokal bereitstellen lassen.</p>
        </div>
      </li>
    </ul>
  </div>

  <script src="/js/uikit.min.js"></script>
  <script src="/js/uikit-icons.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function(){
      const toggle = document.getElementById('theme-toggle');
      const isDark = localStorage.getItem('darkMode') === 'true';
      if(isDark){
        document.body.classList.add('dark-mode','uk-light');
        if(toggle) toggle.checked = true;
      }
      if(toggle){
        toggle.addEventListener('change', function(){
          if(this.checked){
            document.body.classList.add('dark-mode','uk-light');
            localStorage.setItem('darkMode', 'true');
          } else {
            document.body.classList.remove('dark-mode','uk-light');
            localStorage.setItem('darkMode', 'false');
          }
        });
      }
    });
  </script>
</body>
</html>
