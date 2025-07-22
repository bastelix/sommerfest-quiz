(function(){
  function HtmlEditButton(quill, options) {
    this.quill = quill;
    const toolbar = quill.getModule('toolbar');
    if (!toolbar) return;
    toolbar.addHandler('html', this.toggleHtmlEdit.bind(this));
    const button = toolbar.container.querySelector('.ql-html');
    if (button) {
      button.innerHTML = options.buttonHTML || '&lt;/&gt;';
      button.setAttribute('type', 'button');
    }
  }
  HtmlEditButton.prototype.toggleHtmlEdit = function() {
    const quill = this.quill;
    const modal = document.createElement('div');
    modal.className = 'uk-modal';
    const textarea = document.createElement('textarea');
    textarea.className = 'uk-textarea';
    textarea.style.width = '100%';
    textarea.style.height = '300px';
    textarea.value = quill.root.innerHTML;
    const apply = document.createElement('button');
    apply.className = 'uk-button uk-button-primary apply-btn';
    apply.textContent = 'Übernehmen';
    const cancel = document.createElement('button');
    cancel.className = 'uk-button uk-button-default uk-modal-close';
    cancel.textContent = 'Abbrechen';
    const footer = document.createElement('p');
    footer.className = 'uk-text-right';
    footer.appendChild(cancel);
    footer.appendChild(document.createTextNode(' '));
    footer.appendChild(apply);
    const dialog = document.createElement('div');
    dialog.className = 'uk-modal-dialog uk-modal-body';
    dialog.appendChild(textarea);
    dialog.appendChild(footer);
    modal.appendChild(dialog);
    document.body.appendChild(modal);
    const modalObj = UIkit.modal(modal);
    modalObj.show();
    apply.addEventListener('click', function(){
      quill.root.innerHTML = textarea.value;
      modalObj.hide();
    });
    modal.addEventListener('hidden', function(){
      modal.remove();
    });
  };
  if (window.Quill) {
    window.Quill.register('modules/htmlEditButton', HtmlEditButton);
  }
})();
