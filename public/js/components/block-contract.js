import { RENDERER_MATRIX } from './block-renderer-matrix-data.js';

// Block contract aligned with docs/calserver-block-consolidation.md
const VARIANT_ALIASES = {
  hero: {
    'media-right': 'media_right',
    'media-left': 'media_left',
    'centered-cta': 'centered_cta'
  },
  feature_list: {
    stacked_cards: 'card-stack',
    icon_grid: 'grid-bullets'
  },
  audience_spotlight: {
    'single_focus': 'single-focus'
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
        "variant": { "enum": ["centered_cta", "media-right", "media_right", "media-left", "media_left"] },
        "data": { "$ref": "#/definitions/HeroData" }
      },
      "required": ["type", "variant", "data"]
    },
    {
      "title": "Feature list block",
      "properties": {
        "type": { "const": "feature_list" },
        "variant": { "enum": ["stacked_cards", "icon_grid", "detailed-cards", "grid-bullets", "text-columns", "card-stack"] },
        "data": { "$ref": "#/definitions/FeatureListData" }
      },
      "required": ["type", "variant", "data"]
    },
    {
      "title": "Process steps block",
      "properties": {
        "type": { "const": "process_steps" },
        "variant": { "enum": ["timeline_horizontal", "timeline_vertical", "timeline", "numbered-vertical", "numbered-horizontal"] },
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
        "variant": { "enum": ["full_width", "split"] },
        "data": { "$ref": "#/definitions/CtaBlockData" }
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
        "variant": { "enum": ["tabs", "tiles", "single-focus", "single_focus"] },
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
        "cta": { "$ref": "#/definitions/CallToActionGroup" }
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
        "eyebrow": { "type": "string" },
        "title": { "type": "string" },
        "subtitle": { "type": "string" },
        "body": { "type": "string" },
        "media": { "$ref": "#/definitions/Media" },
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
    "CtaBlockData": {
      "type": "object",
      "additionalProperties": false,
      "required": ["primary"],
      "properties": {
        "title": { "type": "string" },
        "body": { "type": "string" },
        "primary": { "$ref": "#/definitions/CallToAction" },
        "secondary": { "$ref": "#/definitions/CallToAction" }
      }
    },
    "CallToActionGroup": {
      "oneOf": [
        { "$ref": "#/definitions/CallToAction" },
        {
          "type": "object",
          "additionalProperties": false,
          "required": ["primary"],
          "properties": {
            "primary": { "$ref": "#/definitions/CallToAction" },
            "secondary": { "$ref": "#/definitions/CallToAction" }
          }
        }
      ]
    },
    "StatStripData": {
      "type": "object",
      "additionalProperties": false,
      "required": ["metrics"],
      "properties": {
        "title": { "type": "string" },
        "lede": { "type": "string" },
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
        "icon": { "type": "string" },
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
        "label": { "type": "string" },
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

function normalizeCallToAction(value) {
  if (!isPlainObject(value)) {
    return undefined;
  }

  const normalized = {};

  if (typeof value.label === 'string' && value.label.trim() !== '') {
    normalized.label = value.label;
  }

  if (typeof value.href === 'string' && value.href.trim() !== '') {
    normalized.href = value.href;
  }

  if (typeof value.ariaLabel === 'string' && value.ariaLabel.trim() !== '') {
    normalized.ariaLabel = value.ariaLabel;
  }

  if (!normalized.label || !normalized.href) {
    return undefined;
  }

  return normalized;
}

function normalizeProcessStepsCtas(data) {
  const normalized = { ...data };

  if (isPlainObject(data.cta)) {
    const primary = normalizeCallToAction(data.cta.primary);
    const secondary = normalizeCallToAction(data.cta.secondary);
    if (primary) {
      normalized.ctaPrimary = primary;
    }
    if (secondary) {
      normalized.ctaSecondary = secondary;
    }
    delete normalized.cta;
  }

  if (isPlainObject(data.ctaPrimary) || isPlainObject(data.ctaSecondary)) {
    const primary = normalizeCallToAction(data.ctaPrimary);
    const secondary = normalizeCallToAction(data.ctaSecondary);
    if (primary) {
      normalized.ctaPrimary = primary;
    }
    if (secondary) {
      normalized.ctaSecondary = secondary;
    }
  }

  return normalized;
}

function normalizePackagePlanCtas(plan) {
  if (!isPlainObject(plan)) {
    return plan;
  }

  const normalized = { ...plan };
  const nestedCta = isPlainObject(plan.cta) ? plan.cta : {};
  const primary = normalizeCallToAction(plan.primaryCta ?? nestedCta.primary);
  const secondary = normalizeCallToAction(plan.secondaryCta ?? nestedCta.secondary);

  if (primary) {
    normalized.primaryCta = primary;
  }
  if (secondary) {
    normalized.secondaryCta = secondary;
  }

  delete normalized.cta;

  return normalized;
}

function normalizeCtaBlockData(data) {
  if (!isPlainObject(data)) {
    return data;
  }

  const normalized = {};

  const title = typeof data.title === 'string' && data.title.trim() !== '' ? data.title : undefined;
  const body = typeof data.body === 'string' && data.body.trim() !== '' ? data.body : undefined;
  if (title) {
    normalized.title = title;
  }
  if (body) {
    normalized.body = body;
  }

  const primary = normalizeCallToAction(data.primary ?? data);
  if (primary) {
    normalized.primary = primary;
  }

  const secondary = normalizeCallToAction(data.secondary);
  if (secondary) {
    normalized.secondary = secondary;
  }

  return normalized;
}

function normalizeBlockData(type, data) {
  if (!isPlainObject(data)) {
    return data;
  }

  let normalized = { ...data };

  if (type === 'hero' && typeof normalized.subline === 'string' && !normalized.subheadline) {
    normalized.subheadline = normalized.subline;
  }

  if (type === 'process_steps') {
    normalized = normalizeProcessStepsCtas(normalized);
  }

  if (type === 'package_summary' && Array.isArray(normalized.plans)) {
    normalized = { ...normalized, plans: normalized.plans.map(normalizePackagePlanCtas) };
  }

  if (type === 'cta') {
    normalized = normalizeCtaBlockData(normalized);
  }

  if (isPlainObject(normalized.cta) && !['process_steps', 'package_summary'].includes(type)) {
    const primary = normalizeCallToAction(normalized.cta.primary);
    const secondary = normalizeCallToAction(normalized.cta.secondary);
    const collapsed = normalizeCallToAction(normalized.cta);
    normalized.cta = primary || collapsed || normalized.cta;
    if (secondary && !normalized.ctaSecondary) {
      normalized.ctaSecondary = secondary;
    }
  }

  delete normalized.subline;

  return normalized;
}

export function normalizeBlockContract(block) {
  if (!isPlainObject(block)) {
    return block;
  }

  const normalized = { ...block };
  const type = normalized.type;

  if (typeof type === 'string') {
    normalized.variant = normalizeBlockVariant(type, normalized.variant);
  }

  if (normalized.data !== undefined) {
    normalized.data = normalizeBlockData(type, normalized.data);
  }

  return normalized;
}

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

function hasContent(value) {
  return typeof value === 'string' && value.trim().length > 0;
}

function validateHeroData(data) {
  if (!hasContent(data?.headline)) {
    return false;
  }

  const cta = data?.cta ?? {};
  if (hasContent(cta.label) && hasContent(cta.href)) {
    return true;
  }

  if (hasContent(cta.primary?.label) && hasContent(cta.primary?.href)) {
    return true;
  }

  return false;
}

function validateFeatureListData(data) {
  if (!hasContent(data?.title) || !Array.isArray(data?.items) || data.items.length === 0) {
    return false;
  }

  return data.items.every(item => hasContent(item?.id) && hasContent(item?.title) && hasContent(item?.description));
}

function validateProcessStepsData(data) {
  if (!hasContent(data?.title) || !Array.isArray(data?.steps) || data.steps.length < 2) {
    return false;
  }

  return data.steps.every(step => hasContent(step?.id) && hasContent(step?.title) && hasContent(step?.description));
}

const DATA_VALIDATORS = {
  hero: validateHeroData,
  feature_list: validateFeatureListData,
  process_steps: validateProcessStepsData,
  testimonial: data => hasContent(data?.quote) && hasContent(data?.author?.name),
  rich_text: data => hasContent(data?.body)
};

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

  const validator = DATA_VALIDATORS[block.type];
  if (validator && !validator(block.data)) {
    return { valid: false, reason: 'Block data is missing required fields' };
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
