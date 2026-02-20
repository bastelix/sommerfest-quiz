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
    try {
      const res = await fetch(withBase('/admin/subscription/status'));
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
              body: JSON.stringify({ plan: data.plan })
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
              body: JSON.stringify({ plan: null })
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
    try {
      const res = await fetch(withBase('/admin/subscription/invoices'));
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

  window.loadSubscription = loadSubscription;

  document.addEventListener('DOMContentLoaded', () => {
    loadSubscription();
    loadInvoices();
  });
})();
