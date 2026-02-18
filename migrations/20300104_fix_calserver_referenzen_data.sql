-- Fix calserver page: ensure config row exists, set custom CSS + design tokens,
-- and fix referenzen block data (HTML body → plain text, add keyFacts).
--
-- Root cause: Earlier migrations (20260217, 20300103) tried to INSERT into config
-- with event_uid='calserver' while the FK constraint to events(uid) still existed.
-- The FK was only dropped in 20290625_allow_namespace_config.sql, but the INSERT
-- was attempted before that. This migration runs after the FK drop, so the INSERT
-- succeeds reliably.

-- ── 1. Ensure config row exists (FK to events was dropped in 20290625) ──

INSERT INTO config (event_uid)
SELECT 'calserver'
WHERE NOT EXISTS (SELECT 1 FROM config WHERE event_uid = 'calserver');

-- ── 2. Set namespace custom CSS ──

UPDATE config
SET custom_css = $CSS$/* ── calServer page namespace styles (2026-02) ── */

/* ── Typography ── */
[data-namespace="calserver"] {
  --font-family-heading: 'Plus Jakarta Sans', 'Poppins', system-ui, sans-serif;
  --font-family-body: 'Plus Jakarta Sans', 'Poppins', system-ui, sans-serif;
  scroll-behavior: smooth;
}
[data-namespace="calserver"] h1,
[data-namespace="calserver"] h2,
[data-namespace="calserver"] h3,
[data-namespace="calserver"] h4,
[data-namespace="calserver"] .uk-heading-medium,
[data-namespace="calserver"] .uk-heading-small {
  font-family: var(--font-family-heading);
}

/* ── Hero section (navy gradient, compact padding) ── */
[data-namespace="calserver"] .section[data-section-intent="hero"] {
  background: linear-gradient(170deg, #0b1a2e 0%, #122240 100%) !important;
  --section-padding-outer: clamp(20px, 3vw, 40px);
  --section-padding-inner: clamp(16px, 2.5vw, 28px);
  padding-top: clamp(20px, 3vw, 40px);
  padding-bottom: clamp(20px, 3vw, 40px);
}
[data-namespace="calserver"] .section[data-section-intent="hero"].section--full .section__inner {
  padding: clamp(16px, 2.5vw, 28px);
}
[data-namespace="calserver"] .section[data-section-intent="hero"] .uk-heading-medium {
  color: #fff;
  font-weight: 800;
  font-size: clamp(2.4rem, 5vw, 3.4rem);
  line-height: 1.1;
  margin-bottom: 0.5em;
}
[data-namespace="calserver"] .section[data-section-intent="hero"] .uk-text-lead {
  color: rgba(255, 255, 255, 0.85);
  font-size: clamp(1.05rem, 1.8vw, 1.25rem);
  line-height: 1.5;
}

/* Stat tiles inside hero */
[data-namespace="calserver"] .cs-stat-tile {
  background: rgba(255, 255, 255, 0.07) !important;
  border: 1px solid rgba(255, 255, 255, 0.10);
  text-align: center;
  padding: 1.2rem 1rem;
}
[data-namespace="calserver"] .cs-stat-num {
  font-size: clamp(2rem, 3.5vw, 2.8rem);
  font-weight: 800;
  font-family: var(--font-family-heading);
  color: #58a6ff;
  line-height: 1.1;
}
[data-namespace="calserver"] .cs-stat-tile .uk-text-small {
  font-size: 0.82rem;
}
[data-namespace="calserver"] .section[data-section-intent="hero"] .cs-stat-tile .uk-text-muted {
  color: rgba(255, 255, 255, 0.65) !important;
}

/* Hero CTA buttons */
[data-namespace="calserver"] .section[data-section-intent="hero"] .uk-button-primary {
  background: #1a73e8 !important;
  color: #fff !important;
  border-color: #1a73e8 !important;
  border-radius: 100px;
  padding: 0 2.2rem;
  font-weight: 600;
  font-size: 0.95rem;
  line-height: 44px;
  height: 44px;
}
[data-namespace="calserver"] .section[data-section-intent="hero"] .uk-button-primary:hover {
  background: #1557b0 !important;
}
[data-namespace="calserver"] .section[data-section-intent="hero"] .uk-button-default {
  color: rgba(255, 255, 255, 0.9);
  border-color: rgba(255, 255, 255, 0.25);
  border-radius: 100px;
  padding: 0 2.2rem;
  font-weight: 600;
  font-size: 0.95rem;
  line-height: 44px;
  height: 44px;
}
[data-namespace="calserver"] .section[data-section-intent="hero"] .uk-button-default:hover {
  background: rgba(255, 255, 255, 0.1);
  color: #fff;
}
/* Hero CTA group spacing */
[data-namespace="calserver"] .hero-cta-group {
  gap: 0.75rem;
}

/* ── ProvenExpert badge ── */
[data-namespace="calserver"] .cs-proven-expert {
  gap: 0.75rem;
}
[data-namespace="calserver"] .cs-proven-expert__stars {
  display: flex;
  gap: 2px;
}
[data-namespace="calserver"] .cs-star {
  width: 18px;
  height: 18px;
  fill: #eab308;
}
[data-namespace="calserver"] .cs-proven-expert__text .uk-text-small {
  color: rgba(255, 255, 255, 0.9);
}
[data-namespace="calserver"] .cs-proven-expert__text .uk-text-meta {
  color: rgba(255, 255, 255, 0.55);
}

/* ── Trust bar ── */
[data-namespace="calserver"] .section[data-block-variant="trust_bar"] {
  background: var(--global-muted-background, #f8f8f8);
  border-top: 1px solid rgba(0, 0, 0, 0.06);
  border-bottom: 1px solid rgba(0, 0, 0, 0.08);
}
[data-namespace="calserver"] .section[data-block-variant="trust_bar"] .uk-subnav-divider > li {
  padding-left: 1rem;
}

/* ── Blue icon accent ── */
[data-namespace="calserver"] .cs-blue {
  color: #1a73e8 !important;
}

/* ── Feature cards (grid-bullets) ── */
[data-namespace="calserver"] .section[data-block-type="feature_list"] .uk-card {
  border-top: 3px solid #1a73e8;
  border-radius: 10px;
  transition: box-shadow 0.2s ease, transform 0.2s ease;
}
[data-namespace="calserver"] .section[data-block-type="feature_list"] .uk-card:hover {
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
  transform: translateY(-2px);
}

/* ── Product screenshots (info_media:switcher) ── */
[data-namespace="calserver"] .section[data-block-type="info_media"] .uk-subnav > li > a {
  font-weight: 600;
}

/* ── Referenzen (audience_spotlight:tabs) ── */
[data-namespace="calserver"] .section[data-block-type="audience_spotlight"] .uk-card {
  border-radius: 10px;
}

/* ── Testimonial slider ── */
[data-namespace="calserver"] .cs-quote-deco {
  font-size: 4.5rem;
  line-height: 1;
  color: rgba(26, 115, 232, 0.12);
  font-family: Georgia, serif;
  pointer-events: none;
}
[data-namespace="calserver"] .cs-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: linear-gradient(135deg, #1a73e8, #58a6ff);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 0.85rem;
  letter-spacing: 0.02em;
}
[data-namespace="calserver"] .section[data-block-type="testimonial"] .uk-card {
  border-radius: 10px;
  position: relative;
  overflow: hidden;
}

/* ── Pricing cards ── */
[data-namespace="calserver"] .section[data-block-type="package_summary"] .uk-card {
  border-radius: 10px;
  transition: box-shadow 0.2s ease, transform 0.2s ease;
}
[data-namespace="calserver"] .section[data-block-type="package_summary"] .uk-card:hover {
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
  transform: translateY(-2px);
}
/* Popular plan ring */
[data-namespace="calserver"] .section[data-block-type="package_summary"] .uk-card:has(.uk-label) {
  box-shadow: 0 0 0 2px #1a73e8;
}
[data-namespace="calserver"] .section[data-block-type="package_summary"] .uk-label {
  background: #1a73e8;
  border-radius: 4px;
}
[data-namespace="calserver"] .section[data-block-type="package_summary"] .uk-button-primary {
  border-radius: 100px;
  background: #1a73e8;
  font-weight: 600;
}
[data-namespace="calserver"] .section[data-block-type="package_summary"] .uk-button-primary:hover {
  background: #1557b0;
}

/* ── Founder card ── */
[data-namespace="calserver"] .cs-link-btn {
  border-radius: 100px;
  font-size: 0.82rem;
}

/* ── CTA section (highlight intent – navy gradient) ── */
[data-namespace="calserver"] .section[data-section-intent="highlight"] {
  background: linear-gradient(170deg, #0b1a2e 0%, #122240 100%) !important;
}
[data-namespace="calserver"] .section[data-section-intent="highlight"] .uk-heading-medium {
  color: #fff;
  font-weight: 800;
}
[data-namespace="calserver"] .section[data-section-intent="highlight"] .uk-text-lead {
  color: rgba(255, 255, 255, 0.85);
}
[data-namespace="calserver"] .section[data-section-intent="highlight"] .uk-button-primary {
  background: #1a73e8 !important;
  color: #fff !important;
  border-color: #1a73e8 !important;
  border-radius: 100px;
  padding: 0 2rem;
  font-weight: 600;
}
[data-namespace="calserver"] .section[data-section-intent="highlight"] .uk-button-primary:hover {
  background: #1557b0 !important;
}
[data-namespace="calserver"] .section[data-section-intent="highlight"] .uk-button-default {
  color: rgba(255, 255, 255, 0.9);
  border-color: rgba(255, 255, 255, 0.25);
  border-radius: 100px;
  padding: 0 2rem;
  font-weight: 600;
}
[data-namespace="calserver"] .section[data-section-intent="highlight"] .uk-button-default:hover {
  background: rgba(255, 255, 255, 0.1);
  color: #fff;
}

/* ── General rounded buttons for calServer ── */
[data-namespace="calserver"] .section .uk-button {
  border-radius: 100px;
}

/* ── Eyebrow tag style (green hosted badge) ── */
[data-namespace="calserver"] .section[data-section-intent="hero"] .hero-eyebrow-tag {
  background: rgba(52, 211, 153, 0.15);
  color: #34d399;
  border-radius: 100px;
  font-size: 0.82rem;
  padding: 0.35rem 1rem;
  font-weight: 600;
  letter-spacing: 0.03em;
  text-transform: none;
  border: 1px solid rgba(52, 211, 153, 0.3);
}
[data-namespace="calserver"] .section[data-section-intent="hero"] .hero-eyebrow-tag::before {
  content: "";
  display: inline-block;
  width: 14px;
  height: 14px;
  margin-right: 6px;
  background: currentColor;
  -webkit-mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'%3E%3Cpath fill='currentColor' d='M16.707 5.293a1 1 0 0 1 0 1.414l-8 8a1 1 0 0 1-1.414 0l-4-4a1 1 0 1 1 1.414-1.414L8 12.586l7.293-7.293a1 1 0 0 1 1.414 0z'/%3E%3C/svg%3E") no-repeat center / contain;
  mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'%3E%3Cpath fill='currentColor' d='M16.707 5.293a1 1 0 0 1 0 1.414l-8 8a1 1 0 0 1-1.414 0l-4-4a1 1 0 1 1 1.414-1.414L8 12.586l7.293-7.293a1 1 0 0 1 1.414 0z'/%3E%3C/svg%3E") no-repeat center / contain;
  vertical-align: -2px;
}

/* ── Section header label/eyebrow ── */
[data-namespace="calserver"] .section .uk-text-meta {
  color: #1a73e8;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  font-size: 0.8rem;
}

/* ── Logo blue border ── */
[data-namespace="calserver"] .cs-logo__image {
  border: 2px solid #1a73e8;
  border-radius: 8px;
  padding: 3px;
  display: inline-flex;
  align-items: center;
}

/* ── Navbar CTA button ── */
[data-namespace="calserver"] .cs-nav-cta {
  border-radius: 100px;
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  padding: 0 1.2rem;
  margin-right: 0.5rem;
}

/* ── General card radius ── */
[data-namespace="calserver"] .uk-card {
  border-radius: 10px;
}

/* ── FAQ section ── */
[data-namespace="calserver"] .section[data-block-type="faq"] .uk-accordion-title {
  font-weight: 600;
}

/* ── Logo / topbar styling ── */
[data-namespace="calserver"] .uk-logo {
  font-weight: 700;
  font-size: 1.2rem;
  letter-spacing: -0.02em;
  font-family: var(--font-family-heading, 'Plus Jakarta Sans', system-ui, sans-serif);
  color: #0b1a2e;
}
[data-namespace="calserver"] .qr-topbar .uk-navbar-container {
  border-bottom: 1px solid rgba(0, 0, 0, 0.08);
}

/* ── Section heading sizes ── */
[data-namespace="calserver"] .section h2,
[data-namespace="calserver"] .section .uk-heading-small {
  font-size: clamp(1.6rem, 3.5vw, 2.2rem);
  font-weight: 700;
  line-height: 1.15;
}
[data-namespace="calserver"] .section .uk-text-lead {
  font-size: clamp(1rem, 1.6vw, 1.15rem);
  line-height: 1.55;
}
$CSS$
WHERE event_uid = 'calserver';

-- ── 3. Set design tokens ──

UPDATE config
SET design_tokens = jsonb_set(
  jsonb_set(
    jsonb_set(
      COALESCE(design_tokens, '{}')::jsonb,
      '{brand,primary}', '"#1a73e8"'
    ),
    '{brand,secondary}', '"#0b1a2e"'
  ),
  '{brand,accent}', '"#58a6ff"'
)
WHERE event_uid = 'calserver';

-- ── 4. Configure project_settings for calserver namespace (logo) ──

INSERT INTO project_settings (namespace, header_logo_mode, header_logo_label)
VALUES ('calserver', 'text', 'calServer')
ON CONFLICT (namespace) DO UPDATE SET
  header_logo_mode = EXCLUDED.header_logo_mode,
  header_logo_label = EXCLUDED.header_logo_label;

-- ── 5. Fix referenzen block: HTML body → plain text, add keyFacts ──

UPDATE pages
SET content = jsonb_set(
  content::jsonb,
  '{blocks,4,data,cases}',
  $CASES$[
    {
      "id": "thermo-fisher",
      "title": "Thermo Fisher Scientific",
      "badge": "Kalibrierlabor",
      "lead": "Globaler Life-Science-Konzern \u00b7 EMEA-weites Deployment",
      "body": "EMEA-weite Leihger\u00e4te-Verwaltung und l\u00fcckenlose Ger\u00e4teakten \u00fcber mehrere Standorte. Bidirektionale Synchronisation mit Fluke MET/TEAM f\u00fcr einen konsistenten Datenbestand.",
      "keyFacts": [
        "EMEA-weites Deployment",
        "Bidirektionale MET/TEAM-Synchronisation",
        "Eliminierte Datensilos",
        "Revisionssichere Nachverfolgung"
      ]
    },
    {
      "id": "zf",
      "title": "ZF",
      "badge": "Industrielabor",
      "lead": "Automobilzulieferer \u00b7 Enterprise-Infrastruktur",
      "body": "API-basierte Messwert-Erfassung auf Kubernetes-Infrastruktur mit SSO-Anbindung (Azure AD). Bidirektionale MET/TEAM-Synchronisation f\u00fcr nahtlosen Datenaustausch.",
      "keyFacts": [
        "Enterprise-Kubernetes-Infrastruktur",
        "Azure AD SSO-Integration",
        "Automatisierte Messwert-Pipelines",
        "Nahtloser MET/TEAM-Datenaustausch"
      ]
    },
    {
      "id": "vde",
      "title": "VDE",
      "badge": "Qualit\u00e4tsmanagement",
      "lead": "Verband der Elektrotechnik \u00b7 Normungsinstitut",
      "body": "Agile Auftragssteuerung mit integriertem Dokumentenmanagement. calServer als zentrales Intranet und Ticketing-Plattform f\u00fcr die QM-Abteilung.",
      "keyFacts": [
        "Transparente Auftragsprozesse",
        "Revisionssicheres DMS",
        "Zentraler QM-Hub",
        "Einsatz jenseits klassischer Kalibrierung"
      ]
    },
    {
      "id": "ifm",
      "title": "ifm electronic",
      "badge": "Kalibrierlabor",
      "lead": "Sensorhersteller \u00b7 2 Standorte",
      "body": "Standort\u00fcbergreifendes Ticket-Management f\u00fcr St\u00f6rungen und CAPA-Prozesse. Bidirektionale Synchronisation mit MET/TEAM und MET/CAL.",
      "keyFacts": [
        "2 Standorte vernetzt",
        "CAPA-Dokumentation",
        "MET/TEAM + MET/CAL Sync",
        "Einheitliche St\u00f6rungsbearbeitung"
      ]
    },
    {
      "id": "berliner-stadtwerke",
      "title": "Berliner Stadtwerke",
      "badge": "Assetmanagement",
      "lead": "Kommunaler Energieversorger \u00b7 Erneuerbare Energien",
      "body": "Projekt- und Wartungsmanagement f\u00fcr dezentrale erneuerbare Energieanlagen (PV, Speicher). calServer als zentrale Plattform jenseits der klassischen Kalibrierung.",
      "keyFacts": [
        "Erneuerbare Energien (PV, Speicher)",
        "Dezentrale Assetverwaltung",
        "Strukturierte Wartungsplanung",
        "Einsatz jenseits der Kalibrierung"
      ]
    },
    {
      "id": "ksw",
      "title": "KSW",
      "badge": "Kalibrierlabor",
      "lead": "Kalibrierdienstleister \u00b7 End-to-End-Prozess",
      "body": "Kompletter Workflow vom Wareneingang \u00fcber die Laborbearbeitung bis zur automatisierten Rechnungsstellung \u2013 alles in calServer abgebildet.",
      "keyFacts": [
        "End-to-End-Auftragsprozess",
        "Automatisierte Abrechnung",
        "Kein Medienbruch",
        "Vollst\u00e4ndige Nachverfolgbarkeit"
      ]
    },
    {
      "id": "teramess",
      "title": "TERAMESS",
      "badge": "Kalibrierlabor",
      "lead": "Kalibrierdienstleister \u00b7 DAkkS-akkreditiert",
      "body": "DAkkS-konforme Kalibrierscheine direkt aus calServer in der Cloud erstellen. Audit-sichere Dokumentation \u00fcber den gesamten Kalibrierprozess.",
      "keyFacts": [
        "DAkkS-akkreditiert",
        "Cloud-basiert",
        "Normkonforme Zertifikate",
        "Jederzeitige Audit-Bereitschaft"
      ]
    },
    {
      "id": "systems-engineering",
      "title": "Systems Engineering",
      "badge": "Kalibrierlabor",
      "lead": "Kalibrierdienstleister \u00b7 Auftragssteuerung",
      "body": "calServer als steuerndes Herzst\u00fcck der gesamten Auftragsbearbeitung \u2013 von der Anfrage \u00fcber die Kalibrierung bis zur Auslieferung und Dokumentation.",
      "keyFacts": [
        "Zentrale Auftragssteuerung",
        "Reduzierter Verwaltungsaufwand",
        "Einheitlicher Prozess",
        "End-to-End-Dokumentation"
      ]
    }
  ]$CASES$::jsonb
)::text,
    updated_at = CURRENT_TIMESTAMP
WHERE slug = 'calserver' AND namespace = 'calserver';
