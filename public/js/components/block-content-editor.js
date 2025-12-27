import { Editor } from '../vendor/tiptap/core.esm.js';
import StarterKit from '../vendor/tiptap/starter-kit.esm.js';

const BLOCK_TYPES = [
  { value: 'hero', label: 'Hero' },
  { value: 'text', label: 'Text' },
  { value: 'feature_list', label: 'Feature list' },
  { value: 'testimonial', label: 'Testimonial' }
];

const createId = () => {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID();
  }
  return `id-${Math.random().toString(16).slice(2)}-${Date.now()}`;
};

const deepClone = value => JSON.parse(JSON.stringify(value));

const stripHtml = html => {
  if (!html) {
    return '';
  }
  const container = document.createElement('div');
  container.innerHTML = html;
  return container.textContent || container.innerText || '';
};

const defaultBlocks = {
  hero: () => ({
    id: createId(),
    type: 'hero',
    data: {
      eyebrow: '',
      headline: '',
      subheadline: '',
      media: {
        imageId: '',
        alt: '',
        focalPoint: { x: 0.5, y: 0.5 }
      },
      cta: {
        label: '',
        href: '',
        style: 'primary'
      }
    }
  }),
  text: () => ({
    id: createId(),
    type: 'text',
    data: {
      body: '',
      alignment: 'start'
    }
  }),
  feature_list: () => ({
    id: createId(),
    type: 'feature_list',
    data: {
      title: '',
      layout: 'stacked',
      items: [
        {
          id: createId(),
          icon: '',
          title: '',
          description: ''
        }
      ]
    }
  }),
  testimonial: () => ({
    id: createId(),
    type: 'testimonial',
    data: {
      quote: '',
      author: {
        name: '',
        role: '',
        avatarId: ''
      }
    }
  })
};

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

    const blocks = Array.isArray(parsed.blocks) ? parsed.blocks.map(block => this.normalizeBlock(block)).filter(Boolean) : [];
    this.state = {
      id: typeof parsed.id === 'string' ? parsed.id : null,
      blocks,
      meta: parsed.meta || {},
      selectedBlockId: blocks[0]?.id || null
    };
    this.render();
  }

  normalizeBlock(block) {
    if (!block || typeof block !== 'object') {
      return null;
    }
    const { id, type, data } = block;
    if (!type || typeof type !== 'string') {
      return null;
    }
    const safeId = typeof id === 'string' && id ? id : createId();
    const defaultFactory = defaultBlocks[type];
    if (!defaultFactory) {
      return null;
    }
    const fallback = defaultFactory();
    return {
      id: safeId,
      type,
      data: Object.assign({}, fallback.data, deepClone(data || {}))
    };
  }

  getContent() {
    return JSON.stringify({
      id: this.state.id || null,
      blocks: this.state.blocks,
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
    BLOCK_TYPES.forEach(entry => {
      const option = document.createElement('option');
      option.value = entry.value;
      option.textContent = entry.label;
      typeSelect.append(option);
    });

    const addBtn = document.createElement('button');
    addBtn.type = 'button';
    addBtn.dataset.action = 'add-block';
    addBtn.textContent = 'Block hinzufügen';
    addBtn.addEventListener('click', () => {
      this.addBlock(typeSelect.value);
    });

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

    container.append(typeSelect, addBtn, duplicateBtn, deleteBtn);
    return container;
  }

  buildBlockList() {
    const aside = document.createElement('aside');
    aside.dataset.blockList = 'true';
    const list = document.createElement('ul');

    if (this.state.blocks.length === 0) {
      const empty = document.createElement('div');
      empty.textContent = 'Keine Blöcke vorhanden.';
      aside.append(empty);
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
      label.textContent = `${block.type} – ${stripHtml(this.getPrimaryText(block))}`.trim();

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

    const formContent = this.buildBlockForm(block);
    form.append(formContent);

    panel.append(form);
    return panel;
  }

  buildBlockForm(block) {
    switch (block.type) {
      case 'hero':
        return this.buildHeroForm(block);
      case 'text':
        return this.buildTextForm(block);
      case 'feature_list':
        return this.buildFeatureListForm(block);
      case 'testimonial':
        return this.buildTestimonialForm(block);
      default:
        return document.createElement('div');
    }
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

    wrapper.append(this.addLabeledInput('CTA Label', block.data.cta?.label, value => this.updateBlockData(block.id, ['data', 'cta', 'label'], value)));
    wrapper.append(this.addLabeledInput('CTA Link', block.data.cta?.href, value => this.updateBlockData(block.id, ['data', 'cta', 'href'], value)));

    const styleSelect = document.createElement('select');
    ['primary', 'secondary'].forEach(style => {
      const option = document.createElement('option');
      option.value = style;
      option.textContent = style;
      if (style === block.data.cta?.style) {
        option.selected = true;
      }
      styleSelect.append(option);
    });
    styleSelect.addEventListener('change', event => this.updateBlockData(block.id, ['data', 'cta', 'style'], event.target.value));
    wrapper.append(this.wrapField('CTA Style', styleSelect));

    return wrapper;
  }

  buildTextForm(block) {
    const wrapper = document.createElement('div');
    const bodyField = document.createElement('div');
    bodyField.dataset.field = 'body';
    bodyField.dataset.richtext = 'true';
    this.mountRichText(bodyField, block.data.body, value => this.updateBlockData(block.id, ['data', 'body'], value));
    wrapper.append(this.wrapField('Text', bodyField));

    const alignmentSelect = document.createElement('select');
    ['start', 'center', 'end'].forEach(value => {
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

    const layoutSelect = document.createElement('select');
    ['stacked', 'grid'].forEach(value => {
      const option = document.createElement('option');
      option.value = value;
      option.textContent = value;
      if (value === block.data.layout) {
        option.selected = true;
      }
      layoutSelect.append(option);
    });
    layoutSelect.addEventListener('change', event => this.updateBlockData(block.id, ['data', 'layout'], event.target.value));
    wrapper.append(this.wrapField('Layout', layoutSelect));

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
      case 'text':
        return block.data.body || '';
      case 'feature_list':
        return block.data.title || block.data.items?.[0]?.title || block.data.items?.[0]?.description || '';
      case 'testimonial':
        return block.data.quote || block.data.author?.name || '';
      default:
        return '';
    }
  }

  selectBlock(id) {
    this.state.selectedBlockId = id;
    this.render();
  }

  addBlock(type) {
    const factory = defaultBlocks[type];
    if (!factory) {
      return;
    }
    const newBlock = factory();
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
    const insertIndex = this.state.blocks.findIndex(item => item.id === block.id) + 1;
    const blocks = [...this.state.blocks];
    blocks.splice(insertIndex, 0, clone);
    this.state.blocks = blocks;
    this.state.selectedBlockId = clone.id;
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

  addFeatureItem(blockId) {
    const blocks = this.state.blocks.map(block => {
      if (block.id !== blockId) {
        return block;
      }
      const updated = deepClone(block);
      const items = Array.isArray(updated.data.items) ? updated.data.items : [];
      items.push({ id: createId(), icon: '', title: '', description: '' });
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
      updated.data.items = (updated.data.items || []).filter(item => item.id !== itemId);
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
}

export default BlockContentEditor;
