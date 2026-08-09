/**
 * EIS Bridge site chrome — WebShoppe-parity motion + dual theme (site-wide)
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
  } else if (!root.getAttribute('data-theme')) {
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
    try {
      var meta = document.querySelector('meta[name="theme-color"]');
      if (meta) meta.setAttribute('content', theme === 'dark' ? '#070b12' : '#0057D9');
    } catch (e2) {}
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

  /* Auto-mark content for motion (all pages, not only homepage) */
  function autoMarkReveals() {
    var selectors = [
      'main .hero-inner > *',
      'main .portal-hero .container > *',
      'main .section-title',
      'main .section-lead',
      'main .audience-card',
      'main .edition-card',
      'main .pillar-card',
      'main .insight-card',
      'main .article-hero > *',
      'main .article-content > h2',
      'main .article-content > h3',
      'main .article-content > p',
      'main .article-summary',
      /* Mark section children — never a whole tall content-section (breaks API docs) */
      'main .content-section > h1',
      'main .content-section > h2',
      'main .content-section > h3',
      'main .content-section > p',
      'main .content-section > .callout',
      'main .content-section > .checklist-section',
      'main .content-section > .portal-card',
      'main .content-section > .endpoint-block',
      'main .content-section > .card-grid > *',
      'main .page-title',
      'main .page-intro',
      'main .cta-band .cta-inner > *',
      'main .cta-inner > *',
      'body.has-site-chrome main > section > .container > h1',
      'body.has-site-chrome main > section > .container > h2',
      'body.has-site-chrome main > section > .container > p',
      'body.has-site-chrome main.container > h1',
      'body.has-site-chrome main.container > h2',
      'body.has-site-chrome main.container > p'
    ];
    var nodes = document.querySelectorAll(selectors.join(','));
    var i = 0;
    var maxH = Math.max(window.innerHeight * 1.15, 900);
    nodes.forEach(function (el) {
      if (el.classList.contains('reveal') || el.classList.contains('hero-reveal')) return;
      if (el.closest('[data-site-header], .site-footer, .top-ribbon, .portal-subnav')) return;
      /* Tall blocks stay visible — opacity:0 + high IO threshold caused blank gaps */
      if (el.scrollHeight > maxH) return;
      el.classList.add('reveal');
      if (!el.getAttribute('data-delay')) {
        el.setAttribute('data-delay', String(Math.min((i % 6) * 45, 200)));
      }
      i += 1;
    });
  }

  autoMarkReveals();

  /* Scroll / load reveals */
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
      /* threshold 0: any pixel visible — tall docs never hit 0.12 of their height */
      { threshold: 0, rootMargin: '0px 0px -8% 0px' }
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
