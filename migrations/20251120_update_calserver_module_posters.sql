-- Update calServer module preview posters to reference MP4 assets
UPDATE pages
SET content = REPLACE(
    REPLACE(
        REPLACE(
            REPLACE(content,
                'poster="{{ basePath }}/uploads/calserver-module-device-management.webp"',
                'poster="{{ basePath }}/uploads/calserver-module-device-management.mp4"'),
            'poster="{{ basePath }}/uploads/calserver-module-calendar-resources.webp"',
            'poster="{{ basePath }}/uploads/calserver-module-calendar-resources.mp4"'),
        'poster="{{ basePath }}/uploads/calserver-module-order-ticketing.webp"',
        'poster="{{ basePath }}/uploads/calserver-module-order-ticketing.mp4"'),
    'poster="{{ basePath }}/uploads/calserver-module-self-service.webp"',
    'poster="{{ basePath }}/uploads/calserver-module-self-service.mp4"'),
    updated_at = CURRENT_TIMESTAMP
WHERE slug = 'calserver';

UPDATE pages
SET content = REPLACE(
    REPLACE(
        REPLACE(
            REPLACE(content,
                'poster="{{ basePath }}/uploads/calserver-module-device-management.webp"',
                'poster="{{ basePath }}/uploads/calserver-module-device-management.mp4"'),
            'poster="{{ basePath }}/uploads/calserver-module-calendar-resources.webp"',
            'poster="{{ basePath }}/uploads/calserver-module-calendar-resources.mp4"'),
        'poster="{{ basePath }}/uploads/calserver-module-order-ticketing.webp"',
        'poster="{{ basePath }}/uploads/calserver-module-order-ticketing.mp4"'),
    'poster="{{ basePath }}/uploads/calserver-module-self-service.webp"',
    'poster="{{ basePath }}/uploads/calserver-module-self-service.mp4"'),
    updated_at = CURRENT_TIMESTAMP
WHERE slug = 'calserver-en';
