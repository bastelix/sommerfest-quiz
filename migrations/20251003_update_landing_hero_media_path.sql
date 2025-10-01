UPDATE pages
SET content = REPLACE(
        REPLACE(
            content,
            '{{ basePath }}/uploads/landing/quizrace-shot.avif',
            '{{ basePath }}/uploads/quizrace-shot.avif'
        ),
        '{{ basePath }}/uploads/landing/quizrace-shot.webp',
        '{{ basePath }}/uploads/quizrace-shot.webp'
    ),
    updated_at = CURRENT_TIMESTAMP
WHERE slug = 'landing';
