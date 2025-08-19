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
      let html = `<div><strong>${el.dataset.labelPlan}: ${planName}</strong></div>`;
      html += `<div>${el.dataset.labelPrice}: ${price}</div>`;
      html += `<div>${el.dataset.labelNext}: ${next}</div>`;
      html += `<div>${el.dataset.labelStatus}: ${data.status || '-'}</div>`;
      html += `<div class="uk-margin-top"><a class="uk-button uk-button-danger uk-button-small" href="${withBase('/admin/subscription/portal')}">${el.dataset.actionCancel}</a></div>`;
      el.innerHTML = html;
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
        el.innerHTML = `<div class="uk-text-meta">${el.dataset.textEmpty || ''}</div>`;
        return;
      }
      let html = '<div class="uk-overflow-auto"><table class="uk-table uk-table-divider uk-table-small">';
      html += '<thead><tr>' +
        `<th>${el.dataset.labelInvoice}</th>` +
        `<th>${el.dataset.labelDate}</th>` +
        `<th>${el.dataset.labelAmount}</th>` +
        `<th>${el.dataset.labelStatus}</th>` +
        `<th></th>` +
        '</tr></thead><tbody>';
      for (const inv of data){
        const num = inv.number || inv.id;
        const date = inv.created ? new Date(inv.created).toLocaleDateString() : '-';
        const amount = fmtAmount(inv.amount || 0, inv.currency || 'eur');
        const status = inv.status || '';
        const link = inv.invoice_pdf ? `<a href="${inv.invoice_pdf}" target="_blank" rel="noopener">${el.dataset.actionDownload}</a>` : '';
        html += `<tr><td>${num}</td><td>${date}</td><td>${amount}</td><td>${status}</td><td>${link}</td></tr>`;
      }
      html += '</tbody></table></div>';
      el.innerHTML = html;
    } catch (e) {
      console.error(e);
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    loadSubscription();
    loadInvoices();
  });
})();
