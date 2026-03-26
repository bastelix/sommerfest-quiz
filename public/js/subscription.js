(function(){
  const basePath = window.basePath || '';
  const withBase = p => basePath + p;
  function capitalize(s){ return s ? s.charAt(0).toUpperCase() + s.slice(1) : s; }
  function fmtAmount(amount, currency){
    try {
      return (amount / 100).toLocaleString(undefined, { style: 'currency', currency: (currency || 'EUR').toUpperCase() });
    } catch (e) {
      return amount / 100 + ' ' + (currency || '');
    }
  }

  function safeUrl(url){
    try {
      const u = new URL(url, window.location.origin);
      if(['http:', 'https:'].includes(u.protocol)){
        return u.href;
      }
    } catch (e) {}
    return '#';
  }

  function getSelectedNamespace() {
    const select = document.getElementById('subscriptionNamespaceSelect');
    if (select) {
      return select.value || '';
    }
    return window.subscriptionNamespace || '';
  }

  function statusBadgeClass(status) {
    const map = {
      active: 'uk-label uk-label-success',
      trialing: 'uk-label uk-label-warning',
      past_due: 'uk-label uk-label-warning',
      paused: 'uk-label uk-label-warning',
      canceled: 'uk-label',
      unpaid: 'uk-label uk-label-danger',
      incomplete_expired: 'uk-label uk-label-danger'
    };
    return map[status] || 'uk-label';
  }

  function detailItem(label, value, renderFn) {
    const wrapper = document.createElement('div');
    const labelDiv = document.createElement('div');
    labelDiv.className = 'uk-text-meta';
    labelDiv.textContent = label;
    wrapper.appendChild(labelDiv);
    const valueDiv = document.createElement('div');
    valueDiv.className = 'uk-text-bold';
    if (typeof renderFn === 'function') {
      renderFn(valueDiv);
    } else {
      valueDiv.textContent = value;
    }
    wrapper.appendChild(valueDiv);
    return wrapper;
  }

  async function loadSubscription(){
    const el = document.getElementById('subscription-details');
    if (!el) return;
    const namespace = getSelectedNamespace();
    const params = namespace ? `?namespace=${encodeURIComponent(namespace)}` : '';
    try {
      const res = await fetch(withBase(`/admin/subscription/status${params}`));
      if (!res.ok) return;
      const data = await res.json();
      if (!data.plan) {
        window.currentSubscriptionPlan = null;
        return;
      }
      window.currentSubscriptionPlan = data.plan;
      const planName = el.dataset['plan' + capitalize(data.plan)] || data.plan;
      const price = fmtAmount(data.amount || 0, data.currency || 'eur');
      const next = data.next_payment ? new Date(data.next_payment).toLocaleDateString() : '-';
      const status = data.subscription_status || data.status || '';

      el.textContent = '';

      // Detail grid: 2x2 on medium+, stacked on mobile
      const grid = document.createElement('div');
      grid.className = 'uk-grid-small uk-child-width-1-2@m uk-margin-small-top';
      grid.setAttribute('uk-grid', '');

      // Plan with inline trial badge
      grid.appendChild(detailItem(el.dataset.labelPlan, planName, (div) => {
        div.textContent = planName;
        if (data.subscription_status === 'trialing') {
          const badge = document.createElement('span');
          badge.className = 'uk-label uk-label-warning uk-margin-small-left';
          badge.style.fontSize = '0.75rem';
          badge.textContent = el.dataset.labelTrial || 'Trial';
          div.appendChild(badge);
        }
      }));

      // Price
      grid.appendChild(detailItem(el.dataset.labelPrice, price));

      // Status as colored badge
      grid.appendChild(detailItem(el.dataset.labelStatus, '', (div) => {
        const badge = document.createElement('span');
        badge.className = statusBadgeClass(status);
        badge.textContent = status || '-';
        div.appendChild(badge);
      }));

      // Next payment
      grid.appendChild(detailItem(el.dataset.labelNext, next));

      el.appendChild(grid);

      // Cancellation notice
      if (data.cancel_at_period_end) {
        const cancelNotice = document.createElement('div');
        cancelNotice.className = 'uk-alert uk-alert-warning uk-margin-small-top';
        const cancelText = document.createElement('p');
        const endDate = data.next_payment ? new Date(data.next_payment).toLocaleDateString() : '';
        cancelText.textContent = (el.dataset.textCancelScheduled || 'Cancellation scheduled') +
          (endDate ? ` (${endDate})` : '');
        cancelNotice.appendChild(cancelText);
        el.appendChild(cancelNotice);
      }

      // Action buttons
      const actionsDiv = document.createElement('div');
      actionsDiv.className = 'uk-margin-top';

      const apiFetch = window.apiFetch || fetch;
      const notify = window.notify || (() => {});

      if (data.cancel_at_period_end) {
        const reactivateBtn = document.createElement('button');
        reactivateBtn.className = 'uk-button uk-button-primary uk-button-small';
        reactivateBtn.textContent = el.dataset.actionReactivate || 'Reactivate';
        reactivateBtn.addEventListener('click', async () => {
          reactivateBtn.disabled = true;
          try {
            const r = await apiFetch(withBase('/admin/subscription/toggle'), {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ plan: data.plan, namespace: getSelectedNamespace() })
            });
            if (!r.ok) {
              let errorMsg = el.dataset.errorSubscriptionUpdateFailed || 'Failed';
              try {
                const errData = await r.json();
                if (errData.error === 'subscription-not-found') {
                  errorMsg = el.dataset.errorSubscriptionNotFound || errorMsg;
                }
              } catch (_) {}
              throw new Error(errorMsg);
            }
            notify(el.dataset.actionReactivate || 'Reactivated', 'success');
            loadSubscription();
          } catch (err) {
            notify(err.message || 'Error', 'danger');
            reactivateBtn.disabled = false;
          }
        });
        actionsDiv.appendChild(reactivateBtn);
      } else {
        const cancelBtn = document.createElement('button');
        cancelBtn.className = 'uk-button uk-button-danger uk-button-small';
        cancelBtn.textContent = el.dataset.actionCancel;
        cancelBtn.addEventListener('click', async () => {
          const msg = el.dataset.confirmCancel || 'Cancel subscription?';
          if (!window.confirm(msg)) return;
          cancelBtn.disabled = true;
          try {
            const r = await apiFetch(withBase('/admin/subscription/toggle'), {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ plan: null, namespace: getSelectedNamespace() })
            });
            if (!r.ok) {
              let errorMsg = el.dataset.errorSubscriptionUpdateFailed || 'Failed';
              try {
                const errData = await r.json();
                if (errData.error === 'subscription-not-found') {
                  errorMsg = el.dataset.errorSubscriptionNotFound || errorMsg;
                }
              } catch (_) {}
              throw new Error(errorMsg);
            }
            notify(el.dataset.actionCancel || 'Cancelled', 'success');
            loadSubscription();
          } catch (err) {
            notify(err.message || 'Error', 'danger');
            cancelBtn.disabled = false;
          }
        });
        actionsDiv.appendChild(cancelBtn);
      }

      el.appendChild(actionsDiv);

      // Highlight current plan card
      document.querySelectorAll('[data-plan-card]').forEach(card => {
        card.classList.toggle('pricing-plan-card--active', card.dataset.planCard === data.plan);
      });

    } catch (e) {
      console.error(e);
    }
  }

  async function loadInvoices(){
    const el = document.getElementById('invoice-list');
    if (!el) return;
    const namespace = getSelectedNamespace();
    const params = namespace ? `?namespace=${encodeURIComponent(namespace)}` : '';
    try {
      const res = await fetch(withBase(`/admin/subscription/invoices${params}`));
      if (!res.ok) return;
      const data = await res.json();
      if (!Array.isArray(data) || !data.length){
        el.textContent = '';
        const emptyDiv = document.createElement('div');
        emptyDiv.className = 'uk-text-meta';
        emptyDiv.textContent = el.dataset.textEmpty || '';
        el.appendChild(emptyDiv);
        return;
      }

      const wrapper = document.createElement('div');
      wrapper.className = 'uk-overflow-auto';
      const table = document.createElement('table');
      table.className = 'uk-table uk-table-divider uk-table-small';

      const thead = document.createElement('thead');
      const headRow = document.createElement('tr');
      const headers = [el.dataset.labelInvoice, el.dataset.labelDate, el.dataset.labelAmount, el.dataset.labelStatus, el.dataset.labelDownload];
      headers.forEach(h => {
        const th = document.createElement('th');
        th.textContent = h;
        headRow.appendChild(th);
      });
      thead.appendChild(headRow);
      table.appendChild(thead);

      const invoiceStatusMap = { paid: 'uk-label-success', open: 'uk-label-warning', uncollectible: 'uk-label-danger', void: 'uk-label-danger' };

      const tbody = document.createElement('tbody');
      for (const inv of data){
        const row = document.createElement('tr');
        const num = inv.number || inv.id;
        const date = inv.created ? new Date(inv.created).toLocaleDateString() : '-';
        const amount = fmtAmount(inv.amount || 0, inv.currency || 'eur');
        const status = inv.status || '';

        [num, date, amount].forEach(val => {
          const td = document.createElement('td');
          td.textContent = val;
          row.appendChild(td);
        });

        // Status badge
        const statusTd = document.createElement('td');
        const statusBadge = document.createElement('span');
        statusBadge.className = 'uk-label ' + (invoiceStatusMap[status] || '');
        statusBadge.style.fontSize = '0.75rem';
        statusBadge.textContent = status;
        statusTd.appendChild(statusBadge);
        row.appendChild(statusTd);

        const downloadTd = document.createElement('td');
        if (inv.invoice_pdf){
          const link = document.createElement('a');
          link.href = safeUrl(inv.invoice_pdf);
          link.target = '_blank';
          link.rel = 'noopener';
          link.textContent = el.dataset.actionDownload;
          downloadTd.appendChild(link);
        }
        row.appendChild(downloadTd);
        tbody.appendChild(row);
      }
      table.appendChild(tbody);
      wrapper.appendChild(table);
      el.textContent = '';
      el.appendChild(wrapper);
    } catch (e) {
      console.error(e);
    }
  }

  function initPlanSelect() {
    const apiFetch = window.apiFetch || fetch;
    const notify = window.notify || (() => {});

    document.querySelectorAll('.plan-select').forEach(btn => {
      btn.addEventListener('click', async () => {
        const plan = btn.dataset.plan;
        if (!plan) return;

        const namespace = getSelectedNamespace();
        const emailInput = document.getElementById('subscription-email');
        const email = emailInput ? emailInput.value : '';

        btn.disabled = true;
        try {
          const r = await apiFetch(withBase('/admin/subscription/checkout'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ plan, namespace, email: email || undefined })
          });
          const data = await r.json();
          if (!r.ok) {
            throw new Error(data.error || 'checkout failed');
          }
          if (data.url) {
            window.location.href = data.url;
          }
        } catch (err) {
          notify(err.message || 'Error', 'danger');
          btn.disabled = false;
        }
      });
    });
  }

  async function loadPlans() {
    const container = document.getElementById('plan-cards');
    if (!container) return;

    try {
      const res = await fetch(withBase('/admin/subscription/plans'));
      if (!res.ok) return;
      const plans = await res.json();
      if (!Array.isArray(plans) || !plans.length) return;

      container.textContent = '';

      const grid = document.createElement('div');
      grid.className = 'uk-grid-small uk-child-width-1-1 uk-child-width-1-2@m uk-grid-match';
      grid.setAttribute('uk-grid', '');

      const currentPlan = window.currentSubscriptionPlan || null;

      for (const plan of plans) {
        const col = document.createElement('div');
        const card = document.createElement('div');
        card.className = 'uk-card qr-card uk-card-body pricing-plan-card';
        card.setAttribute('data-plan-card', plan.plan_key);
        if (currentPlan === plan.plan_key) {
          card.classList.add('pricing-plan-card--active');
        }

        // Plan name
        const nameDiv = document.createElement('div');
        nameDiv.className = 'uk-text-bold uk-text-large';
        nameDiv.textContent = plan.name;
        card.appendChild(nameDiv);

        // Price
        if (plan.price > 0) {
          const priceDiv = document.createElement('div');
          priceDiv.className = 'pricing-plan-price';
          const formattedPrice = fmtAmount(plan.price, plan.currency);
          const intervalLabel = plan.interval === 'year' ? '/Jahr' : '/Monat';
          priceDiv.innerHTML = formattedPrice + '<span class="uk-text-meta">' + intervalLabel + '</span>';
          card.appendChild(priceDiv);
        }

        // Description
        if (plan.description) {
          const descDiv = document.createElement('div');
          descDiv.className = 'uk-text-meta uk-margin-small-top';
          descDiv.textContent = plan.description;
          card.appendChild(descDiv);
        }

        // Feature list
        if (plan.features && plan.features.length) {
          const ul = document.createElement('ul');
          ul.className = 'uk-list uk-list-bullet uk-text-small uk-margin-small-top';
          for (const feature of plan.features) {
            const li = document.createElement('li');
            li.textContent = feature;
            ul.appendChild(li);
          }
          card.appendChild(ul);
        }

        // Trial badge
        if (plan.trial_days && plan.trial_days > 0) {
          const trialDiv = document.createElement('div');
          trialDiv.className = 'uk-margin-small-top';
          const trialBadge = document.createElement('span');
          trialBadge.className = 'uk-label uk-label-success';
          trialBadge.style.fontSize = '0.75rem';
          const trialLabel = container.dataset.labelTrial || '{n} Tage kostenlos testen';
          trialBadge.textContent = trialLabel.replace('{n}', plan.trial_days);
          trialDiv.appendChild(trialBadge);
          card.appendChild(trialDiv);
        }

        // Select button
        const btn = document.createElement('button');
        btn.className = plan.highlighted
          ? 'uk-button uk-button-primary uk-width-1-1 plan-select uk-margin-small-top'
          : 'uk-button uk-button-default uk-width-1-1 plan-select uk-margin-small-top';
        btn.setAttribute('data-plan', plan.plan_key);

        const actionLabel = container.dataset.actionStart || 'Abo starten';
        const currentLabel = container.dataset.labelCurrent || 'Aktueller Plan';

        if (currentPlan === plan.plan_key) {
          btn.textContent = currentLabel;
          btn.disabled = true;
          btn.classList.add('uk-disabled');
        } else {
          btn.textContent = actionLabel;
        }

        card.appendChild(btn);
        col.appendChild(card);
        grid.appendChild(col);
      }

      container.appendChild(grid);

      // Bind click handlers on dynamically created buttons
      initPlanSelect();

    } catch (e) {
      console.error('Failed to load plans', e);
    }
  }

  function initNamespaceSelect() {
    const select = document.getElementById('subscriptionNamespaceSelect');
    if (!select) return;

    select.addEventListener('change', () => {
      window.subscriptionNamespace = select.value;
      loadSubscription().then(() => {
        loadPlans();
      });
      loadInvoices();
    });
  }

  window.loadSubscription = loadSubscription;

  // ── Billing Hub Settings ──
  function loadBillingSettings() {
    const container = document.querySelector('[data-billing-settings]');
    if (!container) return;
    const ns = getSelectedNamespace();
    if (!ns) return;

    window.apiFetch(withBase('/admin/subscription/billing-settings?namespace=' + encodeURIComponent(ns)))
      .then(r => r.json())
      .then(data => {
        const product = container.querySelector('#billing-product');
        const tableId = container.querySelector('#billing-pricing-table-id');
        const webhookUrl = container.querySelector('#billing-webhook-url');
        const secretKey = container.querySelector('#billing-stripe-secret-key');
        const publishableKey = container.querySelector('#billing-stripe-publishable-key');
        if (product) product.value = data.product || '';
        if (tableId) tableId.value = data.stripe_pricing_table_id || '';
        if (webhookUrl) webhookUrl.value = data.webhook_url || '';
        if (secretKey) secretKey.value = data.stripe_secret_key || '';
        if (publishableKey) publishableKey.value = data.stripe_publishable_key || '';
      })
      .catch(() => {});
  }

  function initBillingSettings() {
    const btn = document.getElementById('billing-settings-save');
    if (!btn) return;

    btn.addEventListener('click', () => {
      const ns = getSelectedNamespace();
      if (!ns) return;
      const container = document.querySelector('[data-billing-settings]');
      if (!container) return;

      const secretKeyEl = container.querySelector('#billing-stripe-secret-key');
      const secretKeyVal = secretKeyEl ? secretKeyEl.value : '';
      // Only send secret key if it was actually changed (not the masked value)
      const sendSecretKey = secretKeyVal && !secretKeyVal.startsWith('*');

      const payload = {
        namespace: ns,
        product: (container.querySelector('#billing-product') || {}).value || '',
        stripe_pricing_table_id: (container.querySelector('#billing-pricing-table-id') || {}).value || '',
        webhook_url: (container.querySelector('#billing-webhook-url') || {}).value || '',
        stripe_publishable_key: (container.querySelector('#billing-stripe-publishable-key') || {}).value || '',
      };
      if (sendSecretKey) {
        payload.stripe_secret_key = secretKeyVal;
      }

      window.apiFetch(withBase('/admin/subscription/billing-settings'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      })
        .then(r => r.json())
        .then(() => { if (window.notify) window.notify('Billing-Einstellungen gespeichert.', 'success'); })
        .catch(() => { if (window.notify) window.notify('Fehler beim Speichern.', 'danger'); });
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    loadSubscription().then(() => {
      loadPlans();
    });
    loadInvoices();
    initPlanSelect();
    initNamespaceSelect();
    loadBillingSettings();
    initBillingSettings();
  });
})();
