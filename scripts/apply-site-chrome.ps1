# Apply dual-theme site chrome to all public EIS Bridge HTML pages
$ErrorActionPreference = 'Stop'
$root = 'C:\laragon\www\EIS Bridge'

function Get-HeadExtras([string]$prefix) {
  @(
    '  <link rel="preconnect" href="https://fonts.googleapis.com">'
    '  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
    '  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Syne:wght@600;700;800&display=swap" rel="stylesheet">'
    "  <link rel=`"stylesheet`" href=`"${prefix}styles/marketing-home.css`">"
    '  <script>'
    '    (function () {'
    '      try {'
    "        var t = localStorage.getItem('eis-theme');"
    "        if (t === 'dark' || t === 'light') document.documentElement.setAttribute('data-theme', t);"
    '      } catch (e) {}'
    '    })();'
    '  </script>'
  ) -join "`n"
}

function Get-Chrome([string]$prefix, [string]$current) {
  $homeA = if ($current -eq 'home') { ' aria-current="page"' } else { '' }
  $partnerA = if ($current -eq 'partner') { ' aria-current="page"' } else { '' }
  $insightsA = if ($current -eq 'insights') { ' aria-current="page"' } else { '' }
  $portalA = if ($current -eq 'portal') { ' aria-current="page"' } else { '' }
  $editionsHref = if ($prefix -eq '') { 'index.html#editions' } else { "${prefix}index.html#editions" }

  @"
  <header class="site-top" data-site-header>
    <div class="top-ribbon">
      <p>EIS Bridge&trade; — connect any POS to BIR EIS transmission without source-code changes</p>
    </div>
    <div class="wrap header-row">
      <a class="logo" href="${prefix}index.html" aria-label="EIS Bridge home">
        <span class="logo-mark">EIS</span> Bridge
      </a>
      <nav class="nav-desktop" aria-label="Primary">
        <a href="${prefix}index.html"$homeA>Home</a>
        <a href="${prefix}partner.html"$partnerA>Partner</a>
        <a href="$editionsHref">Editions</a>
        <a href="${prefix}insights/index.html"$insightsA>Insights</a>
        <a href="${prefix}portal/index.html"$portalA>Developers</a>
        <button type="button" class="theme-toggle" data-theme-toggle aria-pressed="false">
          <span aria-hidden="true">◐</span>
          <span data-theme-label>Dark</span>
        </button>
        <a class="btn btn-primary btn-header" href="${prefix}partner.html">Partner with us</a>
      </nav>
      <div class="header-actions">
        <button type="button" class="theme-toggle" data-theme-toggle aria-pressed="false" aria-label="Switch to dark look">
          <span aria-hidden="true">◐</span>
          <span data-theme-label>Dark</span>
        </button>
        <button type="button" class="nav-burger" data-nav-toggle aria-expanded="false" aria-controls="mobile-nav">Menu</button>
      </div>
    </div>
    <div id="mobile-nav" class="mobile-nav" data-mobile-nav hidden>
      <a href="${prefix}index.html">Home</a>
      <a href="${prefix}partner.html">Partner</a>
      <a href="$editionsHref">Editions</a>
      <a href="${prefix}insights/index.html">Insights</a>
      <a href="${prefix}portal/index.html">Developers</a>
      <a href="${prefix}certification-playbook.html">Certification playbook</a>
      <a class="btn btn-primary" href="${prefix}partner.html">Partner with us</a>
    </div>
  </header>
"@
}

function Patch-Page([string]$relPath, [string]$prefix, [string]$current) {
  $path = Join-Path $root $relPath
  $html = [IO.File]::ReadAllText($path)

  # data-theme on <html>
  if ($html -notmatch 'data-theme=') {
    if ($html -match '<html lang="en">') {
      $html = $html.Replace('<html lang="en">', '<html lang="en" data-theme="light">')
    } else {
      $html = [regex]::Replace($html, '<html([^>]*)>', '<html$1 data-theme="light">', 1)
    }
  }

  # body class has-site-chrome
  if ($html -notmatch 'has-site-chrome') {
    if ($html -match '<body class="([^"]*)">') {
      $html = [regex]::Replace($html, '<body class="([^"]*)">', '<body class="$1 has-site-chrome">', 1)
    } elseif ($html -match '<body>') {
      $html = $html.Replace('<body>', '<body class="has-site-chrome">')
    } else {
      $html = [regex]::Replace($html, '<body([^>]*)>', '<body class="has-site-chrome"$1>', 1)
    }
  }

  # Head extras
  if ($html -notmatch 'marketing-home\.css') {
    $extras = Get-HeadExtras $prefix
    $html = $html.Replace('</head>', "$extras`n</head>")
  }

  # Header chrome
  $chrome = Get-Chrome $prefix $current
  if ($html -match 'data-site-header') {
    # already has chrome
  } elseif ($html -match '(?s)<header class="site-header.*?</header>') {
    $html = [regex]::Replace($html, '(?s)<header class="site-header.*?</header>', $chrome.Trim(), 1)
  } else {
    # insert after skip-link
    $html = [regex]::Replace($html, '(<a class="skip-link"[^>]*>.*?</a>)', "`$1`n`n$chrome", 1)
  }

  # Script
  $scriptTag = "<script src=`"${prefix}assets/js/marketing-home.js`" defer></script>"
  if ($html -notmatch 'marketing-home\.js') {
    $html = $html.Replace('</body>', "  $scriptTag`n</body>")
  }

  # Inner heroes
  $html = $html.Replace('<section class="hero"', '<section class="hero hero--page"')
  $html = $html.Replace('<section class="hero hero--page" aria-labelledby="hero-brand">', '<section class="hero" aria-labelledby="hero-brand">') # safety for home if ever run

  [IO.File]::WriteAllText($path, $html)
  Write-Host "Patched $relPath"
}

$pages = @(
  @{ Path = 'partner.html'; Prefix = ''; Current = 'partner' },
  @{ Path = 'privacy.html'; Prefix = ''; Current = 'home' },
  @{ Path = 'terms.html'; Prefix = ''; Current = 'home' },
  @{ Path = 'certification-playbook.html'; Prefix = ''; Current = 'home' },
  @{ Path = 'insights\index.html'; Prefix = '../'; Current = 'insights' },
  @{ Path = 'insights\bir-eis-readiness-retail-chains.html'; Prefix = '../'; Current = 'insights' },
  @{ Path = 'insights\philippine-convenience-store-business-june-2026.html'; Prefix = '../'; Current = 'insights' },
  @{ Path = 'insights\sari-sari-to-modern-retail-upgrade.html'; Prefix = '../'; Current = 'insights' },
  @{ Path = 'portal\index.html'; Prefix = '../'; Current = 'portal' },
  @{ Path = 'portal\quickstart.html'; Prefix = '../'; Current = 'portal' },
  @{ Path = 'portal\api.html'; Prefix = '../'; Current = 'portal' },
  @{ Path = 'portal\data-model.html'; Prefix = '../'; Current = 'portal' },
  @{ Path = 'portal\tools.html'; Prefix = '../'; Current = 'portal' },
  @{ Path = 'portal\testing.html'; Prefix = '../'; Current = 'portal' },
  @{ Path = 'portal\support.html'; Prefix = '../'; Current = 'portal' }
)

foreach ($p in $pages) {
  Patch-Page $p.Path $p.Prefix $p.Current
}

Write-Host 'Done'
