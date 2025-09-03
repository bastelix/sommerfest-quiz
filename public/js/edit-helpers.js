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
  const modalEl = document.querySelector(modalSelector);
  const input = document.querySelector(inputSelector);
  const saveBtn = document.querySelector(saveSelector);
  const cancelBtn = document.querySelector(cancelSelector);

  if (!modalEl) {
    console.error(`createCellEditor: modal element not found for selector '${modalSelector}'`);
    return null;
  }
  if (!input) {
    console.error(`createCellEditor: input element not found for selector '${inputSelector}'`);
    return null;
  }
  if (!saveBtn) {
    console.error(`createCellEditor: save button not found for selector '${saveSelector}'`);
    return null;
  }
  if (!cancelBtn) {
    console.error(`createCellEditor: cancel button not found for selector '${cancelSelector}'`);
    return null;
  }

  const modal = UIkit.modal(modalEl);
  if (!modal) {
    console.error(`createCellEditor: failed to initialize modal for selector '${modalSelector}'`);
    return null;
  }

  const titleEl = modalEl.querySelector('.uk-modal-title');
  let currentId = null;
  let currentKey = null;

  cancelBtn.addEventListener('click', e => {
    e.preventDefault();
    modal.hide();
  });

  saveBtn.addEventListener('click', () => {
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
