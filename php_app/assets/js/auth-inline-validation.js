/**
 * Uthenga — Auth Inline Real-Time Validation Engine
 * Provides real-time email checks, password strength meters, password match validation,
 * specific error messaging, and password visibility toggling across all auth screens.
 */
(function () {
  'use strict';

  function getBaseUrl() {
    var meta = document.querySelector('meta[name="base-url"]');
    return meta ? meta.getAttribute('content') : '/';
  }

  var EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  var debounceTimers = {};

  function debounce(key, fn, delay) {
    if (debounceTimers[key]) clearTimeout(debounceTimers[key]);
    debounceTimers[key] = setTimeout(fn, delay || 250);
  }

  function getOrCreateInfoMsg(inputEl) {
    var parent = inputEl.closest('.form-group') || inputEl.parentElement;
    if (!parent) return null;

    var msgEl = parent.querySelector('.auth-inline-msg');
    if (!msgEl) {
      msgEl = document.createElement('div');
      msgEl.className = 'auth-inline-msg';
      msgEl.style.fontSize = '0.74rem';
      msgEl.style.fontWeight = '600';
      msgEl.style.marginTop = '0.35rem';
      msgEl.style.transition = 'all 0.15s ease';
      parent.appendChild(msgEl);
    }
    return msgEl;
  }

  function setMsg(inputEl, text, type) {
    var msgEl = getOrCreateInfoMsg(inputEl);
    if (!msgEl) return;

    if (!text) {
      msgEl.style.display = 'none';
      msgEl.textContent = '';
      inputEl.classList.remove('is-invalid', 'is-valid');
      return;
    }

    msgEl.style.display = 'block';
    msgEl.textContent = text;

    if (type === 'error') {
      msgEl.style.color = '#ef4444';
      inputEl.classList.add('is-invalid');
      inputEl.classList.remove('is-valid');
    } else if (type === 'success') {
      msgEl.style.color = '#10b981';
      inputEl.classList.add('is-valid');
      inputEl.classList.remove('is-invalid');
    } else {
      msgEl.style.color = '#6b7280';
      inputEl.classList.remove('is-invalid', 'is-valid');
    }
  }

  /* ── 1. Email Validation & Live Server Lookup ── */
  function initEmailValidation(emailInput, formType) {
    if (!emailInput) return;

    function validate() {
      var val = emailInput.value.trim();
      if (!val) {
        setMsg(emailInput, 'Email address is required.', 'error');
        return;
      }

      if (!EMAIL_REGEX.test(val)) {
        setMsg(emailInput, 'Please enter a valid email address (e.g. name@domain.com).', 'error');
        return;
      }

      setMsg(emailInput, 'Checking email address...', 'info');

      debounce('email-check', function () {
        var url = getBaseUrl() + 'api/auth/check_email.php?email=' + encodeURIComponent(val);
        fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
          .then(function (r) { return r.json(); })
          .then(function (res) {
            if (!res.success) {
              setMsg(emailInput, res.error || 'Invalid email format.', 'error');
              return;
            }

            if (formType === 'login' || formType === 'admin_login') {
              if (res.exists) {
                var roleLabel = res.role ? ' (' + res.role + ')' : '';
                setMsg(emailInput, '✓ Account registered' + roleLabel, 'success');
              } else {
                setMsg(emailInput, '✗ No account found with this email address.', 'error');
              }
            } else if (formType === 'register' || formType === 'vendor_register') {
              if (res.exists) {
                setMsg(emailInput, '✗ Account already exists with this email. Please sign in or reset password.', 'error');
              } else {
                setMsg(emailInput, '✓ Email address is available', 'success');
              }
            } else if (formType === 'forgot_password') {
              if (res.exists) {
                setMsg(emailInput, '✓ Account found (' + (res.name || 'User') + ')', 'success');
              } else {
                setMsg(emailInput, '✗ No account found with this email address.', 'error');
              }
            } else {
              setMsg(emailInput, '✓ Valid email format', 'success');
            }
          })
          .catch(function () {
            setMsg(emailInput, '✓ Valid email format', 'success');
          });
      }, 300);
    }

    emailInput.addEventListener('input', validate);
    emailInput.addEventListener('blur', validate);
  }

  /* ── 2. Password Strength Meter & Live Requirement Checklist ── */
  function initPasswordStrength(pwInput, isNewPw) {
    if (!pwInput) return;

    var parent = pwInput.closest('.form-group') || pwInput.parentElement;
    if (!parent) return;

    var meterWrap = parent.querySelector('.pw-strength-wrap');
    if (!meterWrap && isNewPw) {
      meterWrap = document.createElement('div');
      meterWrap.className = 'pw-strength-wrap';
      meterWrap.style.marginTop = '0.4rem';
      meterWrap.innerHTML =
        '<div style="height:4px;background:#e2e8f0;border-radius:2px;overflow:hidden;margin-bottom:0.35rem;">' +
          '<div class="pw-strength-bar" style="height:100%;width:0%;background:#ef4444;transition:all 0.2s ease;"></div>' +
        '</div>' +
        '<div class="pw-req-list" style="display:flex;gap:0.6rem;font-size:0.68rem;color:#6b7280;flex-wrap:wrap;">' +
          '<span class="req-len">○ 8+ characters</span>' +
          '<span class="req-case">○ Upper & lower</span>' +
          '<span class="req-num">○ Number or symbol</span>' +
        '</div>';
      parent.appendChild(meterWrap);
    }

    function checkStrength() {
      var val = pwInput.value;
      if (!val) {
        setMsg(pwInput, isNewPw ? 'Password is required.' : '', 'error');
        if (meterWrap) {
          meterWrap.querySelector('.pw-strength-bar').style.width = '0%';
        }
        return;
      }

      if (!isNewPw) {
        if (val.length < 1) {
          setMsg(pwInput, 'Please enter your password.', 'error');
        } else {
          setMsg(pwInput, '', '');
        }
        return;
      }

      var lenOk = val.length >= 8;
      var caseOk = /[a-z]/.test(val) && /[A-Z]/.test(val);
      var numOk = /[0-9]/.test(val) || /[^a-zA-Z0-9]/.test(val);

      var score = 0;
      if (lenOk) score += 35;
      if (caseOk) score += 35;
      if (numOk) score += 30;

      if (meterWrap) {
        var bar = meterWrap.querySelector('.pw-strength-bar');
        var reqLen = meterWrap.querySelector('.req-len');
        var reqCase = meterWrap.querySelector('.req-case');
        var reqNum = meterWrap.querySelector('.req-num');

        bar.style.width = score + '%';
        bar.style.background = score < 50 ? '#ef4444' : score < 80 ? '#f59e0b' : '#10b981';

        reqLen.textContent = (lenOk ? '✓ ' : '○ ') + '8+ characters';
        reqLen.style.color = lenOk ? '#10b981' : '#6b7280';

        reqCase.textContent = (caseOk ? '✓ ' : '○ ') + 'Upper & lower';
        reqCase.style.color = caseOk ? '#10b981' : '#6b7280';

        reqNum.textContent = (numOk ? '✓ ' : '○ ') + 'Number or symbol';
        reqNum.style.color = numOk ? '#10b981' : '#6b7280';
      }

      if (score < 50) {
        setMsg(pwInput, 'Weak password — add uppercase, numbers or symbols.', 'error');
      } else {
        setMsg(pwInput, 'Strong password ✓', 'success');
      }
    }

    pwInput.addEventListener('input', checkStrength);
    pwInput.addEventListener('blur', checkStrength);
  }

  /* ── 3. Password Match Checker ── */
  function initPasswordMatch(pwInput, pw2Input) {
    if (!pwInput || !pw2Input) return;

    function checkMatch() {
      var val1 = pwInput.value;
      var val2 = pw2Input.value;

      if (!val2) {
        setMsg(pw2Input, '', '');
        return;
      }

      if (val1 === val2) {
        setMsg(pw2Input, '✓ Passwords match', 'success');
      } else {
        setMsg(pw2Input, '✗ Passwords do not match', 'error');
      }
    }

    pw2Input.addEventListener('input', checkMatch);
    pw2Input.addEventListener('blur', checkMatch);
  }

  /* ── 4. Password Toggle Masking ── */
  function initPasswordToggles() {
    var pwInputs = document.querySelectorAll('input[type="password"]');
    pwInputs.forEach(function (input) {
      var parent = input.parentElement;
      if (!parent || parent.querySelector('.pw-toggle-btn')) return;

      if (getComputedStyle(parent).position === 'static') {
        parent.style.position = 'relative';
      }

      input.style.paddingRight = '2.5rem';

      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'pw-toggle-btn';
      btn.setAttribute('aria-label', 'Toggle password visibility');
      btn.style.position = 'absolute';
      btn.style.right = '0.75rem';
      btn.style.top = '50%';
      btn.style.transform = 'translateY(-50%)';
      btn.style.background = 'none';
      btn.style.border = 'none';
      btn.style.cursor = 'pointer';
      btn.style.padding = '0.2rem';
      btn.style.color = '#9ca3af';
      btn.style.lineHeight = '1';
      btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';

      btn.addEventListener('click', function (e) {
        e.preventDefault();
        var isText = input.type === 'text';
        input.type = isText ? 'password' : 'text';
        btn.innerHTML = isText
          ? '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>'
          : '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
      });

      parent.appendChild(btn);
    });
  }

  /* ── Boot Handler ── */
  document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('form');
    var formType = form ? (form.getAttribute('data-auth-type') || 'login') : 'login';

    var emailInput = document.querySelector('input[type="email"]') || document.querySelector('input[name="email"]');
    var pwInput = document.querySelector('input[name="password"]') || document.querySelector('input[name="password_hash"]');
    var pw2Input = document.querySelector('input[name="password2"]') || document.querySelector('input[name="confirm_password"]');

    var isNewPw = formType === 'register' || formType === 'vendor_register' || formType === 'reset_password';

    initPasswordToggles();

    if (emailInput) initEmailValidation(emailInput, formType);
    if (pwInput) initPasswordStrength(pwInput, isNewPw);
    if (pwInput && pw2Input) initPasswordMatch(pwInput, pw2Input);
  });
})();
