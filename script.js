document.addEventListener('DOMContentLoaded', () => {
  const btn = document.querySelector('.menu-toggle');
  const nav = document.getElementById('hauptnav');

  const setMenuState = (isOpen) => {
    if (!btn || !nav) return;
    document.body.classList.toggle('menu-open', isOpen);
    btn.setAttribute('aria-expanded', String(isOpen));
    btn.setAttribute('aria-label', isOpen ? 'Hauptmenü schließen' : 'Hauptmenü öffnen');
  };

  if (btn && nav) {
    btn.addEventListener('click', () => {
      setMenuState(!document.body.classList.contains('menu-open'));
    });

    nav.addEventListener('click', (event) => {
      if (event.target.tagName === 'A') {
        setMenuState(false);
      }
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && document.body.classList.contains('menu-open')) {
        setMenuState(false);
        btn.focus();
      }
    });
  }

  const cookieBanner = document.querySelector('.cookie-banner');
  const cookieAccept = document.querySelector('.cookie-accept');
  const consentKey = 'weidenborn-cookie-consent';

  const getConsentCookie = () => {
    return document.cookie.split('; ').find((row) => row.startsWith(`${consentKey}=`))?.split('=')[1] === 'accepted';
  };

  const setConsentCookie = () => {
    const sixMonthsInSeconds = 60 * 60 * 24 * 180;
    const secureAttribute = window.location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = `${consentKey}=accepted; Max-Age=${sixMonthsInSeconds}; Path=/; SameSite=Lax${secureAttribute}`;
  };

  if (cookieBanner) {
    const alreadyAccepted = getConsentCookie() || localStorage.getItem(consentKey) === 'accepted';
    if (alreadyAccepted) {
      cookieBanner.classList.add('is-hidden');
      if (!getConsentCookie()) {
        setConsentCookie();
      }
    } else {
      cookieBanner.classList.add('is-visible');
    }

    if (cookieAccept) {
      cookieAccept.addEventListener('click', () => {
        setConsentCookie();
        localStorage.removeItem(consentKey);
        cookieBanner.classList.remove('is-visible');
        cookieBanner.classList.add('is-hidden');
      });
    }
  }

  const contactForm = document.querySelector('.contact-form');
  if (contactForm) {
    const honeypot = contactForm.querySelector('input[name="website"]');
    const quizInput = contactForm.querySelector('input[name="quiz_answer"]');
    const statusText = contactForm.querySelector('.contact-status');
    const startTimeInput = contactForm.querySelector('input[name="form_started_at"]');
    const minimumFormTimeMs = 3000;

    if (startTimeInput) {
      startTimeInput.value = String(Date.now());
    }

    contactForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (startTimeInput) {
        const elapsedMs = Date.now() - Number(startTimeInput.value || 0);
        if (Number.isFinite(elapsedMs) && elapsedMs < minimumFormTimeMs) {
          if (statusText) {
            statusText.textContent = 'Nachricht wurde nicht gesendet. Bitte füllen Sie das Formular kurz in Ruhe aus.';
          }
          return;
        }
      }

      if (honeypot && honeypot.value.trim() !== '') {
        if (statusText) {
          statusText.textContent = 'Nachricht wurde nicht gesendet. Bitte versuchen Sie es erneut.';
        }
        return;
      }

      if (quizInput && quizInput.value.trim() !== '5') {
        if (statusText) {
          statusText.textContent = 'Bitte beantworten Sie die Kontrollfrage mit der Zahl 5.';
          quizInput.focus();
        }
        return;
      }

      if (statusText) {
        statusText.textContent = 'Nachricht wird gesendet...';
      }

      const payload = {
        name: contactForm.querySelector('input[name="name"]')?.value || '',
        email: contactForm.querySelector('input[name="email"]')?.value || '',
        message: contactForm.querySelector('textarea[name="message"]')?.value || '',
        website: honeypot?.value || '',
        quiz_answer: quizInput?.value || '',
        form_started_at: startTimeInput?.value || '',
      };

      try {
        const response = await fetch('/api/contact', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        });

        const data = await response.json().catch(() => ({}));

        if (response.ok) {
          contactForm.reset();
          if (startTimeInput) {
            startTimeInput.value = String(Date.now());
          }
          if (statusText) {
            statusText.textContent = 'Vielen Dank! Ihre Nachricht wurde gesendet.';
          }
        } else if (statusText) {
          statusText.textContent = data.error || 'Leider ist ein Fehler aufgetreten.';
        }
      } catch (error) {
        if (statusText) {
          statusText.textContent = 'Die Nachricht konnte nicht gesendet werden. Bitte versuchen Sie es erneut.';
        }
      }
    });
  }
});
