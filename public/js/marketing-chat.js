(function () {
  'use strict';

  function ready(callback) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', callback, { once: true });
    } else {
      callback();
    }
  }

  function createParagraph(text) {
    var p = document.createElement('p');
    p.textContent = text;
    return p;
  }

  ready(function () {
    var config = globalThis.marketingChatConfig || {};
    var modalElement = document.getElementById('marketing-chat-modal')
      || document.getElementById('calserver-chat-modal');
    if (!modalElement) {
      return;
    }

    var modal = null;
    if (typeof UIkit !== 'undefined' && UIkit.modal) {
      modal = UIkit.modal(modalElement);
    }

    var openers = document.querySelectorAll('[data-marketing-chat-open], [data-calserver-chat-open]');
    var messagesContainer =
      modalElement.querySelector('[data-marketing-chat-messages]')
      || modalElement.querySelector('[data-calserver-chat-messages]');
    var statusElement =
      modalElement.querySelector('[data-marketing-chat-status]')
      || modalElement.querySelector('[data-calserver-chat-status]');
    var form =
      modalElement.querySelector('[data-marketing-chat-form]')
      || modalElement.querySelector('[data-calserver-chat-form]');
    var input =
      modalElement.querySelector('[data-marketing-chat-input]')
      || modalElement.querySelector('[data-calserver-chat-input]');
    if (!messagesContainer || !form || !input) {
      return;
    }

    var submitButton = form.querySelector('button[type="submit"]');
    var introShown = false;
    var pending = false;

    function setStatus(message, isError) {
      if (!statusElement) {
        return;
      }
      statusElement.textContent = message || '';
      statusElement.classList.toggle('marketing-chat__status--error', !!isError && message);
    }

    function showIntroMessage() {
      if (introShown) {
        return;
      }
      introShown = true;
      var intro = (config.texts && config.texts.intro) || '';
      if (intro) {
        var introBlock = document.createElement('div');
        introBlock.className = 'marketing-chat__system';
        introBlock.appendChild(createParagraph(intro));
        messagesContainer.appendChild(introBlock);
      }
    }

    function scrollMessages() {
      if (messagesContainer && typeof messagesContainer.scrollTo === 'function') {
        messagesContainer.scrollTo({ top: messagesContainer.scrollHeight, behavior: 'smooth' });
      }
    }

    function renderContext(context) {
      if (!Array.isArray(context) || context.length === 0) {
        return null;
      }

      var validItems = [];
      context.forEach(function (item) {
        if (!item || typeof item !== 'object') {
          return;
        }

        var label = item.label ? String(item.label) : '';
        var metadata = item.metadata && typeof item.metadata === 'object' ? item.metadata : {};
        var url = '';
        if (metadata.url && typeof metadata.url === 'string') {
          url = metadata.url.trim();
        } else if (metadata.permalink && typeof metadata.permalink === 'string') {
          url = metadata.permalink.trim();
        } else if (metadata.source && typeof metadata.source === 'string') {
          var source = metadata.source.trim();
          if (/^https?:\/\//i.test(source)) {
            url = source;
          }
        }

        validItems.push({
          label: label,
          url: url
        });
      });

      if (validItems.length === 0) {
        return null;
      }

      var details = document.createElement('details');
      details.className = 'marketing-chat__context';

      var summary = document.createElement('summary');
      summary.className = 'marketing-chat__context-toggle';
      var toggleText = (config.texts && (config.texts.sourcesToggle || config.texts.sources)) || 'Sources';
      summary.textContent = toggleText;
      details.appendChild(summary);

      var list = document.createElement('ol');
      list.className = 'marketing-chat__context-list';

      validItems.forEach(function (item) {
        if (!item.label && !item.url) {
          return;
        }

        var li = document.createElement('li');
        li.className = 'marketing-chat__context-item';

        if (item.url) {
          var link = document.createElement('a');
          link.className = 'marketing-chat__context-link';
          link.href = item.url;
          link.target = '_blank';
          link.rel = 'noopener noreferrer';
          link.textContent = item.label || item.url;
          li.appendChild(link);
        } else {
          var text = document.createElement('span');
          text.className = 'marketing-chat__context-label';
          text.textContent = item.label;
          li.appendChild(text);
        }

        list.appendChild(li);
      });

      if (!list.children.length) {
        return null;
      }

      details.appendChild(list);
      return details;
    }

    function appendTurn(question, answer, context) {
      var turn = document.createElement('article');
      turn.className = 'marketing-chat__turn';

      var questionHeading = document.createElement('h3');
      questionHeading.className = 'marketing-chat__question';
      var questionLabel = (config.texts && config.texts.question) || '';
      questionHeading.textContent = questionLabel ? questionLabel + ': ' + question : question;
      turn.appendChild(questionHeading);

      if (answer) {
        var answerContainer = document.createElement('div');
        answerContainer.className = 'marketing-chat__answer';
        String(answer)
          .split(/\n+/)
          .filter(function (line) { return line.trim() !== ''; })
          .forEach(function (line) {
            answerContainer.appendChild(createParagraph(line));
          });
        turn.appendChild(answerContainer);
      }

      var contextNode = renderContext(context);
      if (contextNode) {
        turn.appendChild(contextNode);
      }

      messagesContainer.appendChild(turn);
      scrollMessages();
    }

    function openChat(event) {
      if (event) {
        event.preventDefault();
      }
      showIntroMessage();
      if (modal) {
        modal.show();
      }
      setTimeout(function () {
        input.focus();
      }, 150);
    }

    openers.forEach(function (button) {
      button.addEventListener('click', openChat);
      button.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' || event.key === ' ') {
          openChat(event);
        }
      });
    });

    function resetForm() {
      input.disabled = false;
      input.value = '';
      if (submitButton) {
        submitButton.disabled = false;
      }
      form.classList.remove('marketing-chat__form--loading');
      pending = false;
      input.focus();
    }

    function handleError(status) {
      if (status === 429) {
        setStatus((config.texts && config.texts.errorRateLimited) || '', true);
      } else if (status === 422) {
        setStatus((config.texts && config.texts.errorValidation) || '', true);
      } else {
        setStatus((config.texts && config.texts.errorGeneric) || '', true);
      }
    }

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      if (pending) {
        return;
      }
      var value = input.value.trim();
      if (!value) {
        setStatus((config.texts && config.texts.errorValidation) || '', true);
        input.focus();
        return;
      }

      pending = true;
      form.classList.add('marketing-chat__form--loading');
      input.disabled = true;
      if (submitButton) {
        submitButton.disabled = true;
      }
      setStatus((config.texts && config.texts.loading) || '', false);

      var body = JSON.stringify({ question: value });
      var headers = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'fetch'
      };
      if (config.csrfToken) {
        headers['X-CSRF-Token'] = config.csrfToken;
      }

      if (typeof fetch !== 'function' || !config.endpoint) {
        handleError(503);
        resetForm();
        return;
      }

      fetch(config.endpoint, {
        method: 'POST',
        headers: headers,
        body: body,
        credentials: 'same-origin'
      })
        .then(function (response) {
          if (!response.ok) {
            handleError(response.status);
            throw new Error('Request failed with status ' + response.status);
          }
          return response.json();
        })
        .then(function (payload) {
          setStatus('', false);
          var context = payload && Array.isArray(payload.context) ? payload.context : [];
          appendTurn(payload.question || value, payload.answer || '', context);
          resetForm();
        })
        .catch(function () {
          if (!statusElement || statusElement.textContent === '') {
            setStatus((config.texts && config.texts.errorGeneric) || '', true);
          }
          resetForm();
        });
    });
  });
})();
