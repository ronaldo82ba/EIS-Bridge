#!/usr/bin/env node
/**
 * Generate EIS Bridge Insights HTML for PH Findings · 60-Day Series.
 * Source: scripts/data/ph-findings-60.json
 * Output: insights/ph-findings/index.html + only day pages through --through=YYYY-MM-DD
 *
 * Public series hub is live; ship one day at a time. Default --through is today (Asia/Manila).
 * Example (Day 01 only): node scripts/generate-ph-findings-insights.mjs --through=2026-08-09
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, '..');
const DATA = path.join(ROOT, 'scripts/data/ph-findings-60.json');
const OUT = path.join(ROOT, 'insights/ph-findings');

const SERIES = 'PH Findings · 60-Day Series';
const DISCLAIMER =
  'Figures are from published PSA / PIDS / DepEd / CHED / TESDA / DSWD / DTI / NEDA releases as compiled for this series (updated 2026-08-09). Survey estimates have sampling error. Household income ≠ individual salary. Not legal, tax, or investment advice. EIS Bridge is not affiliated with or accredited by the BIR. BIR certifies taxpayer systems, not software providers. Tax compliance remains the taxpayer’s responsibility.';

function manilaToday() {
  return new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Asia/Manila',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).format(new Date());
}

function parseThroughArg(argv) {
  const flag = argv.find((a) => a.startsWith('--through='));
  if (flag) return flag.slice('--through='.length);
  if (process.env.PH_FINDINGS_THROUGH) return process.env.PH_FINDINGS_THROUGH;
  return manilaToday();
}

function esc(s) {
  return String(s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function inlineMd(text) {
  let t = esc(text);
  t = t.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
  t = t.replace(/`([^`]+)`/g, '<code>$1</code>');
  return t;
}

function bodyToHtml(body) {
  const blocks = String(body).replace(/\r\n/g, '\n').trim().split(/\n\n+/);
  const html = [];
  for (const block of blocks) {
    const lines = block.split('\n').map((l) => l.trimEnd());
    const isList = lines.every((l) => /^[-*]\s+/.test(l.trim()) || l.trim() === '');
    if (isList) {
      html.push('<ul>');
      for (const line of lines) {
        const m = line.trim().match(/^[-*]\s+(.*)$/);
        if (m) html.push(`        <li>${inlineMd(m[1])}</li>`);
      }
      html.push('      </ul>');
    } else {
      const joined = lines.join(' ').replace(/\s+/g, ' ').trim();
      if (joined) html.push(`      <p>${inlineMd(joined)}</p>`);
    }
  }
  return html.join('\n');
}

function pad(n) {
  return String(n).padStart(2, '0');
}

function formatDate(iso) {
  const d = new Date(`${iso}T12:00:00+08:00`);
  return d.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    timeZone: 'Asia/Manila',
  });
}

function siteChrome({ title, description, canonical, ogType, breadcrumbHtml, mainHtml, assetPrefix }) {
  const p = assetPrefix;
  return `<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="${esc(description)}">
  <title>${esc(title)}</title>
  <link rel="icon" href="${p}assets/brand/favicon.svg" type="image/svg+xml">
  <link rel="icon" href="${p}assets/brand/favicon-32.png" sizes="32x32" type="image/png">
  <link rel="apple-touch-icon" href="${p}assets/brand/apple-touch-icon.png">
  <meta name="theme-color" content="#0057D9">
  <link rel="canonical" href="${esc(canonical)}">
  <meta property="og:type" content="${esc(ogType)}">
  <meta property="og:site_name" content="EIS Bridge">
  <meta property="og:title" content="${esc(title)}">
  <meta property="og:description" content="${esc(description)}">
  <meta property="og:image" content="https://eisbridge.com/assets/brand/og-image.png">
  <meta property="og:url" content="${esc(canonical)}">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="${esc(title)}">
  <meta name="twitter:description" content="${esc(description)}">
  <meta name="twitter:image" content="https://eisbridge.com/assets/brand/og-image.png">
  <link rel="stylesheet" href="${p}styles/theme.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Syne:wght@600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="${p}styles/marketing-home.css">
  <script>
    (function () {
      try {
        var t = localStorage.getItem('eis-theme');
        if (t === 'dark' || t === 'light') document.documentElement.setAttribute('data-theme', t);
      } catch (e) {}
    })();
  </script>
</head>
<body class="has-site-chrome">
  <a class="skip-link" href="#main">Skip to main content</a>

  <header class="site-top" data-site-header>
    <div class="top-ribbon">
      <p>EIS Bridge&trade; &mdash; connect any POS to BIR EIS transmission without source-code changes</p>
    </div>
    <div class="wrap header-row">
      <a class="logo" href="${p}index.html" aria-label="EIS Bridge home">
        <span class="logo-mark">EIS</span> Bridge
      </a>
      <nav class="nav-desktop" aria-label="Primary">
        <a href="${p}index.html">Home</a>
        <a href="${p}partner.html">Partner</a>
        <a href="${p}index.html#editions">Editions</a>
        <a href="${p}insights/index.html" aria-current="page">Insights</a>
        <a href="${p}portal/index.html">Developers</a>
        <button type="button" class="theme-toggle" data-theme-toggle aria-pressed="false">
          <span aria-hidden="true">&#9678;</span>
          <span data-theme-label>Dark</span>
        </button>
        <a class="btn btn-primary btn-header" href="${p}partner.html">Partner with us</a>
      </nav>
      <div class="header-actions">
        <button type="button" class="theme-toggle" data-theme-toggle aria-pressed="false" aria-label="Switch to dark look">
          <span aria-hidden="true">&#9678;</span>
          <span data-theme-label>Dark</span>
        </button>
        <button type="button" class="nav-burger" data-nav-toggle aria-expanded="false" aria-controls="mobile-nav">Menu</button>
      </div>
    </div>
    <div id="mobile-nav" class="mobile-nav" data-mobile-nav hidden>
      <a href="${p}index.html">Home</a>
      <a href="${p}partner.html">Partner</a>
      <a href="${p}index.html#editions">Editions</a>
      <a href="${p}insights/index.html">Insights</a>
      <a href="${p}portal/index.html">Developers</a>
      <a href="${p}certification-playbook.html">Certification playbook</a>
      <a class="btn btn-primary" href="${p}partner.html">Partner with us</a>
    </div>
  </header>

  <main id="main">
${breadcrumbHtml}
${mainHtml}
  </main>

  <footer class="site-footer">
    <div class="container footer-inner">
      <p class="footer-positioning">
        EIS Bridge connects any POS, ERP, or merchant to the BIR Electronic Invoicing System — without POS source-code changes. Merchants complete EIS CERT and Permit to Transmit (PTT) per BIR; the bridge handles mapping, signing, and transmission.
      </p>
      <p class="footer-disclaimer">
        EIS Bridge is not affiliated with or accredited by the BIR. BIR certifies taxpayer systems, not software providers. Tax compliance remains the taxpayer's responsibility.
      </p>
      <p class="footer-ip">EIS Bridge&trade; &mdash; trademark application pending, IPOPHL Class 42, Ref EFPH202600003850268</p>
      <p class="footer-links">
        <a href="${p}index.html">Home</a> &middot;
        <a href="${p}partner.html">Partner Program</a> &middot;
        <a href="${p}insights/index.html">Insights</a> &middot;
        <a href="${p}privacy.html">Privacy Policy</a> &middot;
        <a href="${p}terms.html">Terms of Service</a>
      </p>
      <p class="footer-copy">&copy; 2026 EIS Bridge</p>
    </div>
  </footer>
  <script src="${p}assets/js/marketing-home.js" defer></script>
</body>
</html>
`;
}

function articlePage(article, published) {
  const n = article.day;
  const file = `day-${pad(n)}.html`;
  const idx = published.findIndex((a) => a.day === n);
  const prev = idx > 0 ? published[idx - 1] : null;
  const next = idx >= 0 && idx < published.length - 1 ? published[idx + 1] : null;
  const tags = (article.tags || []).join(', ');
  const description = `${article.key_stat} — ${SERIES} Day ${n} on EIS Bridge Insights.`;
  const canonical = `https://eisbridge.com/insights/ph-findings/${file}`;

  const navBits = [];
  if (prev) {
    navBits.push(
      `<a class="btn btn-secondary" href="day-${pad(prev.day)}.html">← Day ${pad(prev.day)}</a>`
    );
  }
  navBits.push(`<a class="btn btn-secondary" href="index.html">Series index</a>`);
  if (next) {
    navBits.push(
      `<a class="btn btn-secondary" href="day-${pad(next.day)}.html">Day ${pad(next.day)} →</a>`
    );
  }

  const breadcrumbHtml = `    <header class="article-hero">
      <div class="container">
        <p class="breadcrumb"><a href="../index.html">Insights</a> &rsaquo; <a href="index.html">${esc(SERIES)}</a> &rsaquo; Day ${pad(n)}</p>
        <h1>${esc(article.title)}</h1>
        <p class="article-meta">${esc(formatDate(article.date))} &middot; Day ${pad(n)} of 60 &middot; ${esc(SERIES)}${tags ? ` &middot; ${esc(tags)}` : ''}</p>
      </div>
    </header>`;

  const mainHtml = `    <article class="container article-content">
      <div class="article-summary">
        <p><strong>Key stat:</strong> ${inlineMd(article.key_stat)}</p>
      </div>

${bodyToHtml(article.body)}

      <h2>Sources</h2>
      <p>${inlineMd(article.sources)}</p>

      <div class="article-cta-inline">
        <p><strong>Disclaimer:</strong> ${esc(DISCLAIMER)}</p>
      </div>

      <div class="article-cta-inline">
        <h3>Retail compliance next?</h3>
        <p>When Philippine stores formalize and scale, BIR EIS transmission becomes part of the operating stack. EIS Bridge connects existing POS systems without source-code changes.</p>
        <a class="btn btn-primary" href="../../certification-playbook.html">Certification playbook</a>
        <a class="btn btn-secondary" href="mailto:support@eisbridge.com?subject=PH%20Findings%20Series%20Inquiry" style="margin-left: 0.75rem;">Contact support</a>
      </div>

      <p class="article-meta" style="margin-top: 2rem;">${navBits.join(' ')}</p>
    </article>

    <section class="cta-band" aria-labelledby="cta-heading">
      <div class="container cta-inner">
        <h2 id="cta-heading">Need BIR EIS compliance for your store?</h2>
        <p>EIS Bridge connects your POS to the BIR Electronic Invoicing System — without source-code changes. Merchants complete EIS CERT and PTT per BIR; we handle mapping, signing, and transmission.</p>
        <a class="btn btn-primary" href="mailto:support@eisbridge.com?subject=Merchant%20EIS%20Compliance%20Inquiry">Contact support</a>
        <a class="btn btn-secondary" href="index.html">More PH Findings</a>
      </div>
    </section>`;

  return siteChrome({
    title: `${article.title} — Day ${pad(n)} — EIS Bridge Insights`,
    description,
    canonical,
    ogType: 'article',
    breadcrumbHtml,
    mainHtml,
    assetPrefix: '../../',
  });
}

function hubPage(published, throughDate, totalCount) {
  const remaining = totalCount - published.length;
  const cards = published
    .map((a) => {
      const file = `day-${pad(a.day)}.html`;
      return `          <a class="insight-card" href="${file}">
            <p class="insight-meta">Day ${pad(a.day)} &middot; ${esc(formatDate(a.date))}</p>
            <h3>${esc(a.title)}</h3>
            <p>${esc(a.key_stat)}</p>
          </a>`;
    })
    .join('\n');

  const upcomingNote =
    remaining > 0
      ? `<p class="section-lead">Published through ${esc(formatDate(throughDate))} (${published.length} of ${totalCount}). Later days are not live until released one at a time — ${remaining} remain in the pack.</p>`
      : `<p class="section-lead">All ${totalCount} readings are published.</p>`;

  const breadcrumbHtml = `    <section class="hero hero--page" aria-labelledby="hero-heading">
      <div class="container hero-inner">
        <p class="breadcrumb"><a href="../index.html">Insights</a> &rsaquo; ${esc(SERIES)}</p>
        <h1 id="hero-heading">${esc(SERIES)}</h1>
        <p class="hero-subheader">
          Sixty short, public-data readings on Philippine demography, income, labor, regions, and market behaviour — posted one day at a time for store owners, operators, and builders who want numbers over vibes.
        </p>
      </div>
    </section>`;

  const mainHtml = `    <section class="audiences" aria-labelledby="series-heading">
      <div class="container">
        <h2 id="series-heading" class="section-title">Published so far</h2>
        ${upcomingNote}
        <div class="insights-grid">
${cards}
        </div>
      </div>
    </section>

    <section class="cta-band" aria-labelledby="cta-heading">
      <div class="container cta-inner">
        <h2 id="cta-heading">From market reading to EIS readiness</h2>
        <p>As stores grow past informal operations, certified POS and BIR EIS transmission enter the picture. EIS Bridge keeps your existing POS — and handles mapping, signing, and async submission.</p>
        <a class="btn btn-primary" href="../../certification-playbook.html">Certification playbook</a>
        <a class="btn btn-secondary" href="../index.html">Back to Insights</a>
      </div>
    </section>`;

  return siteChrome({
    title: `${SERIES} — EIS Bridge Insights`,
    description:
      'Daily Philippine public-data findings on population, income, labor, and market behaviour — EIS Bridge Insights series.',
    canonical: 'https://eisbridge.com/insights/ph-findings/',
    ogType: 'website',
    breadcrumbHtml,
    mainHtml,
    assetPrefix: '../../',
  });
}

function main() {
  const throughDate = parseThroughArg(process.argv.slice(2));
  if (!/^\d{4}-\d{2}-\d{2}$/.test(throughDate)) {
    throw new Error(`Invalid --through date: ${throughDate}`);
  }

  const articles = JSON.parse(fs.readFileSync(DATA, 'utf8'));
  if (!Array.isArray(articles) || articles.length !== 60) {
    throw new Error(`Expected 60 articles, got ${articles?.length}`);
  }
  for (let i = 0; i < 60; i++) {
    if (articles[i].day !== i + 1) {
      throw new Error(`Day mismatch at index ${i}: ${articles[i].day}`);
    }
  }

  const published = articles.filter((a) => a.date <= throughDate);
  if (published.length === 0) {
    throw new Error(`No articles on or before ${throughDate}`);
  }

  fs.mkdirSync(OUT, { recursive: true });

  // Remove previously generated day pages that are not yet published.
  for (const name of fs.readdirSync(OUT)) {
    const m = name.match(/^day-(\d{2})\.html$/);
    if (!m) continue;
    const dayNum = Number(m[1]);
    if (!published.some((a) => a.day === dayNum)) {
      fs.unlinkSync(path.join(OUT, name));
    }
  }

  fs.writeFileSync(
    path.join(OUT, 'index.html'),
    hubPage(published, throughDate, articles.length),
    'utf8'
  );
  for (const article of published) {
    const name = `day-${pad(article.day)}.html`;
    fs.writeFileSync(path.join(OUT, name), articlePage(article, published), 'utf8');
  }
  console.log(
    `Published through ${throughDate}: wrote index.html + ${published.length} day page(s) (day-${pad(published[0].day)} … day-${pad(published[published.length - 1].day)})`
  );
}

main();
