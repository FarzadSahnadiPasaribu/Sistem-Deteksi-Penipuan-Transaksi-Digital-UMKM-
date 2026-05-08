'use strict';

// ── SIDEBAR ──────────────────────────────────────────
const Sidebar = {
  sidebar: null,
  overlay: null,

  init() {
    this.sidebar = document.querySelector('.dashboard-sidebar');
    this.overlay = document.querySelector('.sidebar-overlay');
    if (!this.sidebar) return;

    const toggleBtn = document.querySelector('[data-sidebar-toggle]');
    if (toggleBtn) toggleBtn.addEventListener('click', () => this.toggle());
    if (this.overlay) this.overlay.addEventListener('click', () => this.close());

    window.addEventListener('resize', () => {
      if (window.innerWidth >= 1024) this.close();
    });
  },

  toggle() {
    this.sidebar.classList.toggle('open');
    if (this.overlay) this.overlay.classList.toggle('visible');
    document.body.classList.toggle('modal-open');
  },

  close() {
    this.sidebar.classList.remove('open');
    if (this.overlay) this.overlay.classList.remove('visible');
    document.body.classList.remove('modal-open');
  }
};

// ── TOAST NOTIFICATIONS ───────────────────────────────
const Toast = {
  container: null,

  init() {
    this.container = document.getElementById('toast-container');
    if (!this.container) {
      this.container = document.createElement('div');
      this.container.id = 'toast-container';
      document.body.appendChild(this.container);
    }
  },

  show(message, type = 'info', title = '', duration = 4000) {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;

    const icons = { success: '✓', error: '✕', warning: '⚠', info: 'ℹ' };
    const titles = {
      success: title || 'Berhasil',
      error:   title || 'Gagal',
      warning: title || 'Perhatian',
      info:    title || 'Informasi'
    };

    toast.innerHTML = `
      <span style="font-size:1.25rem;flex-shrink:0;margin-top:1px">${icons[type] || 'ℹ'}</span>
      <div>
        <div class="toast-title">${titles[type]}</div>
        <div class="toast-message">${message}</div>
      </div>
    `;

    this.container.appendChild(toast);
    requestAnimationFrame(() => {
      requestAnimationFrame(() => toast.classList.add('show'));
    });

    setTimeout(() => {
      toast.classList.remove('show');
      toast.classList.add('hide');
      toast.addEventListener('transitionend', () => toast.remove(), { once: true });
    }, duration);
  }
};

// ── MODAL ─────────────────────────────────────────────
const Modal = {
  init() {
    document.addEventListener('click', (e) => {
      const trigger = e.target.closest('[data-modal-open]');
      if (trigger) {
        const modalId = trigger.dataset.modalOpen;
        this.open(modalId);
      }

      const closeBtn = e.target.closest('[data-modal-close]');
      if (closeBtn) {
        const backdrop = closeBtn.closest('.modal-backdrop');
        if (backdrop) this.close(backdrop.id);
      }

      if (e.target.classList.contains('modal-backdrop')) {
        this.close(e.target.id);
      }
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        const openModal = document.querySelector('.modal-backdrop.open');
        if (openModal) this.close(openModal.id);
      }
    });
  },

  open(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.add('open');
    document.body.classList.add('modal-open');
  },

  close(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.remove('open');
    document.body.classList.remove('modal-open');
  }
};

// ── FORM VALIDATION ───────────────────────────────────
const FormValidator = {
  init() {
    document.querySelectorAll('[data-validate]').forEach(form => {
      form.addEventListener('submit', (e) => {
        if (!this.validate(form)) e.preventDefault();
      });
    });
  },

  validate(form) {
    let isValid = true;

    form.querySelectorAll('[required]').forEach(field => {
      this.clearError(field);

      if (!field.value.trim()) {
        this.showError(field, 'Field ini wajib diisi');
        isValid = false;
      } else if (field.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(field.value)) {
        this.showError(field, 'Format email tidak valid');
        isValid = false;
      } else if (field.dataset.minlength && field.value.length < parseInt(field.dataset.minlength)) {
        this.showError(field, `Minimal ${field.dataset.minlength} karakter`);
        isValid = false;
      } else if (field.dataset.match) {
        const matchField = document.getElementById(field.dataset.match);
        if (matchField && field.value !== matchField.value) {
          this.showError(field, 'Password tidak cocok');
          isValid = false;
        }
      }
    });

    return isValid;
  },

  showError(field, message) {
    field.classList.add('is-invalid');
    const error = document.createElement('span');
    error.className = 'form-error';
    error.textContent = message;
    field.parentNode.appendChild(error);
  },

  clearError(field) {
    field.classList.remove('is-invalid');
    const err = field.parentNode.querySelector('.form-error');
    if (err) err.remove();
  }
};

// ── INTEREST SELECTOR ──────────────────────────────────
const InterestSelector = {
  init() {
    const container = document.querySelector('.interest-grid');
    if (!container) return;

    container.addEventListener('click', (e) => {
      const item = e.target.closest('.interest-item');
      if (!item) return;
      item.classList.toggle('selected');
      const input = item.querySelector('input[type="checkbox"]');
      if (input) input.checked = !input.checked;
    });
  }
};

// ── POSTER UPLOAD PREVIEW ─────────────────────────────
const PosterUpload = {
  init() {
    const input = document.getElementById('poster-input');
    const preview = document.getElementById('poster-preview');
    if (!input || !preview) return;

    input.addEventListener('change', () => {
      const file = input.files[0];
      if (!file) return;

      if (file.size > 2 * 1024 * 1024) {
        Toast.show('Ukuran file maksimal 2MB', 'error');
        input.value = '';
        return;
      }

      const reader = new FileReader();
      reader.onload = (e) => {
        preview.src = e.target.result;
        preview.style.display = 'block';
      };
      reader.readAsDataURL(file);
    });
  }
};

// ── MULTI-STEP FORM ────────────────────────────────────
const MultiStep = {
  currentStep: 1,
  totalSteps: 2,

  init() {
    const form = document.querySelector('[data-multistep]');
    if (!form) return;

    this.totalSteps = parseInt(form.dataset.multistep) || 2;
    this.showStep(1);

    form.addEventListener('click', (e) => {
      if (e.target.matches('[data-next]') || e.target.closest('[data-next]')) {
        e.preventDefault();
        if (this.currentStep < this.totalSteps) {
          this.currentStep++;
          this.showStep(this.currentStep);
        }
      }
      if (e.target.matches('[data-prev]') || e.target.closest('[data-prev]')) {
        e.preventDefault();
        if (this.currentStep > 1) {
          this.currentStep--;
          this.showStep(this.currentStep);
        }
      }
    });
  },

  showStep(step) {
    document.querySelectorAll('[data-step]').forEach((el) => {
      el.style.display = parseInt(el.dataset.step) === step ? 'block' : 'none';
    });
    document.querySelectorAll('.step-indicator').forEach((el, i) => {
      el.classList.toggle('active', i + 1 === step);
      el.classList.toggle('done', i + 1 < step);
    });
  }
};

// ── CONFIRM DIALOG ─────────────────────────────────────
const Confirm = {
  init() {
    document.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-confirm]');
      if (!btn) return;
      e.preventDefault();
      const message = btn.dataset.confirm || 'Apakah kamu yakin?';
      if (window.confirm(message)) {
        const href = btn.href || btn.dataset.href;
        const form = btn.closest('form');
        if (form) form.submit();
        else if (href) window.location.href = href;
      }
    });
  }
};

// ── COUNTER ANIMATION ──────────────────────────────────
const CounterAnimation = {
  init() {
    const counters = document.querySelectorAll('[data-counter]');
    if (!counters.length) return;

    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          this.animate(entry.target);
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.5 });

    counters.forEach(c => observer.observe(c));
  },

  animate(el) {
    const target = parseInt(el.dataset.counter);
    const duration = 1500;
    const start = performance.now();

    const update = (time) => {
      const elapsed = time - start;
      const progress = Math.min(elapsed / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 3);
      el.textContent = Math.round(eased * target).toLocaleString('id-ID');
      if (progress < 1) requestAnimationFrame(update);
    };

    requestAnimationFrame(update);
  }
};

// ── NAVBAR SCROLL ──────────────────────────────────────
const NavScroll = {
  init() {
    const nav = document.querySelector('.public-nav');
    if (!nav) return;

    const onScroll = () => {
      nav.classList.toggle('scrolled', window.scrollY > 60);
    };

    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }
};

// ── PASSWORD STRENGTH ──────────────────────────────────
const PasswordStrength = {
  init() {
    const pwField = document.getElementById('password');
    const strengthEl = document.querySelector('.password-strength');
    if (!pwField || !strengthEl) return;

    const barsEl    = strengthEl.querySelector('.strength-bars');
    const labelEl   = strengthEl.querySelector('.strength-label');

    pwField.addEventListener('input', () => {
      const val = pwField.value;
      let score = 0;
      if (val.length >= 8) score++;
      if (/[A-Z]/.test(val)) score++;
      if (/[0-9]/.test(val)) score++;
      if (/[^A-Za-z0-9]/.test(val)) score++;

      barsEl.className = 'strength-bars';
      if (val.length === 0) {
        labelEl.textContent = '';
        return;
      }
      if (score <= 1) {
        barsEl.classList.add('strength-weak');
        labelEl.textContent = 'Lemah';
        labelEl.style.color = 'var(--clr-accent)';
      } else if (score <= 2) {
        barsEl.classList.add('strength-fair');
        labelEl.textContent = 'Sedang';
        labelEl.style.color = 'var(--clr-gold)';
      } else {
        barsEl.classList.add('strength-strong');
        labelEl.textContent = 'Kuat';
        labelEl.style.color = 'var(--clr-mint)';
      }
    });
  }
};

// ── PASSWORD TOGGLE ────────────────────────────────────
const PasswordToggle = {
  init() {
    document.querySelectorAll('[data-pw-toggle]').forEach(btn => {
      btn.addEventListener('click', () => {
        const targetId = btn.dataset.pwToggle;
        const field = document.getElementById(targetId);
        if (!field) return;

        if (field.type === 'password') {
          field.type = 'text';
          btn.innerHTML = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>`;
        } else {
          field.type = 'password';
          btn.innerHTML = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>`;
        }
      });
    });
  }
};

// ── INIT ALL ──────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  Sidebar.init();
  Toast.init();
  Modal.init();
  FormValidator.init();
  InterestSelector.init();
  PosterUpload.init();
  MultiStep.init();
  Confirm.init();
  CounterAnimation.init();
  NavScroll.init();
  PasswordStrength.init();
  PasswordToggle.init();

  const flashEl = document.getElementById('flash-message');
  if (flashEl) {
    Toast.show(flashEl.dataset.message, flashEl.dataset.type);
  }
});
