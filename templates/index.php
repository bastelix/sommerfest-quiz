<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Modernes Quiz mit UIkit</title>
  <link rel="stylesheet" href="/css/uikit.min.css">
  <link rel="stylesheet" href="/css/dark.css">
  <style>
    body {
      min-height: 100vh;
    }
    .sortable-list li,
    .terms li,
    .dropzone,
    .mc-option {
      cursor: grab;
      background: #f3f7fa;
      border: 1px solid #ddd;
      border-radius: 8px;
      padding: 16px;
      margin-bottom: 12px;
      font-size: 1rem;
    }

    .mc-option {
      display: block;
    }
    .mc-option input {
      transform: scale(1.2);
      margin-right: 8px;
    }

    .sortable-list li:focus {
      outline: 2px solid #39f;
    }
    .dropzone {
      min-height: 3.5em;
      background: #f3f7fa;
      border: 1.5px dashed #aaa;
      border-radius: 8px;
      margin-bottom: 12px;
      display: flex;
      align-items: center;
      padding: 8px 12px;
    }
    .dropzone.over {
      border-color: #39f;
      background: #e0f7fa;
    }
    .dropzone:focus {
      border-color: #39f;
      outline: none;
    }
    .question {
      animation: fadeIn 0.3s ease;
      margin-top: 1em;
    }
    #results-slideshow {
      position: relative;
      min-height: 2.5em;
    }
    .result-slide {
      position: absolute;
      width: 100%;
      top: 0;
      left: 0;
    }
    .theme-switch {
      position: fixed;
      top: 8px;
      left: 8px;
      z-index: 1000;
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
    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }
    /* Link-Größe an Fließtext anpassen */
    a.uk-accordion-title {
      font-size: 1rem;
    }

    @media (min-width: 640px) {
      .sortable-list li,
      .terms li,
      .dropzone,
      .mc-option {
        font-size: 1.3rem;
      }
    }

    @media (min-width: 960px) {
      .sortable-list li,
      .terms li,
      .dropzone,
      .mc-option {
        font-size: 1.5rem;
      }
      .mc-option input {
        transform: scale(1.3);
      }
    }
    @media (max-width: 639px) {
      .uk-container,
      body.uk-padding {
        padding-left: 0;
        padding-right: 0;
      }
    }
  </style>
</head>
<body class="uk-padding uk-flex uk-flex-center">
  <a href="faq.html" class="uk-icon-button uk-position-fixed uk-position-top-right uk-margin-small-right uk-margin-small-top" uk-icon="question" title="Hilfe" aria-label="Hilfe"></a>
  <div class="theme-switch">
    <input type="checkbox" id="theme-toggle" aria-label="Design wechseln">
    <label for="theme-toggle" class="theme-switch-label">Design wechseln</label>
  </div>
  <div class="uk-container uk-width-1-1 uk-width-1-2@s uk-width-2-3@m">
    <div class="uk-card uk-card-default uk-card-body uk-box-shadow-large uk-margin">
      <div id="quiz-header" class="uk-text-center uk-margin"></div>
      <progress id="progress" class="uk-progress" value="0" max="1" aria-label="Fortschritt"></progress>
      <div id="quiz"></div>
    </div>
    <div class="uk-card uk-card-default uk-card-body uk-margin">
      <h3 class="uk-heading-bullet">Informationen zur Quiz-Anwendung</h3>
      <p>Dieses Tool ist ein reiner Prototyp, der zu 100% mit Codex von OpenAI umgesetzt wurde. Die Arbeit diente ausschließlich der Erprobung des Coding-Assistenten und seiner Möglichkeiten.</p>
      <p>Der Quellcode befindet sich auf GitHub: <a href="https://github.com/bastelix/sommerfest-quiz">https://github.com/bastelix/sommerfest-quiz</a>.</p>
      <ul uk-accordion>
        <li>
          <a class="uk-accordion-title" href="#">Der Code steht unter der MIT-Lizenz. Siehe die Datei <code>LICENSE</code> f&uuml;r Details.</a>
          <div class="uk-accordion-content">
            <pre><code>MIT License

Copyright (c) 2025 calhelp

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the &quot;Software&quot;), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED &quot;AS IS&quot;, WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.</code></pre>
          </div>
        </li>
      </ul>
      <p><strong>Datenschutz:</strong> Das Quiz läuft komplett im Browser und speichert Ergebnisse nur lokal im <code>localStorage</code>. Es werden keine personenbezogenen Daten an einen Server übertragen. Beim Export der Datei <code>statistical.log</code> werden lediglich Pseudonyme und Punktzahlen gespeichert.</p>
      </div>
  </div>
  <script src="/js/uikit.min.js"></script>
  <script src="/js/uikit-icons.min.js"></script>
  <script src="https://unpkg.com/html5-qrcode@2.3.7/html5-qrcode.min.js"></script>
  <script src="/js/config.js"></script>
  <script id="catalogs-data" type="application/json">
    [
      {
        "id": "fragen_basis",
        "file": "fragen_basis.json",
        "name": "Basisfragen",
        "description": "Beispielkatalog mit allgemeinen Fragen"
      },
      {
        "id": "fragen_it",
        "file": "fragen_it.json",
        "name": "IT-Katalog",
        "description": "Fragen rund um Computer und Technik"
      }
    ]
  </script>
  <script id="fragen_basis-data" type="application/json">
    [
      {
        "type": "sort",
        "prompt": "Bringe die Schritte zum Serienbrief in die richtige Reihenfolge:",
        "items": [
          "Datenquelle (Tabelle) erstellen",
          "Neues Dokument von Vorlage anlegen",
          "Serienbrieffunktion in Word starten",
          "Abgangsvermerk am Dokument anbringen"
        ]
      },
      {
        "type": "assign",
        "prompt": "Ordne die Begriffe den Definitionen zu:",
        "terms": [
          {"term": "Akte", "definition": "Sammlung von Vorg\u00e4ngen und Dokumenten"},
          {"term": "OE", "definition": "Organisationseinheit mit Rechten an der Akte"},
          {"term": "Snapshot", "definition": "PDF mit Status zum Zeichnungsprozess"}
        ]
      },
      {
        "type": "mc",
        "prompt": "Wer hat automatisch Schreibrechte an einer Akte?",
        "options": [
          "Nur die Fachadministration",
          "Die besitzende OE",
          "Alle Nutzer"
        ],
        "answers": [1]
      },
      {
        "type": "mc",
        "prompt": "In welchem Monat beginnt der Sommer?",
        "options": ["Januar", "M\u00e4rz", "Juni", "September"],
        "answers": [2]
      },
      {
        "type": "sort",
        "prompt": "Sortiere die Jahreszeiten chronologisch:",
        "items": ["Fr\u00fchling", "Sommer", "Herbst", "Winter"]
      }
    ]
  </script>
  <script id="fragen_it-data" type="application/json">
    [
      {
        "type": "mc",
        "prompt": "Welches Protokoll wird f\u00fcr verschl\u00fcsselte Webseiten verwendet?",
        "options": ["FTP", "HTTP", "HTTPS"],
        "answers": [2]
      },
      {
        "type": "sort",
        "prompt": "Sortiere die Speichermedien nach Geschwindigkeit (langsam zu schnell):",
        "items": ["DVD", "HDD", "SSD"]
      },
      {
        "type": "assign",
        "prompt": "Ordne die Begriffe den passenden Erkl\u00e4rungen zu:",
        "terms": [
          {"term": "RAM", "definition": "Fl\u00fcchtiger Arbeitsspeicher"},
          {"term": "CPU", "definition": "Zentrale Recheneinheit"},
          {"term": "GPU", "definition": "Grafikprozessor"}
        ]
      },
      {
        "type": "mc",
        "prompt": "Welches Betriebssystem ist quelloffen?",
        "options": ["Windows", "macOS", "Linux"],
        "answers": [2]
      },
      {
        "type": "sort",
        "prompt": "Bringe die Netzwerkger\u00e4te nach Reichweite in die richtige Reihenfolge:",
        "items": ["Bluetooth", "WLAN", "Mobilfunk"]
      }
    ]
  </script>
  <script src="/js/catalog.js"></script>
  <script src="/js/confetti.js"></script>
  <script src="/js/quiz.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function(){
      const toggle = document.getElementById('theme-toggle');
      const isDark = localStorage.getItem('darkMode') === 'true';
      if(isDark){
        document.body.classList.add('dark-mode','uk-light');
        toggle.checked = true;
      }
      toggle.addEventListener('change', function(){
        if(this.checked){
          document.body.classList.add('dark-mode','uk-light');
          localStorage.setItem('darkMode', 'true');
        } else {
          document.body.classList.remove('dark-mode','uk-light');
          localStorage.setItem('darkMode', 'false');
        }
      });
    });
  </script>
</body>
</html>
