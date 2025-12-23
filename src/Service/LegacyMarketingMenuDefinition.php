<?php

declare(strict_types=1);

namespace App\Service;

final class LegacyMarketingMenuDefinition
{
    /**
     * @return array<string, mixed>|null
     */
    public static function getDefinitionForSlug(string $slug): ?array
    {
        $normalized = trim($slug);

        return match ($normalized) {
            'calserver' => self::calserverDefinition(),
            'future-is-green' => self::futureIsGreenDefinition(),
            'labor' => self::laborDefinition(),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function getDefaultDefinition(): array
    {
        return [
            'locales' => ['de', 'en'],
            'items' => [
                ['href' => '#innovations', 'label' => 'Innovationen', 'icon' => 'star', 'layout' => 'link'],
                ['href' => '#how-it-works', 'label' => 'So funktioniert’s', 'icon' => 'settings', 'layout' => 'link'],
                ['href' => '#scenarios', 'label' => 'Szenarien', 'icon' => 'thumbnails', 'layout' => 'link'],
                ['href' => '#pricing', 'label' => 'Preise', 'icon' => 'credit-card', 'layout' => 'link'],
                ['href' => '#faq', 'label' => 'FAQ', 'icon' => 'question', 'layout' => 'link'],
                ['href' => '#contact-us', 'label' => 'Kontakt', 'icon' => 'mail', 'layout' => 'link'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function calserverDefinition(): array
    {
        return [
            'locales' => ['de', 'en'],
            'items' => [
                [
                    'href' => '#trust',
                    'label_key' => 'calserver_mega_overview',
                    'layout' => 'mega',
                    'children' => [
                        [
                            'href' => '#trust',
                            'label_key' => 'calserver_mega_overview_trust',
                            'detail_title_key' => 'calserver_mega_overview_trust_title',
                            'detail_text_key' => 'calserver_mega_overview_trust_desc',
                            'detail_subline_key' => 'calserver_mega_overview_trust_subline',
                        ],
                        [
                            'href' => '#demo',
                            'label_key' => 'calserver_mega_overview_demo',
                            'detail_title_key' => 'calserver_mega_overview_demo_title',
                            'detail_text_key' => 'calserver_mega_overview_demo_desc',
                            'detail_subline_key' => 'calserver_mega_overview_demo_subline',
                        ],
                    ],
                ],
                [
                    'href' => '#features',
                    'label_key' => 'calserver_mega_features',
                    'layout' => 'mega',
                    'children' => [
                        [
                            'href' => '#features',
                            'label_key' => 'calserver_mega_features_features',
                            'detail_title_key' => 'calserver_mega_features_features_title',
                            'detail_text_key' => 'calserver_mega_features_features_desc',
                            'detail_subline_key' => 'calserver_mega_features_features_subline',
                        ],
                        [
                            'href' => '#modules',
                            'label_key' => 'calserver_mega_features_modules',
                            'detail_title_key' => 'calserver_mega_features_modules_title',
                            'detail_text_key' => 'calserver_mega_features_modules_desc',
                            'detail_subline_key' => 'calserver_mega_features_modules_subline',
                        ],
                    ],
                ],
                [
                    'href' => '#usecases',
                    'label_key' => 'calserver_mega_solutions',
                    'layout' => 'mega',
                    'children' => [
                        [
                            'href' => '#usecases',
                            'label_key' => 'calserver_mega_solutions_usecases',
                            'detail_title_key' => 'calserver_mega_solutions_usecases_title',
                            'detail_text_key' => 'calserver_mega_solutions_usecases_desc',
                            'detail_subline_key' => 'calserver_mega_solutions_usecases_subline',
                        ],
                        [
                            'href' => '#metcal',
                            'label_key' => 'calserver_mega_solutions_metcal',
                            'detail_title_key' => 'calserver_mega_solutions_metcal_title',
                            'detail_text_key' => 'calserver_mega_solutions_metcal_desc',
                            'detail_subline_key' => 'calserver_mega_solutions_metcal_subline',
                        ],
                    ],
                ],
                [
                    'href' => '#modes',
                    'label_key' => 'calserver_mega_operations',
                    'layout' => 'mega',
                    'children' => [
                        [
                            'href' => '#modes',
                            'label_key' => 'calserver_mega_operations_modes',
                            'detail_title_key' => 'calserver_mega_operations_modes_title',
                            'detail_text_key' => 'calserver_mega_operations_modes_desc',
                            'detail_subline_key' => 'calserver_mega_operations_modes_subline',
                        ],
                        [
                            'href' => '#pricing',
                            'label_key' => 'calserver_mega_operations_pricing',
                            'detail_title_key' => 'calserver_mega_operations_pricing_title',
                            'detail_text_key' => 'calserver_mega_operations_pricing_desc',
                            'detail_subline_key' => 'calserver_mega_operations_pricing_subline',
                        ],
                    ],
                ],
                [
                    'href' => '#calserver-news',
                    'label_key' => 'calserver_mega_resources',
                    'layout' => 'mega',
                    'children' => [
                        [
                            'href' => '#calserver-news',
                            'label_key' => 'calserver_mega_resources_news',
                            'detail_title_key' => 'calserver_mega_resources_news_title',
                            'detail_text_key' => 'calserver_mega_resources_news_desc',
                            'detail_subline_key' => 'calserver_mega_resources_news_subline',
                        ],
                        [
                            'href' => '#faq',
                            'label_key' => 'calserver_mega_resources_faq',
                            'detail_title_key' => 'calserver_mega_resources_faq_title',
                            'detail_text_key' => 'calserver_mega_resources_faq_desc',
                            'detail_subline_key' => 'calserver_mega_resources_faq_subline',
                        ],
                        [
                            'href' => '#demo',
                            'label_key' => 'calserver_mega_resources_contact',
                            'detail_title_key' => 'calserver_mega_resources_contact_title',
                            'detail_text_key' => 'calserver_mega_resources_contact_desc',
                            'detail_subline_key' => 'calserver_mega_resources_contact_subline',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function futureIsGreenDefinition(): array
    {
        return [
            'locales' => ['de', 'en'],
            'items' => [
                [
                    'href' => '#benefits',
                    'label' => 'Wirkung',
                    'layout' => 'mega',
                    'children' => [
                        [
                            'href' => '#benefits',
                            'label' => 'KPIs im Quartier',
                            'detail_title' => 'Wirkung verstehen',
                            'detail_text' => 'Sechs Kennzahlen zeigen, wie Klimaschutz, Lebensqualität und Tempo zusammengehen.',
                            'detail_subline' => '0 Emissionen, −60% Fahrten, +24% Tempo',
                        ],
                        [
                            'href' => '#benefits',
                            'label' => 'Pilotgebiete & Zahlen',
                            'detail_title' => 'Reale Ergebnisse',
                            'detail_text' => 'Pilotquartiere mit verifizierten Kennzahlen und Lessons Learned.',
                            'detail_subline' => 'Filterbar nach Stadt/Branche',
                        ],
                        [
                            'href' => '#benefits',
                            'label' => 'CO₂-Bilanz & Audit',
                            'detail_title' => 'Transparenz & Audit',
                            'detail_text' => 'Live-Dashboards, Messmethoden und Audit-Scopes für Kommunen & Partner.',
                            'detail_subline' => 'Methodik, Audit-Scopes, Reporting',
                        ],
                    ],
                ],
                [
                    'href' => '#how-it-works',
                    'label' => 'Lösungen',
                    'layout' => 'mega',
                    'children' => [
                        [
                            'href' => '#how-it-works',
                            'label' => 'So funktioniert’s',
                            'detail_title' => 'Vom Mikrohub zur Haustür',
                            'detail_text' => 'KI-Routen, gebündelte Stopps und Off-Peak-Lieferungen sorgen für Tempo ohne Lärm.',
                            'detail_subline' => 'KI-Routen, gebündelte Stopps, Off-Peak-Lieferungen',
                        ],
                        [
                            'href' => '#offerings',
                            'label' => 'Pakete & Module',
                            'detail_title' => 'Passend skalieren',
                            'detail_text' => 'Start, Pro und City kombinieren Module für Quartiere jeder Größe.',
                            'detail_subline' => 'Start, Pro, City – kombinierbar',
                        ],
                        [
                            'href' => '#technology',
                            'label' => 'Technologie & Integrationen',
                            'detail_title' => 'API-first',
                            'detail_text' => 'ERP- & Shop-Integrationen, Live-Dashboards und auditierbare CO₂-Bilanzen.',
                            'detail_subline' => 'ERP/Shop-Anbindung, Dashboards, CO₂-Bilanz',
                        ],
                    ],
                ],
                [
                    'href' => '#cases',
                    'label' => 'Referenzen',
                    'layout' => 'mega',
                    'children' => [
                        [
                            'href' => '#cases',
                            'label' => 'Case Stories',
                            'detail_title' => 'Stimmen aus der Praxis',
                            'detail_text' => 'Was Kommunen, Händler:innen und Rider über den Umbau berichten.',
                            'detail_subline' => 'Kommunen, Händler:innen & Rider im O-Ton',
                        ],
                        [
                            'href' => '#cases',
                            'label' => 'Branchenfilter',
                            'detail_title' => 'Filter nach Branchen',
                            'detail_text' => 'Von Lebensmitteln bis Pharma – passende Referenzen in Sekunden finden.',
                            'detail_subline' => 'Von Lebensmittel bis Pharma – schnell gefunden',
                        ],
                        [
                            'href' => '#cases',
                            'label' => 'Impact-Reports',
                            'detail_title' => 'Zahlen zum Nachlesen',
                            'detail_text' => 'PDF-Reports mit Kennzahlen, Lessons Learned und Zertifizierungen.',
                            'detail_subline' => 'Kennzahlen, Lessons Learned, Zertifizierungen',
                        ],
                    ],
                ],
                [
                    'href' => '#pricing',
                    'label' => 'Preise & FAQ',
                    'layout' => 'mega',
                    'children' => [
                        [
                            'href' => '#pricing',
                            'label' => 'Pakete & Vergleich',
                            'detail_title' => 'Transparente Pakete',
                            'detail_text' => 'Vergleiche Start, Pro und City – monatlich kündbar und förderfähig.',
                            'detail_subline' => 'Start, Pro, City im direkten Überblick',
                        ],
                        [
                            'href' => '#faq',
                            'label' => 'Häufige Fragen',
                            'detail_title' => 'Antworten in Sekunden',
                            'detail_text' => 'Finanzierung, Aufbauzeit und Zustellfenster sind kompakt erklärt.',
                            'detail_subline' => 'Finanzierung, Aufbauzeit, Zustellfenster',
                        ],
                        [
                            'href' => '#pricing',
                            'label' => 'Service-Level',
                            'detail_title' => 'Service & Support',
                            'detail_text' => 'SLA, Schulungen und Monitoring – abgestimmt auf Stadt & Partner.',
                            'detail_subline' => 'SLA, Schulung, Monitoring auf einen Blick',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function laborDefinition(): array
    {
        return [
            'locales' => ['de', 'en'],
            'items' => [
                [
                    'href' => '#kalibrierbereiche',
                    'label' => 'Angebot',
                    'layout' => 'mega',
                    'children' => [
                        [
                            'href' => '#kalibrierbereiche',
                            'label' => 'Kalibrierbereiche',
                            'detail_title' => 'Alle Kalibrierbereiche',
                            'detail_text' => 'Elektrische Messgrößen, Temperatur, Vor-Ort-Kalibrierung und Prüfmittelmanagement auf einen Blick.',
                            'detail_subline' => 'Kalibrierungen & Services gebündelt',
                        ],
                        [
                            'href' => '#messgroessen',
                            'label' => 'Messgrößen',
                            'detail_title' => 'Messgrößen im Überblick',
                            'detail_text' => 'Von Spannung über Drehmoment bis Feuchte – transparent dokumentiert und rückführbar.',
                            'detail_subline' => 'Elektrisch, mechanisch, thermisch',
                        ],
                        [
                            'href' => '#digitales-portal',
                            'label' => 'Digitales Portal',
                            'detail_title' => 'Digitales Messmittelportal',
                            'detail_text' => 'calServer bündelt Zertifikate, Fristen und Geräte-Historien – inkl. Rollen, Rechte und API.',
                            'detail_subline' => '24/7 verfügbar, DSGVO-konform',
                        ],
                    ],
                ],
                [
                    'href' => '#kennzahlen',
                    'label' => 'Qualität & Ablauf',
                    'layout' => 'mega',
                    'children' => [
                        [
                            'href' => '#kennzahlen',
                            'label' => 'Kennzahlen & SLAs',
                            'detail_title' => 'Transparente Kennzahlen',
                            'detail_text' => 'DAkkS-Status, Termintreue und Rückläuferquote zeigen die Stabilität unseres Labors.',
                            'detail_subline' => '25.000+ Kalibrierungen · 98,7 % Termintreue',
                        ],
                        [
                            'href' => '#laborprofil',
                            'label' => 'Labor im Überblick',
                            'detail_title' => 'Labor im Fokus',
                            'detail_text' => 'Akkreditierte Messketten, kurze Liegezeiten und persönlicher Service mit Abhol-Option.',
                            'detail_subline' => 'DIN EN ISO/IEC 17025 geprüft',
                        ],
                        [
                            'href' => '#ablauf',
                            'label' => 'Ablauf & Tracking',
                            'detail_title' => 'Geführter Ablauf',
                            'detail_text' => 'Vom Anmelden bis zum Zertifikat – klar strukturierter Prozess mit Status-Tracking.',
                            'detail_subline' => 'Anmeldung · Abholung · Kalibrierung · Zertifikat',
                        ],
                    ],
                ],
                [
                    'href' => '#branchen',
                    'label' => 'Branchen & Team',
                    'layout' => 'mega',
                    'children' => [
                        [
                            'href' => '#branchen',
                            'label' => 'Einsatzfelder',
                            'detail_title' => 'Stories aus der Praxis',
                            'detail_text' => 'Automotive, Energie, Luftfahrt oder Pharma – jede Branche mit passendem Workflow.',
                            'detail_subline' => '8 Branchen mit konkreten Use Cases',
                        ],
                        [
                            'href' => '#labor-historie',
                            'label' => 'Über uns',
                            'detail_title' => 'Menschen im Labor',
                            'detail_text' => '28 Spezialist:innen für elektrische und thermische Messtechnik – bundesweit im Einsatz.',
                            'detail_subline' => 'Feste Ansprechpartner:innen & Service-Slots',
                        ],
                        [
                            'href' => '#akkreditierung',
                            'label' => 'Akkreditierung',
                            'detail_title' => 'Auditierte Qualität',
                            'detail_text' => 'DAkkS-Urkunde, dokumentierte Normale und regelmäßige Audits sichern die Rückführbarkeit.',
                            'detail_subline' => 'DIN EN ISO/IEC 17025',
                        ],
                    ],
                ],
                [
                    'href' => '#prozesse',
                    'label' => 'Service',
                    'layout' => 'mega',
                    'children' => [
                        [
                            'href' => '#prozesse',
                            'label' => 'Prozess-Features',
                            'detail_title' => 'Laborlogistik & Tracking',
                            'detail_text' => 'Track & Trace, Rollenmodelle und signierte Zertifikate für durchgängige Compliance.',
                            'detail_subline' => 'Transparenz wie in der Logistik',
                        ],
                        [
                            'href' => '#faq',
                            'label' => 'FAQ',
                            'detail_title' => 'Antworten parat',
                            'detail_text' => 'Fragen zu Messmitteln, Laufzeiten und Versand sind kompakt erklärt.',
                            'detail_subline' => 'Kalibrierung, Versand, Dokumentation',
                        ],
                        [
                            'href' => '#kontakt',
                            'label' => 'Kontakt',
                            'detail_title' => 'Direkter Draht',
                            'detail_text' => 'Anfrage stellen, Rückruf buchen oder einen Labor-Rundgang vereinbaren.',
                            'detail_subline' => 'Antwort innerhalb eines Werktags',
                        ],
                    ],
                ],
            ],
        ];
    }
}
