document.addEventListener('DOMContentLoaded', () => {
  const step1 = document.getElementById('step1');
  const step2 = document.getElementById('step2');
  const step3 = document.getElementById('step3');
  const emailInput = document.getElementById('email');
  const sendEmailBtn = document.getElementById('sendEmail');
  const emailStatus = document.getElementById('emailStatus');
  const subdomainInput = document.getElementById('subdomain');
  const subdomainPreview = document.getElementById('subdomainPreview');
  const saveSubdomainBtn = document.getElementById('saveSubdomain');
  const planButtons = document.querySelectorAll('.plan-select');
  const verifiedHint = document.getElementById('verifiedHint');

  const params = new URLSearchParams(window.location.search);
  const emailParam = params.get('email');
  if (emailParam && emailInput) {
    emailInput.value = emailParam;
  }
  if (params.get('verified') === '1') {
    step1.hidden = true;
    step2.hidden = false;
    if (verifiedHint) verifiedHint.hidden = false;
  }

  if (sendEmailBtn) {
    sendEmailBtn.addEventListener('click', async () => {
      const email = emailInput.value.trim();
      if (!email) return;
      const res = await fetch('/onboarding/email', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': window.csrfToken || ''
        },
        body: JSON.stringify({ email })
      });
      if (res.ok) {
        emailStatus.textContent = 'E-Mail versendet. Bitte prüfe dein Postfach.';
        emailStatus.hidden = false;
      }
    });
  }

  if (subdomainInput) {
    subdomainInput.addEventListener('input', () => {
      subdomainPreview.textContent = subdomainInput.value.trim().toLowerCase();
    });
  }

  if (saveSubdomainBtn) {
    saveSubdomainBtn.addEventListener('click', async () => {
      const subdomain = subdomainInput.value.trim().toLowerCase();
      if (!subdomain) return;
      const res = await fetch('/tenants/' + encodeURIComponent(subdomain));
      if (res.ok) {
        alert('Subdomain bereits vergeben.');
        return;
      }
      if (res.status !== 404) {
        alert('Fehler bei der Prüfung der Subdomain.');
        return;
      }
      step2.hidden = true;
      if (step3) step3.hidden = false;
    });
  }

  if (planButtons.length) {
    planButtons.forEach(btn => {
      btn.addEventListener('click', async () => {
        const plan = btn.dataset.plan;
        const email = emailInput.value.trim();
        if (!plan) return;
        try {
          const res = await fetch('/onboarding/checkout', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': window.csrfToken || ''
            },
            body: JSON.stringify({ plan, email })
          });
          if (res.ok) {
            const data = await res.json();
            if (data.url) {
              window.location.href = data.url;
              return;
            }
          }
        } catch (e) {
          // ignore and show alert below
        }
        alert('Fehler beim Start der Zahlung.');
      });
    });
  }
});

