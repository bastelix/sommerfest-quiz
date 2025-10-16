-- Align MET/CAL contact CTAs with the calServer contact anchor
UPDATE pages
SET content = REPLACE(
        REPLACE(
            REPLACE(content,
                'href="{{ basePath }}/kontakt"',
                'href="{{ basePath }}/calserver#contact-us"'
            ),
            'data-analytics-target="/kontakt"',
            'data-analytics-target="/calserver#contact-us"'
        ),
        'updated_at: 2025-05-20',
        'updated_at: 2025-05-28'
    )
WHERE slug = 'fluke-metcal';

UPDATE pages
SET content = REPLACE(
        REPLACE(content,
            'href="{{ basePath }}/kontakt"',
            'href="{{ basePath }}/calserver#contact-us"'
        ),
        'data-analytics-to="#kontakt"',
        'data-analytics-to="#contact-us"'
    )
WHERE slug = 'calserver';
