UPDATE pages
SET content = REPLACE(
    content,
    'src="https://www.software-made-in-germany.org/wp-content/uploads/2021/06/Software-Made-in-Germany-Siegel.webp"',
    'src="/uploads/software-made-in-germany-siegel.webp"'
)
WHERE slug IN ('calserver', 'calserver-en')
  AND content LIKE '%https://www.software-made-in-germany.org/wp-content/uploads/2021/06/Software-Made-in-Germany-Siegel.webp%';
