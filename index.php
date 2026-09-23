<?php
// ============================================================
// Interim landing page for /lg/.
//
// This exists only so https://sthaniya.gov.np/lg/ has something
// to show instead of a 403/404 - it's deliberately simple. When
// the comprehensive dashboard is built, this file is what gets
// replaced.
// ============================================================

require_once __DIR__ . '/staff_common.php';

$modules = require __DIR__ . '/modules_config.php';
$liveModules = array_values(array_filter($modules, function ($m) {
    return !empty($m['file']);
}));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LG Directory - Gandaki Province</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Noto+Sans+Devanagari:wght@400;500;600&display=swap" rel="stylesheet">
<link href="assets/site.css" rel="stylesheet">
<style>
.landing-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: 14px;
  margin-top: 1.2rem;
}
.landing-card {
  background: var(--white); border: 1px solid var(--border); border-radius: 10px;
  padding: 18px; text-decoration: none; color: var(--text);
  transition: box-shadow .2s, transform .2s;
}
.landing-card:hover { box-shadow: 0 6px 18px rgba(30,58,95,.1); transform: translateY(-2px); }
.landing-card .lc-title { font-size: 14px; font-weight: 600; color: var(--navy); margin-bottom: 3px; }
.landing-card .lc-title-np { font-size: 12px; color: var(--navy-mid); font-family: 'Noto Sans Devanagari', sans-serif; margin-bottom: 8px; }
.landing-card .lc-desc { font-size: 12px; color: var(--text-muted); line-height: 1.5; }
.landing-note {
  margin-top: 1.5rem; font-size: 12px; color: var(--text-muted);
  border-top: 1px solid var(--border); padding-top: 1rem;
}
</style>
</head>
<body>

<div class="gov-bar">
  <strong>Government of Nepal</strong> &nbsp;·&nbsp; Local Governments Nationwide &nbsp;·&nbsp; Office of Chief Minister and Council of Ministers, Gandaki Province &nbsp;·&nbsp; PLGSP
</div>

<header>
  <div class="header-inner">
    <div class="header-logo"><span>ने</span></div>
    <div class="header-text">
      <div class="header-eyebrow">Nepal · Local Governments</div>
      <div class="header-title">LG Directory</div>
      <div class="header-sub">स्थानीय तह डाइरेक्टरी &nbsp;·&nbsp; Data and tools covering all 753 Local Governments</div>
    </div>
  </div>
</header>

<div class="wrap">
  <p style="font-size:12.5px; color:var(--text-muted);">Pick a directory below. A unified dashboard bringing these together is in development.</p>

  <div class="landing-grid">
    <?php foreach ($liveModules as $m): ?>
      <a class="landing-card" href="<?= htmlspecialchars($m['file']) ?>">
        <div class="lc-title"><?= htmlspecialchars($m['label']) ?></div>
        <?php if (!empty($m['label_np'])): ?><div class="lc-title-np"><?= htmlspecialchars($m['label_np']) ?></div><?php endif; ?>
        <div class="lc-desc"><?= htmlspecialchars($m['desc']) ?></div>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="landing-note">
    More modules (news &amp; notices, documents, wards, gallery, services, and others) are planned — see the
    Modules menu on any page above for what's coming next.
  </div>
</div>

<footer>
  <strong>LG Directory</strong> &nbsp;·&nbsp; Nepal &nbsp;·&nbsp; Office of Chief Minister and Council of Ministers, Gandaki Province &nbsp;·&nbsp; PLGSP
</footer>

</body>
</html>