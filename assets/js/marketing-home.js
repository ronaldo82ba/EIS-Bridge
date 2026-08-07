/**
 * EIS Bridge marketing home — WebShoppe-parity motion (static)
 * Mirrors: framer-motion fadeUp, whileInView, header compact scroll, theme toggle
 */
(function () {
  'use strict';

  var root = document.documentElement;
  var header = document.querySelector('[data-site-header]');
  var prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* Theme */
  var stored = null;
  try {
    stored = localStorage.getItem('eis-theme');
  } catch (e) {}
  if (stored === 'dark' || stored === 'light') {
    root.setAttribute('data-theme', stored);
  } else {
    root.setAttribute('data-theme', 'light');
  }

  function syncThemeToggle() {
    var theme = root.getAttribute('data-theme') || 'light';
    document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
      var next = theme === 'dark' ? 'light' : 'dark';
      btn.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
      btn.setAttribute('aria-label', next === 'dark' ? 'Switch to dark look' : 'Switch to light look');
      var label = btn.querySelector('[data-theme-label]');
      if (label) label.textContent = theme === 'dark' ? 'Light' : 'Dark';
    });
  }

  syncThemeToggle();

  document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-theme', next);
      try {
        localStorage.setItem('eis-theme', next);
      } catch (e) {}
      syncThemeToggle();
    });
  });

  /* Header compact on scroll */
  function onScroll() {
    if (!header) return;
    header.classList.toggle('is-compact', window.scrollY > 24);
  }
  onScroll();
  window.addEventListener('scroll', onScroll, { passive: true });

  /* Mobile nav */
  var navToggle = document.querySelector('[data-nav-toggle]');
  var mobileNav = document.querySelector('[data-mobile-nav]');
  if (navToggle && mobileNav) {
    navToggle.addEventListener('click', function () {
      var open = mobileNav.hasAttribute('hidden');
      if (open) {
        mobileNav.removeAttribute('hidden');
        navToggle.setAttribute('aria-expanded', 'true');
      } else {
        mobileNav.setAttribute('hidden', '');
        navToggle.setAttribute('aria-expanded', 'false');
      }
    });
    mobileNav.querySelectorAll('a').forEach(function (a) {
      a.addEventListener('click', function () {
        mobileNav.setAttribute('hidden', '');
        navToggle.setAttribute('aria-expanded', 'false');
      });
    });
  }

  /* Scroll / load reveals (Framer Motion equivalent) */
  if (prefersReduced) {
    document.querySelectorAll('.reveal, .hero-reveal').forEach(function (el) {
      el.classList.add('is-in');
    });
    return;
  }

  document.querySelectorAll('.hero-reveal').forEach(function (el) {
    var delay = Number(el.getAttribute('data-delay') || 0);
    window.setTimeout(function () {
      el.classList.add('is-in');
    }, 80 + delay);
  });

  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (!entry.isIntersecting) return;
          var el = entry.target;
          var delay = Number(el.getAttribute('data-delay') || 0);
          window.setTimeout(function () {
            el.classList.add('is-in');
          }, delay);
          io.unobserve(el);
        });
      },
      { threshold: 0.18, rootMargin: '0px 0px -8% 0px' }
    );
    document.querySelectorAll('.reveal').forEach(function (el) {
      io.observe(el);
    });
  } else {
    document.querySelectorAll('.reveal').forEach(function (el) {
      el.classList.add('is-in');
    });
  }
})();
