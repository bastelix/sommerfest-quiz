---
layout: default
title: Design Tokens
---

# Design Tokens

Die Landing-Seite verwendet zentrale Design Tokens, die in `resources/scss/_tokens.scss` gepflegt und während des Build-Prozesses zu CSS-Variablen in `public/css/landing.css` kompiliert werden. Sie dienen als einheitliche Quelle für Farben, Typografie und Abstände.

## Farben

| Variable | Wert | Beschreibung |
|---------|------|--------------|
| `--brand` | `#2F81F7` | Primäre Markenfarbe |
| `--accent` | `#0EA5E9` | Sekundäre Akzentfarbe |
| `--text` | `#24292F` | Standard-Textfarbe |
| `--muted` | `#57606A` | Gedämpfter Text |
| `--bg` | `#FFFFFF` | Hintergrund hell |
| `--bg-alt` | `#F6F8FA` | Alternativer Hintergrund |
| `--border` | `#D0D7DE` | Rahmenfarbe |
| `--qr-canvas` | `#FFFFFF` | Basis der Screenshot-Komponente |
| `--qr-subtle` | `#F6F8FA` | Subtile Fläche der Screenshot-Komponente |
| `--qr-border` | `#D0D7DE` | Rahmen der Screenshot-Komponente |

## Typografie

| Variable | Wert |
|---------|------|
| `--fs-h1` | `clamp(2rem,5vw,3rem)` |
| `--fs-h2` | `clamp(1.5rem,3.5vw,2.25rem)` |
| `--fs-h3` | `clamp(1.25rem,3vw,1.5rem)` |

## Spacing

| Variable | Wert |
|---------|------|
| `--gap` | `clamp(12px,2vw,20px)` |
| `--pad-section` | `clamp(48px,8vw,72px)` |
| `--radius` | `14px` |

Weitere tokens (z.B. `--qr-blue`, `--qr-cyan` oder Schattenwerte) können bei Bedarf in `_tokens.scss` angepasst werden, um neue Komponenten konsistent zu stylen.
