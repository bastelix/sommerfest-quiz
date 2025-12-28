-- Rebind calServer page content to the existing calserver namespace/slug
WITH payload AS (
    SELECT $CALSERVER_PAGE$
{
  "id": "calserver",
  "locale": "de",
  "title": "calServer",
  "meta": {
    "rebuiltFromMigration": true,
    "reviewed": false
  },
  "blocks": [
    {
      "id": "hero",
      "type": "hero",
      "variant": "media-right",
      "data": {
        "headline": "Ein System. Klare Prozesse.",
        "subheadline": "Migration und Hybridbetrieb mit FLUKE MET/CAL werden planbar: Wir orchestrieren den Wechsel von MET/TRACK in den calServer, binden METTEAM sinnvoll ein und sichern auditfähige Nachweise ohne Unterbrechung.",
        "media": {
          "image": "uploads/calserver-module-device-management.webp",
          "alt": "Screenshot der calServer-Geräteverwaltung mit Geräteakte, Historie und Messwerten"
        },
        "ctaPrimary": {
          "label": "Demo buchen",
          "href": "https://calendly.com/calhelp/calserver-vorstellung"
        },
        "ctaSecondary": {
          "label": "Jetzt testen",
          "href": "#offer"
        }
      }
    },
    {
      "id": "why-calserver",
      "type": "feature_list",
      "variant": "text-columns",
      "data": {
        "title": "Funktionen, die den Alltag erleichtern",
        "subtitle": "Scroll & entdecke die wichtigsten Bereiche",
        "items": [
          {
            "id": "feature-inventare-intervalle",
            "title": "Inventare & Intervalle",
            "description": "Fälligkeiten kommen zu dir – Erinnerungen, Abrufe und Planungsübersichten sorgen für Ruhe.",
            "bullets": [
              "Automatische Erinnerungslogik",
              "Klare Statusfarben",
              "Planungs-Dashboards"
            ]
          },
          {
            "id": "feature-kalibrierscheine",
            "title": "Kalibrierscheine digitalisieren",
            "description": "Einfach hochladen, alles Weitere erledigt die Zuordnung mit Versionierung & Vorschau.",
            "bullets": [
              "Intelligente Dateinamen-Erkennung",
              "Versionen & Freigaben",
              "Teilen per Link"
            ]
          },
          {
            "id": "feature-email-abrufe",
            "title": "E-Mail-Abrufe & Erinnerungen",
            "description": "Persönlich und planbar – Serien mit System statt Einzelmails.",
            "bullets": [
              "Vorlagen & Platzhalter",
              "Zeitpläne je Zielgruppe",
              "Versandprotokoll"
            ]
          },
          {
            "id": "feature-geraeteverwaltung",
            "title": "Geräteverwaltung",
            "description": "Ob 100 oder 100.000 Geräte – Suche, Filter und Gruppen bleiben schnell.",
            "bullets": [
              "Schnellsuche & Filter",
              "Sets & Zubehör",
              "Exporte (CSV/PDF)"
            ]
          },
          {
            "id": "feature-kalibrier-reparatur",
            "title": "Kalibrier- & Reparaturverwaltung",
            "description": "Von Messwerten bis Bericht – ohne Medienbrüche, mit revisionssicheren Freigaben.",
            "bullets": [
              "Messwerte erfassen/importieren",
              "Bearbeitbare Übersichten",
              "Berichte direkt erzeugen"
            ]
          },
          {
            "id": "feature-auftragsbearbeitung",
            "title": "Auftragsbearbeitung",
            "description": "Ein Flow für alles: Angebot → Auftrag → Rechnung – mit eigenem Briefpapier.",
            "bullets": [
              "Sammel- & Teilrechnungen",
              "Preislisten & Nummernkreise",
              "Automatische Status"
            ]
          },
          {
            "id": "feature-leihverwaltung",
            "title": "Leihverwaltung",
            "description": "Reservieren statt Telefonkette – Kalender auf, Gerät rein, fertig.",
            "bullets": [
              "Drag-and-Drop Kalender",
              "Zubehör-Sets",
              "Rückgabe-Erinnerungen"
            ]
          },
          {
            "id": "feature-dokumentationen",
            "title": "Dokumentationen & Wiki",
            "description": "Wissen bleibt im Team – gepflegt, versioniert und durchsuchbar.",
            "bullets": [
              "Editor mit Inhaltsverzeichnis",
              "Versionen & Berechtigungen",
              "Interne Verlinkungen"
            ]
          },
          {
            "id": "feature-dms",
            "title": "Dateimanagement (DMS)",
            "description": "„Nur speichern, nicht sortieren“ – Zuordnung läuft im Hintergrund.",
            "bullets": [
              "Auto-Zuordnung",
              "Versionierung",
              "PDF-Viewer"
            ]
          },
          {
            "id": "feature-meldungen",
            "title": "Meldungen & Tickets",
            "description": "Alles an einem Ort: Anliegen, Verlauf, Benachrichtigungen.",
            "bullets": [
              "Zentrales Ticketing",
              "Teilnehmerwechsel",
              "Benachrichtigungsketten"
            ]
          },
          {
            "id": "feature-cloud",
            "title": "Moderne Cloud-Basis",
            "description": "Schnell, stabil und updatefreundlich – ohne großen Admin-Aufwand.",
            "bullets": [
              "Skalierbare Umgebung",
              "Regelmäßige Updates",
              "Tägliche Backups"
            ]
          }
        ]
      }
    },
    {
      "id": "core-modules",
      "type": "system_module",
      "variant": "showcase",
      "data": {
        "title": "Module, die den Unterschied machen",
        "subtitle": "Individuell kombinierbar – ohne versteckte Kosten",
        "items": [
          {
            "id": "module-device-management",
            "title": "Geräteverwaltung & Historie",
            "description": "Geräteakten, Anhänge und Historie in einer Oberfläche – inklusive Messwerten.",
            "media": {
              "image": "uploads/calserver-module-device-management.webp",
              "alt": "Screenshot der calServer-Geräteverwaltung mit Geräteakte, Historie und Messwerten"
            },
            "bullets": [
              "Geräte- & Standortverwaltung",
              "Versionierte Dokumente & Bilder",
              "Messwerte direkt verknüpfen"
            ]
          },
          {
            "id": "module-calendar-resources",
            "title": "Kalender & Ressourcen",
            "description": "Planung von Terminen, Leihgeräten und Personal in einer Ansicht.",
            "media": {
              "image": "uploads/calserver-module-calendar-resources.webp",
              "alt": "Screenshot des calServer-Kalenders mit Ressourcen- und Terminplanung"
            },
            "bullets": [
              "Gantt & Kalender",
              "Verfügbarkeits-Check in Echtzeit",
              "Outlook/iCal-Integration"
            ]
          },
          {
            "id": "module-order-ticketing",
            "title": "Auftrags- & Ticketverwaltung",
            "description": "Vom Auftrag bis zur Rechnung – mit klaren Status, Workflows und Dokumenten.",
            "media": {
              "image": "uploads/calserver-module-order-ticketing.webp",
              "alt": "Screenshot der calServer-Auftrags- und Ticketverwaltung mit Workflow-Status"
            },
            "bullets": [
              "Service & Ticketsystem",
              "Angebote, Aufträge, Rechnungen",
              "Eskalationen & SLAs"
            ]
          },
          {
            "id": "module-self-service",
            "title": "Self-Service & Extranet",
            "description": "Stellen Sie Kunden & Partnern Geräteinfos, Zertifikate und Formulare bereit.",
            "media": {
              "image": "uploads/calserver-module-self-service.webp",
              "alt": "Screenshot des calServer-Self-Service-Portals mit Kundenansicht und Zertifikaten"
            },
            "bullets": [
              "Kundenportale",
              "Dokumente & Zertifikate",
              "Individuelle Rechte"
            ]
          }
        ]
      }
    },
    {
      "id": "lifecycle",
      "type": "process_steps",
      "variant": "numbered-vertical",
      "data": {
        "title": "So fühlt sich calServer im Alltag an",
        "intro": "Vom ersten Login bis zur entspannten Audit-Vorbereitung: calServer nimmt dir Schritt für Schritt den Druck aus der Kalibrier- und Inventarverwaltung.",
        "summary": "FLUKE MET/CAL · MET/TRACK — Migration und Hybridbetrieb mit FLUKE MET/CAL werden planbar: Wir orchestrieren den Wechsel von MET/TRACK in den calServer, binden METTEAM sinnvoll ein und sichern auditfähige Nachweise ohne Unterbrechung.",
        "steps": [
          {
            "id": "trust-devices",
            "title": "Alle Geräte auf einen Blick",
            "description": "Importiere Bestandslisten oder starte direkt im Browser. calServer sammelt Stammdaten, Dokumente und Verantwortlichkeiten an einem Ort, damit nichts verloren geht."
          },
          {
            "id": "trust-deadlines",
            "title": "Fristen melden sich von selbst",
            "description": "Erinnerungen, Eskalationspfade und mobile Checklisten halten dein Team auf Kurs. Jede Person sieht sofort, welche Prüfaufträge heute wichtig sind."
          },
          {
            "id": "trust-audit",
            "title": "Auditbereit – jederzeit",
            "description": "Nachweise, Zertifikate und Gerätehistorien liegen revisionssicher bereit. Mit Hosting in Deutschland und täglichen Backups bist du auf Kontrollen vorbereitet."
          },
          {
            "id": "migration",
            "title": "Migration ohne Stillstand",
            "description": "Assessment bis Nachprüfung – klare Timeline, Dry-Run und Cut-over-Regeln.\n• Datenmapping für Kunden, Geräte, Historien und Dokumente\n• Delta-Sync & Freeze-Fenster für den Go-Live\n• Abnahmebericht mit KPIs und Korrekturschleifen"
          },
          {
            "id": "hybrid",
            "title": "Hybrid & METTEAM eingebunden",
            "description": "Synchronisation in beide Richtungen – MET/CAL läuft weiter, calServer erledigt Verwaltung und Berichte.\n• Klare Feldregeln mit Freigabe der letzten Änderung\n• Änderungsprotokoll, Abweichungslisten und erneuter Abgleich bei Konflikten\n• Pro Gerät aktivierbar – Daten und Historie sind sofort vorhanden"
          },
          {
            "id": "certificates",
            "title": "Auditfähige Zertifikate",
            "description": "DAkkS-taugliche Berichte – Guardband, Messunsicherheit und Konformitätsangaben sind vorbereitet.\n• Vorlagen in Deutsch und Englisch, inklusive QR-/Barcode und Versionierung\n• Standardtexte steuern Rückführbarkeit und Konformität\n• Endlich korrekte Anzeigen der erweiterten Messunsicherheit durch intelligente Feldformeln"
          }
        ],
        "closing": {
          "title": "Beruhigende Sicherheit für dein Team",
          "body": "DSGVO-konform, zuverlässig betreut und flexibel erweiterbar – calServer wächst mit deinen Abläufen und sorgt dafür, dass Termine, Rollen und Geräte harmonisch zusammenspielen."
        },
        "ctaPrimary": {
          "label": "Jetzt testen",
          "href": "#trial"
        },
        "ctaSecondary": {
          "label": "Demo buchen",
          "href": "https://calendly.com/calhelp/calserver-vorstellung"
        }
      }
    },
    {
      "id": "proof",
      "type": "proof",
      "variant": "metric-callout",
      "data": {
        "title": "Anwendungsfälle aus der Praxis",
        "subtitle": "Vom Labor bis zum Außendienst",
        "metrics": [
          {
            "id": "metrics-wishes",
            "value": "1.668",
            "label": "umgesetzte Kund:innen-Wünsche",
            "asOf": "Stand: 23.09.2025",
            "tooltip": "Priorisierte Kundenanforderungen, ausgeliefert und abgenommen.",
            "benefit": "Durch Community driven Engineering kundennahe Entwicklung."
          },
          {
            "id": "metrics-availability",
            "value": "99,9 %",
            "label": "Systemverfügbarkeit",
            "asOf": "Stand: 23.09.2025",
            "tooltip": "Zeitanteil, in dem der Service erreichbar war.",
            "benefit": "Sichere, planbare Abläufe im Alltag."
          },
          {
            "id": "metrics-years",
            "value": "> 15",
            "label": "Jahre am Markt",
            "asOf": "Stand: 23.09.2025",
            "tooltip": "calServer wird seit über 15 Jahren produktiv eingesetzt.",
            "benefit": "Erfahrung, Stabilität und gereifte Prozesse."
          }
        ],
        "marquee": [
          "Hosting in Deutschland",
          "DSGVO-konform",
          "Software Made in Germany",
          "REST-API & Webhooks",
          "Autom. Backups und weitere keys"
        ],
        "cases": [
          {
            "id": "ifm",
            "badge": "Kalibrierlabor",
            "title": "ifm – Störungsbearbeitung & Verbesserungen über zwei Standorte",
            "lead": "calServer steuert die interne Kalibrier- und Störungsbearbeitung über zwei Standorte hinweg.",
            "body": "Fehlermeldungen werden im Ticketmanagement strukturiert aufgenommen, priorisiert und nachverfolgt; gleichzeitig dient es der Chancen-/Risiken-Betrachtung für kontinuierliche Verbesserungen. Die bidirektionale Synchronisation mit Fluke MET/TEAM und MET/CAL hält Geräte- und Kalibrierdaten standortübergreifend konsistent.",
            "bullets": [
              "Ticketmanagement für Störungen & CAPA-nahe Auswertungen",
              "Standortübergreifende Geräteakten & Zertifikate im DMS",
              "Bidirektionale MET/TEAM- & MET/CAL-Synchronisation"
            ],
            "keyFacts": [
              "Zwei Standorte, konsistente Datenbasis",
              "Tickets + Chancen-/Risiken-Bewertung",
              "Bidirektionale Synchronisation mit Fluke MET/TEAM & MET/CAL"
            ],
            "media": {
              "image": "uploads/calserver-usecase-ifm-ticketmanagement.webp",
              "alt": "Screenshot des calServer-Ticketboards im ifm-Use-Case mit CAPA-Bewertung"
            }
          },
          {
            "id": "ksw",
            "badge": "Kalibrierlabor",
            "title": "KSW – End-to-End vom Wareneingang bis zur Rechnung",
            "lead": "calServer bildet den gesamten Ablauf von Wareneingang über Labor bis zur Abrechnung ab.",
            "body": "Laborbegleitscheine, Geräteakten und Zertifikate liegen zentral vor; Auftragsbearbeitung, Status und Kommunikation greifen ineinander — revisionssicher, schnell, transparent.",
            "bullets": [
              "Auftragsbearbeitung mit SLAs, Eskalation & Serienmails",
              "DMS für Zertifikate, Berichte und Historie",
              "Durchgängige Übergabe an Abrechnung & Reporting"
            ],
            "keyFacts": [
              "Wareneingang → Labor → Rechnung",
              "Auftragsbearbeitung als Taktgeber",
              "DMS & Reporting integriert"
            ],
            "media": {
              "image": "uploads/calserver-usecase-ksw-prozesskette.webp",
              "alt": "Screenshot des calServer-Prozessflusses von Wareneingang bis Abrechnung im KSW-Use-Case"
            }
          },
          {
            "id": "systems",
            "badge": "Kalibrierlabor",
            "title": "Systems Engineering – Auftragsbearbeitung als Herzstück",
            "lead": "calServer macht die Auftragsbearbeitung zum steuernden Zentrum des Labors.",
            "body": "Interne Aufgaben werden klar priorisiert, Reportings kommen direkt aus dem System und bleiben dank Versionierung nachvollziehbar — für verlässliche Termine und klare Zuständigkeiten.",
            "bullets": [
              "Aufgaben-/Rollensteuerung mit Status & Checklisten",
              "Eigene Reports aus Auftrags- und Gerätedaten",
              "DMS & Historie für Audit- und Nachweispflichten"
            ],
            "keyFacts": [
              "Klarer Status- & Rollenfluss",
              "Reports direkt aus den Prozessdaten",
              "Versionierung & Nachvollziehbarkeit (DMS)"
            ],
            "media": {
              "image": "uploads/calserver-usecase-systems-reporting.webp",
              "alt": "Screenshot der calServer-Auftragssteuerung und Reporting-Widgets im Systems-Engineering-Use-Case"
            }
          },
          {
            "id": "teramess",
            "badge": "Kalibrierlabor",
            "title": "TERAMESS – DAkkS-konforme Zertifikate in der Cloud",
            "lead": "calServer CLOUD bündelt DAkkS-konforme Zertifikate, Geräteakten und Prüfberichte.",
            "body": "Zertifikate werden revisionssicher abgelegt; Wiederhol- und Folgemessungen bleiben transparent nachvollziehbar. Kommunikation und Dokumente laufen in klaren, prüffesten Bahnen.",
            "bullets": [
              "Revisionssichere Ablage mit Versionierung",
              "Strukturierte Prüf- & Messhistorie",
              "Auswertungen & Serienexports für Kund:innenkommunikation"
            ],
            "keyFacts": [
              "Revisionssicheres DMS in der Cloud",
              "DAkkS-konforme Prüfhistorie",
              "Serienexports & Auswertungen"
            ],
            "media": {
              "image": "uploads/calserver-usecase-teramess-dakks.webp",
              "alt": "Screenshot der calServer-Zertifikatsverwaltung mit DAkkS-Historie im TERAMESS-Use-Case"
            }
          },
          {
            "id": "thermo",
            "badge": "Kalibrierlabor",
            "title": "Thermo Fisher Scientific – EMEA Labore",
            "lead": "calServer orchestriert EMEA-weit Leihgeräte, Geräteakten und ISO-/DAkkS-konforme Zertifikate auf einer Plattform.",
            "body": "Mehrere Labore arbeiten in einem konsistenten Workflow: Leihverwaltung, Prüffristen und Zertifikate werden zentral gesteuert, während Aufträge und Rückgaben standortübergreifend nachvollziehbar bleiben. Die bidirektionale Synchronisation mit Fluke MET/TEAM und MET/CAL hält Stammdaten, Kalibrierläufe und Ergebnisse automatisch aktuell.",
            "bullets": [
              "Zentrale Leihverwaltung mit Termin- & Rückgabe-Erinnerungen",
              "Revisionssicheres DMS für Zertifikate & Historie",
              "Bidirektionale MET/TEAM- & MET/CAL-Synchronisation"
            ],
            "keyFacts": [
              "EMEA-weit einheitliche Leihverwaltung & Geräteakten",
              "Revisionssicheres DMS für Zertifikate & Historie",
              "Bidirektionale Synchronisation mit Fluke MET/TEAM & MET/CAL"
            ],
            "media": {
              "image": "uploads/calserver-usecase-thermo-leihverwaltung.webp",
              "alt": "Screenshot der calServer-Leihgeräte- und Geräteaktenübersicht im Thermo-Fisher-Use-Case"
            }
          },
          {
            "id": "zf",
            "badge": "Industrielabor",
            "title": "ZF – API-Messwerte auf Kubernetes mit SSO",
            "lead": "calServer verbindet skalierbare Messwert-APIs auf Kubernetes mit SSO und Geräteakten-Management.",
            "body": "Messwerte fließen über Microservices automatisiert ein; Geräte, Zertifikate und Auswertungen bleiben im Zugriff der berechtigten Teams. Single Sign-On vereinfacht den Zugang, die bidirektionale Synchronisation mit Fluke MET/TEAM und MET/CAL stellt durchgehend konsistente Kalibrierdaten sicher.",
            "bullets": [
              "API-Ingestion von Messwerten (Microservices/Kubernetes)",
              "SSO (z. B. EntraID/Google) für nahtlosen Zugriff",
              "Bidirektionale MET/TEAM- & MET/CAL-Synchronisation"
            ],
            "keyFacts": [
              "Microservices auf Kubernetes",
              "SSO für schnellen, sicheren Zugang",
              "Bidirektionale Synchronisation mit Fluke MET/TEAM & MET/CAL"
            ],
            "media": {
              "image": "uploads/calserver-usecase-zf-messwerte-sso.webp",
              "alt": "Screenshot der calServer-Messwert-APIs und SSO-Konfiguration im ZF-Use-Case"
            }
          },
          {
            "id": "berlin",
            "badge": "Assetmanagement",
            "title": "Berliner Stadtwerke – Projekte & Wartung für erneuerbare Anlagen",
            "lead": "calServer steuert Projekte, Wartungspläne und Einsätze für regenerative Energieanlagen der Stadt Berlin.",
            "body": "Vom Maßnahmenplan bis zur Einsatzplanung: Teams behalten Verfügbarkeit, Leistung und Kosten im Blick. Checklisten, Offline-Fähigkeit und Eskalationslogik sichern die fristgerechte Abarbeitung. (Ohne MET/CAL/MET/TEAM – Fokus auf Projekt- & Wartungssteuerung.)",
            "bullets": [
              "Projekt- & Maßnahmensteuerung inkl. Einsatzplanung",
              "Geplante/ungeplante Wartung mit Checklisten & Offline-Modus",
              "Dashboards für Verfügbarkeit, Leistung & Kosten/Nutzen"
            ],
            "keyFacts": [
              "Stadtweite EE-Anlagen im Überblick",
              "Geplante & ungeplante Wartung aus einem System",
              "Dashboards für Verfügbarkeit & Performance"
            ],
            "media": {
              "image": "uploads/calserver-usecase-berlin-wartung.webp",
              "alt": "Screenshot der calServer-Wartungs- und Projektplanung für Berliner Stadtwerke"
            }
          },
          {
            "id": "vde",
            "badge": "Qualitätsmanagement",
            "title": "VDE – Agile Auftragssteuerung & Intranet",
            "lead": "calServer bündelt agile Auftragssteuerung und Dokumentenflüsse für Prüf- und Zertifizierungsprozesse.",
            "body": "Teams planen, priorisieren und dokumentieren Vorgänge auf einem anpassbaren Board; Freigaben sind versioniert, nachvollziehbar und auditfest. Rollen und Vorlagen verteilen Informationen zielgerichtet. Die bidirektionale Synchronisation mit Fluke MET/TEAM und MET/CAL sorgt für konsistente Mess- und Auftragsdaten.",
            "bullets": [
              "Agiles Auftragsboard mit SLAs, Eskalationen und Vorlagen",
              "Revisionssichere DMS-Ablage inkl. Freigabe-Workflow",
              "Bidirektionale MET/TEAM- & MET/CAL-Synchronisation"
            ],
            "keyFacts": [
              "Agiles Auftragsboard (SLAs, Eskalationslogik)",
              "Audit-Trails & versionierte Freigaben",
              "Bidirektionale Synchronisation mit Fluke MET/TEAM & MET/CAL"
            ],
            "media": {
              "image": "uploads/calserver-usecase-vde-auftragsboard.webp",
              "alt": "Screenshot des calServer-Auftragsboards mit DMS-Integration im VDE-Use-Case"
            }
          }
        ]
      }
    },
    {
      "id": "operating-models",
      "type": "feature_list",
      "variant": "operating-models",
      "data": {
        "title": "Betriebsarten, die zu Ihnen passen",
        "subtitle": "Wählen Sie, wie calServer betrieben wird: als sichere Cloud-Lösung oder in Ihrer eigenen Umgebung.",
        "items": [
          {
            "id": "cloud",
            "title": "Cloud",
            "description": "Die Cloud-Variante betreiben wir vollständig für Sie: Updates, Monitoring und Sicherheit bleiben bei uns, damit Ihr Team sich sofort auf die Arbeit mit calServer konzentrieren kann.",
            "bullets": [
              "Bereitstellung in wenigen Tagen",
              "Automatisierte Updates & Monitoring",
              "Backup-Strategie inklusive",
              "Rechenzentrum in Deutschland",
              "ISO 27001 zertifizierte Infrastruktur",
              "Flexible Nutzer:innenzahlen"
            ]
          },
          {
            "id": "on-prem",
            "title": "On-Premise",
            "description": "Mit der On-Premise-Variante läuft calServer in Ihrer Infrastruktur: Sie behalten volle Datenhoheit, wir begleiten Installation, Updates und binden bestehende Systeme nahtlos an.",
            "bullets": [
              "Betrieb im eigenen Netzwerk",
              "Unterstützung bei Installation & Updates",
              "Integration in bestehende Systeme",
              "Anbindung an Ihr Identity-Management",
              "Flexible Backup- und Wartungsfenster",
              "Support per SLA vereinbar"
            ]
          },
          {
            "id": "standard",
            "title": "Standard-Hosting",
            "description": "Für Teams, die schnell und zuverlässig starten wollen.",
            "bullets": [
              "Inventar-, Kalibrier- & Auftragsverwaltung",
              "Dokumentenmanagement (Basis-Kontingent)",
              "Tägliche Backups, SSL & Subdomain",
              "Basis-Updateservice (Security & regelmäßige Features)",
              "Rollen & Berechtigungen, Audit-fähige Historie",
              "Monatliche Abrechnung · Kündigungsfrist 30 Tage",
              "Erweiterungen (z. B. Speicher, SSO) zubuchbar"
            ],
            "badge": "Cloud in DE",
            "primaryCta": {
              "label": "Anfrage senden",
              "href": "#offer"
            },
            "secondaryCta": {
              "label": "Leistungsdetails",
              "href": "#modal-standard-hosting"
            }
          },
          {
            "id": "performance",
            "title": "Performance-Hosting",
            "description": "Mehr Leistung und Spielraum für wachsende Anforderungen.",
            "bullets": [
              "Erhöhte Performance & skalierbare Ressourcen",
              "Mehr Speicher, keine Moduleinschränkungen",
              "Priorisiertes Monitoring & Stabilität",
              "Tägliche Backups, SSL, Subdomain",
              "Rollen & Berechtigungen, Team-Workflows",
              "Monatliche Abrechnung · Kündigungsfrist 30 Tage",
              "Upgrade/Downgrade zwischen Plänen möglich"
            ],
            "badge": "Beliebt",
            "primaryCta": {
              "label": "Anfrage senden",
              "href": "#offer"
            },
            "secondaryCta": {
              "label": "Leistungsdetails",
              "href": "#modal-performance-hosting"
            }
          },
          {
            "id": "enterprise",
            "title": "Enterprise (On-Prem)",
            "description": "Volle Datenhoheit und individuelle Compliance.",
            "bullets": [
              "On-Prem-Betrieb in Ihrer Infrastruktur",
              "SSO (Azure/Google), erweiterte Integrationen",
              "Erweiterte Compliance & individuelle SLAs",
              "Optionale Synchronisationen (z. B. METBASE/METTEAM)",
              "Change-/Release-Management nach Vorgabe",
              "Monatliche Abrechnung · Kündigungsfrist 30 Tage",
              "Rollout & Betrieb nach gemeinsamem Migrationsplan"
            ],
            "badge": "Max. Kontrolle",
            "primaryCta": {
              "label": "Anfrage senden",
              "href": "#offer"
            },
            "secondaryCta": {
              "label": "Leistungsdetails",
              "href": "#modal-enterprise-hosting"
            }
          },
          {
            "id": "plans-disclaimer",
            "title": "Hinweis",
            "description": "Vollständige AGB, SLA und AVV auf Anfrage oder im Kundenportal einsehbar."
          },
          {
            "id": "faq-cloud-start",
            "title": "Wie schnell bin ich mit der Cloud-Version startklar?",
            "description": "In der Regel innerhalb weniger Tage – wir begleiten den Kick-off persönlich."
          },
          {
            "id": "faq-switch",
            "title": "Kann ich zwischen Cloud und On-Premise wechseln?",
            "description": "Ja, ein Wechsel ist jederzeit möglich. Wir unterstützen bei Migration und Datenübernahme."
          },
          {
            "id": "faq-import",
            "title": "Welche Datenimporte sind möglich?",
            "description": "Excel/CSV-Importe, API-Schnittstellen sowie individuelle Integrationen."
          },
          {
            "id": "faq-support",
            "title": "Wie funktioniert der Support?",
            "description": "Support per E-Mail, Telefon oder Ticketsystem – je nach Paket sogar mit SLA."
          },
          {
            "id": "faq-test-data",
            "title": "Was passiert mit meinen Daten nach dem Test?",
            "description": "Nach Testende entscheiden Sie: weiter nutzen, exportieren oder löschen lassen – ganz transparent."
          },
          {
            "id": "faq-follow-up",
            "title": "Noch nicht fündig geworden?",
            "description": "Weitere Fragen → Kontakt",
            "href": "#contact-form"
          }
        ]
      }
    },
    {
      "id": "cta-final",
      "type": "cta",
      "variant": "split",
      "data": {
        "title": "Bereit, calServer live zu erleben?",
        "body": "Jetzt testen oder Demo buchen – wir zeigen, wie Ihre Prozesse in calServer aussehen.",
        "primary": {
          "label": "Demo buchen",
          "href": "https://calendly.com/calhelp/calserver-vorstellung",
          "description": "Wir führen Sie durch calServer, beantworten Fragen und zeigen passende Workflows."
        },
        "secondary": {
          "label": "Jetzt testen",
          "href": "#offer",
          "description": "Starten Sie mit einer eigenen Umgebung und prüfen Sie calServer mit Ihren Prozessen."
        }
      }
    }
  ]
}
$CALSERVER_PAGE$::text AS content
)
UPDATE pages AS p
SET content = payload.content,
    content_source = 'db',
    updated_at = CURRENT_TIMESTAMP
FROM payload
WHERE p.namespace IN ('calserver', 'default')
  AND p.slug = 'calserver';
