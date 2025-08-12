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
  const basePath = window.basePath || '';
  const withBase = p => basePath + p;

  const params = new URLSearchParams(window.location.search);
  const sessionId = params.get('session_id');
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
      const res = await fetch(withBase('/onboarding/email'), {
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
      const res = await fetch(withBase('/tenants/' + encodeURIComponent(subdomain)));
      if (res.ok) {
        alert('Subdomain bereits vergeben.');
        return;
      }
      if (res.status !== 404) {
        alert('Fehler bei der Prüfung der Subdomain.');
        return;
      }
      localStorage.setItem('onboard_subdomain', subdomain);
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
        localStorage.setItem('onboard_plan', plan);
        if (plan === 'starter') {
          const subdomain = localStorage.getItem('onboard_subdomain') || '';
          try {
            const tRes = await fetch('/tenants', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.csrfToken || ''
              },
              body: JSON.stringify({
                uid: subdomain,
                schema: subdomain,
                plan
              })
            });
            if (tRes.ok) {
              localStorage.removeItem('onboard_subdomain');
              localStorage.removeItem('onboard_plan');
              window.location.href = `https://${subdomain}.${window.mainDomain}/`;
              return;
            }
          } catch (e) {
            // ignore and show alert below
          }
          alert('Fehler bei der Registrierung.');
          return;
        }
        try {
          const res = await fetch(withBase('/onboarding/checkout'), {
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

  if (sessionId) {
    (async () => {
      try {
        const res = await fetch('/onboarding/checkout/' + encodeURIComponent(sessionId));
        if (res.ok) {
          const data = await res.json();
          if (data.paid) {
            const subdomain = localStorage.getItem('onboard_subdomain') || '';
            const plan = localStorage.getItem('onboard_plan') || '';
            if (subdomain && plan) {
              const tRes = await fetch('/tenants', {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json',
                  'X-CSRF-Token': window.csrfToken || ''
                },
                body: JSON.stringify({
                  uid: subdomain,
                  schema: subdomain,
                  plan,
                  billing: sessionId
                })
              });
              if (tRes.ok) {
                localStorage.removeItem('onboard_subdomain');
                localStorage.removeItem('onboard_plan');
                window.location.href = `https://${subdomain}.${window.mainDomain}/`;
                return;
              }
            }
          }
        }
      } catch (e) {
        // ignore and handle below
      }
      if (params.get('paid') === '1') {
        alert('Fehler bei der Registrierung.');
      }
    })();
  }
});

