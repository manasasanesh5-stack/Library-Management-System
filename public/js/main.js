/**
 * public/js/main.js  –  SecureLibrary Client-Side JS
 * Client-side validation is a UX layer ONLY.
 * All security enforcement is done server-side.
 */

'use strict';

/* ============================================================
   Password visibility toggle
   ============================================================ */
function togglePassword(fieldId) {
  const field = document.getElementById(fieldId);
  if (!field) return;
  field.type = field.type === 'password' ? 'text' : 'password';
}

/* ============================================================
   Client-side form validation (UX only)
   ============================================================ */
document.addEventListener('DOMContentLoaded', function () {

  // --- Login form ---
  const loginForm = document.getElementById('loginForm');
  if (loginForm) {
    loginForm.addEventListener('submit', function (e) {
      const email = document.getElementById('email').value.trim();
      const pw    = document.getElementById('password').value;
      if (!isValidEmail(email)) {
        e.preventDefault();
        showError(loginForm, 'Please enter a valid email address.');
        return;
      }
      if (!pw) {
        e.preventDefault();
        showError(loginForm, 'Please enter your password.');
      }
    });
  }

  // --- Register form ---
  const registerForm = document.getElementById('registerForm');
  if (registerForm) {
    registerForm.addEventListener('submit', function (e) {
      const name  = document.getElementById('name').value.trim();
      const email = document.getElementById('email').value.trim();
      const pw    = document.getElementById('password').value;
      const pw2   = document.getElementById('password_confirm').value;

      if (name.length < 2) {
        e.preventDefault();
        showError(registerForm, 'Full name must be at least 2 characters.');
        return;
      }
      if (!isValidEmail(email)) {
        e.preventDefault();
        showError(registerForm, 'Please enter a valid email address.');
        return;
      }
      if (!isStrongPassword(pw)) {
        e.preventDefault();
        showError(registerForm, 'Password must be at least 8 characters and include uppercase, lowercase, a digit, and a special character.');
        return;
      }
      if (pw !== pw2) {
        e.preventDefault();
        showError(registerForm, 'Passwords do not match.');
      }
    });

    // Live password strength indicator
    const pwField = document.getElementById('password');
    if (pwField) {
      pwField.addEventListener('input', updatePasswordStrength);
    }
  }

  // --- Book form ---
  const bookForm = document.getElementById('bookForm');
  if (bookForm) {
    bookForm.addEventListener('submit', function (e) {
      const isbn = bookForm.querySelector('[name="isbn"]').value.trim();
      if (isbn && !/^\d{10}(\d{3})?$/.test(isbn.replace(/-/g, ''))) {
        // warn but don't block (ISBN format can vary)
        console.warn('Non-standard ISBN format entered.');
      }
    });
  }

  // --- Auto-dismiss alerts after 5 seconds ---
  document.querySelectorAll('.alert').forEach(function (el) {
    setTimeout(function () {
      el.style.transition = 'opacity .5s';
      el.style.opacity = '0';
      setTimeout(function () { el.remove(); }, 500);
    }, 5000);
  });

  // --- Confirm dangerous actions ---
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      if (!confirm(el.dataset.confirm)) e.preventDefault();
    });
  });

});

/* ============================================================
   Helpers
   ============================================================ */
function isValidEmail(email) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email);
}

function isStrongPassword(pw) {
  return pw.length >= 8
    && /[A-Z]/.test(pw)
    && /[a-z]/.test(pw)
    && /[0-9]/.test(pw)
    && /[\W_]/.test(pw);
}

function showError(form, message) {
  // Remove old inline error if any
  const old = form.querySelector('.js-error');
  if (old) old.remove();

  const div = document.createElement('div');
  div.className = 'alert alert-danger js-error';
  div.innerHTML = '<i class="fa fa-exclamation-triangle"></i> ' + escapeHtml(message);
  form.insertBefore(div, form.firstChild);
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

function updatePasswordStrength() {
  const pw  = this.value;
  let score = 0;
  if (pw.length >= 8)    score++;
  if (/[A-Z]/.test(pw))  score++;
  if (/[a-z]/.test(pw))  score++;
  if (/[0-9]/.test(pw))  score++;
  if (/[\W_]/.test(pw))  score++;

  const labels = ['', 'Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
  const colors = ['', '#dc2626',   '#ea580c','#ca8a04','#16a34a','#15803d'];

  let indicator = document.getElementById('pwStrength');
  if (!indicator) {
    indicator = document.createElement('small');
    indicator.id = 'pwStrength';
    indicator.style.display = 'block';
    indicator.style.marginTop = '.25rem';
    this.parentElement.insertAdjacentElement('afterend', indicator);
  }
  indicator.textContent = score > 0 ? 'Strength: ' + labels[score] : '';
  indicator.style.color = colors[score] || '';
}
