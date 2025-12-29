import { Editor } from '../vendor/tiptap/core.esm.js';
import StarterKit from '../vendor/tiptap/starter-kit.esm.js';
import {
  ACTIVE_BLOCK_TYPES,
  BLOCK_CONTRACT_SCHEMA,
  DEPRECATED_BLOCK_MAP,
  normalizeBlockContract,
  normalizeVariant,
  validateBlockContract
} from './block-contract.js';
import { RENDERER_MATRIX } from './block-renderer-matrix.js';
const BLOCK_TYPE_LABELS = {
  hero: 'Hero',
  feature_list: 'Feature list',
  process_steps: 'Process steps',
  testimonial: 'Testimonial',
  rich_text: 'Rich text',
  info_media: 'Info + media',
  stat_strip: 'Stat strip',
  audience_spotlight: 'Audience spotlight',
  package_summary: 'Package summary',
  faq: 'FAQ',
  system_module: 'System module (deprecated)',
  case_showcase: 'Case showcase (deprecated)',
  cta: 'CTA'
};

const createId = () => {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID();
  }
  return `id-${Math.random().toString(16).slice(2)}-${Date.now()}`;
};

const deepClone = value => JSON.parse(JSON.stringify(value));

const getRendererVariants = type => Object.keys(RENDERER_MATRIX[type] || {});

const buildRecoverableVariantBlock = (block, error) => {
  if (!block || typeof block !== 'object') {
    return null;
  }

  const normalized = normalizeBlockContract(deepClone(block));
  const type = normalized?.type;
  const allowedVariants = type ? getRendererVariants(type) : [];

  if (!type || !allowedVariants.length) {
    return null;
  }

  const normalizedVariant = normalizeVariant(type, normalized.variant);
  const variantSupported = typeof normalizedVariant === 'string' && allowedVariants.includes(normalizedVariant);

  if (variantSupported) {
    return null;
  }

  const recoverable = {
    ...normalized,
    id: typeof normalized.id === 'string' && normalized.id ? normalized.id : createId(),
    variant: normalizedVariant,
    invalidVariant: {
      attemptedVariant: normalizedVariant,
      allowedVariants,
      reason: error instanceof Error ? error.message : 'Unsupported variant'
    }
  };

  if (!isPlainObject(recoverable.data)) {
    recoverable.data = {};
  }

  return recoverable;
};

const resolveBlockSchema = (type, variant) => {
  const blocks = BLOCK_CONTRACT_SCHEMA.oneOf || [];

  for (const entry of blocks) {
    if (entry?.properties?.type?.const === type) {
      return entry;
    }

    if (Array.isArray(entry?.oneOf)) {
      const matches = entry.oneOf.filter(candidate => candidate?.properties?.type?.const === type);
      if (!matches.length) {
        continue;
      }

      if (!variant) {
        return matches[0];
      }

      const exactMatch = matches.find(candidate => {
        const variantSchema = candidate?.properties?.variant;
        if (!variantSchema) {
          return false;
        }
        if (typeof variantSchema.const === 'string') {
          return normalizeVariant(type, variant) === variantSchema.const;
        }
        if (Array.isArray(variantSchema.enum)) {
          return variantSchema.enum.includes(normalizeVariant(type, variant));
        }
        return false;
      });

      return exactMatch || matches[0];
    }
  }

  return null;
};

const ensureRendererVariant = (type, requestedVariant) => {
  const variants = getRendererVariants(type);
  if (!variants.length) {
    throw new Error(`No renderer variants registered for type: ${type}`);
  }
  const normalizedVariant = normalizeVariant(type, requestedVariant);
  if (requestedVariant) {
    if (!variants.includes(normalizedVariant)) {
      throw new Error(`Unsupported variant for ${type}: ${requestedVariant}`);
    }
    return normalizedVariant;
  }
  return normalizedVariant || variants[0];
};

const stripHtml = html => {
  if (!html) {
    return '';
  }
  const container = document.createElement('div');
  container.innerHTML = html;
  return container.textContent || container.innerText || '';
};

const getSchemaDefinition = ref => {
  if (!ref || typeof ref !== 'string' || !ref.startsWith('#/definitions/')) {
    return null;
  }
  const key = ref.replace('#/definitions/', '');
  return BLOCK_CONTRACT_SCHEMA.definitions?.[key] || null;
};

const isPlainObject = value => value !== null && typeof value === 'object' && !Array.isArray(value);

function sanitizeValue(value, schema, legacyIgnores = []) {
  if (schema?.$ref) {
    const definition = getSchemaDefinition(schema.$ref);
    return sanitizeValue(value, definition, legacyIgnores);
  }

  if (schema?.oneOf && Array.isArray(schema.oneOf)) {
    for (const entry of schema.oneOf) {
      try {
        return sanitizeValue(value, entry, legacyIgnores);
      } catch (error) {
        // Try next entry
      }
    }
    throw new Error('Value does not match any allowed schema variant');
  }

  if (schema?.type === 'object') {
    if (!isPlainObject(value)) {
      throw new Error('Expected object');
    }
    const allowedKeys = Object.keys(schema.properties || {});
    const disallowed = Object.keys(value).filter(key => !allowedKeys.includes(key) && !legacyIgnores.includes(key));
    if (disallowed.length > 0) {
      throw new Error(`Unexpected field: ${disallowed[0]}`);
    }
    const required = schema.required || [];
    required.forEach(key => {
      if (value[key] === undefined) {
        throw new Error(`Missing required field: ${key}`);
      }
    });
    const sanitized = {};
    allowedKeys.forEach(key => {
      if (value[key] !== undefined) {
        sanitized[key] = sanitizeValue(value[key], schema.properties[key], legacyIgnores);
      }
    });
    return sanitized;
  }

  if (schema?.type === 'array') {
    if (!Array.isArray(value)) {
      throw new Error('Expected array');
    }
    if (typeof schema.minItems === 'number' && value.length < schema.minItems) {
      throw new Error(`Array requires at least ${schema.minItems} entries`);
    }
    return value.map(entry => sanitizeValue(entry, schema.items, legacyIgnores));
  }

  if (schema?.type === 'string') {
    if (typeof value !== 'string') {
      throw new Error('Expected string');
    }
    if (schema.minLength && value.length < schema.minLength) {
      throw new Error('String is too short');
    }
    if (Array.isArray(schema.enum) && !schema.enum.includes(value)) {
      throw new Error(`Value must be one of: ${schema.enum.join(', ')}`);
    }
    if (schema.const && value !== schema.const) {
      throw new Error(`Value must equal ${schema.const}`);
    }
    return value;
  }

  if (schema?.type === 'number') {
    if (typeof value !== 'number') {
      throw new Error('Expected number');
    }
    if (schema.minimum !== undefined && value < schema.minimum) {
      throw new Error('Number below minimum');
    }
    if (schema.maximum !== undefined && value > schema.maximum) {
      throw new Error('Number above maximum');
    }
    return value;
  }

  if (schema?.enum && Array.isArray(schema.enum)) {
    if (!schema.enum.includes(value)) {
      throw new Error(`Value must be one of: ${schema.enum.join(', ')}`);
    }
    return value;
  }

  return value;
}

function sanitizeTokens(tokens) {
  if (tokens === undefined) {
    return undefined;
  }
  const tokensSchema = BLOCK_CONTRACT_SCHEMA.definitions?.Tokens;
  return sanitizeValue(tokens, tokensSchema);
}

function buildDefaultBlock(type, variant) {
  const factories = {
    hero: () => ({
      id: createId(),
      type: 'hero',
      variant,
      data: {
        eyebrow: '',
        headline: 'Hero Headline',
        subheadline: '',
        media: {
          imageId: '',
          alt: '',
          focalPoint: { x: 0.5, y: 0.5 }
        },
        cta: {
          primary: {
            label: 'Jetzt starten',
            href: 'https://example.com',
            ariaLabel: ''
          }
        }
      }
    }),
    feature_list: () => ({
      id: createId(),
      type: 'feature_list',
      variant,
      data: {
        title: 'Feature Headline',
        intro: '',
        items: [
          { id: createId(), title: 'Feature eins', description: 'Beschreibung' }
        ]
      }
    }),
    process_steps: () => ({
      id: createId(),
      type: 'process_steps',
      variant,
      data: {
        title: 'Ablauf',
        summary: '',
        steps: [
          { id: createId(), title: 'Schritt eins', description: 'Beschreibung' },
          { id: createId(), title: 'Schritt zwei', description: 'Beschreibung' }
        ]
      }
    }),
    testimonial: () => ({
      id: createId(),
      type: 'testimonial',
      variant,
      data: {
        quote: 'Zitat des Kunden',
        author: {
          name: 'Kunde',
          role: '',
          avatarId: ''
        },
        source: ''
      }
    }),
    rich_text: () => ({
      id: createId(),
      type: 'rich_text',
      variant,
      data: {
        body: '<p>Text</p>',
        alignment: 'start'
      }
    }),
    info_media: () => ({
      id: createId(),
      type: 'info_media',
      variant,
      data: {
        eyebrow: '',
        title: 'Info block',
        subtitle: '',
        body: '<p>Text</p>',
        media: {
          imageId: '',
          image: '',
          alt: '',
          focalPoint: { x: 0.5, y: 0.5 }
        },
        items: [{
          id: createId(),
          title: 'Eintrag',
          description: 'Beschreibung',
          bullets: [],
          media: { imageId: '', image: '', alt: '', focalPoint: { x: 0.5, y: 0.5 } }
        }]
      }
    }),
    stat_strip: () => ({
      id: createId(),
      type: 'stat_strip',
      variant,
      data: {
        title: 'Kennzahlen',
        lede: '',
        metrics: [
          { id: createId(), value: '100%', label: 'Zuverlässig', icon: '', asOf: '', tooltip: '', benefit: '' }
        ],
        marquee: []
      }
    }),
    audience_spotlight: () => ({
      id: createId(),
      type: 'audience_spotlight',
      variant,
      data: {
        title: 'Use cases',
        subtitle: '',
        cases: [{ id: createId(), title: 'Fallstudie', lead: '', body: '', bullets: [], keyFacts: [] }]
      }
    }),
    package_summary: () => ({
      id: createId(),
      type: 'package_summary',
      variant,
      data: {
        title: 'Pakete',
        subtitle: '',
        options: [
          { id: createId(), title: 'Option A', intro: '', highlights: [{ title: 'Vorteile', bullets: [] }] }
        ],
        plans: [
          { id: createId(), title: 'Plan', description: '', features: [], notes: [], primaryCta: { label: 'Los', href: '#' } }
        ],
        disclaimer: ''
      }
    }),
    faq: () => ({
      id: createId(),
      type: 'faq',
      variant,
      data: {
        title: 'FAQ',
        items: [{ id: createId(), question: 'Frage', answer: 'Antwort' }],
        followUp: { text: '', linkLabel: '', href: '' }
      }
    }),
    cta: () => ({
      id: createId(),
      type: 'cta',
      variant,
      data: {
        title: '',
        body: '',
        primary: {
          label: 'Mehr erfahren',
          href: '#',
          ariaLabel: ''
        }
      }
    })
  };

  const factory = factories[type];
  if (!factory) {
    throw new Error(`Unsupported block type: ${type}`);
  }
  const block = factory();
  const validation = validateBlockContract(block);
  if (!validation.valid) {
    throw new Error(validation.reason || 'Invalid default block');
  }
  return block;
}

const determineVariant = (type, requestedVariant) => ensureRendererVariant(type, requestedVariant);

function migrateLegacyBlock(block) {
  if (!block || typeof block !== 'object') {
    throw new Error('Block payload must be an object');
  }

  if (block.type === 'text') {
    const alignment = ['start', 'center', 'end', 'justify'].includes(block?.data?.alignment)
      ? block.data.alignment
      : 'start';
    return {
      id: block.id,
      type: 'rich_text',
      variant: determineVariant('rich_text', block.variant),
      data: { body: block?.data?.body || '', alignment },
      tokens: block.tokens
    };
  }

  if (!getRendererVariants(block.type).length) {
    throw new Error(`Unsupported block type: ${block.type}`);
  }

  const variant = determineVariant(block.type, block.variant);
  const clone = deepClone(block);
  if (clone?.data?.layout) {
    delete clone.data.layout;
  }
  if (clone?.data?.cta?.style) {
    delete clone.data.cta.style;
  }
  if (clone.type === 'faq' && clone?.data?.followUp) {
    const { followUp } = clone.data;
    if (followUp.label && !followUp.linkLabel) {
      followUp.linkLabel = followUp.label;
    }
    if ('label' in followUp) {
      delete followUp.label;
    }
  }
  if (clone.type === 'hero' && clone?.data?.cta && !clone.data.cta.primary && !clone.data.cta.secondary) {
    clone.data.cta = { primary: clone.data.cta };
  }
  return { ...clone, variant };
}

function sanitizeBlock(block) {
  const migrated = normalizeBlockContract(migrateLegacyBlock(block));
  const sanitizedTopLevel = { ...migrated };
  delete sanitizedTopLevel.invalidVariant;

  const blockSchema = resolveBlockSchema(sanitizedTopLevel.type, sanitizedTopLevel.variant);
  if (!blockSchema) {
    throw new Error(`Unknown block type: ${sanitizedTopLevel.type}`);
  }
  const allowedKeys = Object.keys(BLOCK_CONTRACT_SCHEMA.properties || {});
  const disallowedTopLevel = Object.keys(sanitizedTopLevel).filter(key => !allowedKeys.includes(key));
  if (disallowedTopLevel.length > 0) {
    throw new Error(`Unexpected top-level field: ${disallowedTopLevel[0]}`);
  }

  const dataDefinition = getSchemaDefinition(blockSchema.properties?.data?.$ref);
  if (!dataDefinition) {
    throw new Error(`Missing data schema for ${sanitizedTopLevel.type}`);
  }

  const sanitizedData = sanitizeValue(sanitizedTopLevel.data, dataDefinition, ['layout', 'style']);
  const sanitizedTokens = sanitizeTokens(sanitizedTopLevel.tokens);

  const sanitizedBlock = {
    id: typeof sanitizedTopLevel.id === 'string' && sanitizedTopLevel.id ? sanitizedTopLevel.id : createId(),
    type: sanitizedTopLevel.type,
    variant: sanitizedTopLevel.variant,
    data: sanitizedData
  };

  if (sanitizedTokens !== undefined) {
    sanitizedBlock.tokens = sanitizedTokens;
  }

  const validation = validateBlockContract(sanitizedBlock);
  if (!validation.valid) {
    throw new Error(validation.reason || 'Block failed contract validation');
  }

  return sanitizedBlock;
}

const getDefaultBlock = (type, variant) => buildDefaultBlock(type, variant);

class RichTextField {
  constructor(container, initialValue, onUpdate) {
    this.container = container;
    this.onUpdate = typeof onUpdate === 'function' ? onUpdate : () => {};
    this.editor = new Editor({
      element: container,
      content: initialValue || '',
      extensions: [StarterKit],
      editorProps: {
        attributes: {
          class: 'content-editor-richtext',
          spellcheck: 'true'
        }
      },
      onUpdate: ({ editor }) => {
        this.onUpdate(editor.getHTML());
      }
    });
  }

  destroy() {
    if (this.editor) {
      this.editor.destroy();
      this.editor = null;
    }
  }
}

export class BlockContentEditor {
  constructor(root, initialContent = '{}', options = {}) {
    this.root = root;
    this.pageId = options.pageId || null;
    this.state = {
      blocks: [],
      meta: {},
      selectedBlockId: null
    };
    this.richTextInstances = new Map();
    this.handleKeyNavigation = this.handleKeyNavigation.bind(this);
    this.setContent(initialContent);
    this.render();
    this.bindKeyboardNavigation();
  }

  destroy() {
    document.removeEventListener('keydown', this.handleKeyNavigation);
    this.richTextInstances.forEach(instance => instance.destroy());
    this.richTextInstances.clear();
    this.root.innerHTML = '';
  }

  bindKeyboardNavigation() {
    document.addEventListener('keydown', this.handleKeyNavigation);
  }

  handleKeyNavigation(event) {
    if (!this.root.contains(document.activeElement)) {
      return;
    }
    const { key } = event;
    if (key !== 'ArrowUp' && key !== 'ArrowDown') {
      return;
    }
    event.preventDefault();
    const ids = this.state.blocks.map(block => block.id);
    const currentIndex = ids.indexOf(this.state.selectedBlockId);
    if (key === 'ArrowUp' && currentIndex > 0) {
      this.selectBlock(ids[currentIndex - 1]);
    } else if (key === 'ArrowDown' && currentIndex < ids.length - 1) {
      this.selectBlock(ids[currentIndex + 1]);
    }
  }

  setContent(content) {
    this.richTextInstances.forEach(instance => instance.destroy());
    this.richTextInstances.clear();

    let parsed = {};
    if (typeof content === 'string') {
      try {
        parsed = JSON.parse(content || '{}');
      } catch (error) {
        parsed = {};
      }
    } else if (content && typeof content === 'object') {
      parsed = content;
    }

    const skippedBlocks = [];
    const blocks = [];
    if (Array.isArray(parsed.blocks)) {
      parsed.blocks.forEach((block, index) => {
        try {
          blocks.push(this.normalizeBlock(block));
        } catch (error) {
          const message = error instanceof Error ? error.message : 'Unbekannter Fehler';
          skippedBlocks.push({ index, block, message });
          console.warn('Skipping invalid block during initialization', { index, block, error });
        }
      });
    }

    if (skippedBlocks.length > 0 && typeof notify === 'function') {
      notify('Einige Blöcke wurden aufgrund von Validierungsfehlern übersprungen.', 'warning');
    }

    this.state = {
      id: typeof parsed.id === 'string' ? parsed.id : null,
      blocks,
      meta: parsed.meta || {},
      selectedBlockId: blocks[0]?.id || null,
      skippedBlocks
    };
    this.render();
  }

  normalizeBlock(block) {
    try {
      return sanitizeBlock(block);
    } catch (error) {
      const recoverable = buildRecoverableVariantBlock(block, error);
      if (recoverable) {
        return recoverable;
      }
      throw error;
    }
  }

  getContent() {
    const recoverable = this.state.blocks.find(block => block.invalidVariant);
    if (recoverable) {
      const attempted = recoverable.invalidVariant?.attemptedVariant || recoverable.variant || 'unbekannt';
      const allowed = (recoverable.invalidVariant?.allowedVariants || []).join(', ');
      throw new Error(`Block-Variante nicht unterstützt (${attempted}). Erlaubte Varianten: ${allowed || 'keine'}.`);
    }

    const validatedBlocks = this.state.blocks.map(block => sanitizeBlock(block));
    return JSON.stringify({
      id: this.state.id || null,
      blocks: validatedBlocks,
      meta: this.state.meta || {}
    });
  }

  render() {
    if (!this.root) {
      return;
    }
    this.richTextInstances.forEach(instance => instance.destroy());
    this.richTextInstances.clear();
    this.root.innerHTML = '';
    const wrapper = document.createElement('div');
    wrapper.dataset.editorRoot = 'true';

    const controls = this.buildControls();
    const body = document.createElement('div');
    body.className = 'content-editor-body';

    const list = this.buildBlockList();
    const panel = this.buildEditorPanel();

    body.append(list, panel);
    wrapper.append(controls, body);
    this.root.append(wrapper);
  }

  buildControls() {
    const container = document.createElement('div');
    container.dataset.editorControls = 'true';

    const typeSelect = document.createElement('select');
    typeSelect.dataset.action = 'insert-block-type';
    const supportedTypes = ACTIVE_BLOCK_TYPES.filter(type => getRendererVariants(type).length > 0);
    supportedTypes.forEach(type => {
      const option = document.createElement('option');
      option.value = type;
      option.textContent = BLOCK_TYPE_LABELS[type] || type;
      typeSelect.append(option);
    });

    const variantSelect = document.createElement('select');
    variantSelect.dataset.action = 'insert-block-variant';

    const populateVariants = () => {
      variantSelect.innerHTML = '';
      const variants = getRendererVariants(typeSelect.value);
      variants.forEach(variant => {
        const option = document.createElement('option');
        option.value = variant;
        option.textContent = variant;
        variantSelect.append(option);
      });
      if (variants[0]) {
        variantSelect.value = variants[0];
      }
      variantSelect.disabled = variants.length === 0;
      addBtn.disabled = variants.length === 0;
    };

    const addBtn = document.createElement('button');
    addBtn.type = 'button';
    addBtn.dataset.action = 'add-block';
    addBtn.textContent = 'Block hinzufügen';
    addBtn.disabled = true;
    addBtn.addEventListener('click', () => {
      try {
        this.addBlock(typeSelect.value);
      } catch (error) {
        window.alert(error.message || 'Block konnte nicht erstellt werden');
      }
    });

    typeSelect.addEventListener('change', populateVariants);
    variantSelect.addEventListener('change', () => {
      addBtn.disabled = !variantSelect.value;
    });

    populateVariants();

    const duplicateBtn = document.createElement('button');
    duplicateBtn.type = 'button';
    duplicateBtn.dataset.action = 'duplicate-block';
    duplicateBtn.textContent = 'Duplizieren';
    duplicateBtn.disabled = !this.state.selectedBlockId;
    duplicateBtn.addEventListener('click', () => this.duplicateSelected());

    const deleteBtn = document.createElement('button');
    deleteBtn.type = 'button';
    deleteBtn.dataset.action = 'delete-block';
    deleteBtn.textContent = 'Löschen';
    deleteBtn.disabled = !this.state.selectedBlockId;
    deleteBtn.addEventListener('click', () => this.deleteSelected());

    container.append(typeSelect, variantSelect, addBtn, duplicateBtn, deleteBtn);
    return container;
  }

  buildBlockList() {
    const aside = document.createElement('aside');
    aside.dataset.blockList = 'true';
    const list = document.createElement('ul');

    if (Array.isArray(this.state.skippedBlocks) && this.state.skippedBlocks.length > 0) {
      const warning = document.createElement('div');
      warning.dataset.blockWarning = 'true';
      warning.textContent = 'Einige Blöcke konnten nicht geladen werden. Bitte überprüfe sie und füge sie erneut hinzu.';
      aside.append(warning);

      this.state.skippedBlocks.forEach(entry => {
        const placeholder = document.createElement('li');
        placeholder.dataset.blockRow = 'true';
        placeholder.setAttribute('aria-selected', 'false');
        const label = document.createElement('span');
        const typeLabel = entry?.block?.type || 'Block';
        label.textContent = `${typeLabel} (übersprungen): ${entry.message}`;
        placeholder.append(label);

        const canRecreate = entry?.block?.type && getRendererVariants(entry.block.type).length > 0;
        if (canRecreate) {
          const recreate = document.createElement('button');
          recreate.type = 'button';
          recreate.textContent = 'Block neu anlegen';
          recreate.addEventListener('click', () => this.addBlock(entry.block.type));
          placeholder.append(recreate);
        }

        list.append(placeholder);
      });
    }

    if (this.state.blocks.length === 0) {
      const empty = document.createElement('div');
      empty.textContent = 'Keine Blöcke vorhanden.';
      aside.append(list, empty);
      return aside;
    }

    this.state.blocks.forEach((block, index) => {
      const row = document.createElement('li');
      row.dataset.blockRow = 'true';
      row.dataset.blockId = block.id;
      row.setAttribute('aria-selected', block.id === this.state.selectedBlockId ? 'true' : 'false');

      const selectBtn = document.createElement('button');
      selectBtn.type = 'button';
      selectBtn.dataset.action = 'select-block';
      selectBtn.textContent = 'Auswählen';
      selectBtn.addEventListener('click', () => this.selectBlock(block.id));

      const label = document.createElement('span');
      label.dataset.blockLabel = 'true';
      const deprecated = DEPRECATED_BLOCK_MAP[block.type] ? ' [deprecated]' : '';
      const invalidVariant = block.invalidVariant ? ' [Variante ungültig]' : '';
      label.textContent = `${block.type}:${block.variant}${deprecated}${invalidVariant} – ${stripHtml(this.getPrimaryText(block))}`.trim();

      const moveUp = document.createElement('button');
      moveUp.type = 'button';
      moveUp.dataset.action = 'move-up';
      moveUp.textContent = '↑';
      moveUp.disabled = index === 0;
      moveUp.addEventListener('click', () => this.moveBlock(block.id, -1));

      const moveDown = document.createElement('button');
      moveDown.type = 'button';
      moveDown.dataset.action = 'move-down';
      moveDown.textContent = '↓';
      moveDown.disabled = index === this.state.blocks.length - 1;
      moveDown.addEventListener('click', () => this.moveBlock(block.id, 1));

      row.append(selectBtn, label, moveUp, moveDown);
      list.append(row);
    });

    aside.append(list);
    return aside;
  }

  buildEditorPanel() {
    const panel = document.createElement('section');
    panel.dataset.blockEditor = 'true';
    panel.dataset.selectedBlockId = this.state.selectedBlockId || '';

    const block = this.state.blocks.find(item => item.id === this.state.selectedBlockId);
    if (!block) {
      const hint = document.createElement('div');
      hint.textContent = 'Wähle einen Block aus, um ihn zu bearbeiten.';
      panel.append(hint);
      return panel;
    }

    const form = document.createElement('div');
    form.dataset.blockType = block.type;

    if (DEPRECATED_BLOCK_MAP[block.type]) {
      const deprecatedNotice = document.createElement('div');
      deprecatedNotice.dataset.deprecatedNotice = 'true';
      deprecatedNotice.textContent = 'Hinweis: Dieser Block ist laut calserver-block-consolidation als veraltet markiert.';
      form.append(deprecatedNotice);
    }

    const variantSelector = this.buildVariantSelector(block);
    if (variantSelector) {
      form.append(variantSelector);
    }

    const formContent = this.buildBlockForm(block);
    form.append(formContent);

    panel.append(form);
    return panel;
  }

  buildVariantSelector(block) {
    const variants = getRendererVariants(block.type);
    if (!variants.length) {
      return null;
    }
    const wrapper = document.createElement('div');
    const label = document.createElement('div');
    label.textContent = 'Variante';
    const select = document.createElement('select');
    const hasInvalidVariant = Boolean(block.invalidVariant);

    if (hasInvalidVariant) {
      const notice = document.createElement('div');
      notice.dataset.invalidVariantNotice = 'true';
      const attempted = block.invalidVariant?.attemptedVariant || block.variant || 'unbekannt';
      const allowed = (block.invalidVariant?.allowedVariants || variants).join(', ');
      notice.textContent = `Diese Variante (${attempted}) wird nicht unterstützt. Wähle eine der erlaubten Varianten: ${allowed}.`;
      wrapper.append(notice);

      const placeholder = document.createElement('option');
      placeholder.value = '';
      placeholder.textContent = 'Variante auswählen';
      placeholder.disabled = true;
      placeholder.selected = true;
      select.append(placeholder);
    }

    variants.forEach(variant => {
      const option = document.createElement('option');
      option.value = variant;
      option.textContent = variant;
      if (!hasInvalidVariant && variant === block.variant) {
        option.selected = true;
      }
      select.append(option);
    });
    select.addEventListener('change', event => this.updateVariant(block.id, event.target.value));
    wrapper.append(label, select);
    return wrapper;
  }

  buildBlockForm(block) {
    switch (block.type) {
      case 'hero':
        return this.buildHeroForm(block);
      case 'rich_text':
        return this.buildRichTextForm(block);
      case 'info_media':
        return this.buildInfoMediaForm(block);
      case 'feature_list':
        return this.buildFeatureListForm(block);
      case 'process_steps':
        return this.buildProcessStepsForm(block);
      case 'testimonial':
        return this.buildTestimonialForm(block);
      case 'faq':
        return this.buildFaqForm(block);
      case 'cta':
        return this.buildCtaForm(block);
      case 'stat_strip':
        return this.buildStatStripForm(block);
      case 'audience_spotlight':
        return this.buildAudienceSpotlightForm(block);
      case 'package_summary':
        return this.buildPackageSummaryForm(block);
      default:
        return this.buildGenericJsonForm(block);
    }
  }

  buildPackageSummaryForm(block) {
    const wrapper = document.createElement('div');

    wrapper.append(this.addLabeledInput('Titel', block.data.title, value => this.updateBlockData(block.id, ['data', 'title'], value)));
    wrapper.append(this.addLabeledInput('Untertitel', block.data.subtitle, value => this.updateBlockData(block.id, ['data', 'subtitle'], value)));

    const optionsWrapper = document.createElement('div');
    optionsWrapper.dataset.field = 'options';

    const addOptionBtn = document.createElement('button');
    addOptionBtn.type = 'button';
    addOptionBtn.textContent = 'Option hinzufügen';
    addOptionBtn.addEventListener('click', () => this.addPackageOption(block.id));
    optionsWrapper.append(addOptionBtn);

    (block.data.options || []).forEach((option, index) => {
      const optionCard = document.createElement('div');
      optionCard.dataset.packageOption = option.id;

      optionCard.append(this.addLabeledInput('ID', option.id, value => this.updatePackageOption(block.id, option.id, 'id', value)));
      optionCard.append(this.addLabeledInput('Titel', option.title, value => this.updatePackageOption(block.id, option.id, 'title', value)));
      optionCard.append(this.addLabeledInput('Intro', option.intro, value => this.updatePackageOption(block.id, option.id, 'intro', value), { multiline: true }));

      const highlightsWrapper = document.createElement('div');
      highlightsWrapper.dataset.field = 'highlights';

      const addHighlightBtn = document.createElement('button');
      addHighlightBtn.type = 'button';
      addHighlightBtn.textContent = 'Highlight hinzufügen';
      addHighlightBtn.addEventListener('click', () => this.addPackageOptionHighlight(block.id, option.id));
      highlightsWrapper.append(addHighlightBtn);

      (option.highlights || []).forEach((highlight, highlightIndex) => {
        const highlightCard = document.createElement('div');

        highlightCard.append(this.addLabeledInput('Highlight-Titel', highlight.title, value => this.updatePackageOptionHighlight(block.id, option.id, highlightIndex, 'title', value)));

        highlightCard.append(this.buildStringList(highlight.bullets, 'Bullet', {
          add: () => this.addPackageOptionHighlightBullet(block.id, option.id, highlightIndex),
          update: (itemIndex, value) => this.updatePackageOptionHighlightBullet(block.id, option.id, highlightIndex, itemIndex, value),
          remove: itemIndex => this.removePackageOptionHighlightBullet(block.id, option.id, highlightIndex, itemIndex),
          move: (itemIndex, delta) => this.movePackageOptionHighlightBullet(block.id, option.id, highlightIndex, itemIndex, delta)
        }));

        const highlightControls = document.createElement('div');
        const removeHighlight = document.createElement('button');
        removeHighlight.type = 'button';
        removeHighlight.textContent = 'Highlight entfernen';
        removeHighlight.disabled = (option.highlights || []).length <= 1;
        removeHighlight.addEventListener('click', () => this.removePackageOptionHighlight(block.id, option.id, highlightIndex));

        const moveHighlightUp = document.createElement('button');
        moveHighlightUp.type = 'button';
        moveHighlightUp.textContent = '↑';
        moveHighlightUp.disabled = highlightIndex === 0;
        moveHighlightUp.addEventListener('click', () => this.movePackageOptionHighlight(block.id, option.id, highlightIndex, -1));

        const moveHighlightDown = document.createElement('button');
        moveHighlightDown.type = 'button';
        moveHighlightDown.textContent = '↓';
        moveHighlightDown.disabled = highlightIndex === (option.highlights || []).length - 1;
        moveHighlightDown.addEventListener('click', () => this.movePackageOptionHighlight(block.id, option.id, highlightIndex, 1));

        highlightControls.append(removeHighlight, moveHighlightUp, moveHighlightDown);
        highlightCard.append(highlightControls);
        highlightsWrapper.append(highlightCard);
      });

      optionCard.append(highlightsWrapper);

      const optionControls = document.createElement('div');
      const removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.textContent = 'Option entfernen';
      removeBtn.disabled = (block.data.options || []).length <= 1;
      removeBtn.addEventListener('click', () => this.removePackageOption(block.id, option.id));

      const moveUp = document.createElement('button');
      moveUp.type = 'button';
      moveUp.textContent = '↑';
      moveUp.disabled = index === 0;
      moveUp.addEventListener('click', () => this.movePackageOption(block.id, option.id, -1));

      const moveDown = document.createElement('button');
      moveDown.type = 'button';
      moveDown.textContent = '↓';
      moveDown.disabled = index === (block.data.options || []).length - 1;
      moveDown.addEventListener('click', () => this.movePackageOption(block.id, option.id, 1));

      optionControls.append(removeBtn, moveUp, moveDown);
      optionCard.append(optionControls);
      optionsWrapper.append(optionCard);
    });

    wrapper.append(optionsWrapper);

    const plansWrapper = document.createElement('div');
    plansWrapper.dataset.field = 'plans';

    const addPlanBtn = document.createElement('button');
    addPlanBtn.type = 'button';
    addPlanBtn.textContent = 'Paket hinzufügen';
    addPlanBtn.addEventListener('click', () => this.addPackagePlan(block.id));
    plansWrapper.append(addPlanBtn);

    (block.data.plans || []).forEach((plan, index) => {
      const planCard = document.createElement('div');
      planCard.dataset.packagePlan = plan.id;

      planCard.append(this.addLabeledInput('ID', plan.id, value => this.updatePackagePlan(block.id, plan.id, 'id', value)));
      planCard.append(this.addLabeledInput('Titel', plan.title, value => this.updatePackagePlan(block.id, plan.id, 'title', value)));
      planCard.append(this.addLabeledInput('Badge', plan.badge, value => this.updatePackagePlan(block.id, plan.id, 'badge', value)));
      planCard.append(this.addLabeledInput('Beschreibung', plan.description, value => this.updatePackagePlan(block.id, plan.id, 'description', value), { multiline: true }));

      planCard.append(this.buildStringList(plan.features, 'Feature', {
        add: () => this.addPackagePlanListItem(block.id, plan.id, 'features'),
        update: (itemIndex, value) => this.updatePackagePlanListItem(block.id, plan.id, 'features', itemIndex, value),
        remove: itemIndex => this.removePackagePlanListItem(block.id, plan.id, 'features', itemIndex),
        move: (itemIndex, delta) => this.movePackagePlanListItem(block.id, plan.id, 'features', itemIndex, delta)
      }));

      planCard.append(this.buildStringList(plan.notes, 'Hinweis', {
        add: () => this.addPackagePlanListItem(block.id, plan.id, 'notes'),
        update: (itemIndex, value) => this.updatePackagePlanListItem(block.id, plan.id, 'notes', itemIndex, value),
        remove: itemIndex => this.removePackagePlanListItem(block.id, plan.id, 'notes', itemIndex),
        move: (itemIndex, delta) => this.movePackagePlanListItem(block.id, plan.id, 'notes', itemIndex, delta)
      }));

      const ctaWrapper = document.createElement('div');
      ctaWrapper.dataset.field = 'primaryCta';
      ctaWrapper.append(this.addLabeledInput('Primäre CTA (Label)', plan.primaryCta?.label, value => this.updatePackagePlan(block.id, plan.id, ['primaryCta', 'label'], value)));
      ctaWrapper.append(this.addLabeledInput('Primäre CTA (Link)', plan.primaryCta?.href, value => this.updatePackagePlan(block.id, plan.id, ['primaryCta', 'href'], value)));
      ctaWrapper.append(this.addLabeledInput('Primäre CTA (Aria-Label)', plan.primaryCta?.ariaLabel, value => this.updatePackagePlan(block.id, plan.id, ['primaryCta', 'ariaLabel'], value)));
      planCard.append(ctaWrapper);

      const secondaryCtaWrapper = document.createElement('div');
      secondaryCtaWrapper.dataset.field = 'secondaryCta';
      secondaryCtaWrapper.append(this.addLabeledInput('Sekundäre CTA (Label)', plan.secondaryCta?.label, value => this.updatePackagePlan(block.id, plan.id, ['secondaryCta', 'label'], value)));
      secondaryCtaWrapper.append(this.addLabeledInput('Sekundäre CTA (Link)', plan.secondaryCta?.href, value => this.updatePackagePlan(block.id, plan.id, ['secondaryCta', 'href'], value)));
      secondaryCtaWrapper.append(this.addLabeledInput('Sekundäre CTA (Aria-Label)', plan.secondaryCta?.ariaLabel, value => this.updatePackagePlan(block.id, plan.id, ['secondaryCta', 'ariaLabel'], value)));
      planCard.append(secondaryCtaWrapper);

      const planControls = document.createElement('div');
      const removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.textContent = 'Paket entfernen';
      removeBtn.disabled = (block.data.plans || []).length <= 1;
      removeBtn.addEventListener('click', () => this.removePackagePlan(block.id, plan.id));

      const moveUp = document.createElement('button');
      moveUp.type = 'button';
      moveUp.textContent = '↑';
      moveUp.disabled = index === 0;
      moveUp.addEventListener('click', () => this.movePackagePlan(block.id, plan.id, -1));

      const moveDown = document.createElement('button');
      moveDown.type = 'button';
      moveDown.textContent = '↓';
      moveDown.disabled = index === (block.data.plans || []).length - 1;
      moveDown.addEventListener('click', () => this.movePackagePlan(block.id, plan.id, 1));

      planControls.append(removeBtn, moveUp, moveDown);
      planCard.append(planControls);
      plansWrapper.append(planCard);
    });

    wrapper.append(plansWrapper);
    wrapper.append(this.addLabeledInput('Disclaimer', block.data.disclaimer, value => this.updateBlockData(block.id, ['data', 'disclaimer'], value), { multiline: true }));

    return wrapper;
  }

  buildAudienceSpotlightForm(block) {
    const wrapper = document.createElement('div');

    wrapper.append(this.addLabeledInput('Titel', block.data.title, value => this.updateBlockData(block.id, ['data', 'title'], value)));
    wrapper.append(this.addLabeledInput('Untertitel', block.data.subtitle, value => this.updateBlockData(block.id, ['data', 'subtitle'], value)));

    const casesWrapper = document.createElement('div');
    casesWrapper.dataset.field = 'cases';

    const addCaseBtn = document.createElement('button');
    addCaseBtn.type = 'button';
    addCaseBtn.textContent = 'Use Case hinzufügen';
    addCaseBtn.addEventListener('click', () => this.addAudienceCase(block.id));
    casesWrapper.append(addCaseBtn);

    (block.data.cases || []).forEach((audienceCase, index) => {
      const card = document.createElement('div');
      card.dataset.audienceCase = audienceCase.id;

      card.append(this.addLabeledInput('ID', audienceCase.id, value => this.updateAudienceCase(block.id, audienceCase.id, 'id', value)));
      card.append(this.addLabeledInput('Badge', audienceCase.badge, value => this.updateAudienceCase(block.id, audienceCase.id, 'badge', value)));
      card.append(this.addLabeledInput('Titel', audienceCase.title, value => this.updateAudienceCase(block.id, audienceCase.id, 'title', value)));
      card.append(this.addLabeledInput('Lead', audienceCase.lead, value => this.updateAudienceCase(block.id, audienceCase.id, 'lead', value), { multiline: true }));
      card.append(this.addLabeledInput('Body', audienceCase.body, value => this.updateAudienceCase(block.id, audienceCase.id, 'body', value), { multiline: true, rows: 4 }));

      card.append(this.buildStringList(audienceCase.bullets, 'Bullet', {
        add: () => this.addAudienceCaseListItem(block.id, audienceCase.id, 'bullets'),
        update: (itemIndex, value) => this.updateAudienceCaseListItem(block.id, audienceCase.id, 'bullets', itemIndex, value),
        remove: itemIndex => this.removeAudienceCaseListItem(block.id, audienceCase.id, 'bullets', itemIndex),
        move: (itemIndex, delta) => this.moveAudienceCaseListItem(block.id, audienceCase.id, 'bullets', itemIndex, delta)
      }));

      card.append(this.buildStringList(audienceCase.keyFacts, 'Key Fact', {
        add: () => this.addAudienceCaseListItem(block.id, audienceCase.id, 'keyFacts'),
        update: (itemIndex, value) => this.updateAudienceCaseListItem(block.id, audienceCase.id, 'keyFacts', itemIndex, value),
        remove: itemIndex => this.removeAudienceCaseListItem(block.id, audienceCase.id, 'keyFacts', itemIndex),
        move: (itemIndex, delta) => this.moveAudienceCaseListItem(block.id, audienceCase.id, 'keyFacts', itemIndex, delta)
      }));

      card.append(this.addLabeledInput('Medienbild', audienceCase.media?.image, value => this.updateAudienceCase(block.id, audienceCase.id, ['media', 'image'], value)));
      card.append(this.addLabeledInput('Alt-Text', audienceCase.media?.alt, value => this.updateAudienceCase(block.id, audienceCase.id, ['media', 'alt'], value)));

      const controls = document.createElement('div');
      const removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.textContent = 'Use Case entfernen';
      removeBtn.disabled = (block.data.cases || []).length <= 1;
      removeBtn.addEventListener('click', () => this.removeAudienceCase(block.id, audienceCase.id));

      const moveUp = document.createElement('button');
      moveUp.type = 'button';
      moveUp.textContent = '↑';
      moveUp.disabled = index === 0;
      moveUp.addEventListener('click', () => this.moveAudienceCase(block.id, audienceCase.id, -1));

      const moveDown = document.createElement('button');
      moveDown.type = 'button';
      moveDown.textContent = '↓';
      moveDown.disabled = index === (block.data.cases || []).length - 1;
      moveDown.addEventListener('click', () => this.moveAudienceCase(block.id, audienceCase.id, 1));

      controls.append(removeBtn, moveUp, moveDown);
      card.append(controls);
      casesWrapper.append(card);
    });

    wrapper.append(casesWrapper);
    return wrapper;
  }

  buildGenericJsonForm(block) {
    const wrapper = document.createElement('div');
    const info = document.createElement('p');
    info.textContent = 'Dieser Block nutzt eine generische Struktur. Passe die Felder als JSON an.';
    wrapper.append(info);

    if (DEPRECATED_BLOCK_MAP[block.type]) {
      const deprecated = document.createElement('p');
      deprecated.textContent = 'Veraltet: Nicht neu anlegen, nur bestehende Inhalte pflegen.';
      wrapper.append(deprecated);
    }

    const textarea = document.createElement('textarea');
    textarea.rows = 16;
    textarea.value = JSON.stringify(block.data, null, 2);
    wrapper.append(textarea);

    const applyBtn = document.createElement('button');
    applyBtn.type = 'button';
    applyBtn.textContent = 'Änderungen übernehmen';
    applyBtn.addEventListener('click', () => {
      try {
        const parsed = JSON.parse(textarea.value || '{}');
        this.replaceBlock(block.id, { data: parsed });
      } catch (error) {
        window.alert(error.message || 'JSON ungültig');
      }
    });

    wrapper.append(applyBtn);
    return wrapper;
  }

  addLabeledInput(labelText, value, onChange, options = {}) {
    const wrapper = document.createElement('label');
    wrapper.dataset.fieldLabel = 'true';
    wrapper.textContent = labelText;
    const input = document.createElement(options.element || 'input');
    if (options.type) {
      input.type = options.type;
    }
    if (options.step) {
      input.step = options.step;
    }
    if (options.min !== undefined) {
      input.min = options.min;
    }
    if (options.max !== undefined) {
      input.max = options.max;
    }
    if (options.placeholder) {
      input.placeholder = options.placeholder;
    }
    if (options.multiline) {
      input.value = value || '';
      input.addEventListener('input', event => onChange(event.target.value));
      input.rows = options.rows || 3;
    } else {
      input.value = value || '';
      input.addEventListener('input', event => onChange(event.target.value));
    }
    wrapper.append(document.createElement('br'), input);
    return wrapper;
  }

  buildStringList(items, label, onChange) {
    const listWrapper = document.createElement('div');
    const listLabel = document.createElement('div');
    listLabel.textContent = label;
    listWrapper.append(listLabel);

    const addEntryBtn = document.createElement('button');
    addEntryBtn.type = 'button';
    addEntryBtn.textContent = `${label} hinzufügen`;
    addEntryBtn.addEventListener('click', onChange.add);
    listWrapper.append(addEntryBtn);

    (items || []).forEach((item, index) => {
      const entry = document.createElement('div');
      const input = document.createElement('input');
      input.value = item || '';
      input.addEventListener('input', event => onChange.update(index, event.target.value));
      entry.append(input);

      const controls = document.createElement('div');
      const removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.textContent = 'Entfernen';
      removeBtn.addEventListener('click', () => onChange.remove(index));

      const moveUp = document.createElement('button');
      moveUp.type = 'button';
      moveUp.textContent = '↑';
      moveUp.disabled = index === 0;
      moveUp.addEventListener('click', () => onChange.move(index, -1));

      const moveDown = document.createElement('button');
      moveDown.type = 'button';
      moveDown.textContent = '↓';
      moveDown.disabled = index === (items?.length || 0) - 1;
      moveDown.addEventListener('click', () => onChange.move(index, 1));

      controls.append(removeBtn, moveUp, moveDown);
      entry.append(controls);
      listWrapper.append(entry);
    });

    return listWrapper;
  }

  mountRichText(container, initialValue, onUpdate) {
    const existing = this.richTextInstances.get(container);
    if (existing) {
      existing.destroy();
    }
    const instance = new RichTextField(container, initialValue, onUpdate);
    this.richTextInstances.set(container, instance);
    return instance;
  }

  buildHeroForm(block) {
    const wrapper = document.createElement('div');

    wrapper.append(this.addLabeledInput('Eyebrow', block.data.eyebrow, value => this.updateBlockData(block.id, ['data', 'eyebrow'], value)));

    const headlineField = document.createElement('div');
    headlineField.dataset.field = 'headline';
    headlineField.dataset.richtext = 'true';
    this.mountRichText(headlineField, block.data.headline, value => this.updateBlockData(block.id, ['data', 'headline'], value));
    wrapper.append(this.wrapField('Headline', headlineField));

    const subheadlineField = document.createElement('div');
    subheadlineField.dataset.field = 'subheadline';
    subheadlineField.dataset.richtext = 'true';
    this.mountRichText(subheadlineField, block.data.subheadline, value => this.updateBlockData(block.id, ['data', 'subheadline'], value));
    wrapper.append(this.wrapField('Subheadline', subheadlineField));

    wrapper.append(this.addLabeledInput('Media ID', block.data.media?.imageId, value => this.updateBlockData(block.id, ['data', 'media', 'imageId'], value)));
    wrapper.append(this.addLabeledInput('Alt-Text', block.data.media?.alt, value => this.updateBlockData(block.id, ['data', 'media', 'alt'], value)));
    wrapper.append(this.addLabeledInput('Focal X', block.data.media?.focalPoint?.x ?? 0.5, value => this.updateBlockData(block.id, ['data', 'media', 'focalPoint', 'x'], Number(value)), { type: 'number', step: '0.01', min: 0, max: 1 }));
    wrapper.append(this.addLabeledInput('Focal Y', block.data.media?.focalPoint?.y ?? 0.5, value => this.updateBlockData(block.id, ['data', 'media', 'focalPoint', 'y'], Number(value)), { type: 'number', step: '0.01', min: 0, max: 1 }));

    const heroCta = block.data.cta || {};
    const primaryCta = heroCta.primary || heroCta;
    const secondaryCta = heroCta.secondary || {};

    wrapper.append(this.addLabeledInput('Primäre CTA (Label)', primaryCta?.label, value => this.updateBlockData(block.id, ['data', 'cta', 'primary', 'label'], value)));
    wrapper.append(this.addLabeledInput('Primäre CTA (Link)', primaryCta?.href, value => this.updateBlockData(block.id, ['data', 'cta', 'primary', 'href'], value)));
    wrapper.append(this.addLabeledInput('Primäre CTA (Aria-Label)', primaryCta?.ariaLabel, value => this.updateBlockData(block.id, ['data', 'cta', 'primary', 'ariaLabel'], value)));

    wrapper.append(this.addLabeledInput('Sekundäre CTA (Label)', secondaryCta?.label, value => this.updateBlockData(block.id, ['data', 'cta', 'secondary', 'label'], value)));
    wrapper.append(this.addLabeledInput('Sekundäre CTA (Link)', secondaryCta?.href, value => this.updateBlockData(block.id, ['data', 'cta', 'secondary', 'href'], value)));
    wrapper.append(this.addLabeledInput('Sekundäre CTA (Aria-Label)', secondaryCta?.ariaLabel, value => this.updateBlockData(block.id, ['data', 'cta', 'secondary', 'ariaLabel'], value)));

    return wrapper;
  }

  buildCtaForm(block) {
    const wrapper = document.createElement('div');

    wrapper.append(
      this.addLabeledInput('Titel', block.data.title, value => this.updateBlockData(block.id, ['data', 'title'], value))
    );
    wrapper.append(
      this.addLabeledInput('Body', block.data.body, value => this.updateBlockData(block.id, ['data', 'body'], value), {
        multiline: true,
        rows: 3
      })
    );

    const addCtaInputs = (cta, labelPrefix, path) => {
      wrapper.append(
        this.addLabeledInput(
          `${labelPrefix} CTA (Label)`,
          cta.label,
          value => this.updateBlockData(block.id, [...path, 'label'], value)
        )
      );
      wrapper.append(
        this.addLabeledInput(
          `${labelPrefix} CTA (Link)`,
          cta.href,
          value => this.updateBlockData(block.id, [...path, 'href'], value)
        )
      );
      wrapper.append(
        this.addLabeledInput(
          `${labelPrefix} CTA (Aria-Label)`,
          cta.ariaLabel,
          value => this.updateBlockData(block.id, [...path, 'ariaLabel'], value)
        )
      );
    };

    addCtaInputs(block.data.primary || {}, 'Primäre', ['data', 'primary']);
    addCtaInputs(block.data.secondary || {}, 'Sekundäre', ['data', 'secondary']);

    return wrapper;
  }

  buildRichTextForm(block) {
    const wrapper = document.createElement('div');
    const bodyField = document.createElement('div');
    bodyField.dataset.field = 'body';
    bodyField.dataset.richtext = 'true';
    this.mountRichText(bodyField, block.data.body, value => this.updateBlockData(block.id, ['data', 'body'], value));
    wrapper.append(this.wrapField('Text', bodyField));

    const alignmentSelect = document.createElement('select');
    ['start', 'center', 'end', 'justify'].forEach(value => {
      const option = document.createElement('option');
      option.value = value;
      option.textContent = value;
      if (value === block.data.alignment) {
        option.selected = true;
      }
      alignmentSelect.append(option);
    });
    alignmentSelect.addEventListener('change', event => this.updateBlockData(block.id, ['data', 'alignment'], event.target.value));
    wrapper.append(this.wrapField('Ausrichtung', alignmentSelect));

    return wrapper;
  }

  buildInfoMediaForm(block) {
    const wrapper = document.createElement('div');

    wrapper.append(this.addLabeledInput('Eyebrow', block.data.eyebrow, value => this.updateBlockData(block.id, ['data', 'eyebrow'], value)));
    wrapper.append(this.addLabeledInput('Titel', block.data.title, value => this.updateBlockData(block.id, ['data', 'title'], value)));
    wrapper.append(this.addLabeledInput('Untertitel', block.data.subtitle, value => this.updateBlockData(block.id, ['data', 'subtitle'], value)));

    const bodyField = document.createElement('div');
    bodyField.dataset.field = 'body';
    bodyField.dataset.richtext = 'true';
    this.mountRichText(bodyField, block.data.body, value => this.updateBlockData(block.id, ['data', 'body'], value));
    wrapper.append(this.wrapField('Text', bodyField));

    const mediaWrapper = document.createElement('div');
    mediaWrapper.dataset.field = 'media';
    mediaWrapper.append(this.addLabeledInput('Media ID', block.data.media?.imageId, value => this.updateBlockData(block.id, ['data', 'media', 'imageId'], value)));
    mediaWrapper.append(this.addLabeledInput('Media URL', block.data.media?.image, value => this.updateBlockData(block.id, ['data', 'media', 'image'], value)));
    mediaWrapper.append(this.addLabeledInput('Alt-Text', block.data.media?.alt, value => this.updateBlockData(block.id, ['data', 'media', 'alt'], value)));
    mediaWrapper.append(
      this.addLabeledInput(
        'Focal X',
        block.data.media?.focalPoint?.x ?? 0.5,
        value => this.updateBlockData(block.id, ['data', 'media', 'focalPoint', 'x'], Number(value)),
        { type: 'number', step: '0.01', min: 0, max: 1 }
      )
    );
    mediaWrapper.append(
      this.addLabeledInput(
        'Focal Y',
        block.data.media?.focalPoint?.y ?? 0.5,
        value => this.updateBlockData(block.id, ['data', 'media', 'focalPoint', 'y'], Number(value)),
        { type: 'number', step: '0.01', min: 0, max: 1 }
      )
    );
    wrapper.append(mediaWrapper);

    const itemsWrapper = document.createElement('div');
    itemsWrapper.dataset.field = 'items';

    const addItemBtn = document.createElement('button');
    addItemBtn.type = 'button';
    addItemBtn.textContent = 'Info-Item hinzufügen';
    addItemBtn.addEventListener('click', () => this.addInfoMediaItem(block.id));
    itemsWrapper.append(addItemBtn);

    (block.data.items || []).forEach((item, index) => {
      const itemCard = document.createElement('div');
      itemCard.dataset.infoMediaItem = item.id;

      itemCard.append(this.addLabeledInput('Titel', item.title, value => this.updateInfoMediaItem(block.id, item.id, 'title', value)));

      const descriptionField = document.createElement('div');
      descriptionField.dataset.field = 'description';
      descriptionField.dataset.richtext = 'true';
      this.mountRichText(descriptionField, item.description, value => this.updateInfoMediaItem(block.id, item.id, 'description', value));
      itemCard.append(this.wrapField('Beschreibung', descriptionField));

      const itemMediaWrapper = document.createElement('div');
      itemMediaWrapper.dataset.field = 'media';
      itemMediaWrapper.append(
        this.addLabeledInput('Media ID', item.media?.imageId, value => this.updateInfoMediaItem(block.id, item.id, ['media', 'imageId'], value))
      );
      itemMediaWrapper.append(
        this.addLabeledInput('Media URL', item.media?.image, value => this.updateInfoMediaItem(block.id, item.id, ['media', 'image'], value))
      );
      itemMediaWrapper.append(
        this.addLabeledInput('Alt-Text', item.media?.alt, value => this.updateInfoMediaItem(block.id, item.id, ['media', 'alt'], value))
      );
      itemMediaWrapper.append(
        this.addLabeledInput(
          'Focal X',
          item.media?.focalPoint?.x ?? 0.5,
          value => this.updateInfoMediaItem(block.id, item.id, ['media', 'focalPoint', 'x'], Number(value)),
          { type: 'number', step: '0.01', min: 0, max: 1 }
        )
      );
      itemMediaWrapper.append(
        this.addLabeledInput(
          'Focal Y',
          item.media?.focalPoint?.y ?? 0.5,
          value => this.updateInfoMediaItem(block.id, item.id, ['media', 'focalPoint', 'y'], Number(value)),
          { type: 'number', step: '0.01', min: 0, max: 1 }
        )
      );
      itemCard.append(itemMediaWrapper);

      const bulletsWrapper = document.createElement('div');
      bulletsWrapper.dataset.field = 'bullets';

      const addBulletBtn = document.createElement('button');
      addBulletBtn.type = 'button';
      addBulletBtn.textContent = 'Bullet hinzufügen';
      addBulletBtn.addEventListener('click', () => this.addInfoMediaItemBullet(block.id, item.id));
      bulletsWrapper.append(addBulletBtn);

    const bulletCount = item.bullets?.length || 0;
    (item.bullets || []).forEach((bullet, bulletIndex) => {
      const bulletRow = document.createElement('div');
      bulletRow.dataset.bulletIndex = bulletIndex;
      const bulletInput = document.createElement('input');
      bulletInput.value = bullet || '';
      bulletInput.addEventListener('input', event => this.updateInfoMediaItemBullet(block.id, item.id, bulletIndex, event.target.value));
        bulletRow.append(bulletInput);

        const bulletControls = document.createElement('div');
        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.textContent = 'Entfernen';
        removeBtn.disabled = bulletCount <= 0;
        removeBtn.addEventListener('click', () => this.removeInfoMediaItemBullet(block.id, item.id, bulletIndex));
        const moveUp = document.createElement('button');
        moveUp.type = 'button';
        moveUp.textContent = '↑';
        moveUp.disabled = bulletIndex === 0;
        moveUp.addEventListener('click', () => this.moveInfoMediaItemBullet(block.id, item.id, bulletIndex, -1));
        const moveDown = document.createElement('button');
        moveDown.type = 'button';
        moveDown.textContent = '↓';
        moveDown.disabled = bulletIndex === bulletCount - 1;
        moveDown.addEventListener('click', () => this.moveInfoMediaItemBullet(block.id, item.id, bulletIndex, 1));
        bulletControls.append(removeBtn, moveUp, moveDown);
        bulletRow.append(bulletControls);

        bulletsWrapper.append(bulletRow);
      });

      itemCard.append(bulletsWrapper);

      const itemControls = document.createElement('div');
      const removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.textContent = 'Entfernen';
      removeBtn.disabled = (block.data.items || []).length <= 1;
      removeBtn.addEventListener('click', () => this.removeInfoMediaItem(block.id, item.id));

      const duplicateBtn = document.createElement('button');
      duplicateBtn.type = 'button';
      duplicateBtn.textContent = 'Duplizieren';
      duplicateBtn.addEventListener('click', () => this.duplicateInfoMediaItem(block.id, item.id));

      const moveUp = document.createElement('button');
      moveUp.type = 'button';
      moveUp.textContent = '↑';
      moveUp.disabled = index === 0;
      moveUp.addEventListener('click', () => this.moveInfoMediaItem(block.id, item.id, -1));

      const moveDown = document.createElement('button');
      moveDown.type = 'button';
      moveDown.textContent = '↓';
      moveDown.disabled = index === (block.data.items?.length || 0) - 1;
      moveDown.addEventListener('click', () => this.moveInfoMediaItem(block.id, item.id, 1));

      itemControls.append(removeBtn, duplicateBtn, moveUp, moveDown);
      itemCard.append(itemControls);

      itemsWrapper.append(itemCard);
    });

    wrapper.append(itemsWrapper);
    return wrapper;
  }

  buildFeatureListForm(block) {
    const wrapper = document.createElement('div');

    const titleField = document.createElement('div');
    titleField.dataset.field = 'title';
    titleField.dataset.richtext = 'true';
    this.mountRichText(titleField, block.data.title, value => this.updateBlockData(block.id, ['data', 'title'], value));
    wrapper.append(this.wrapField('Titel', titleField));

    const introField = document.createElement('div');
    introField.dataset.field = 'intro';
    introField.dataset.richtext = 'true';
    this.mountRichText(introField, block.data.intro, value => this.updateBlockData(block.id, ['data', 'intro'], value));
    wrapper.append(this.wrapField('Intro', introField));

    const itemsWrapper = document.createElement('div');
    itemsWrapper.dataset.field = 'items';

    const addItemBtn = document.createElement('button');
    addItemBtn.type = 'button';
    addItemBtn.textContent = 'Feature hinzufügen';
    addItemBtn.addEventListener('click', () => this.addFeatureItem(block.id));
    itemsWrapper.append(addItemBtn);

    (block.data.items || []).forEach((item, index) => {
      const itemCard = document.createElement('div');
      itemCard.dataset.featureItem = item.id;

      itemCard.append(this.addLabeledInput('Icon', item.icon, value => this.updateFeatureItem(block.id, item.id, 'icon', value)));
      itemCard.append(this.addLabeledInput('Titel', item.title, value => this.updateFeatureItem(block.id, item.id, 'title', value)));

      const descField = document.createElement('div');
      descField.dataset.richtext = 'true';
      this.mountRichText(descField, item.description, value => this.updateFeatureItem(block.id, item.id, 'description', value));
      itemCard.append(this.wrapField('Beschreibung', descField));

      const itemControls = document.createElement('div');
      const removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.textContent = 'Entfernen';
      removeBtn.disabled = (block.data.items || []).length <= 1;
      removeBtn.addEventListener('click', () => this.removeFeatureItem(block.id, item.id));
      const moveUp = document.createElement('button');
      moveUp.type = 'button';
      moveUp.textContent = '↑';
      moveUp.disabled = index === 0;
      moveUp.addEventListener('click', () => this.moveFeatureItem(block.id, item.id, -1));
      const moveDown = document.createElement('button');
      moveDown.type = 'button';
      moveDown.textContent = '↓';
      moveDown.disabled = index === block.data.items.length - 1;
      moveDown.addEventListener('click', () => this.moveFeatureItem(block.id, item.id, 1));
      itemControls.append(removeBtn, moveUp, moveDown);
      itemCard.append(itemControls);

      itemsWrapper.append(itemCard);
    });

    wrapper.append(itemsWrapper);
    return wrapper;
  }

  buildProcessStepsForm(block) {
    const wrapper = document.createElement('div');

    wrapper.append(this.addLabeledInput('Titel', block.data.title, value => this.updateBlockData(block.id, ['data', 'title'], value)));
    wrapper.append(this.addLabeledInput('Zusammenfassung', block.data.summary, value => this.updateBlockData(block.id, ['data', 'summary'], value), { multiline: true }));

    const stepsWrapper = document.createElement('div');
    stepsWrapper.dataset.field = 'steps';

    const addStepBtn = document.createElement('button');
    addStepBtn.type = 'button';
    addStepBtn.textContent = 'Schritt hinzufügen';
    addStepBtn.addEventListener('click', () => this.addProcessStep(block.id));
    stepsWrapper.append(addStepBtn);

    (block.data.steps || []).forEach((step, index) => {
      const stepCard = document.createElement('div');
      stepCard.dataset.processStep = step.id;

      stepCard.append(this.addLabeledInput('Titel', step.title, value => this.updateProcessStep(block.id, step.id, 'title', value)));
      stepCard.append(this.addLabeledInput('Beschreibung', step.description, value => this.updateProcessStep(block.id, step.id, 'description', value), { multiline: true }));
      stepCard.append(this.addLabeledInput('Dauer', step.duration, value => this.updateProcessStep(block.id, step.id, 'duration', value)));

      const controls = document.createElement('div');
      const removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.textContent = 'Entfernen';
      removeBtn.disabled = (block.data.steps || []).length <= 2;
      removeBtn.addEventListener('click', () => this.removeProcessStep(block.id, step.id));
      const moveUp = document.createElement('button');
      moveUp.type = 'button';
      moveUp.textContent = '↑';
      moveUp.disabled = index === 0;
      moveUp.addEventListener('click', () => this.moveProcessStep(block.id, step.id, -1));
      const moveDown = document.createElement('button');
      moveDown.type = 'button';
      moveDown.textContent = '↓';
      moveDown.disabled = index === block.data.steps.length - 1;
      moveDown.addEventListener('click', () => this.moveProcessStep(block.id, step.id, 1));

      controls.append(removeBtn, moveUp, moveDown);
      stepCard.append(controls);
      stepsWrapper.append(stepCard);
    });

    wrapper.append(stepsWrapper);
    return wrapper;
  }

  buildTestimonialForm(block) {
    const wrapper = document.createElement('div');

    const quoteField = document.createElement('div');
    quoteField.dataset.field = 'quote';
    quoteField.dataset.richtext = 'true';
    this.mountRichText(quoteField, block.data.quote, value => this.updateBlockData(block.id, ['data', 'quote'], value));
    wrapper.append(this.wrapField('Zitat', quoteField));

    wrapper.append(this.addLabeledInput('Name', block.data.author?.name, value => this.updateBlockData(block.id, ['data', 'author', 'name'], value)));
    wrapper.append(this.addLabeledInput('Rolle', block.data.author?.role, value => this.updateBlockData(block.id, ['data', 'author', 'role'], value)));
    wrapper.append(this.addLabeledInput('Avatar-ID', block.data.author?.avatarId, value => this.updateBlockData(block.id, ['data', 'author', 'avatarId'], value)));

    return wrapper;
  }

  buildFaqForm(block) {
    const wrapper = document.createElement('div');

    wrapper.append(this.addLabeledInput('Titel', block.data.title, value => this.updateBlockData(block.id, ['data', 'title'], value)));

    const itemsWrapper = document.createElement('div');
    itemsWrapper.dataset.field = 'items';

    const addItemBtn = document.createElement('button');
    addItemBtn.type = 'button';
    addItemBtn.textContent = 'Frage hinzufügen';
    addItemBtn.addEventListener('click', () => this.addFaqItem(block.id));
    itemsWrapper.append(addItemBtn);

    (block.data.items || []).forEach((item, index) => {
      const itemCard = document.createElement('div');
      itemCard.dataset.faqItem = item.id;

      itemCard.append(this.addLabeledInput('Frage', item.question, value => this.updateFaqItem(block.id, item.id, 'question', value)));
      itemCard.append(
        this.addLabeledInput('Antwort', item.answer, value => this.updateFaqItem(block.id, item.id, 'answer', value), {
          multiline: true,
          rows: 3
        })
      );

      const controls = document.createElement('div');
      const removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.textContent = 'Entfernen';
      removeBtn.disabled = (block.data.items || []).length <= 1;
      removeBtn.addEventListener('click', () => this.removeFaqItem(block.id, item.id));

      const moveUp = document.createElement('button');
      moveUp.type = 'button';
      moveUp.textContent = '↑';
      moveUp.disabled = index === 0;
      moveUp.addEventListener('click', () => this.moveFaqItem(block.id, item.id, -1));

      const moveDown = document.createElement('button');
      moveDown.type = 'button';
      moveDown.textContent = '↓';
      moveDown.disabled = index === (block.data.items || []).length - 1;
      moveDown.addEventListener('click', () => this.moveFaqItem(block.id, item.id, 1));

      controls.append(removeBtn, moveUp, moveDown);
      itemCard.append(controls);
      itemsWrapper.append(itemCard);
    });

    wrapper.append(itemsWrapper);

    const followUpWrapper = document.createElement('div');
    followUpWrapper.dataset.field = 'followUp';
    followUpWrapper.append(
      this.addLabeledInput('Hinweis', block.data.followUp?.text, value => this.updateBlockData(block.id, ['data', 'followUp', 'text'], value))
    );
    followUpWrapper.append(
      this.addLabeledInput('Link-Label', block.data.followUp?.linkLabel, value => this.updateBlockData(block.id, ['data', 'followUp', 'linkLabel'], value))
    );
    followUpWrapper.append(
      this.addLabeledInput('Link URL', block.data.followUp?.href, value => this.updateBlockData(block.id, ['data', 'followUp', 'href'], value))
    );
    wrapper.append(followUpWrapper);

    return wrapper;
  }

  buildStatStripForm(block) {
    const wrapper = document.createElement('div');

    wrapper.append(this.addLabeledInput('Titel', block.data.title, value => this.updateBlockData(block.id, ['data', 'title'], value)));
    wrapper.append(
      this.addLabeledInput('Lead', block.data.lede, value => this.updateBlockData(block.id, ['data', 'lede'], value), {
        multiline: true
      })
    );

    const metricsWrapper = document.createElement('div');
    metricsWrapper.dataset.field = 'metrics';

    const addMetricBtn = document.createElement('button');
    addMetricBtn.type = 'button';
    addMetricBtn.textContent = 'Kennzahl hinzufügen';
    addMetricBtn.addEventListener('click', () => this.addStatStripMetric(block.id));
    metricsWrapper.append(addMetricBtn);

    (block.data.metrics || []).forEach((metric, index) => {
      const metricCard = document.createElement('div');
      metricCard.dataset.statMetric = metric.id;

      metricCard.append(this.addLabeledInput('ID', metric.id, value => this.updateStatStripMetric(block.id, metric.id, 'id', value)));
      metricCard.append(
        this.addLabeledInput('Wert', metric.value, value => this.updateStatStripMetric(block.id, metric.id, 'value', value))
      );
      metricCard.append(
        this.addLabeledInput('Label', metric.label, value => this.updateStatStripMetric(block.id, metric.id, 'label', value))
      );
      metricCard.append(this.addLabeledInput('Icon', metric.icon, value => this.updateStatStripMetric(block.id, metric.id, 'icon', value)));
      metricCard.append(
        this.addLabeledInput('Tooltip', metric.tooltip, value => this.updateStatStripMetric(block.id, metric.id, 'tooltip', value))
      );
      metricCard.append(
        this.addLabeledInput('Stand (as of)', metric.asOf, value => this.updateStatStripMetric(block.id, metric.id, 'asOf', value))
      );
      metricCard.append(
        this.addLabeledInput(
          'Supporting text',
          metric.benefit,
          value => this.updateStatStripMetric(block.id, metric.id, 'benefit', value),
          { multiline: true, rows: 2 }
        )
      );

      const controls = document.createElement('div');
      const removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.textContent = 'Entfernen';
      removeBtn.disabled = (block.data.metrics || []).length <= 1;
      removeBtn.addEventListener('click', () => this.removeStatStripMetric(block.id, metric.id));

      const moveUp = document.createElement('button');
      moveUp.type = 'button';
      moveUp.textContent = '↑';
      moveUp.disabled = index === 0;
      moveUp.addEventListener('click', () => this.moveStatStripMetric(block.id, metric.id, -1));

      const moveDown = document.createElement('button');
      moveDown.type = 'button';
      moveDown.textContent = '↓';
      moveDown.disabled = index === (block.data.metrics || []).length - 1;
      moveDown.addEventListener('click', () => this.moveStatStripMetric(block.id, metric.id, 1));

      controls.append(removeBtn, moveUp, moveDown);
      metricCard.append(controls);
      metricsWrapper.append(metricCard);
    });

    wrapper.append(metricsWrapper);

    const marqueeWrapper = document.createElement('div');
    marqueeWrapper.dataset.field = 'marquee';
    marqueeWrapper.append(
      this.buildStringList(block.data.marquee, 'Marquee-Eintrag', {
        add: () => this.addStatStripMarquee(block.id),
        update: (itemIndex, value) => this.updateStatStripMarquee(block.id, itemIndex, value),
        remove: itemIndex => this.removeStatStripMarquee(block.id, itemIndex),
        move: (itemIndex, delta) => this.moveStatStripMarquee(block.id, itemIndex, delta)
      })
    );
    wrapper.append(marqueeWrapper);

    return wrapper;
  }

  wrapField(labelText, element) {
    const wrapper = document.createElement('div');
    const label = document.createElement('div');
    label.textContent = labelText;
    wrapper.append(label, element);
    return wrapper;
  }

  getPrimaryText(block) {
    switch (block.type) {
      case 'hero':
        return block.data.headline || block.data.subheadline || block.data.eyebrow || '';
      case 'rich_text':
        return block.data.body || '';
      case 'feature_list':
        return block.data.title || block.data.items?.[0]?.title || block.data.items?.[0]?.description || '';
      case 'process_steps':
        return block.data.title || block.data.steps?.[0]?.title || '';
      case 'testimonial':
        return block.data.quote || block.data.author?.name || '';
      case 'info_media':
      case 'system_module':
        return block.data.title || block.data.eyebrow || block.data.items?.[0]?.title || '';
      case 'stat_strip':
        return block.data.title || block.data.lede || block.data.metrics?.[0]?.label || block.data.metrics?.[0]?.value || '';
      case 'audience_spotlight':
      case 'case_showcase':
        return block.data.title || block.data.cases?.[0]?.title || '';
      case 'package_summary':
        return block.data.title || block.data.plans?.[0]?.title || block.data.options?.[0]?.title || '';
      case 'faq':
        return block.data.title || block.data.items?.[0]?.question || '';
      default:
        return '';
    }
  }

  selectBlock(id) {
    this.state.selectedBlockId = id;
    this.render();
  }

  addBlock(type) {
    const variant = ensureRendererVariant(type);
    if (!type || !variant) {
      throw new Error('Ungültiger Blocktyp oder Variante');
    }
    const newBlock = getDefaultBlock(type, variant);
    const blocks = [...this.state.blocks, newBlock];
    this.state.blocks = blocks;
    this.state.selectedBlockId = newBlock.id;
    this.render();
  }

  duplicateSelected() {
    const block = this.state.blocks.find(item => item.id === this.state.selectedBlockId);
    if (!block) {
      return;
    }
    const clone = deepClone(block);
    clone.id = createId();
    if (clone.type === 'feature_list' && Array.isArray(clone.data.items)) {
      clone.data.items = clone.data.items.map(item => ({ ...item, id: createId() }));
    }
    if (clone.type === 'process_steps' && Array.isArray(clone.data.steps)) {
      clone.data.steps = clone.data.steps.map(step => ({ ...step, id: createId() }));
    }
    if (clone.type === 'info_media' && Array.isArray(clone.data.items)) {
      clone.data.items = clone.data.items.map(item => ({
        ...item,
        id: createId(),
        bullets: Array.isArray(item.bullets) ? [...item.bullets] : [],
        media: item.media ? { ...item.media, focalPoint: item.media.focalPoint ? { ...item.media.focalPoint } : undefined } : undefined
      }));
    }
    if (clone.type === 'faq' && Array.isArray(clone.data.items)) {
      clone.data.items = clone.data.items.map(entry => ({ ...entry, id: createId() }));
    }
    if (clone.type === 'audience_spotlight' && Array.isArray(clone.data.cases)) {
      clone.data.cases = clone.data.cases.map(entry => ({ ...entry, id: createId() }));
    }
    const sanitizedClone = sanitizeBlock(clone);
    const insertIndex = this.state.blocks.findIndex(item => item.id === block.id) + 1;
    const blocks = [...this.state.blocks];
    blocks.splice(insertIndex, 0, sanitizedClone);
    this.state.blocks = blocks;
    this.state.selectedBlockId = sanitizedClone.id;
    this.render();
  }

  deleteSelected() {
    const blocks = this.state.blocks.filter(item => item.id !== this.state.selectedBlockId);
    this.state.blocks = blocks;
    this.state.selectedBlockId = blocks[0]?.id || null;
    this.render();
  }

  moveBlock(id, delta) {
    const index = this.state.blocks.findIndex(block => block.id === id);
    const targetIndex = index + delta;
    if (index < 0 || targetIndex < 0 || targetIndex >= this.state.blocks.length) {
      return;
    }
    const blocks = [...this.state.blocks];
    const [item] = blocks.splice(index, 1);
    blocks.splice(targetIndex, 0, item);
    this.state.blocks = blocks;
    this.render();
  }

  addInfoMediaItem(blockId) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = deepClone(block);
      const items = Array.isArray(updated.data.items) ? [...updated.data.items] : [];
      items.push({
        id: createId(),
        title: 'Eintrag',
        description: 'Beschreibung',
        bullets: [],
        media: { imageId: '', image: '', alt: '', focalPoint: { x: 0.5, y: 0.5 } }
      });
      updated.data.items = items;
      return updated;
    });
    this.render();
  }

  duplicateInfoMediaItem(blockId, itemId) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = deepClone(block);
      const items = Array.isArray(updated.data.items) ? [...updated.data.items] : [];
      const index = items.findIndex(item => item.id === itemId);
      if (index === -1) {
        return block;
      }
      const source = items[index];
      const clone = {
        ...source,
        id: createId(),
        bullets: Array.isArray(source.bullets) ? [...source.bullets] : [],
        media: source.media ? { ...source.media, focalPoint: source.media.focalPoint ? { ...source.media.focalPoint } : undefined } : undefined
      };
      items.splice(index + 1, 0, clone);
      updated.data.items = items;
      return updated;
    });
    this.render();
  }

  updateInfoMediaItem(blockId, itemId, field, value) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = deepClone(block);
      updated.data.items = (updated.data.items || []).map(item => {
        if (item.id !== itemId) {
          return item;
        }
        if (Array.isArray(field)) {
          const clone = { ...item };
          let cursor = clone;
          for (let i = 0; i < field.length - 1; i += 1) {
            const key = field[i];
            if (!cursor[key] || typeof cursor[key] !== 'object') {
              cursor[key] = {};
            }
            cursor = cursor[key];
          }
          cursor[field[field.length - 1]] = value;
          return clone;
        }
        return { ...item, [field]: value };
      });
      return updated;
    });
  }

  removeInfoMediaItem(blockId, itemId) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = deepClone(block);
      const items = Array.isArray(updated.data.items) ? [...updated.data.items] : [];
      if (items.length <= 1) {
        return block;
      }
      updated.data.items = items.filter(item => item.id !== itemId);
      return updated;
    });
    this.render();
  }

  moveInfoMediaItem(blockId, itemId, delta) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = deepClone(block);
      const items = Array.isArray(updated.data.items) ? [...updated.data.items] : [];
      const index = items.findIndex(item => item.id === itemId);
      const target = index + delta;
      if (index < 0 || target < 0 || target >= items.length) {
        return block;
      }
      const [entry] = items.splice(index, 1);
      items.splice(target, 0, entry);
      updated.data.items = items;
      return updated;
    });
    this.render();
  }

  addInfoMediaItemBullet(blockId, itemId) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = deepClone(block);
      updated.data.items = (updated.data.items || []).map(item => {
        if (item.id !== itemId) {
          return item;
        }
        const bullets = Array.isArray(item.bullets) ? [...item.bullets] : [];
        bullets.push('');
        return { ...item, bullets };
      });
      return updated;
    });
    this.render();
  }

  updateInfoMediaItemBullet(blockId, itemId, bulletIndex, value) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = deepClone(block);
      updated.data.items = (updated.data.items || []).map(item => {
        if (item.id !== itemId) {
          return item;
        }
        const bullets = Array.isArray(item.bullets) ? [...item.bullets] : [];
        if (bulletIndex < 0 || bulletIndex >= bullets.length) {
          return item;
        }
        bullets[bulletIndex] = value;
        return { ...item, bullets };
      });
      return updated;
    });
  }

  removeInfoMediaItemBullet(blockId, itemId, bulletIndex) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = deepClone(block);
      updated.data.items = (updated.data.items || []).map(item => {
        if (item.id !== itemId) {
          return item;
        }
        const bullets = Array.isArray(item.bullets) ? [...item.bullets] : [];
        if (bulletIndex < 0 || bulletIndex >= bullets.length) {
          return item;
        }
        bullets.splice(bulletIndex, 1);
        return { ...item, bullets };
      });
      return updated;
    });
    this.render();
  }

  moveInfoMediaItemBullet(blockId, itemId, bulletIndex, delta) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = deepClone(block);
      updated.data.items = (updated.data.items || []).map(item => {
        if (item.id !== itemId) {
          return item;
        }
        const bullets = Array.isArray(item.bullets) ? [...item.bullets] : [];
        const target = bulletIndex + delta;
        if (bulletIndex < 0 || target < 0 || target >= bullets.length) {
          return item;
        }
        const [entry] = bullets.splice(bulletIndex, 1);
        bullets.splice(target, 0, entry);
        return { ...item, bullets };
      });
      return updated;
    });
    this.render();
  }

  updateBlockData(blockId, path, value) {
    const blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = deepClone(block);
      let cursor = updated;
      for (let i = 0; i < path.length - 1; i += 1) {
        const key = path[i];
        if (cursor[key] === undefined) {
          cursor[key] = {};
        }
        cursor = cursor[key];
      }
      cursor[path[path.length - 1]] = value;
      return updated;
    });
    this.state.blocks = blocks;
  }

  updateVariant(blockId, variant) {
    try {
      this.state.blocks = this.state.blocks.map(block => {
        if (block.id !== blockId) {
          return block;
        }
        const allowedVariant = ensureRendererVariant(block.type, variant);
        return sanitizeBlock({ ...block, variant: allowedVariant });
      });
      this.render();
    } catch (error) {
      window.alert(error.message || 'Variante ungültig');
    }
  }

  replaceBlock(blockId, changes) {
    try {
      this.state.blocks = this.state.blocks.map(block => {
        if (block.id !== blockId) {
          return block;
        }
        return sanitizeBlock({ ...block, ...changes });
      });
      this.render();
    } catch (error) {
      window.alert(error.message || 'Block konnte nicht aktualisiert werden');
    }
  }

  sanitizeStatStripLists(block) {
    const updated = deepClone(block);
    if (!isPlainObject(updated.data)) {
      updated.data = {};
    }
    const metrics = Array.isArray(updated.data.metrics)
      ? updated.data.metrics.filter(entry => entry && typeof entry === 'object')
      : [];
    updated.data.metrics = metrics.map(metric => ({
      ...metric,
      id: typeof metric.id === 'string' && metric.id ? metric.id : createId()
    }));
    updated.data.marquee = Array.isArray(updated.data.marquee)
      ? updated.data.marquee.filter(entry => typeof entry === 'string')
      : [];
    return updated;
  }

  addStatStripMetric(blockId) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = this.sanitizeStatStripLists(block);
      const metrics = Array.isArray(updated.data.metrics) ? [...updated.data.metrics] : [];
      metrics.push({ id: createId(), value: '100', label: 'Kennzahl', icon: '', tooltip: '', asOf: '', benefit: '' });
      updated.data.metrics = metrics;
      return updated;
    });
    this.render();
  }

  updateStatStripMetric(blockId, metricId, field, value) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = this.sanitizeStatStripLists(block);
      updated.data.metrics = (updated.data.metrics || []).map(metric =>
        metric.id === metricId ? { ...metric, [field]: value } : metric
      );
      return updated;
    });
  }

  removeStatStripMetric(blockId, metricId) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = this.sanitizeStatStripLists(block);
      const metrics = Array.isArray(updated.data.metrics) ? [...updated.data.metrics] : [];
      const index = metrics.findIndex(metric => metric.id === metricId);
      if (metrics.length <= 1 || index < 0) {
        return block;
      }
      metrics.splice(index, 1);
      updated.data.metrics = metrics;
      return updated;
    });
    this.render();
  }

  moveStatStripMetric(blockId, metricId, delta) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = this.sanitizeStatStripLists(block);
      const metrics = Array.isArray(updated.data.metrics) ? [...updated.data.metrics] : [];
      const index = metrics.findIndex(metric => metric.id === metricId);
      const target = index + delta;
      if (index < 0 || target < 0 || target >= metrics.length) {
        return block;
      }
      const [entry] = metrics.splice(index, 1);
      metrics.splice(target, 0, entry);
      updated.data.metrics = metrics;
      return updated;
    });
    this.render();
  }

  addStatStripMarquee(blockId) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = this.sanitizeStatStripLists(block);
      const marquee = Array.isArray(updated.data.marquee) ? [...updated.data.marquee] : [];
      marquee.push('Neuer Punkt');
      updated.data.marquee = marquee;
      return updated;
    });
    this.render();
  }

  updateStatStripMarquee(blockId, index, value) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = this.sanitizeStatStripLists(block);
      const marquee = Array.isArray(updated.data.marquee) ? [...updated.data.marquee] : [];
      if (index < 0 || index >= marquee.length) {
        return block;
      }
      marquee[index] = value;
      updated.data.marquee = marquee;
      return updated;
    });
  }

  removeStatStripMarquee(blockId, index) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = this.sanitizeStatStripLists(block);
      const marquee = Array.isArray(updated.data.marquee) ? [...updated.data.marquee] : [];
      if (index < 0 || index >= marquee.length) {
        return block;
      }
      marquee.splice(index, 1);
      updated.data.marquee = marquee;
      return updated;
    });
    this.render();
  }

  moveStatStripMarquee(blockId, index, delta) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = this.sanitizeStatStripLists(block);
      const marquee = Array.isArray(updated.data.marquee) ? [...updated.data.marquee] : [];
      const target = index + delta;
      if (index < 0 || target < 0 || index >= marquee.length || target >= marquee.length) {
        return block;
      }
      const [entry] = marquee.splice(index, 1);
      marquee.splice(target, 0, entry);
      updated.data.marquee = marquee;
      return updated;
    });
    this.render();
  }

  addFaqItem(blockId) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = deepClone(block);
      const items = Array.isArray(updated.data.items) ? updated.data.items : [];
      items.push({ id: createId(), question: 'Frage', answer: 'Antwort' });
      updated.data.items = items;
      return updated;
    });
    this.render();
  }

  updateFaqItem(blockId, itemId, field, value) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = deepClone(block);
      updated.data.items = (updated.data.items || []).map(item => (item.id === itemId ? { ...item, [field]: value } : item));
      return updated;
    });
  }

  removeFaqItem(blockId, itemId) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = deepClone(block);
      const items = Array.isArray(updated.data.items) ? [...updated.data.items] : [];
      if (items.length <= 1) {
        return block;
      }
      updated.data.items = items.filter(item => item.id !== itemId);
      return updated;
    });
    this.render();
  }

  moveFaqItem(blockId, itemId, delta) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = deepClone(block);
      const items = Array.isArray(updated.data.items) ? [...updated.data.items] : [];
      const index = items.findIndex(item => item.id === itemId);
      const target = index + delta;
      if (index < 0 || target < 0 || target >= items.length) {
        return block;
      }
      const [entry] = items.splice(index, 1);
      items.splice(target, 0, entry);
      updated.data.items = items;
      return updated;
    });
    this.render();
  }

  addFeatureItem(blockId) {
    const blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = deepClone(block);
      const items = Array.isArray(updated.data.items) ? updated.data.items : [];
      items.push({ id: createId(), icon: '', title: 'Feature', description: 'Beschreibung' });
      updated.data.items = items;
      return updated;
    });
    this.state.blocks = blocks;
    this.render();
  }

  updateFeatureItem(blockId, itemId, field, value) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = deepClone(block);
      updated.data.items = (updated.data.items || []).map(item => (item.id === itemId ? { ...item, [field]: value } : item));
      return updated;
    });
  }

  removeFeatureItem(blockId, itemId) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = deepClone(block);
      const items = Array.isArray(updated.data.items) ? [...updated.data.items] : [];
      if (items.length <= 1) {
        return block;
      }
      updated.data.items = items.filter(item => item.id !== itemId);
      return updated;
    });
    this.render();
  }

  moveFeatureItem(blockId, itemId, delta) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = deepClone(block);
      const items = Array.isArray(updated.data.items) ? [...updated.data.items] : [];
      const index = items.findIndex(item => item.id === itemId);
      const target = index + delta;
      if (index < 0 || target < 0 || target >= items.length) {
        return block;
      }
      const [entry] = items.splice(index, 1);
      items.splice(target, 0, entry);
      updated.data.items = items;
      return updated;
    });
    this.render();
  }

  addProcessStep(blockId) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = deepClone(block);
      const steps = Array.isArray(updated.data.steps) ? updated.data.steps : [];
      steps.push({ id: createId(), title: 'Schritt', description: 'Beschreibung', duration: '' });
      updated.data.steps = steps;
      return updated;
    });
    this.render();
  }

  addAudienceCase(blockId) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = deepClone(block);
      const cases = Array.isArray(updated.data.cases) ? updated.data.cases : [];
      cases.push({ id: createId(), title: 'Use Case', lead: '', body: '', bullets: [], keyFacts: [] });
      updated.data.cases = cases;
      return updated;
    });
    this.render();
  }

  updateAudienceCase(blockId, caseId, field, value) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = deepClone(block);
      updated.data.cases = (updated.data.cases || []).map(entry => {
        if (entry.id !== caseId) {
          return entry;
        }
        if (Array.isArray(field)) {
          const clone = { ...entry };
          let cursor = clone;
          for (let i = 0; i < field.length - 1; i += 1) {
            const key = field[i];
            if (!cursor[key] || typeof cursor[key] !== 'object') {
              cursor[key] = {};
            }
            cursor = cursor[key];
          }
          cursor[field[field.length - 1]] = value;
          return clone;
        }
        return { ...entry, [field]: value };
      });
      return updated;
    });
  }

  removeAudienceCase(blockId, caseId) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = deepClone(block);
      const cases = Array.isArray(updated.data.cases) ? [...updated.data.cases] : [];
      if (cases.length <= 1) {
        return block;
      }
      updated.data.cases = cases.filter(entry => entry.id !== caseId);
      return updated;
    });
    this.render();
  }

  moveAudienceCase(blockId, caseId, delta) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = deepClone(block);
      const cases = Array.isArray(updated.data.cases) ? [...updated.data.cases] : [];
      const index = cases.findIndex(entry => entry.id === caseId);
      const target = index + delta;
      if (index < 0 || target < 0 || target >= cases.length) {
        return block;
      }
      const [entry] = cases.splice(index, 1);
      cases.splice(target, 0, entry);
      updated.data.cases = cases;
      return updated;
    });
    this.render();
  }

  addAudienceCaseListItem(blockId, caseId, field) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = deepClone(block);
      updated.data.cases = (updated.data.cases || []).map(entry => {
        if (entry.id !== caseId) {
          return entry;
        }
        const list = Array.isArray(entry[field]) ? [...entry[field]] : [];
        list.push('');
        return { ...entry, [field]: list };
      });
      return updated;
    });
    this.render();
  }

  updateAudienceCaseListItem(blockId, caseId, field, index, value) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = deepClone(block);
      updated.data.cases = (updated.data.cases || []).map(entry => {
        if (entry.id !== caseId) {
          return entry;
        }
        const list = Array.isArray(entry[field]) ? [...entry[field]] : [];
        if (index < 0 || index >= list.length) {
          return entry;
        }
        list[index] = value;
        return { ...entry, [field]: list };
      });
      return updated;
    });
  }

  removeAudienceCaseListItem(blockId, caseId, field, index) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = deepClone(block);
      updated.data.cases = (updated.data.cases || []).map(entry => {
        if (entry.id !== caseId) {
          return entry;
        }
        const list = Array.isArray(entry[field]) ? [...entry[field]] : [];
        if (list.length <= 1 || index < 0 || index >= list.length) {
          return entry;
        }
        list.splice(index, 1);
        return { ...entry, [field]: list };
      });
      return updated;
    });
    this.render();
  }

  moveAudienceCaseListItem(blockId, caseId, field, index, delta) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = deepClone(block);
      updated.data.cases = (updated.data.cases || []).map(entry => {
        if (entry.id !== caseId) {
          return entry;
        }
        const list = Array.isArray(entry[field]) ? [...entry[field]] : [];
        const target = index + delta;
        if (index < 0 || target < 0 || target >= list.length) {
          return entry;
        }
        const [item] = list.splice(index, 1);
        list.splice(target, 0, item);
        return { ...entry, [field]: list };
      });
      return updated;
    });
    this.render();
  }

  addPackageOption(blockId) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = deepClone(block);
      const options = Array.isArray(updated.data.options) ? updated.data.options : [];
      options.push({ id: createId(), title: 'Option', intro: '', highlights: [{ title: 'Highlight', bullets: [] }] });
      updated.data.options = options;
      return updated;
    });
    this.render();
  }

  updatePackageOption(blockId, optionId, field, value) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = deepClone(block);
      updated.data.options = (updated.data.options || []).map(option => (option.id === optionId ? { ...option, [field]: value } : option));
      return updated;
    });
  }

  removePackageOption(blockId, optionId) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = deepClone(block);
      const options = Array.isArray(updated.data.options) ? [...updated.data.options] : [];
      if (options.length <= 1) {
        return block;
      }
      updated.data.options = options.filter(option => option.id !== optionId);
      return updated;
    });
    this.render();
  }

  movePackageOption(blockId, optionId, delta) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = deepClone(block);
      const options = Array.isArray(updated.data.options) ? [...updated.data.options] : [];
      const index = options.findIndex(option => option.id === optionId);
      const target = index + delta;
      if (index < 0 || target < 0 || target >= options.length) {
        return block;
      }
      const [entry] = options.splice(index, 1);
      options.splice(target, 0, entry);
      updated.data.options = options;
      return updated;
    });
    this.render();
  }

  addPackageOptionHighlight(blockId, optionId) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = deepClone(block);
      updated.data.options = (updated.data.options || []).map(option => {
        if (option.id !== optionId) {
          return option;
        }
        const highlights = Array.isArray(option.highlights) ? [...option.highlights] : [];
        highlights.push({ title: 'Highlight', bullets: [] });
        return { ...option, highlights };
      });
      return updated;
    });
    this.render();
  }

  updatePackageOptionHighlight(blockId, optionId, index, field, value) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = deepClone(block);
      updated.data.options = (updated.data.options || []).map(option => {
        if (option.id !== optionId) {
          return option;
        }
        const highlights = Array.isArray(option.highlights) ? [...option.highlights] : [];
        if (index < 0 || index >= highlights.length) {
          return option;
        }
        const updatedHighlights = highlights.map((highlight, highlightIndex) => (highlightIndex === index ? { ...highlight, [field]: value } : highlight));
        return { ...option, highlights: updatedHighlights };
      });
      return updated;
    });
  }

  removePackageOptionHighlight(blockId, optionId, index) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = deepClone(block);
      updated.data.options = (updated.data.options || []).map(option => {
        if (option.id !== optionId) {
          return option;
        }
        const highlights = Array.isArray(option.highlights) ? [...option.highlights] : [];
        if (highlights.length <= 1 || index < 0 || index >= highlights.length) {
          return option;
        }
        highlights.splice(index, 1);
        return { ...option, highlights };
      });
      return updated;
    });
    this.render();
  }

  movePackageOptionHighlight(blockId, optionId, index, delta) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = deepClone(block);
      updated.data.options = (updated.data.options || []).map(option => {
        if (option.id !== optionId) {
          return option;
        }
        const highlights = Array.isArray(option.highlights) ? [...option.highlights] : [];
        const target = index + delta;
        if (index < 0 || target < 0 || target >= highlights.length) {
          return option;
        }
        const [entry] = highlights.splice(index, 1);
        highlights.splice(target, 0, entry);
        return { ...option, highlights };
      });
      return updated;
    });
    this.render();
  }

  addPackageOptionHighlightBullet(blockId, optionId, highlightIndex) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = deepClone(block);
      updated.data.options = (updated.data.options || []).map(option => {
        if (option.id !== optionId) {
          return option;
        }
        const highlights = Array.isArray(option.highlights) ? [...option.highlights] : [];
        if (highlightIndex < 0 || highlightIndex >= highlights.length) {
          return option;
        }
        const updatedHighlights = highlights.map((highlight, index) => {
          if (index !== highlightIndex) {
            return highlight;
          }
          const bullets = Array.isArray(highlight.bullets) ? [...highlight.bullets] : [];
          bullets.push('');
          return { ...highlight, bullets };
        });
        return { ...option, highlights: updatedHighlights };
      });
      return updated;
    });
    this.render();
  }

  updatePackageOptionHighlightBullet(blockId, optionId, highlightIndex, bulletIndex, value) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = deepClone(block);
      updated.data.options = (updated.data.options || []).map(option => {
        if (option.id !== optionId) {
          return option;
        }
        const highlights = Array.isArray(option.highlights) ? [...option.highlights] : [];
        if (highlightIndex < 0 || highlightIndex >= highlights.length) {
          return option;
        }
        const updatedHighlights = highlights.map((highlight, index) => {
          if (index !== highlightIndex) {
            return highlight;
          }
          const bullets = Array.isArray(highlight.bullets) ? [...highlight.bullets] : [];
          if (bulletIndex < 0 || bulletIndex >= bullets.length) {
            return highlight;
          }
          bullets[bulletIndex] = value;
          return { ...highlight, bullets };
        });
        return { ...option, highlights: updatedHighlights };
      });
      return updated;
    });
  }

  removePackageOptionHighlightBullet(blockId, optionId, highlightIndex, bulletIndex) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = deepClone(block);
      updated.data.options = (updated.data.options || []).map(option => {
        if (option.id !== optionId) {
          return option;
        }
        const highlights = Array.isArray(option.highlights) ? [...option.highlights] : [];
        if (highlightIndex < 0 || highlightIndex >= highlights.length) {
          return option;
        }
        const updatedHighlights = highlights.map((highlight, index) => {
          if (index !== highlightIndex) {
            return highlight;
          }
          const bullets = Array.isArray(highlight.bullets) ? [...highlight.bullets] : [];
          if (bullets.length <= 1 || bulletIndex < 0 || bulletIndex >= bullets.length) {
            return highlight;
          }
          bullets.splice(bulletIndex, 1);
          return { ...highlight, bullets };
        });
        return { ...option, highlights: updatedHighlights };
      });
      return updated;
    });
    this.render();
  }

  movePackageOptionHighlightBullet(blockId, optionId, highlightIndex, bulletIndex, delta) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = deepClone(block);
      updated.data.options = (updated.data.options || []).map(option => {
        if (option.id !== optionId) {
          return option;
        }
        const highlights = Array.isArray(option.highlights) ? [...option.highlights] : [];
        if (highlightIndex < 0 || highlightIndex >= highlights.length) {
          return option;
        }
        const updatedHighlights = highlights.map((highlight, index) => {
          if (index !== highlightIndex) {
            return highlight;
          }
          const bullets = Array.isArray(highlight.bullets) ? [...highlight.bullets] : [];
          const target = bulletIndex + delta;
          if (bulletIndex < 0 || target < 0 || target >= bullets.length) {
            return highlight;
          }
          const [entry] = bullets.splice(bulletIndex, 1);
          bullets.splice(target, 0, entry);
          return { ...highlight, bullets };
        });
        return { ...option, highlights: updatedHighlights };
      });
      return updated;
    });
    this.render();
  }

  addPackagePlan(blockId) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = deepClone(block);
      const plans = Array.isArray(updated.data.plans) ? updated.data.plans : [];
      plans.push({
        id: createId(),
        title: 'Paket',
        description: '',
        features: [],
        notes: [],
        primaryCta: { label: 'Mehr erfahren', href: '#' }
      });
      updated.data.plans = plans;
      return updated;
    });
    this.render();
  }

  updatePackagePlan(blockId, planId, field, value) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = deepClone(block);
      updated.data.plans = (updated.data.plans || []).map(plan => {
        if (plan.id !== planId) {
          return plan;
        }
        if (Array.isArray(field)) {
          const clone = { ...plan };
          let cursor = clone;
          for (let i = 0; i < field.length - 1; i += 1) {
            const key = field[i];
            if (!cursor[key] || typeof cursor[key] !== 'object') {
              cursor[key] = {};
            }
            cursor = cursor[key];
          }
          cursor[field[field.length - 1]] = value;
          return clone;
        }
        return { ...plan, [field]: value };
      });
      return updated;
    });
  }

  removePackagePlan(blockId, planId) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = deepClone(block);
      const plans = Array.isArray(updated.data.plans) ? [...updated.data.plans] : [];
      if (plans.length <= 1) {
        return block;
      }
      updated.data.plans = plans.filter(plan => plan.id !== planId);
      return updated;
    });
    this.render();
  }

  movePackagePlan(blockId, planId, delta) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = deepClone(block);
      const plans = Array.isArray(updated.data.plans) ? [...updated.data.plans] : [];
      const index = plans.findIndex(plan => plan.id === planId);
      const target = index + delta;
      if (index < 0 || target < 0 || target >= plans.length) {
        return block;
      }
      const [entry] = plans.splice(index, 1);
      plans.splice(target, 0, entry);
      updated.data.plans = plans;
      return updated;
    });
    this.render();
  }

  addPackagePlanListItem(blockId, planId, field) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = deepClone(block);
      updated.data.plans = (updated.data.plans || []).map(plan => {
        if (plan.id !== planId) {
          return plan;
        }
        const list = Array.isArray(plan[field]) ? [...plan[field]] : [];
        list.push('');
        return { ...plan, [field]: list };
      });
      return updated;
    });
    this.render();
  }

  updatePackagePlanListItem(blockId, planId, field, index, value) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = deepClone(block);
      updated.data.plans = (updated.data.plans || []).map(plan => {
        if (plan.id !== planId) {
          return plan;
        }
        const list = Array.isArray(plan[field]) ? [...plan[field]] : [];
        if (index < 0 || index >= list.length) {
          return plan;
        }
        list[index] = value;
        return { ...plan, [field]: list };
      });
      return updated;
    });
  }

  removePackagePlanListItem(blockId, planId, field, index) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = deepClone(block);
      updated.data.plans = (updated.data.plans || []).map(plan => {
        if (plan.id !== planId) {
          return plan;
        }
        const list = Array.isArray(plan[field]) ? [...plan[field]] : [];
        if (list.length <= 1 || index < 0 || index >= list.length) {
          return plan;
        }
        list.splice(index, 1);
        return { ...plan, [field]: list };
      });
      return updated;
    });
    this.render();
  }

  movePackagePlanListItem(blockId, planId, field, index, delta) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = deepClone(block);
      updated.data.plans = (updated.data.plans || []).map(plan => {
        if (plan.id !== planId) {
          return plan;
        }
        const list = Array.isArray(plan[field]) ? [...plan[field]] : [];
        const target = index + delta;
        if (index < 0 || target < 0 || target >= list.length) {
          return plan;
        }
        const [item] = list.splice(index, 1);
        list.splice(target, 0, item);
        return { ...plan, [field]: list };
      });
      return updated;
    });
    this.render();
  }

  updateProcessStep(blockId, stepId, field, value) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = deepClone(block);
      updated.data.steps = (updated.data.steps || []).map(step => (step.id === stepId ? { ...step, [field]: value } : step));
      return updated;
    });
  }

  removeProcessStep(blockId, stepId) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = deepClone(block);
      const steps = Array.isArray(updated.data.steps) ? [...updated.data.steps] : [];
      if (steps.length <= 2) {
        return block;
      }
      updated.data.steps = steps.filter(step => step.id !== stepId);
      return updated;
    });
    this.render();
  }

  moveProcessStep(blockId, stepId, delta) {
    this.state.blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = deepClone(block);
      const steps = Array.isArray(updated.data.steps) ? [...updated.data.steps] : [];
      const index = steps.findIndex(step => step.id === stepId);
      const target = index + delta;
      if (index < 0 || target < 0 || target >= steps.length) {
        return block;
      }
      const [entry] = steps.splice(index, 1);
      steps.splice(target, 0, entry);
      updated.data.steps = steps;
      return updated;
    });
    this.render();
  }
}

export default BlockContentEditor;
