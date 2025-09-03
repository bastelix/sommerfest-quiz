export function createCellEditor(manager, {
  modalSelector,
  inputSelector,
  saveSelector,
  cancelSelector,
  getTitle = () => '',
  getType = () => 'text',
  validate = () => true,
  onSave = () => {}
} = {}) {
  const modal = UIkit.modal(modalSelector);
  const input = document.querySelector(inputSelector);
  const saveBtn = document.querySelector(saveSelector);
  const cancelBtn = document.querySelector(cancelSelector);
  const titleEl = document.querySelector(`${modalSelector} .uk-modal-title`);
  let currentId = null;
  let currentKey = null;

  cancelBtn?.addEventListener('click', e => {
    e.preventDefault();
    modal.hide();
  });

  saveBtn?.addEventListener('click', () => {
    const val = input.value.trim();
    if (!validate(val, currentKey, input)) return;
    const list = manager.getData();
    const item = list.find(it => it.id === currentId);
    if (item && currentKey) {
      item[currentKey] = val;
      manager.render(list);
      onSave(list, item, currentKey);
    }
    modal.hide();
  });

  return {
    open(cell) {
      currentId = cell?.dataset.id || null;
      currentKey = cell?.dataset.key || null;
      const item = manager.getData().find(it => it.id === currentId) || {};
      input.type = getType(currentKey, item);
      input.value = item[currentKey] || '';
      if (titleEl) {
        titleEl.textContent = getTitle(currentKey, item);
      }
      modal.show();
    }
  };
}
