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
  async function load(){
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
  document.addEventListener('DOMContentLoaded', load);
})();
