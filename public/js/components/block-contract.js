import { RENDERER_MATRIX } from './block-renderer-matrix-data.js';
import { SECTION_INTENTS, normalizeSectionIntent } from './section-intents.js';

// Block contract aligned with docs/calserver-block-consolidation.md
const VARIANT_ALIASES = {
  hero: {
    'media-right': 'media_right',
    'media-left': 'media_left',
    'centered-cta': 'centered_cta',
    'media-video': 'media_video'
  },
  feature_list: {
    stacked_cards: 'card-stack',
    icon_grid: 'grid-bullets'
  },
  audience_spotlight: {
    'single_focus': 'single-focus'
  },
  stat_strip: {
    'three-up': 'cards',
    three_up: 'cards'
  }
};

const SECTION_APPEARANCE_ALIASES = {
  default: 'contained',
  surface: 'contained',
  contrast: 'full',
  image: 'full',
  'image-fixed': 'full'
};

const SECTION_APPEARANCES = ['contained', 'full', 'card', ...Object.keys(SECTION_APPEARANCE_ALIASES)];
const SECTION_LAYOUTS = ['normal', 'full', 'card'];
const SECTION_LAYOUT_ALIASES = {
  fullwidth: 'full'
};
const SECTION_BACKGROUND_MODES = ['none', 'color', 'image'];
const SECTION_BACKGROUND_ATTACHMENTS = ['scroll', 'fixed'];
const CTA_GROUP_TYPES = ['hero'];

const TOKEN_ENUMS = {
  background: ['primary', 'secondary', 'muted', 'accent', 'surface'],
  spacing: ['small', 'normal', 'large'],
  width: ['narrow', 'normal', 'wide'],
  columns: ['single', 'two', 'three', 'four'],
  accent: ['brandA', 'brandB', 'brandC']
};

export const TOKEN_ALIASES = {
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
        "variant": { "enum": ["centered_cta", "media-right", "media_right", "media-left", "media_left", "media_video", "media-video", "minimal"] },
        "data": { "$ref": "#/definitions/HeroData" }
      },
      "required": ["type", "variant", "data"]
    },
    {
      "title": "Feature list block",
      "properties": {
        "type": { "const": "feature_list" },
        "variant": { "enum": ["stacked_cards", "icon_grid", "detailed-cards", "grid-bullets", "text-columns", "card-stack", "slider"] },
        "data": { "$ref": "#/definitions/FeatureListData" }
      },
      "required": ["type", "variant", "data"]
    },
    {
      "title": "Content slider block",
      "properties": {
        "type": { "const": "content_slider" },
        "variant": { "enum": ["words", "images"] },
        "data": { "$ref": "#/definitions/ContentSliderData" }
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
        "variant": { "enum": ["inline", "cards", "centered", "highlight", "three-up", "three_up"] },
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
        "overlay": { "type": "number", "minimum": 0, "maximum": 1 },
        "bleed": { "type": "boolean" }
      }
    },
    "SectionContainer": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "width": { "enum": ["normal", "wide", "full"], "default": "normal" },
        "frame": { "enum": ["none", "card"], "default": "none" },
        "spacing": { "enum": ["compact", "normal", "generous"], "default": "normal" }
      }
    },
    "SectionStyle": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "layout": { "enum": SECTION_LAYOUTS, "default": "normal" },
        "intent": { "enum": SECTION_INTENTS },
        "background": { "$ref": "#/definitions/SectionBackground" },
        "container": { "$ref": "#/definitions/SectionContainer" }
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
        "cta": { "$ref": "#/definitions/CallToActionGroup" },
        "video": { "$ref": "#/definitions/HeroVideo" },
        "referenceLink": { "$ref": "#/definitions/CallToAction" }
      }
    },
    "HeroVideo": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "embedUrl": { "type": "string" },
        "title": { "type": "string" },
        "subtitle": { "type": "string" },
        "note": { "type": "string" },
        "link": { "$ref": "#/definitions/CallToAction" }
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
        "columns": { "type": "integer", "minimum": 1, "maximum": 6 },
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
    "ContentSliderData": {
      "type": "object",
      "additionalProperties": false,
      "required": ["slides"],
      "properties": {
        "title": { "type": "string" },
        "eyebrow": { "type": "string" },
        "intro": { "type": "string" },
        "slides": {
          "type": "array",
          "items": { "$ref": "#/definitions/ContentSlide" },
          "minItems": 1
        }
      }
    },
    "ContentSlide": {
      "type": "object",
      "additionalProperties": false,
      "required": ["id", "label"],
      "properties": {
        "id": { "type": "string", "minLength": 1 },
        "label": { "type": "string", "minLength": 1 },
        "body": { "type": "string" },
        "imageId": { "type": "string" },
        "imageAlt": { "type": "string" },
        "link": { "$ref": "#/definitions/CallToAction" }
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
        "pdf": { "$ref": "#/definitions/CallToAction" },
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

function normalizeContentSliderData(data) {
  if (!isPlainObject(data)) {
    return data;
  }

  const slides = Array.isArray(data.slides)
    ? data.slides
        .map(slide => {
          if (!isPlainObject(slide)) {
            return null;
          }

          const normalizedSlide = { ...slide };
          if (normalizedSlide.link) {
            const link = normalizeCallToAction(normalizedSlide.link);
            normalizedSlide.link = link || undefined;
          }

          if (typeof normalizedSlide.imageAlt === 'string' && normalizedSlide.imageAlt.trim() === '') {
            delete normalizedSlide.imageAlt;
          }

          return normalizedSlide;
        })
        .filter(Boolean)
    : undefined;

  const normalized = { ...data };

  if (slides) {
    normalized.slides = slides;
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

  if (type === 'content_slider') {
    normalized = normalizeContentSliderData(normalized);
  }

  if (isPlainObject(normalized.cta) && !['process_steps', 'package_summary'].includes(type)) {
    const primary = normalizeCallToAction(normalized.cta.primary ?? normalized.cta);
    const secondary = normalizeCallToAction(normalized.cta.secondary);

    if (CTA_GROUP_TYPES.includes(type)) {
      const groupedCta = {};
      if (primary) {
        groupedCta.primary = primary;
        if (secondary) {
          groupedCta.secondary = secondary;
        }
      }

      normalized.cta = Object.keys(groupedCta).length ? groupedCta : undefined;
    } else {
      normalized.cta = primary || normalized.cta;
    }

    delete normalized.ctaSecondary;
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

  if (normalized.tokens !== undefined) {
    normalized.tokens = normalizeTokens(normalized.tokens) || normalized.tokens;
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

function normalizeSectionAppearance(appearance) {
  if (typeof appearance !== 'string') {
    return undefined;
  }

  const normalized = appearance.trim();
  return SECTION_APPEARANCES.includes(normalized) ? normalized : undefined;
}

export function resolveSectionAppearancePreset(appearance) {
  const normalized = normalizeSectionAppearance(appearance) || 'contained';
  return SECTION_APPEARANCE_ALIASES[normalized] || normalized;
}

const APPEARANCE_TO_LAYOUT = {
  contained: 'normal',
  full: 'full',
  card: 'card'
};

function normalizeSectionLayout(layout, legacyAppearance) {
  const normalizedLayout = typeof layout === 'string' ? layout.trim() : undefined;
  const mappedLayout = normalizedLayout ? SECTION_LAYOUT_ALIASES[normalizedLayout] || normalizedLayout : undefined;
  if (mappedLayout && SECTION_LAYOUTS.includes(mappedLayout)) {
    return mappedLayout;
  }

  const preset = normalizeSectionAppearance(legacyAppearance);
  if (preset && APPEARANCE_TO_LAYOUT[preset]) {
    return APPEARANCE_TO_LAYOUT[preset];
  }

  return undefined;
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

export function normalizeSectionBackground(background, legacyBackgroundImage, layout, legacyAppearance) {
  if (!SECTION_LAYOUTS.includes(layout)) {
    return undefined;
  }

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

  const layoutSupportsImages = layout === 'full';

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

function normalizeSectionContainer(container) {
  if (!isPlainObject(container)) return undefined;

  const WIDTHS = ['normal', 'wide', 'full'];
  const FRAMES = ['none', 'card'];
  const SPACINGS = ['compact', 'normal', 'generous'];

  const width = WIDTHS.includes(container.width) ? container.width : 'normal';
  const frame = FRAMES.includes(container.frame) ? container.frame : 'none';
  const spacing = SPACINGS.includes(container.spacing) ? container.spacing : 'normal';

  return { width, frame, spacing };
}

function normalizeSectionStyle(sectionStyle, legacyBackgroundImage, legacyAppearance) {
  const source = isPlainObject(sectionStyle) ? sectionStyle : {};
  const layout = normalizeSectionLayout(source.layout, legacyAppearance);
  if (!layout) {
    return undefined;
  }
  const intent = normalizeSectionIntent(source.intent);
  const normalizedBackground = normalizeSectionBackground(
    source.background,
    legacyBackgroundImage,
    layout,
    legacyAppearance
  );

  const normalized = { layout };
  if (intent) {
    normalized.intent = intent;
  }
  if (normalizedBackground) {
    normalized.background = normalizedBackground;
  }

  const container = normalizeSectionContainer(source.container);
  if (container) {
    normalized.container = container;
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

function validateTokens(tokens) {
  if (tokens === undefined) {
    return true;
  }
  if (!isPlainObject(tokens)) {
    return false;
  }
  return Object.entries(tokens).every(([key, value]) => {
    const normalizedValue = TOKEN_ALIASES[key]?.[value] || value;
    return TOKEN_ENUMS[key]?.includes(normalizedValue);
  });
}

function normalizeTokens(tokens) {
  if (!isPlainObject(tokens)) {
    return undefined;
  }

  const normalized = { ...tokens };
  Object.entries(normalized).forEach(([key, value]) => {
    if (typeof value !== 'string') {
      return;
    }
    normalized[key] = TOKEN_ALIASES[key]?.[value] || value;
  });

  return normalized;
}

function hasContent(value) {
  return typeof value === 'string' && value.trim().length > 0;
}

export function validateSectionBackground(background, layout) {
  if (!isPlainObject(background) || !SECTION_LAYOUTS.includes(layout)) {
    return false;
  }

  const allowedKeys = ['mode', 'colorToken', 'imageId', 'attachment', 'overlay', 'bleed'];
  if (Object.keys(background).some(key => !allowedKeys.includes(key))) {
    return false;
  }

  if (!SECTION_BACKGROUND_MODES.includes(background.mode)) {
    return false;
  }

  const overlayAllowed = layout === 'full' && background.mode === 'image';
  if (background.overlay !== undefined) {
    const numericOverlay = Number.parseFloat(background.overlay);
    if (!overlayAllowed || !Number.isFinite(numericOverlay) || numericOverlay < 0 || numericOverlay > 1) {
      return false;
    }
  }

  if (background.attachment !== undefined) {
    if (background.mode !== 'image' || layout !== 'full') {
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
    if (layout !== 'full') {
      return false;
    }

    return hasContent(background.imageId);
  }

  return true;
}

function validateSectionStyle(style) {
  if (style === undefined) {
    return true;
  }

  if (!isPlainObject(style)) {
    return false;
  }

  const allowedKeys = ['layout', 'background', 'intent', 'container'];
  if (Object.keys(style).some(key => !allowedKeys.includes(key))) {
    return false;
  }

  const layout = normalizeSectionLayout(style.layout);
  if (!layout) {
    return false;
  }

  if (style.intent !== undefined && !normalizeSectionIntent(style.intent)) {
    return false;
  }

  if (style.background === undefined) {
    return true;
  }

  return validateSectionBackground(style.background, layout);
}

function validateBlockMeta(meta) {
  if (meta === undefined) {
    return true;
  }

  if (!isPlainObject(meta)) {
    return false;
  }

  if (meta.anchor !== undefined && !hasContent(meta.anchor)) {
    return false;
  }

  return validateSectionStyle(meta.sectionStyle);
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

function validateContentSliderData(data) {
  if (!Array.isArray(data?.slides) || data.slides.length === 0) {
    return false;
  }

  return data.slides.every(slide => hasContent(slide?.id) && hasContent(slide?.label));
}

const DATA_VALIDATORS = {
  hero: validateHeroData,
  feature_list: validateFeatureListData,
  process_steps: validateProcessStepsData,
  testimonial: data => hasContent(data?.quote) && hasContent(data?.author?.name),
  rich_text: data => hasContent(data?.body),
  content_slider: validateContentSliderData,
  contact_form: data => (
    hasContent(data?.title)
    && hasContent(data?.intro)
    && hasContent(data?.recipient)
    && hasContent(data?.submitLabel)
    && hasContent(data?.privacyHint)
  )
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

  if (!validateBlockMeta(block.meta)) {
    return { valid: false, reason: 'Block meta is invalid' };
  }

  const appearance = normalizeSectionAppearance(block.sectionAppearance) || 'contained';
  if (block.sectionAppearance !== undefined && !normalizeSectionAppearance(block.sectionAppearance)) {
    return { valid: false, reason: 'Unknown section appearance preset' };
  }

  if (block.backgroundImage !== undefined && !hasContent(block.backgroundImage)) {
    return { valid: false, reason: 'Background image must be a non-empty string' };
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
export const SECTION_APPEARANCE_PRESETS = SECTION_APPEARANCES;
export const SECTION_APPEARANCE_ALIAS_MAP = SECTION_APPEARANCE_ALIASES;
