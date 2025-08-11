document.addEventListener('DOMContentLoaded', () => {
  const step1 = document.getElementById('step1');
  const step2 = document.getElementById('step2');
  const emailInput = document.getElementById('email');
  const sendEmailBtn = document.getElementById('sendEmail');
  const emailStatus = document.getElementById('emailStatus');
  const subdomainInput = document.getElementById('subdomain');
  const subdomainPreview = document.getElementById('subdomainPreview');
  const saveSubdomainBtn = document.getElementById('saveSubdomain');
  const verifiedHint = document.getElementById('verifiedHint');

  const params = new URLSearchParams(window.location.search);
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
      subdomainPreview.textContent = subdomainInput.value.trim();
    });
  }

  if (saveSubdomainBtn) {
    saveSubdomainBtn.addEventListener('click', () => {
      const subdomain = subdomainInput.value.trim();
      if (!subdomain) return;
      alert('Subdomain "' + subdomain + '.' + (window.mainDomain || '') + '" gespeichert.');
    });
  }
});

