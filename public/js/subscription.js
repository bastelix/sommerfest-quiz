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

  async function loadSubscription(){
    const el = document.getElementById('subscription-details');
    if (!el) return;
    try {
      const res = await fetch(withBase('/admin/subscription/status'));
      if (!res.ok) return;
      const data = await res.json();
      if (!data.plan) return;
      const planName = el.dataset['plan' + capitalize(data.plan)] || data.plan;
      const price = fmtAmount(data.amount || 0, data.currency || 'eur');
      const next = data.next_payment ? new Date(data.next_payment).toLocaleDateString() : '-';

      el.textContent = '';

      const planDiv = document.createElement('div');
      const planStrong = document.createElement('strong');
      planStrong.textContent = `${el.dataset.labelPlan}: ${planName}`;
      planDiv.appendChild(planStrong);
      el.appendChild(planDiv);

      // Show trial badge when subscription is in trialing state
      if (data.subscription_status === 'trialing') {
        const trialDiv = document.createElement('div');
        trialDiv.className = 'uk-label uk-label-warning uk-margin-small-top';
        trialDiv.textContent = el.dataset.labelTrial || 'Trial';
        el.appendChild(trialDiv);
      }

      const priceDiv = document.createElement('div');
      priceDiv.textContent = `${el.dataset.labelPrice}: ${price}`;
      el.appendChild(priceDiv);

      const nextDiv = document.createElement('div');
      nextDiv.textContent = `${el.dataset.labelNext}: ${next}`;
      el.appendChild(nextDiv);

      const statusDiv = document.createElement('div');
      statusDiv.textContent = `${el.dataset.labelStatus}: ${data.status || '-'}`;
      el.appendChild(statusDiv);

      // Show notice when subscription is scheduled for cancellation
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

      const actionsDiv = document.createElement('div');
      actionsDiv.className = 'uk-margin-top';

      if (data.cancel_at_period_end) {
        // Show reactivate button when cancel is pending
        const reactivateLink = document.createElement('a');
        reactivateLink.className = 'uk-button uk-button-primary uk-button-small';
        reactivateLink.href = safeUrl(withBase('/admin/subscription/portal'));
        reactivateLink.textContent = el.dataset.actionReactivate || 'Reactivate';
        actionsDiv.appendChild(reactivateLink);
      } else {
        // Show manage and cancel buttons
        const manageLink = document.createElement('a');
        manageLink.className = 'uk-button uk-button-default uk-button-small uk-margin-small-right';
        manageLink.href = safeUrl(withBase('/admin/subscription/portal'));
        manageLink.textContent = el.dataset.actionManage || 'Manage';
        actionsDiv.appendChild(manageLink);

        const cancelLink = document.createElement('a');
        cancelLink.className = 'uk-button uk-button-danger uk-button-small';
        cancelLink.href = safeUrl(withBase('/admin/subscription/portal'));
        cancelLink.textContent = el.dataset.actionCancel;
        actionsDiv.appendChild(cancelLink);
      }

      el.appendChild(actionsDiv);
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

      const tbody = document.createElement('tbody');
      for (const inv of data){
        const row = document.createElement('tr');
        const num = inv.number || inv.id;
        const date = inv.created ? new Date(inv.created).toLocaleDateString() : '-';
        const amount = fmtAmount(inv.amount || 0, inv.currency || 'eur');
        const status = inv.status || '';

        [num, date, amount, status].forEach(val => {
          const td = document.createElement('td');
          td.textContent = val;
          row.appendChild(td);
        });

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

  document.addEventListener('DOMContentLoaded', () => {
    loadSubscription();
    loadInvoices();
  });
})();
