import { RENDERER_MATRIX } from './block-renderer-matrix-data.js';

// Block contract aligned with docs/calserver-block-consolidation.md
const VARIANT_ALIASES = {
  hero: {
    'media-right': 'media_right',
    'media-left': 'media_left',
    'centered-cta': 'centered_cta'
  }
};

const schema = {
  "$schema": "http://json-schema.org/draft-07/schema#",
  "$id": "https://quizrace.example.com/schemas/block-contract.schema.json",
  "title": "Block Contract",
  "description": "Strict contract for editorial blocks used by the editor, renderer, and migration tools.",
  "type": "object",
  "additionalProperties": false,
  "required": ["id", "type", "variant", "data"],
  "properties": {
    "id": { "type": "string", "minLength": 1 },
    "type": { "type": "string" },
    "variant": { "type": "string" },
    "data": { "type": "object" },
    "tokens": { "$ref": "#/definitions/Tokens" }
  },
  "oneOf": [
    {
      "title": "Hero block",
      "properties": {
        "type": { "const": "hero" },
        "variant": { "enum": ["centered_cta", "centered-cta", "media_right", "media-right", "media_left", "media-left"] },
        "data": { "$ref": "#/definitions/HeroData" }
      },
      "required": ["type", "variant", "data"]
    },
    {
      "title": "Feature list block",
      "properties": {
        "type": { "const": "feature_list" },
        "variant": { "enum": ["stacked_cards", "icon_grid", "detailed-cards", "grid-bullets"] },
        "data": { "$ref": "#/definitions/FeatureListData" }
      },
      "required": ["type", "variant", "data"]
    },
    {
      "title": "Process steps block",
      "properties": {
        "type": { "const": "process_steps" },
        "variant": { "enum": ["timeline_horizontal", "timeline_vertical", "timeline"] },
        "data": { "$ref": "#/definitions/ProcessStepsData" }
      },
      "required": ["type", "variant", "data"]
    },
    {
      "title": "Testimonial block",
      "properties": {
        "type": { "const": "testimonial" },
        "variant": { "enum": ["single_quote", "quote_wall"] },
        "data": { "$ref": "#/definitions/TestimonialData" }
      },
      "required": ["type", "variant", "data"]
    },
    {
      "title": "Rich text block",
      "properties": {
        "type": { "const": "rich_text" },
        "variant": { "enum": ["prose"] },
        "data": { "$ref": "#/definitions/RichTextData" }
      },
      "required": ["type", "variant", "data"]
    },
    {
      "title": "Info media block",
      "properties": {
        "type": { "const": "info_media" },
        "variant": { "enum": ["stacked", "switcher"] },
        "data": { "$ref": "#/definitions/InfoMediaData" }
      },
      "required": ["type", "variant", "data"]
    },
    {
      "title": "CTA block",
      "properties": {
        "type": { "const": "cta" },
        "variant": { "enum": ["full_width"] },
        "data": { "$ref": "#/definitions/CallToAction" }
      },
      "required": ["type", "variant", "data"]
    },
    {
      "title": "Stat strip block",
      "properties": {
        "type": { "const": "stat_strip" },
        "variant": { "enum": ["three-up"] },
        "data": { "$ref": "#/definitions/StatStripData" }
      },
      "required": ["type", "variant", "data"]
    },
    {
      "title": "Audience spotlight block",
      "properties": {
        "type": { "const": "audience_spotlight" },
        "variant": { "enum": ["tabs"] },
        "data": { "$ref": "#/definitions/AudienceSpotlightData" }
      },
      "required": ["type", "variant", "data"]
    },
    {
      "title": "Package summary block",
      "properties": {
        "type": { "const": "package_summary" },
        "variant": { "enum": ["toggle", "comparison-cards"] },
        "data": { "$ref": "#/definitions/PackageSummaryData" }
      },
      "required": ["type", "variant", "data"]
    },
    {
      "title": "FAQ block",
      "properties": {
        "type": { "const": "faq" },
        "variant": { "enum": ["accordion"] },
        "data": { "$ref": "#/definitions/FaqData" }
      },
      "required": ["type", "variant", "data"]
    },
    {
      // Deprecated: see docs/calserver-block-consolidation.md
      "title": "System module (deprecated)",
      "description": "Deprecated in favour of info_media switcher variant.",
      "properties": {
        "type": { "const": "system_module" },
        "variant": { "enum": ["switcher"] },
        "data": { "$ref": "#/definitions/InfoMediaData" }
      },
      "required": ["type", "variant", "data"],
      "deprecated": true
    },
    {
      // Deprecated: see docs/calserver-block-consolidation.md
      "title": "Case showcase (deprecated)",
      "description": "Deprecated in favour of audience_spotlight tabs variant.",
      "properties": {
        "type": { "const": "case_showcase" },
        "variant": { "enum": ["tabs"] },
        "data": { "$ref": "#/definitions/AudienceSpotlightData" }
      },
      "required": ["type", "variant", "data"],
      "deprecated": true
    }
  ],
  "definitions": {
    "Tokens": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "background": { "enum": ["default", "muted", "primary"] },
        "spacing": { "enum": ["small", "normal", "large"] },
        "width": { "enum": ["narrow", "normal", "wide"] },
        "columns": { "enum": ["single", "two", "three", "four"] },
        "accent": { "enum": ["brandA", "brandB", "brandC"] }
      }
    },
    "HeroData": {
      "type": "object",
      "additionalProperties": false,
      "required": ["headline", "cta"],
      "properties": {
        "eyebrow": { "type": "string" },
        "headline": { "type": "string", "minLength": 1 },
        "subheadline": { "type": "string" },
        "media": { "$ref": "#/definitions/Media" },
        "cta": { "$ref": "#/definitions/CallToAction" }
      }
    },
    "FeatureListData": {
      "type": "object",
      "additionalProperties": false,
      "required": ["title", "items"],
      "properties": {
        "eyebrow": { "type": "string" },
        "title": { "type": "string", "minLength": 1 },
        "subtitle": { "type": "string" },
        "lead": { "type": "string" },
        "intro": { "type": "string" },
        "items": {
          "type": "array",
          "minItems": 1,
          "items": { "$ref": "#/definitions/FeatureItem" }
        },
        "cta": { "$ref": "#/definitions/CallToAction" }
      }
    },
    "ProcessStepsData": {
      "type": "object",
      "additionalProperties": false,
      "required": ["title", "steps"],
      "properties": {
        "title": { "type": "string", "minLength": 1 },
        "summary": { "type": "string" },
        "intro": { "type": "string" },
        "steps": {
          "type": "array",
          "minItems": 2,
          "items": { "$ref": "#/definitions/ProcessStep" }
        },
        "closing": { "$ref": "#/definitions/ClosingCopy" },
        "ctaPrimary": { "$ref": "#/definitions/CallToAction" },
        "ctaSecondary": { "$ref": "#/definitions/CallToAction" }
      }
    },
    "TestimonialData": {
      "type": "object",
      "additionalProperties": false,
      "required": ["quote", "author"],
      "properties": {
        "quote": { "type": "string", "minLength": 1 },
        "author": { "$ref": "#/definitions/Author" },
        "source": { "type": "string" }
      }
    },
    "RichTextData": {
      "type": "object",
      "additionalProperties": false,
      "required": ["body"],
      "properties": {
        "body": { "type": "string", "minLength": 1 },
        "alignment": { "enum": ["start", "center", "end", "justify"] }
      }
    },
    "InfoMediaData": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "title": { "type": "string" },
        "subtitle": { "type": "string" },
        "body": { "type": "string" },
        "items": {
          "type": "array",
          "items": { "$ref": "#/definitions/InfoMediaItem" },
          "minItems": 1
        }
      }
    },
    "CallToAction": {
      "type": "object",
      "additionalProperties": false,
      "required": ["label", "href"],
      "properties": {
        "label": { "type": "string", "minLength": 1 },
        "href": { "type": "string", "minLength": 1 },
        "ariaLabel": { "type": "string" }
      }
    },
    "StatStripData": {
      "type": "object",
      "additionalProperties": false,
      "required": ["metrics"],
      "properties": {
        "metrics": {
          "type": "array",
          "items": { "$ref": "#/definitions/Metric" },
          "minItems": 1
        },
        "marquee": {
          "type": "array",
          "items": { "type": "string" }
        }
      }
    },
    "AudienceSpotlightData": {
      "type": "object",
      "additionalProperties": false,
      "required": ["title", "cases"],
      "properties": {
        "title": { "type": "string", "minLength": 1 },
        "subtitle": { "type": "string" },
        "cases": {
          "type": "array",
          "items": { "$ref": "#/definitions/AudienceCase" },
          "minItems": 1
        }
      }
    },
    "PackageSummaryData": {
      "type": "object",
      "additionalProperties": false,
      "required": ["title"],
      "properties": {
        "title": { "type": "string", "minLength": 1 },
        "subtitle": { "type": "string" },
        "options": {
          "type": "array",
          "items": { "$ref": "#/definitions/PackageOption" },
          "minItems": 1
        },
        "plans": {
          "type": "array",
          "items": { "$ref": "#/definitions/PackagePlan" },
          "minItems": 1
        },
        "disclaimer": { "type": "string" }
      }
    },
    "FaqData": {
      "type": "object",
      "additionalProperties": false,
      "required": ["title", "items"],
      "properties": {
        "title": { "type": "string", "minLength": 1 },
        "items": {
          "type": "array",
          "items": { "$ref": "#/definitions/FaqItem" },
          "minItems": 1
        },
        "followUp": { "$ref": "#/definitions/FaqFollowUp" }
      }
    },
    "Media": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "imageId": { "type": "string" },
        "image": { "type": "string" },
        "alt": { "type": "string" },
        "focalPoint": { "$ref": "#/definitions/FocalPoint" }
      }
    },
    "InfoMediaItem": {
      "type": "object",
      "additionalProperties": false,
      "required": ["id", "title", "description"],
      "properties": {
        "id": { "type": "string", "minLength": 1 },
        "title": { "type": "string", "minLength": 1 },
        "description": { "type": "string", "minLength": 1 },
        "media": { "$ref": "#/definitions/Media" },
        "bullets": { "type": "array", "items": { "type": "string" } }
      }
    },
    "Metric": {
      "type": "object",
      "additionalProperties": false,
      "required": ["id", "value", "label"],
      "properties": {
        "id": { "type": "string", "minLength": 1 },
        "value": { "type": "string", "minLength": 1 },
        "label": { "type": "string", "minLength": 1 },
        "asOf": { "type": "string" },
        "tooltip": { "type": "string" },
        "benefit": { "type": "string" }
      }
    },
    "AudienceCase": {
      "type": "object",
      "additionalProperties": false,
      "required": ["id", "title"],
      "properties": {
        "id": { "type": "string", "minLength": 1 },
        "badge": { "type": "string" },
        "title": { "type": "string", "minLength": 1 },
        "lead": { "type": "string" },
        "body": { "type": "string" },
        "bullets": { "type": "array", "items": { "type": "string" } },
        "keyFacts": { "type": "array", "items": { "type": "string" } },
        "media": { "$ref": "#/definitions/Media" }
      }
    },
    "PackageOption": {
      "type": "object",
      "additionalProperties": false,
      "required": ["id", "title"],
      "properties": {
        "id": { "type": "string", "minLength": 1 },
        "title": { "type": "string", "minLength": 1 },
        "intro": { "type": "string" },
        "highlights": {
          "type": "array",
          "items": { "$ref": "#/definitions/PackageHighlight" }
        }
      }
    },
    "PackageHighlight": {
      "type": "object",
      "additionalProperties": false,
      "required": ["title"],
      "properties": {
        "title": { "type": "string", "minLength": 1 },
        "bullets": { "type": "array", "items": { "type": "string" } }
      }
    },
    "PackagePlan": {
      "type": "object",
      "additionalProperties": false,
      "required": ["id", "title"],
      "properties": {
        "id": { "type": "string", "minLength": 1 },
        "title": { "type": "string", "minLength": 1 },
        "badge": { "type": "string" },
        "description": { "type": "string" },
        "features": { "type": "array", "items": { "type": "string" } },
        "notes": { "type": "array", "items": { "type": "string" } },
        "primaryCta": { "$ref": "#/definitions/CallToAction" },
        "secondaryCta": { "$ref": "#/definitions/CallToAction" }
      }
    },
    "FaqItem": {
      "type": "object",
      "additionalProperties": false,
      "required": ["id", "question", "answer"],
      "properties": {
        "id": { "type": "string", "minLength": 1 },
        "question": { "type": "string", "minLength": 1 },
        "answer": { "type": "string", "minLength": 1 }
      }
    },
    "FaqFollowUp": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "text": { "type": "string" },
        "linkLabel": { "type": "string" },
        "href": { "type": "string" }
      }
    },
    "ClosingCopy": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "title": { "type": "string" },
        "body": { "type": "string" }
      }
    },
    "FeatureItem": {
      "type": "object",
      "additionalProperties": false,
      "required": ["id", "title", "description"],
      "properties": {
        "id": { "type": "string", "minLength": 1 },
        "icon": { "type": "string" },
        "title": { "type": "string", "minLength": 1 },
        "description": { "type": "string", "minLength": 1 },
        "bullets": { "type": "array", "items": { "type": "string" } },
        "media": { "$ref": "#/definitions/Media" }
      }
    },
    "ProcessStep": {
      "type": "object",
      "additionalProperties": false,
      "required": ["id", "title", "description"],
      "properties": {
        "id": { "type": "string", "minLength": 1 },
        "title": { "type": "string", "minLength": 1 },
        "description": { "type": "string", "minLength": 1 },
        "duration": { "type": "string" },
        "media": { "$ref": "#/definitions/Media" }
      }
    },
    "Author": {
      "type": "object",
      "additionalProperties": false,
      "required": ["name"],
      "properties": {
        "name": { "type": "string", "minLength": 1 },
        "role": { "type": "string" },
        "avatarId": { "type": "string" }
      }
    },
    "FocalPoint": {
      "type": "object",
      "additionalProperties": false,
      "required": ["x", "y"],
      "properties": {
        "x": { "type": "number", "minimum": 0, "maximum": 1 },
        "y": { "type": "number", "minimum": 0, "maximum": 1 }
      }
    }
  }
};

export const normalizeBlockVariant = (type, variant) => VARIANT_ALIASES[type]?.[variant] || variant;

const BLOCK_VARIANTS = Object.entries(RENDERER_MATRIX).reduce((accumulator, [type, variants]) => {
  const canonicalVariants = Object.keys(variants);
  const aliasVariants = Object.keys(VARIANT_ALIASES[type] || {});
  accumulator[type] = [...canonicalVariants, ...aliasVariants];
  return accumulator;
}, {});

const DEPRECATED_BLOCK_TYPES = {
  system_module: { replacement: { type: 'info_media', variant: 'switcher' } },
  case_showcase: { replacement: { type: 'audience_spotlight', variant: 'tabs' } }
};

const TOKEN_ENUMS = {
  background: ['default', 'muted', 'primary'],
  spacing: ['small', 'normal', 'large'],
  width: ['narrow', 'normal', 'wide'],
  columns: ['single', 'two', 'three', 'four'],
  accent: ['brandA', 'brandB', 'brandC']
};

function isPlainObject(value) {
  return value !== null && typeof value === 'object' && !Array.isArray(value);
}

function validateTokens(tokens) {
  if (tokens === undefined) {
    return true;
  }
  if (!isPlainObject(tokens)) {
    return false;
  }
  return Object.entries(tokens).every(([key, value]) => TOKEN_ENUMS[key]?.includes(value));
}

export function normalizeVariant(type, variant) {
  if (typeof variant !== 'string') {
    return variant;
  }
  const aliasForType = VARIANT_ALIASES[type];
  return aliasForType?.[variant] || variant;
}

export function validateBlockContract(block) {
  if (!isPlainObject(block)) {
    return { valid: false, reason: 'Block must be an object' };
  }
  if (typeof block.id !== 'string' || block.id.length === 0) {
    return { valid: false, reason: 'Block id must be a non-empty string' };
  }
  if (typeof block.type !== 'string' || !BLOCK_VARIANTS[block.type]) {
    return { valid: false, reason: `Unknown block type: ${block.type}` };
  }
  const normalizedVariant = normalizeVariant(block.type, block.variant);
  if (typeof normalizedVariant !== 'string' || !BLOCK_VARIANTS[block.type].includes(normalizedVariant)) {
    return { valid: false, reason: `Unknown variant for ${block.type}: ${block.variant}` };
  }
  if (!isPlainObject(block.data)) {
    return { valid: false, reason: 'Block data must be an object' };
  }
  if (!validateTokens(block.tokens)) {
    return { valid: false, reason: 'Tokens must match allowed design tokens' };
  }
  return { valid: true };
}

export function getBlockVariants(type) {
  return BLOCK_VARIANTS[type] ? [...BLOCK_VARIANTS[type]] : [];
}

export const BLOCK_CONTRACT_SCHEMA = schema;
export const BLOCK_TYPES = Object.keys(BLOCK_VARIANTS);
export const ACTIVE_BLOCK_TYPES = BLOCK_TYPES.filter(type => !DEPRECATED_BLOCK_TYPES[type]);
export const TOKENS = TOKEN_ENUMS;
export const DEPRECATED_BLOCK_MAP = DEPRECATED_BLOCK_TYPES;
