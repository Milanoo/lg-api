<?php
// ============================================================
// Province x Server Breakdown (public viewer)
//
// Read-only pivot built from website_status_data.json - shows how
// many LG websites per province sit on each distinct server IP.
// Cells link into website_directory.php pre-filtered to that exact
// province + server, for drilling into the actual site list.
// ============================================================

require_once __DIR__ . '/website_status_common.php';

$cache = load_cache(WEBSITE_STATUS_CACHE_FILE);
$byLg = $cache['by_lg'] ?? [];
$generatedAt = $cache['generated_at'];
$hasCache = $generatedAt !== null;

$modules = require __DIR__ . '/modules_config.php';
$currentModuleKey = 'website-status';

$recordsJson = json_encode(
    array_values($byLg),
    JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP
);
$modulesJson = json_encode(
    $modules,
    JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Province &times; Server Breakdown - LG Directory</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Noto+Sans+Devanagari:wght@400;500;600&display=swap" rel="stylesheet">
<link href="assets/site.css" rel="stylesheet">
<style>
.pivot-wrap { overflow-x: auto; }
table.pivot { width: 100%; border-collapse: collapse; font-size: 12px; white-space: nowrap; }
table.pivot th, table.pivot td { padding: 8px 12px; border-bottom: 1px solid #f1f5f9; border-right: 1px solid #f1f5f9; text-align: center; }
table.pivot thead th {
  background: #212a37; color: rgba(255,255,255,.85); font-weight: 600; font-size: 10.5px;
  text-transform: uppercase; letter-spacing: .04em; position: sticky; top: 0;
}
table.pivot thead th.corner { text-align: left; background: #1a212c; }
table.pivot tbody th {
  text-align: left; background: var(--navy-light); color: var(--navy); font-weight: 600;
  position: sticky; left: 0; z-index: 1;
}
table.pivot tbody tr:hover td { background: #f8fafd; }
table.pivot td.cell-link { cursor: pointer; }
table.pivot td.cell-link:hover { background: var(--navy-light) !important; }
table.pivot td .cell-total { font-weight: 700; color: var(--text); }
table.pivot td .cell-down { display: block; font-size: 10px; color: var(--danger); font-weight: 600; }
table.pivot td.zero { color: var(--border); }
table.pivot tfoot th, table.pivot tfoot td { background: #eef2f7; font-weight: 700; border-top: 2px solid var(--border); }
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
      <div class="header-title">Province &times; Server Breakdown</div>
      <div class="header-sub">प्रदेश अनुसार सर्भर विवरण &nbsp;·&nbsp; How many LG websites per province sit on each server</div>
    </div>
    <a href="website_directory.php" class="btn-reset" style="text-decoration:none; margin-left:auto;">&larr; Website Status Directory</a>
    <div class="modules-launcher">
      <button class="modules-btn" id="modulesBtn" type="button">
        <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><rect x="1" y="1" width="6" height="6" rx="1"/><rect x="9" y="1" width="6" height="6" rx="1"/><rect x="1" y="9" width="6" height="6" rx="1"/><rect x="9" y="9" width="6" height="6" rx="1"/></svg>
        Modules
      </button>
      <div class="modules-panel" id="modulesPanel"></div>
    </div>
  </div>
</header>

<div class="wrap">

  <div class="status-bar" style="padding:0 0 14px;">
    <span class="stat-pill" id="lastUpdated">
      <?= $hasCache ? 'Checked ' . htmlspecialchars($generatedAt) : 'No status data yet - an admin needs to run a check.' ?>
    </span>
    <div class="status-actions">
      <select id="valueMode" class="filter-select">
        <option value="all">All sites</option>
        <option value="ok">Up only</option>
      </select>
    </div>
  </div>

  <p style="font-size:11.5px; color:var(--text-muted); margin-bottom:1rem;">
    Each cell shows the number of LG websites from that province hosted on that server IP. The small red number,
    where present, is how many of those are currently down or unreachable. Click any cell to see the exact list.
  </p>

  <div class="pivot-wrap data-table-wrap">
    <table class="pivot" id="pivotTable"></table>
  </div>

  <div class="no-results" id="noResults" style="display:none;">No website status data available yet.</div>
</div>

<footer>
  <strong>Website Status Directory</strong> &nbsp;·&nbsp; Nepal &nbsp;·&nbsp; Office of Chief Minister and Council of Ministers, Gandaki Province &nbsp;·&nbsp; PLGSP
</footer>

<script>
const ALL_RECORDS = <?= $recordsJson ?: '[]' ?>;
const MODULES = <?= $modulesJson ?: '[]' ?>;
const CURRENT_MODULE = <?= json_encode($currentModuleKey) ?>;

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
}

function renderModulesPanel() {
    const panel = document.getElementById('modulesPanel');
    const rows = MODULES.map(m => {
        const isActive = m.key === CURRENT_MODULE;
        const isLive = !!m.file;
        const statusHtml = isActive
            ? `<span class="module-status live">Current</span>`
            : (isLive ? `<span class="module-status live">Live</span>` : `<span class="module-status soon">Soon</span>`);
        const inner = `
            <span class="module-dot"></span>
            <span>
                <div class="module-name">${escapeHtml(m.label)}</div>
                <div class="module-name-np">${escapeHtml(m.label_np)}</div>
            </span>
            ${statusHtml}`;
        if (isLive && !isActive) {
            return `<a class="module-item" href="${escapeHtml(m.file)}">${inner}</a>`;
        }
        return `<div class="module-item ${isActive ? 'active' : 'disabled'}">${inner}</div>`;
    }).join('');
    panel.innerHTML = `<div class="modules-panel-label">LG API Modules</div>${rows}`;
}

const modulesBtn = document.getElementById('modulesBtn');
const modulesPanel = document.getElementById('modulesPanel');
modulesBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    modulesPanel.classList.toggle('open');
});
document.addEventListener('click', (e) => {
    if (!modulesPanel.contains(e.target) && e.target !== modulesBtn) {
        modulesPanel.classList.remove('open');
    }
});

function buildPivot() {
    if (ALL_RECORDS.length === 0) {
        document.getElementById('noResults').style.display = 'block';
        return;
    }

    const valueMode = document.getElementById('valueMode').value; // 'all' | 'ok'
    const records = valueMode === 'ok' ? ALL_RECORDS.filter(r => r.status === 'ok') : ALL_RECORDS;

    // Collect provinces and server IPs, in a stable order (by total volume, descending)
    const provinceTotals = new Map();
    const serverTotals = new Map();
    const cellData = new Map(); // "province|||serverKey" -> { total, down }
    const serverLabels = new Map(); // serverKey -> friendly label

    records.forEach(r => {
        const province = r.province || 'Unknown';
        const serverKey = r.server_ip || '__unknown__';
        provinceTotals.set(province, (provinceTotals.get(province) || 0) + 1);
        serverTotals.set(serverKey, (serverTotals.get(serverKey) || 0) + 1);
        if (!serverLabels.has(serverKey)) {
            serverLabels.set(serverKey, r.server_label || serverKey);
        }

        const cellKey = `${province}|||${serverKey}`;
        if (!cellData.has(cellKey)) cellData.set(cellKey, { total: 0, down: 0 });
        const cell = cellData.get(cellKey);
        cell.total++;
        if (r.status !== 'ok') cell.down++;
    });

    const provinces = [...provinceTotals.keys()].sort((a, b) => provinceTotals.get(b) - provinceTotals.get(a));
    const servers = [...serverTotals.keys()].sort((a, b) => serverTotals.get(b) - serverTotals.get(a));

    let html = '<thead><tr><th class="corner">Province</th>';
    servers.forEach(s => {
        const label = s === '__unknown__' ? 'Unreachable' : (serverLabels.get(s) || s);
        html += `<th>${escapeHtml(label)}</th>`;
    });
    html += '<th>Total</th></tr></thead><tbody>';

    provinces.forEach(province => {
        html += `<tr><th>${escapeHtml(province)}</th>`;
        servers.forEach(server => {
            const cell = cellData.get(`${province}|||${server}`);
            if (!cell || cell.total === 0) {
                html += `<td class="zero">&middot;</td>`;
            } else {
                const downLine = cell.down > 0 ? `<span class="cell-down">${cell.down} down</span>` : '';
                const serverParam = server === '__unknown__' ? '' : server;
                html += `<td class="cell-link" data-province="${escapeHtml(province)}" data-server="${escapeHtml(serverParam)}"><span class="cell-total">${cell.total}</span>${downLine}</td>`;
            }
        });
        html += `<td><span class="cell-total">${provinceTotals.get(province)}</span></td></tr>`;
    });

    html += '<tfoot><tr><th>Total</th>';
    servers.forEach(server => {
        html += `<td>${serverTotals.get(server)}</td>`;
    });
    html += `<td>${records.length}</td></tr></tfoot>`;

    const table = document.getElementById('pivotTable');
    table.innerHTML = html;

    table.querySelectorAll('td.cell-link').forEach(td => {
        td.addEventListener('click', () => {
            const params = new URLSearchParams();
            params.set('province', td.dataset.province);
            if (td.dataset.server) params.set('server_ip', td.dataset.server);
            window.location.href = `website_directory.php?${params.toString()}`;
        });
    });
}

document.getElementById('valueMode').addEventListener('change', buildPivot);

renderModulesPanel();
buildPivot();
</script>

</body>
</html>