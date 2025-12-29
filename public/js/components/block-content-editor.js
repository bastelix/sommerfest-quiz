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
        title: 'Info block',
        subtitle: '',
        body: '',
        items: [{ id: createId(), title: 'Eintrag', description: 'Beschreibung', bullets: [] }]
      }
    }),
    stat_strip: () => ({
      id: createId(),
      type: 'stat_strip',
      variant,
      data: {
        metrics: [{ id: createId(), value: '100%', label: 'Zuverlässig' }],
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
      case 'feature_list':
        return this.buildFeatureListForm(block);
      case 'process_steps':
        return this.buildProcessStepsForm(block);
      case 'testimonial':
        return this.buildTestimonialForm(block);
      case 'audience_spotlight':
        return this.buildAudienceSpotlightForm(block);
      default:
        return this.buildGenericJsonForm(block);
    }
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

    const buildStringList = (items, label, onChange) => {
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
    };

    (block.data.cases || []).forEach((audienceCase, index) => {
      const card = document.createElement('div');
      card.dataset.audienceCase = audienceCase.id;

      card.append(this.addLabeledInput('ID', audienceCase.id, value => this.updateAudienceCase(block.id, audienceCase.id, 'id', value)));
      card.append(this.addLabeledInput('Badge', audienceCase.badge, value => this.updateAudienceCase(block.id, audienceCase.id, 'badge', value)));
      card.append(this.addLabeledInput('Titel', audienceCase.title, value => this.updateAudienceCase(block.id, audienceCase.id, 'title', value)));
      card.append(this.addLabeledInput('Lead', audienceCase.lead, value => this.updateAudienceCase(block.id, audienceCase.id, 'lead', value), { multiline: true }));
      card.append(this.addLabeledInput('Body', audienceCase.body, value => this.updateAudienceCase(block.id, audienceCase.id, 'body', value), { multiline: true, rows: 4 }));

      card.append(buildStringList(audienceCase.bullets, 'Bullet', {
        add: () => this.addAudienceCaseListItem(block.id, audienceCase.id, 'bullets'),
        update: (itemIndex, value) => this.updateAudienceCaseListItem(block.id, audienceCase.id, 'bullets', itemIndex, value),
        remove: itemIndex => this.removeAudienceCaseListItem(block.id, audienceCase.id, 'bullets', itemIndex),
        move: (itemIndex, delta) => this.moveAudienceCaseListItem(block.id, audienceCase.id, 'bullets', itemIndex, delta)
      }));

      card.append(buildStringList(audienceCase.keyFacts, 'Key Fact', {
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
        return block.data.title || block.data.items?.[0]?.title || '';
      case 'stat_strip':
        return block.data.metrics?.[0]?.label || block.data.metrics?.[0]?.value || '';
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
