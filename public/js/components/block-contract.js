import schema from './block-contract.schema.json' assert { type: 'json' };

const BLOCK_VARIANTS = {
  hero: ['centered_cta', 'media_right', 'media_left'],
  feature_list: ['stacked_cards', 'icon_grid'],
  process_steps: ['timeline_horizontal', 'timeline_vertical'],
  testimonial: ['single_quote', 'quote_wall'],
  rich_text: ['prose']
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
  if (typeof block.variant !== 'string' || !BLOCK_VARIANTS[block.type].includes(block.variant)) {
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
export const TOKENS = TOKEN_ENUMS;
