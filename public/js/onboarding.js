document.addEventListener('DOMContentLoaded', () => {
  const step1 = document.getElementById('step1');
  const step2 = document.getElementById('step2');
  const step3 = document.getElementById('step3');
  const step4 = document.getElementById('step4');
  const completeBtn = document.getElementById('completeOnboarding');
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
  const timelineSteps = document.querySelectorAll('.timeline-step');
  const restartBtn = document.getElementById('restartOnboarding');

  function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  }

  function isValidSubdomain(subdomain) {
    return /^[a-z0-9-]{3,}$/.test(subdomain);
  }

  const params = new URLSearchParams(window.location.search);
  const sessionId = params.get('session_id');
  const stepParam = params.get('step');
  const emailParam = params.get('email');
  const storedEmail = localStorage.getItem('onboard_email') || '';
  let subdomainStored = localStorage.getItem('onboard_subdomain') || '';
  let verified = params.get('verified') === '1' || localStorage.getItem('onboard_verified') === '1';
  if (params.get('verified') === '1') {
    localStorage.setItem('onboard_verified', '1');
    verified = true;
  }

  if (emailInput) {
    if (emailParam) {
      emailInput.value = emailParam;
    } else if (storedEmail) {
      emailInput.value = storedEmail;
    }
  }

  let currentStep = 1;
  function showStep(step) {
    currentStep = step;
    step1.hidden = step !== 1;
    step2.hidden = step !== 2;
    if (step3) step3.hidden = step !== 3;
    if (step4) step4.hidden = step !== 4;
    if (verifiedHint) verifiedHint.hidden = !(step === 2 && verified);
    if (subdomainPreview && subdomainStored) {
      subdomainPreview.textContent = subdomainStored;
    }
    timelineSteps.forEach(el => {
      const s = parseInt(el.dataset.step, 10);
      el.classList.toggle('active', s === step);
      el.classList.toggle('completed', s < step);
    });
  }
  if (verified) {
    currentStep = subdomainStored ? 3 : 2;
  }
  if (stepParam === '4') {
    const s4 = document.querySelector('.timeline-step[data-step="4"]');
    if (s4) s4.classList.remove('inactive');
    showStep(4);
  } else {
    showStep(currentStep);
  }

  if (sendEmailBtn) {
    sendEmailBtn.addEventListener('click', async () => {
      const email = emailInput.value.trim();
      if (!email) return;
      if (!isValidEmail(email)) {
        emailStatus.textContent = 'Ungültige E-Mail-Adresse.';
        emailStatus.hidden = false;
        return;
      }
      const res = await fetch(withBase('/onboarding/email'), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': window.csrfToken || ''
        },
        body: JSON.stringify({ email })
      });
      if (res.ok) {
        localStorage.setItem('onboard_email', email);
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
      if (!isValidSubdomain(subdomain)) {
        alert('Ungültige Subdomain.');
        return;
      }
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
      subdomainStored = subdomain;
      showStep(3);
    });
  }
  if (planButtons.length) {
    planButtons.forEach(btn => {
      btn.addEventListener('click', async () => {
        const plan = btn.dataset.plan;
        const email = localStorage.getItem('onboard_email') || emailInput.value.trim();
        const subdomain = localStorage.getItem('onboard_subdomain') || '';
        if (!plan) return;
        if (!isValidEmail(email)) {
          alert('Ungültige E-Mail-Adresse.');
          return;
        }
        if (!isValidSubdomain(subdomain)) {
          alert('Ungültige Subdomain.');
          return;
        }
        localStorage.setItem('onboard_plan', plan);
        if (plan === 'starter') {
          try {
            const tRes = await fetch(withBase('/tenants'), {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.csrfToken || ''
              },
              body: JSON.stringify({
                uid: subdomain,
                schema: subdomain,
                plan,
                email
              })
            });
            if (tRes.ok) {
              localStorage.removeItem('onboard_subdomain');
              localStorage.removeItem('onboard_plan');
              localStorage.removeItem('onboard_email');
              localStorage.removeItem('onboard_verified');
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
            body: JSON.stringify({ plan, email, subdomain })
          });
          const data = await res.json();
          if (res.ok && data.url) {
            window.location.href = data.url;
            return;
          }
          alert(data.error || 'Fehler beim Start der Zahlung.');
        } catch (e) {
          alert('Fehler beim Start der Zahlung.');
        }
      });
    });
  }

  if (timelineSteps.length) {
    timelineSteps.forEach(el => {
      if (el.classList.contains('inactive')) {
        return;
      }
      el.addEventListener('click', () => {
        const target = parseInt(el.dataset.step, 10);
        if (target === 1) {
          showStep(1);
        } else if (target === 2 && verified) {
          showStep(2);
        } else if (target === 3 && verified && subdomainStored) {
          showStep(3);
        }
      });
    });
  }

  if (restartBtn) {
    restartBtn.addEventListener('click', () => {
      localStorage.removeItem('onboard_subdomain');
      localStorage.removeItem('onboard_plan');
      localStorage.removeItem('onboard_email');
      localStorage.removeItem('onboard_verified');
      window.location.href = withBase('/onboarding');
    });
  }

  async function finalizeTenant() {
    try {
      const res = await fetch(withBase('/onboarding/checkout/' + encodeURIComponent(sessionId)));
      if (res.ok) {
        const data = await res.json();
        if (data.paid) {
          const subdomain = localStorage.getItem('onboard_subdomain') || '';
          const plan = localStorage.getItem('onboard_plan') || '';
          const email = localStorage.getItem('onboard_email') || '';
          if (isValidSubdomain(subdomain) && isValidEmail(email) && plan) {
            const tRes = await fetch(withBase('/tenants'), {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.csrfToken || ''
              },
              body: JSON.stringify({
                uid: subdomain,
                schema: subdomain,
                plan,
                billing: sessionId,
                email
              })
            });
            if (tRes.ok) {
              localStorage.removeItem('onboard_subdomain');
              localStorage.removeItem('onboard_plan');
              localStorage.removeItem('onboard_email');
              localStorage.removeItem('onboard_verified');
              window.location.href = `https://${subdomain}.${window.mainDomain}/`;
              return;
            }
          } else {
            alert('Ungültige Daten für die Registrierung.');
            return;
          }
        }
      }
    } catch (e) {
      // ignore and handle below
    }
    if (params.get('paid') === '1') {
      alert('Fehler bei der Registrierung.');
    }
  }

  if (sessionId) {
    if (stepParam === '4') {
      const s4 = document.querySelector('.timeline-step[data-step="4"]');
      if (s4) s4.classList.remove('inactive');
      showStep(4);
      if (completeBtn) {
        completeBtn.addEventListener('click', finalizeTenant);
      }
    } else {
      finalizeTenant();
    }
  }
});

