<?php
// ============================================================
// Data Refresh Console (admin-only)
//
// The only page that can trigger a nationwide fetch. Kept
// separate from the public viewer pages so an ordinary visitor
// can never cause all ~753 LG sites to be hit at once - by
// accident, repeated clicking, or on purpose.
//
// Works for any module listed in modules_config.php that has a
// 'fetch_endpoint' set - add one there and it shows up here
// automatically, no changes needed in this file.
//
// Renders a lightweight scrolling log (one line per LG) instead
// of a full data grid, so it stays responsive even while a
// nationwide fetch is in flight.
// ============================================================

require_once __DIR__ . '/staff_common.php';

session_start();

$adminConfig = require __DIR__ . '/admin_config.php';
$loginError = '';

if (isset($_POST['password'])) {
    if (hash_equals((string) $adminConfig['admin_password'], (string) $_POST['password'])) {
        $_SESSION['lg_admin_authed'] = true;
    } else {
        $loginError = 'Incorrect password.';
    }
}

if (isset($_GET['logout'])) {
    unset($_SESSION['lg_admin_authed']);
}

$authed = !empty($_SESSION['lg_admin_authed']);

if (!$authed) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login - LG Data Refresh Console</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
        <link href="assets/site.css" rel="stylesheet">
    </head>
    <body>
    <div class="gov-bar"><strong>Government of Nepal</strong> &nbsp;·&nbsp; Admin Access Only</div>
    <div class="login-wrap">
        <h2>Data Refresh Console</h2>
        <?php if ($loginError): ?><div class="login-error"><?= htmlspecialchars($loginError) ?></div><?php endif; ?>
        <form method="post">
            <input type="password" name="password" placeholder="Admin password" autofocus required>
            <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center;">Sign in</button>
        </form>
    </div>
    </body>
    </html>
    <?php
    exit;
}

// ------------------------------------------------------------
// Authenticated - gather stats for every fetchable module
// ------------------------------------------------------------
$allModules = require __DIR__ . '/modules_config.php';
$fetchableModules = array_values(array_filter($allModules, function ($m) {
    return !empty($m['fetch_endpoint']) && !empty($m['cache_file']);
}));

$lgList = load_lg_list();
$totalLgs = count($lgList);

$moduleStats = [];
foreach ($fetchableModules as $m) {
    $cache = load_cache(__DIR__ . '/' . $m['cache_file']);
    $kind = $m['kind'] ?? 'person_directory';
    $ok = 0;
    $fail = 0;
    foreach ($cache['by_lg'] as $entry) {
        $entryStatus = $entry['status'] ?? '';
        if ($kind === 'website_status') {
            if ($entryStatus === 'ok') {
                $ok++;
            } else {
                $fail++; // covers both 'down' and 'error'
            }
        } else {
            if ($entryStatus === 'failed') {
                $fail++;
            } else {
                $ok++;
            }
        }
    }
    $moduleStats[$m['key']] = [
        'key'            => $m['key'],
        'label'          => $m['label'],
        'kind'           => $kind,
        'fetch_endpoint' => $m['fetch_endpoint'],
        'view_page'      => $m['file'],
        'ok'             => $ok,
        'fail'           => $fail,
        'generated_at'   => $cache['generated_at'],
    ];
}

if (empty($moduleStats)) {
    exit('No fetchable modules configured in modules_config.php (need a fetch_endpoint + cache_file).');
}

$moduleStatsJson = json_encode($moduleStats, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Data Refresh Console - LG Directory</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Noto+Sans+Devanagari:wght@400;500;600&display=swap" rel="stylesheet">
<link href="assets/site.css" rel="stylesheet">
</head>
<body>

<div class="gov-bar">
  <strong>Government of Nepal</strong> &nbsp;·&nbsp; Admin Only &nbsp;·&nbsp; Data Refresh Console
</div>

<header>
  <div class="header-inner">
    <div class="header-logo"><span>ने</span></div>
    <div class="header-text">
      <div class="header-eyebrow">Admin</div>
      <div class="header-title">Data Refresh Console</div>
      <div class="header-sub">Fetches data from all <?= $totalLgs ?> Local Governments and updates the module's cache file</div>
    </div>
    <a href="?logout=1" class="btn-reset" style="text-decoration:none; margin-left:auto;">Log out</a>
  </div>
</header>

<div class="wrap" style="max-width:900px;">

  <div class="toolbar-inner" style="padding:0 0 14px; background:none; border:none;">
    <select id="moduleSelect" class="filter-select" style="max-width:260px; font-size:13px; padding:9px 12px;">
      <?php foreach ($moduleStats as $m): ?>
        <option value="<?= htmlspecialchars($m['key']) ?>"><?= htmlspecialchars($m['label']) ?></option>
      <?php endforeach; ?>
    </select>
    <label id="checkLegacyWrap" style="display:none; align-items:center; gap:6px; font-size:12px; color: var(--text-muted); margin-left:6px;">
      <input type="checkbox" id="checkLegacy" checked>
      Also check old.&lt;domain&gt; legacy copies (roughly doubles concurrency per batch, similar total time)
    </label>
    <a id="viewLink" href="#" class="btn-reset" style="text-decoration:none;">View this directory &rarr;</a>
  </div>

  <div class="console-warn">
    <strong>Heads up:</strong> "Full Refresh" contacts all <?= $totalLgs ?> Local Government websites for the
    selected module. It runs in batches of 40 at a time and can take several minutes. Avoid running it more than
    a few times a day - use "Retry Failed" for routine cleanup instead of a full re-run.
  </div>

  <div class="status-bar" style="padding:0 0 14px;">
    <span class="stat-pill"><span class="stat-dot ok"></span><span id="okLabel">LGs with data</span>: <b id="statOk">0</b></span>
    <span class="status-sep"></span>
    <span class="stat-pill"><span class="stat-dot fail"></span><span id="failLabel">Failed</span>: <b id="statFail">0</b></span>
    <span class="status-sep"></span>
    <span class="stat-pill" id="lastUpdated">Never run yet</span>
    <div class="status-actions">
      <button id="btnRetryFailed" class="btn btn-outline-danger">
        Retry <span id="retryLabel">Failed</span> (<span id="failCountLabel">0</span>)
      </button>
      <button id="btnRefreshAll" class="btn btn-primary">
        Start Full Refresh (<?= $totalLgs ?> LGs)
      </button>
    </div>
  </div>

  <div id="progressWrap">
    <div class="progress-meta">
      <span id="progressLabel">Idle.</span>
      <span id="progressCount">0 / 0</span>
    </div>
    <div class="progress-track"><div class="progress-bar-fill" id="progressBar"></div></div>
  </div>

  <div class="log-panel" id="logPanel"></div>

</div>

<script>
const MODULE_STATS = <?= $moduleStatsJson ?>;

const els = {
    moduleSelect: document.getElementById('moduleSelect'),
    checkLegacyWrap: document.getElementById('checkLegacyWrap'),
    checkLegacy: document.getElementById('checkLegacy'),
    viewLink: document.getElementById('viewLink'),
    btnRefreshAll: document.getElementById('btnRefreshAll'),
    btnRetryFailed: document.getElementById('btnRetryFailed'),
    failCountLabel: document.getElementById('failCountLabel'),
    okLabel: document.getElementById('okLabel'),
    failLabel: document.getElementById('failLabel'),
    retryLabel: document.getElementById('retryLabel'),
    statOk: document.getElementById('statOk'),
    statFail: document.getElementById('statFail'),
    lastUpdated: document.getElementById('lastUpdated'),
    progressWrap: document.getElementById('progressWrap'),
    progressBar: document.getElementById('progressBar'),
    progressLabel: document.getElementById('progressLabel'),
    progressCount: document.getElementById('progressCount'),
    logPanel: document.getElementById('logPanel'),
};

let activeSource = null;
let currentModuleKey = els.moduleSelect.value;

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
}

function loadModuleUi(key) {
    const m = MODULE_STATS[key];
    const isWebsite = m.kind === 'website_status';
    els.okLabel.textContent = isWebsite ? 'Up' : 'LGs with data';
    els.failLabel.textContent = isWebsite ? 'Down / Unreachable' : 'Failed';
    els.retryLabel.textContent = isWebsite ? 'Down' : 'Failed';
    els.checkLegacyWrap.style.display = isWebsite ? 'flex' : 'none';
    els.viewLink.href = m.view_page || '#';
    els.statOk.textContent = m.ok;
    els.statFail.textContent = m.fail;
    els.failCountLabel.textContent = m.fail;
    els.lastUpdated.textContent = m.generated_at ? `Last run: ${m.generated_at}` : 'Never run yet';
    els.btnRetryFailed.disabled = m.fail === 0;
    els.logPanel.innerHTML = '';
    els.progressWrap.classList.remove('active');
    els.progressLabel.textContent = 'Idle.';
    els.progressBar.style.width = '0%';
    els.progressCount.textContent = '0 / 0';
}

function appendLog(entry, kind) {
    // Cheap, single-row append - never rebuilds the whole log, so
    // this stays fast even across a full 753-LG run.
    const row = document.createElement('div');
    let rowClass, icon, detail;

    if (kind === 'website_status') {
        // Reuse the ok/failed/stale color classes for the 3-way
        // up/down/error status this module reports.
        rowClass = entry.status === 'ok' ? 'ok' : (entry.status === 'down' ? 'failed' : 'stale');
        icon = entry.status === 'ok' ? '\u2713' : (entry.status === 'down' ? '\u2717' : '\u26a0');
        const ms = entry.response_ms !== null && entry.response_ms !== undefined ? `${entry.response_ms}ms` : '—';
        detail = `HTTP ${entry.http_code || '—'} &middot; ${ms} &middot; ${escapeHtml(entry.server_ip || 'no IP')}`;
    } else {
        rowClass = entry.status;
        icon = entry.status === 'ok' ? '\u2713' : (entry.status === 'stale' ? '\u26a0' : '\u2717');
        const recordCount = entry.staff ? entry.staff.length : 0;
        detail = entry.status === 'failed'
            ? escapeHtml(entry.error || 'failed')
            : `${recordCount} record${recordCount === 1 ? '' : 's'}`;
    }

    row.className = `log-row ${rowClass}`;
    row.innerHTML = `<span class="log-icon">${icon}</span><span class="log-lg">${escapeHtml(entry.lg_name || entry.lgid)} &middot; ${escapeHtml(entry.district || '')}</span><span class="log-detail">${detail}</span>`;
    els.logPanel.appendChild(row);
    els.logPanel.scrollTop = els.logPanel.scrollHeight;
}

function startFetch(mode) {
    if (activeSource) return;
    const m = MODULE_STATS[currentModuleKey];
    if (mode === 'full' && !confirm(`This will contact all Local Government websites for "${m.label}". Continue?`)) return;

    const params = new URLSearchParams();
    if (mode === 'retry_failed') params.set('mode', 'retry_failed');
    if (m.kind === 'website_status') params.set('check_legacy', els.checkLegacy.checked ? '1' : '0');
    const qs = params.toString();
    const url = qs ? `${m.fetch_endpoint}?${qs}` : m.fetch_endpoint;
    els.logPanel.innerHTML = '';
    els.progressWrap.classList.add('active');
    els.progressLabel.textContent = mode === 'retry_failed' ? 'Retrying failed LGs...' : 'Fetching all LGs...';
    els.progressBar.style.width = '0%';
    els.progressCount.textContent = '0 / 0';
    els.btnRefreshAll.disabled = true;
    els.btnRetryFailed.disabled = true;
    els.moduleSelect.disabled = true;

    let done = 0, total = 0;
    const es = new EventSource(url);
    activeSource = es;

    es.addEventListener('meta', e => {
        total = JSON.parse(e.data).total;
        els.progressCount.textContent = `0 / ${total}`;
    });

    es.addEventListener('chunk_progress', e => {
        const data = JSON.parse(e.data);
        const base = mode === 'retry_failed' ? 'Retrying failed LGs' : 'Fetching all LGs';
        els.progressLabel.textContent = `${base}... (batch ${data.chunk} / ${data.of})`;
    });

    es.addEventListener('retry_pass', e => {
        const data = JSON.parse(e.data);
        els.progressLabel.textContent = `Auto-retrying ${data.count} LG(s) that timed out...`;
    });

    es.addEventListener('lg_result', e => {
        const entry = JSON.parse(e.data);
        appendLog(entry, MODULE_STATS[currentModuleKey].kind);
        done++;
        const pct = total ? Math.round((done / total) * 100) : 0;
        els.progressBar.style.width = Math.min(pct, 100) + '%';
        els.progressCount.textContent = `${Math.min(done, total)} / ${total}`;
    });

    es.addEventListener('done', e => {
        const data = JSON.parse(e.data);
        els.progressLabel.textContent = 'Done.';
        els.progressBar.style.width = '100%';
        els.lastUpdated.textContent = `Last run: ${data.generated_at}`;
        els.statOk.textContent = data.ok;
        els.statFail.textContent = data.failed;
        els.failCountLabel.textContent = data.failed;
        MODULE_STATS[currentModuleKey].ok = data.ok;
        MODULE_STATS[currentModuleKey].fail = data.failed;
        MODULE_STATS[currentModuleKey].generated_at = data.generated_at;
        es.close();
        activeSource = null;
        els.btnRefreshAll.disabled = false;
        els.btnRetryFailed.disabled = data.failed === 0;
        els.moduleSelect.disabled = false;
    });

    es.onerror = () => {
        els.progressLabel.textContent = 'Connection lost - partial results were still saved.';
        es.close();
        activeSource = null;
        els.btnRefreshAll.disabled = false;
        els.btnRetryFailed.disabled = MODULE_STATS[currentModuleKey].fail === 0;
        els.moduleSelect.disabled = false;
    };
}

els.moduleSelect.addEventListener('change', () => {
    if (activeSource) { els.moduleSelect.value = currentModuleKey; return; } // don't switch mid-fetch
    currentModuleKey = els.moduleSelect.value;
    loadModuleUi(currentModuleKey);
});
els.btnRefreshAll.addEventListener('click', () => startFetch('full'));
els.btnRetryFailed.addEventListener('click', () => startFetch('retry_failed'));

loadModuleUi(currentModuleKey);
</script>

</body>
</html>