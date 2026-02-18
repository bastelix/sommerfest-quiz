-- Fix the calServer design tokens in the config table.
-- The auto-generated namespace-tokens.css currently has wrong brand colors
-- (#111111/#222222/#f97316 instead of #1f6feb/#58a6ff/#475569).
-- This migration sets the correct tokens from content/design/calserver.json
-- so that rebuildStylesheet() generates the right CSS variables.

UPDATE config
SET design_tokens = '{"brand":{"primary":"#1f6feb","accent":"#58a6ff","secondary":"#475569"},"layout":{"profile":"standard"},"typography":{"preset":"modern"},"components":{"cardStyle":"rounded","buttonStyle":"filled"},"_meta":{"sourcePreset":"calserver","importedAt":"2026-02-18T00:00:00+00:00"}}'::jsonb
WHERE event_uid = 'calserver';
