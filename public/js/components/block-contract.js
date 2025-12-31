import Ajv from 'ajv';

const CONTRACT_BLOCKS = {
  hero: ['centered-cta', 'media-right', 'media-left', 'minimal'],
  feature_list: ['text-columns', 'card-stack', 'grid-bullets', 'detailed-cards'],
  process_steps: ['numbered-vertical', 'numbered-horizontal'],
  cta: ['split', 'full-width'],
  info_media: ['stacked', 'image-left', 'image-right', 'switcher'],
  stat_strip: ['three-up', 'cards', 'inline', 'centered'],
  audience_spotlight: ['tabs', 'tiles', 'single-focus'],
  package_summary: ['toggle', 'comparison-cards'],
  faq: ['accordion'],
  contact_form: ['default', 'compact'],
  testimonial: ['single-quote', 'quote-wall'],
  rich_text: ['prose'],
  system_module: ['switcher'],
  case_showcase: ['tabs']
};

const VARIANT_ALIASES = {
  hero: {
    centered_cta: 'centered-cta',
    media_right: 'media-right',
    media_left: 'media-left'
  },
  feature_list: {
    stacked_cards: 'card-stack',
    icon_grid: 'grid-bullets'
  },
  process_steps: {
    timeline: 'numbered-vertical',
    timeline_vertical: 'numbered-vertical',
    timeline_horizontal: 'numbered-horizontal'
  },
  testimonial: {
    single_quote: 'single-quote',
    quote_wall: 'quote-wall'
  },
  cta: {
    full_width: 'full-width'
  },
  stat_strip: {
    three_up: 'three-up'
  },
  audience_spotlight: {
    single_focus: 'single-focus'
  }
};

const SECTION_APPEARANCE_ALIASES = {
  default: 'contained',
  surface: 'contained',
  contrast: 'full',
  image: 'full',
  'image-fixed': 'full'
};

const SECTION_APPEARANCE_CANONICAL = ['contained', 'full', 'card'];
const SECTION_APPEARANCES = [...SECTION_APPEARANCE_CANONICAL];
const SECTION_LAYOUTS = ['normal', 'fullwidth', 'card'];
const SECTION_BACKGROUND_MODES = ['none', 'color', 'image'];
const SECTION_BACKGROUND_ATTACHMENTS = ['scroll', 'fixed'];

const TOKEN_ENUMS = {
  background: ['primary', 'secondary', 'muted', 'accent', 'surface'],
  spacing: ['small', 'normal', 'large'],
  width: ['narrow', 'normal', 'wide'],
  columns: ['single', 'two', 'three', 'four'],
  accent: ['brandA', 'brandB', 'brandC']
};

const TOKEN_ALIASES = {
  background: {
    default: 'surface'
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
    "tokens": { "$ref": "#/definitions/Tokens" },
    "sectionAppearance": { "enum": SECTION_APPEARANCES },
    "backgroundImage": { "type": "string" },
    "meta": { "$ref": "#/definitions/BlockMeta" }
  },
  "oneOf": [
    {
      "title": "Hero block",
      "properties": {
        "type": { "const": "hero" },
        "variant": { "enum": ["centered-cta", "media-right", "media-left", "minimal"] },
        "data": { "$ref": "#/definitions/HeroData" }
      },
      "required": ["type", "variant", "data"]
    },
    {
      "title": "Feature list block",
      "properties": {
        "type": { "const": "feature_list" },
        "variant": { "enum": ["text-columns", "card-stack", "grid-bullets", "detailed-cards"] },
        "data": { "$ref": "#/definitions/FeatureListData" }
      },
      "required": ["type", "variant", "data"]
    },
    {
      "title": "Process steps block",
      "properties": {
        "type": { "const": "process_steps" },
        "variant": { "enum": ["numbered-vertical", "numbered-horizontal"] },
        "data": { "$ref": "#/definitions/ProcessStepsData" }
      },
      "required": ["type", "variant", "data"]
    },
    {
      "title": "Testimonial block",
      "properties": {
        "type": { "const": "testimonial" },
        "variant": { "enum": ["single-quote", "quote-wall"] },
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
        "variant": { "enum": ["stacked", "image-left", "image-right", "switcher"] },
        "data": { "$ref": "#/definitions/InfoMediaData" }
      },
      "required": ["type", "variant", "data"]
    },
    {
      "title": "CTA block",
      "properties": {
        "type": { "const": "cta" },
        "variant": { "enum": ["split", "full-width"] },
        "data": { "$ref": "#/definitions/CtaBlockData" }
      },
      "required": ["type", "variant", "data"]
    },
    {
      "title": "Stat strip block",
      "properties": {
        "type": { "const": "stat_strip" },
        "variant": { "enum": ["inline", "cards", "centered", "three-up"] },
        "data": { "$ref": "#/definitions/StatStripData" }
      },
      "required": ["type", "variant", "data"]
    },
    {
      "title": "Audience spotlight block",
      "properties": {
        "type": { "const": "audience_spotlight" },
        "variant": { "enum": ["tabs", "tiles", "single-focus"] },
        "data": { "$ref": "#/definitions/AudienceSpotlightData" }
      },
      "required": ["type", "variant", "data"]
    },
    {
      "title": "Contact form block",
      "properties": {
        "type": { "const": "contact_form" },
        "variant": { "enum": ["default", "compact"] },
        "data": { "$ref": "#/definitions/ContactFormData" }
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
        "background": { "enum": ["primary", "secondary", "muted", "accent", "surface"] },
        "spacing": { "enum": ["small", "normal", "large"] },
        "width": { "enum": ["narrow", "normal", "wide"] },
        "columns": { "enum": ["single", "two", "three", "four"] },
        "accent": { "enum": ["brandA", "brandB", "brandC"] }
      }
    },
    "SectionBackground": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "mode": { "enum": SECTION_BACKGROUND_MODES },
        "colorToken": { "enum": TOKEN_ENUMS.background },
        "imageId": { "type": "string" },
        "attachment": { "enum": SECTION_BACKGROUND_ATTACHMENTS },
        "overlay": { "type": "number", "minimum": 0, "maximum": 1 }
      }
    },
    "SectionStyle": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "layout": { "enum": SECTION_LAYOUTS },
        "background": { "$ref": "#/definitions/SectionBackground" }
      }
    },
    "BlockMeta": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "anchor": { "type": "string" },
        "sectionStyle": { "$ref": "#/definitions/SectionStyle" }
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
    "ContactFormData": {
      "type": "object",
      "additionalProperties": false,
      "required": ["title", "intro", "recipient", "submitLabel", "privacyHint"],
      "properties": {
        "title": { "type": "string", "minLength": 1 },
        "intro": { "type": "string", "minLength": 1 },
        "recipient": { "type": "string", "minLength": 1 },
        "submitLabel": { "type": "string", "minLength": 1 },
        "privacyHint": { "type": "string", "minLength": 1 }
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

export function normalizeBlockVariant(type, variant) {
  if (typeof variant !== 'string') {
    return variant;
  }

  const trimmed = variant.trim();
  const aliasForType = VARIANT_ALIASES[type] || {};
  const hyphenated = trimmed.includes('_') ? trimmed.replace(/_/g, '-') : trimmed;
  return aliasForType[trimmed] || aliasForType[hyphenated] || hyphenated;
}

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
  const type = typeof normalized.type === 'string' ? normalized.type.trim() : normalized.type;

  if (typeof type === 'string') {
    normalized.type = type;
    normalized.variant = normalizeBlockVariant(type, normalized.variant);
  }

  if (normalized.data !== undefined) {
    normalized.data = normalizeBlockData(type, normalized.data);
  }

  const appearance = normalizeSectionAppearance(normalized.sectionAppearance);
  if (appearance) {
    normalized.sectionAppearance = appearance;
  } else {
    delete normalized.sectionAppearance;
  }

  const normalizedMeta = normalizeBlockMeta(normalized.meta, {
    sectionAppearance: appearance,
    backgroundImage: normalized.backgroundImage
  });

  if (normalizedMeta) {
    normalized.meta = normalizedMeta;
  } else {
    delete normalized.meta;
  }

  delete normalized.backgroundImage;

  return normalized;
}

const BLOCK_VARIANTS = Object.entries(CONTRACT_BLOCKS).reduce((accumulator, [type, variants]) => {
  accumulator[type] = [...variants];
  return accumulator;
}, {});

const DEPRECATED_BLOCK_TYPES = {
  system_module: { replacement: { type: 'info_media', variant: 'switcher' } },
  case_showcase: { replacement: { type: 'audience_spotlight', variant: 'tabs' } }
};

function normalizeSectionAppearance(appearance) {
  if (typeof appearance !== 'string') {
    return undefined;
  }

  const normalized = appearance.trim();
  const preset = SECTION_APPEARANCE_ALIASES[normalized] || normalized;
  return SECTION_APPEARANCE_CANONICAL.includes(preset) ? preset : undefined;
}

export function resolveSectionAppearancePreset(appearance) {
  return normalizeSectionAppearance(appearance) || 'contained';
}

const APPEARANCE_TO_LAYOUT = {
  contained: 'normal',
  full: 'fullwidth',
  card: 'card'
};

function normalizeSectionLayout(layout, legacyAppearance) {
  if (SECTION_LAYOUTS.includes(layout)) {
    return layout;
  }

  const preset = resolveSectionAppearancePreset(legacyAppearance);
  return APPEARANCE_TO_LAYOUT[preset] || 'normal';
}

function normalizeColorToken(value) {
  const normalized = TOKEN_ALIASES.background?.[value] || value;

  if (TOKEN_ENUMS.background.includes(normalized)) {
    return normalized;
  }
  return undefined;
}

function clampOverlay(value) {
  if (value === null || value === undefined) {
    return undefined;
  }

  const numeric = typeof value === 'number' ? value : Number.parseFloat(value);
  if (!Number.isFinite(numeric)) {
    return undefined;
  }

  return Math.min(1, Math.max(0, numeric));
}

function normalizeBackgroundAttachment(value) {
  return SECTION_BACKGROUND_ATTACHMENTS.includes(value) ? value : 'scroll';
}

export function normalizeSectionBackground(background, legacyBackgroundImage, layout = 'normal', legacyAppearance) {
  const source = isPlainObject(background) ? background : {};
  const legacyImage = hasContent(legacyBackgroundImage) ? legacyBackgroundImage : undefined;
  const baseMode = SECTION_BACKGROUND_MODES.includes(source.mode)
    ? source.mode
    : SECTION_BACKGROUND_MODES.includes(source.type)
      ? source.type
      : undefined;
  const colorToken = normalizeColorToken(source.colorToken) || normalizeColorToken(source.color);
  const imageId = hasContent(source.image)
    ? source.image
    : hasContent(source.imageId)
      ? source.imageId
      : legacyImage;
  const overlay = clampOverlay(source.overlay);
  const defaultAttachment = legacyAppearance === 'image-fixed' ? 'fixed' : 'scroll';
  const attachment = normalizeBackgroundAttachment(source.attachment || defaultAttachment);

  let mode = baseMode;
  if (!mode && colorToken) {
    mode = 'color';
  }
  if (!mode && imageId) {
    mode = 'image';
  }
  if (!mode) {
    mode = 'none';
  }

  const layoutSupportsImages = layout === 'fullwidth';

  const normalized = { mode };

  if (mode === 'color') {
    if (!colorToken) {
      normalized.mode = 'none';
      return normalized;
    }

    normalized.colorToken = colorToken;
    return normalized;
  }

  if (mode === 'image') {
    if (!layoutSupportsImages || !hasContent(imageId)) {
      normalized.mode = 'none';
      return normalized;
    }

    normalized.imageId = imageId;
    normalized.attachment = attachment;

    normalized.overlay = overlay ?? 0;

    return normalized;
  }

  if (!layoutSupportsImages) {
    delete normalized.imageId;
    delete normalized.attachment;
    delete normalized.overlay;

    return normalized;
  }

  return normalized;
}

function normalizeSectionStyle(sectionStyle, legacyBackgroundImage, legacyAppearance) {
  const source = isPlainObject(sectionStyle) ? sectionStyle : {};
  const layout = normalizeSectionLayout(source.layout, legacyAppearance);
  const normalizedBackground = normalizeSectionBackground(
    source.background,
    legacyBackgroundImage,
    layout,
    legacyAppearance
  );

  const normalized = { layout };
  if (normalizedBackground) {
    normalized.background = normalizedBackground;
  }

  return normalized;
}

function normalizeBlockMeta(meta, { sectionAppearance, backgroundImage } = {}) {
  const normalizedMeta = isPlainObject(meta) ? { ...meta } : {};
  const anchor = hasContent(normalizedMeta.anchor) ? normalizedMeta.anchor.trim() : undefined;
  const sectionStyle = normalizeSectionStyle(
    normalizedMeta.sectionStyle,
    backgroundImage,
    sectionAppearance
  );

  const normalized = {};
  if (anchor) {
    normalized.anchor = anchor;
  }

  if (sectionStyle) {
    normalized.sectionStyle = sectionStyle;
  }

  return Object.keys(normalized).length ? normalized : undefined;
}

function isPlainObject(value) {
  return value !== null && typeof value === 'object' && !Array.isArray(value);
}

function hasContent(value) {
  return typeof value === 'string' && value.trim().length > 0;
}

export function validateSectionBackground(background, layout = 'normal') {
  if (!isPlainObject(background) || !SECTION_LAYOUTS.includes(layout)) {
    return false;
  }

  const allowedKeys = ['mode', 'colorToken', 'imageId', 'attachment', 'overlay'];
  if (Object.keys(background).some(key => !allowedKeys.includes(key))) {
    return false;
  }

  if (!SECTION_BACKGROUND_MODES.includes(background.mode)) {
    return false;
  }

  const overlayAllowed = layout === 'fullwidth' && background.mode === 'image';
  if (background.overlay !== undefined) {
    const numericOverlay = Number.parseFloat(background.overlay);
    if (!overlayAllowed || !Number.isFinite(numericOverlay) || numericOverlay < 0 || numericOverlay > 1) {
      return false;
    }
  }

  if (background.attachment !== undefined) {
    if (background.mode !== 'image' || layout !== 'fullwidth') {
      return false;
    }
    if (!SECTION_BACKGROUND_ATTACHMENTS.includes(background.attachment)) {
      return false;
    }
  }

  if (background.mode === 'none') {
    return background.colorToken === undefined
      && background.imageId === undefined
      && background.overlay === undefined;
  }

  if (background.mode === 'color') {
    const normalizedToken = normalizeColorToken(background.colorToken);

    return normalizedToken !== undefined
      && background.imageId === undefined
      && background.overlay === undefined
      && background.attachment === undefined;
  }

  if (background.mode === 'image') {
    if (layout !== 'fullwidth') {
      return false;
    }

    return hasContent(background.imageId);
  }

  return true;
}

export function normalizeVariant(type, variant) {
  return normalizeBlockVariant(type, variant);
}

const ajv = new Ajv({ allErrors: true, strict: false });
const ajvValidate = ajv.compile(schema);

function formatAjvErrors(errors = []) {
  if (!Array.isArray(errors)) {
    return [];
  }

  return errors.map(error => ({
    message: error.message,
    instancePath: error.instancePath,
    schemaPath: error.schemaPath,
    params: error.params
  }));
}

export function validateBlockContract(block) {
  const normalized = normalizeBlockContract(block);
  const valid = ajvValidate(normalized);
  const errors = formatAjvErrors(ajvValidate.errors);

  return { valid, errors, reason: errors[0]?.message, normalized };
}

export function getBlockVariants(type) {
  return BLOCK_VARIANTS[type] ? [...BLOCK_VARIANTS[type]] : [];
}

export const BLOCK_CONTRACT_SCHEMA = schema;
export const BLOCK_TYPES = Object.keys(BLOCK_VARIANTS);
export const ACTIVE_BLOCK_TYPES = BLOCK_TYPES.filter(type => !DEPRECATED_BLOCK_TYPES[type]);
export const TOKENS = TOKEN_ENUMS;
export const DEPRECATED_BLOCK_MAP = DEPRECATED_BLOCK_TYPES;
export const SECTION_APPEARANCE_PRESETS = SECTION_APPEARANCES;
export const SECTION_APPEARANCE_ALIAS_MAP = SECTION_APPEARANCE_ALIASES;
