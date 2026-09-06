<?php
// ============================================================
// LG Website Status / Uptime Directory (public viewer)
//
// Read-only. Loads website_status_data.json written by
// fetch_website_status.php. Probing itself only happens from the
// admin console (fetch_console.php), same separation as the other
// modules - this page never triggers a check.
// ============================================================

require_once __DIR__ . '/website_status_common.php';

$cache = load_cache(WEBSITE_STATUS_CACHE_FILE);
$byLg = $cache['by_lg'] ?? [];
$generatedAt = $cache['generated_at'];
$hasCache = $generatedAt !== null;

$okCount = 0;
$downCount = 0;
$errorCount = 0;
$totalResponseMs = 0;
$responseCount = 0;
$ipSet = [];
foreach ($byLg as $entry) {
    $status = $entry['status'] ?? 'error';
    if ($status === 'ok') {
        $okCount++;
    } elseif ($status === 'down') {
        $downCount++;
    } else {
        $errorCount++;
    }
    if (!empty($entry['response_ms'])) {
        $totalResponseMs += $entry['response_ms'];
        $responseCount++;
    }
    if (!empty($entry['server_ip'])) {
        $ipSet[$entry['server_ip']] = true;
    }
}
$avgResponseMs = $responseCount ? (int) round($totalResponseMs / $responseCount) : null;
$uniqueIpCount = count($ipSet);
$totalChecked = count($byLg);

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
<title>Website Status - LG Directory</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Noto+Sans+Devanagari:wght@400;500;600&display=swap" rel="stylesheet">
<link href="assets/site.css" rel="stylesheet">
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
      <div class="header-title">Website Status Directory</div>
      <div class="header-sub">वेबसाइट स्थिति &nbsp;·&nbsp; Live up/down status, response time, and server info for every LG website</div>
    </div>
    <div class="header-badge">
      <span id="total-count-badge"><?= $totalChecked ?></span>
      <small>Websites Tracked</small>
    </div>
    <a href="server_summary.php" class="btn-reset" style="text-decoration:none;">Province &times; Server Breakdown &rarr;</a>
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

  <div class="stat-cards">
    <div class="stat-card"><div class="sc-value"><?= $totalChecked ?></div><div class="sc-label">Total Websites</div></div>
    <div class="stat-card ok"><div class="sc-value"><?= $okCount ?></div><div class="sc-label">Up</div></div>
    <div class="stat-card down"><div class="sc-value"><?= $downCount ?></div><div class="sc-label">Down (HTTP error)</div></div>
    <div class="stat-card error"><div class="sc-value"><?= $errorCount ?></div><div class="sc-label">Unreachable</div></div>
    <div class="stat-card"><div class="sc-value"><?= $avgResponseMs !== null ? $avgResponseMs . 'ms' : '—' ?></div><div class="sc-label">Avg Response</div></div>
    <div class="stat-card"><div class="sc-value"><?= $uniqueIpCount ?></div><div class="sc-label">Unique Server IPs</div></div>
  </div>

  <div class="data-table-wrap" style="margin-bottom: 1.2rem;">
    <div style="padding:10px 14px; border-bottom:1px solid var(--border); font-size:12.5px; font-weight:600; color: var(--navy);">
      Websites by Server
      <span style="font-weight:400; color: var(--text-muted); font-size:11px;">&mdash; click a row to filter the list below</span>
    </div>
    <table class="data-table">
      <thead>
        <tr>
          <th>Server IP</th>
          <th>Total Sites</th>
          <th>Up</th>
          <th>Down / Unreachable</th>
          <th>Provinces Served</th>
          <th>Avg Response</th>
        </tr>
      </thead>
      <tbody id="serverSummaryBody"></tbody>
    </table>
  </div>

  <div class="toolbar" style="position:static; border-radius:8px; border:1px solid var(--border); margin-bottom: 1rem;">
    <div class="toolbar-inner">
      <div class="search-box">
        <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="6.5" cy="6.5" r="5"/><path d="M10 10 L14 14" stroke-linecap="round"/></svg>
        <input type="text" id="filterSearch" placeholder="Search LG name, domain, IP…">
      </div>
      <select id="filterProvince" class="filter-select"><option value="">All Provinces</option></select>
      <select id="filterDistrict" class="filter-select"><option value="">All Districts</option></select>
      <select id="filterLG" class="filter-select"><option value="">All LGs</option></select>
      <select id="filterStatus" class="filter-select">
        <option value="">All Statuses</option>
        <option value="ok">Up</option>
        <option value="down">Down (HTTP error)</option>
        <option value="error">Unreachable</option>
      </select>
      <select id="filterServerIp" class="filter-select"><option value="">All Servers</option></select>
      <select id="sortBy" class="filter-select">
        <option value="lg_name">Sort: LG Name (A–Z)</option>
        <option value="response_desc">Sort: Slowest first</option>
        <option value="response_asc">Sort: Fastest first</option>
        <option value="status">Sort: Status</option>
      </select>
      <button id="resetFilters" class="btn-reset" type="button">Reset</button>
    </div>
  </div>

  <div class="status-bar" style="padding:0 0 10px;">
    <span class="stat-pill" id="lastUpdated">
      <?= $hasCache ? 'Checked ' . htmlspecialchars($generatedAt) : 'No status data yet - an admin needs to run a check.' ?>
    </span>
    <div class="status-actions">
      <select id="pageSize" class="page-size-select">
        <option value="30">30 / page</option>
        <option value="60" selected>60 / page</option>
        <option value="120">120 / page</option>
      </select>
    </div>
  </div>

  <div class="result-count" id="resultCount"></div>

  <div class="data-table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th style="width:20px;"></th>
          <th>LG Code</th>
          <th>Province</th>
          <th>District</th>
          <th>LG Name</th>
          <th>Website</th>
          <th>Status</th>
          <th>Response</th>
          <th>Server IP</th>
        </tr>
      </thead>
      <tbody id="tableBody"></tbody>
    </table>
  </div>

  <div class="no-results" id="noResults">No matching websites found.</div>
  <div class="pagination" id="pagination"></div>
</div>

<footer>
  <strong>Website Status Directory</strong> &nbsp;·&nbsp; Nepal &nbsp;·&nbsp; Office of Chief Minister and Council of Ministers, Gandaki Province &nbsp;·&nbsp; PLGSP
</footer>

<script>
const ALL_RECORDS = <?= $recordsJson ?: '[]' ?>;
const MODULES = <?= $modulesJson ?: '[]' ?>;
const CURRENT_MODULE = <?= json_encode($currentModuleKey) ?>;

const els = {
    province: document.getElementById('filterProvince'),
    district: document.getElementById('filterDistrict'),
    lg: document.getElementById('filterLG'),
    status: document.getElementById('filterStatus'),
    serverIp: document.getElementById('filterServerIp'),
    sortBy: document.getElementById('sortBy'),
    search: document.getElementById('filterSearch'),
    reset: document.getElementById('resetFilters'),
    tableBody: document.getElementById('tableBody'),
    noResults: document.getElementById('noResults'),
    resultCount: document.getElementById('resultCount'),
    pagination: document.getElementById('pagination'),
    pageSize: document.getElementById('pageSize'),
    modulesBtn: document.getElementById('modulesBtn'),
    modulesPanel: document.getElementById('modulesPanel'),
};

let currentPage = 1;
let pageSize = 60;
let searchDebounce = null;
let expandedLgid = null;

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
}

function uniqueSorted(arr) {
    return [...new Set(arr.filter(v => v && v.trim() !== ''))].sort((a, b) => a.localeCompare(b, 'ne'));
}

function populateSelect(select, values, placeholder) {
    const current = select.value;
    select.innerHTML = `<option value="">${placeholder}</option>`;
    values.forEach(v => {
        const opt = document.createElement('option');
        opt.value = v;
        opt.textContent = v;
        select.appendChild(opt);
    });
    if (values.includes(current)) select.value = current;
}

function refreshFilterOptions() {
    populateSelect(els.province, uniqueSorted(ALL_RECORDS.map(s => s.province)), 'All Provinces');

    const province = els.province.value;
    let pool = province ? ALL_RECORDS.filter(s => s.province === province) : ALL_RECORDS;
    populateSelect(els.district, uniqueSorted(pool.map(s => s.district)), 'All Districts');

    const district = els.district.value;
    if (district) pool = pool.filter(s => s.district === district);
    populateSelect(els.lg, uniqueSorted(pool.map(s => s.lg_name)), 'All LGs');

    populateSelect(els.serverIp, uniqueSorted(ALL_RECORDS.map(s => s.server_ip)), 'All Servers');
}

function getFiltered() {
    const province = els.province.value;
    const district = els.district.value;
    const lg = els.lg.value;
    const status = els.status.value;
    const serverIp = els.serverIp.value;
    const search = els.search.value.trim().toLowerCase();

    let rows = ALL_RECORDS.filter(r => {
        if (province && r.province !== province) return false;
        if (district && r.district !== district) return false;
        if (lg && r.lg_name !== lg) return false;
        if (status && r.status !== status) return false;
        if (serverIp && r.server_ip !== serverIp) return false;
        if (search) {
            const haystack = [r.lg_name, r.domain, r.server_ip, r.lgid].join(' ').toLowerCase();
            if (!haystack.includes(search)) return false;
        }
        return true;
    });

    const sortMode = els.sortBy.value;
    const statusRank = { ok: 0, down: 1, error: 2 };
    rows = rows.slice().sort((a, b) => {
        if (sortMode === 'response_desc') return (b.response_ms ?? -1) - (a.response_ms ?? -1);
        if (sortMode === 'response_asc') return (a.response_ms ?? 1e9) - (b.response_ms ?? 1e9);
        if (sortMode === 'status') return (statusRank[a.status] ?? 9) - (statusRank[b.status] ?? 9);
        return (a.lg_name || '').localeCompare(b.lg_name || '', 'ne');
    });

    return rows;
}

function statusBadge(status) {
    const label = status === 'ok' ? 'Up' : (status === 'down' ? 'Down' : 'Unreachable');
    return `<span class="status-badge ${status === 'ok' ? 'ok' : (status === 'down' ? 'down' : 'error')}">${label}</span>`;
}

function respBadge(ms) {
    if (ms === null || ms === undefined) return `<span class="resp-badge none">&mdash;</span>`;
    let cls = 'fast';
    if (ms >= 3000) cls = 'slow';
    else if (ms >= 1000) cls = 'medium';
    return `<span class="resp-badge ${cls}">${ms}ms</span>`;
}

function detailRow(r) {
    const items = [
        ['HTTP Code', r.http_code || '—'],
        ['DNS Lookup', r.dns_ms !== null ? r.dns_ms + 'ms' : '—'],
        ['Connect', r.connect_ms !== null ? r.connect_ms + 'ms' : '—'],
        ['SSL Handshake', r.ssl_ms !== null ? r.ssl_ms + 'ms' : '—'],
        ['Time to First Byte', r.ttfb_ms !== null ? r.ttfb_ms + 'ms' : '—'],
        ['SSL Verified', r.ssl_verified === true ? 'Yes' : (r.ssl_verified === false ? 'No' : '—')],
        ['Server Header', r.server_header || '—'],
        ['Content Size', r.content_bytes !== null ? (r.content_bytes / 1024).toFixed(1) + ' KB' : '—'],
        ['Server Port', r.server_port || '—'],
        ['Redirected To', r.redirect_url || '—'],
        ['Checked At', r.checked_at || '—'],
    ];
    let html = `<div class="detail-grid">`;
    items.forEach(([label, value]) => {
        html += `<div class="detail-item"><div class="di-label">${escapeHtml(label)}</div><div class="di-value">${escapeHtml(String(value))}</div></div>`;
    });
    if (r.error) {
        html += `<div class="detail-item" style="grid-column:1/-1;"><div class="di-label">Error</div><div class="di-value error-text">${escapeHtml(r.error)}</div></div>`;
    }
    html += `</div>`;
    return `<tr class="detail-row"><td colspan="9">${html}</td></tr>`;
}

function buildServerSummary() {
    const groups = new Map(); // server_ip (or 'unknown') -> { total, up, down, provinces:Set, respSum, respCount }
    ALL_RECORDS.forEach(r => {
        const key = r.server_ip || '__unknown__';
        if (!groups.has(key)) {
            groups.set(key, { ip: r.server_ip, total: 0, up: 0, down: 0, provinces: new Set(), respSum: 0, respCount: 0 });
        }
        const g = groups.get(key);
        g.total++;
        if (r.status === 'ok') g.up++; else g.down++;
        if (r.province) g.provinces.add(r.province);
        if (r.response_ms !== null && r.response_ms !== undefined) {
            g.respSum += r.response_ms;
            g.respCount++;
        }
    });

    const rows = [...groups.values()].sort((a, b) => b.total - a.total);

    document.getElementById('serverSummaryBody').innerHTML = rows.map(g => {
        const label = g.ip ? g.ip : 'No response / Unreachable';
        const avgMs = g.respCount ? Math.round(g.respSum / g.respCount) + 'ms' : '—';
        return `<tr class="expandable" data-server-ip="${escapeHtml(g.ip || '')}">
            <td class="mono">${escapeHtml(label)}</td>
            <td>${g.total}</td>
            <td style="color:var(--ok); font-weight:600;">${g.up}</td>
            <td style="color:var(--danger); font-weight:600;">${g.down}</td>
            <td>${g.provinces.size}</td>
            <td>${avgMs}</td>
        </tr>`;
    }).join('');

    document.querySelectorAll('#serverSummaryBody tr').forEach(tr => {
        tr.addEventListener('click', () => {
            els.serverIp.value = tr.dataset.serverIp;
            currentPage = 1;
            expandedLgid = null;
            render();
            window.scrollTo({ top: els.tableBody.offsetTop - 200, behavior: 'smooth' });
        });
    });
}

function render() {
    const filtered = getFiltered();
    const total = filtered.length;
    const totalPages = Math.max(1, Math.ceil(total / pageSize));
    currentPage = Math.min(Math.max(1, currentPage), totalPages);

    const start = (currentPage - 1) * pageSize;
    const pageItems = filtered.slice(start, start + pageSize);

    els.resultCount.textContent = total === 0
        ? '0 results'
        : `Showing ${start + 1}-${Math.min(start + pageSize, total)} of ${total} websites`;
    els.noResults.style.display = total === 0 ? 'block' : 'none';

    els.tableBody.innerHTML = pageItems.map(r => {
        const isExpanded = r.lgid === expandedLgid;
        const mainRow = `<tr class="expandable ${isExpanded ? 'row-expanded' : ''}" data-lgid="${escapeHtml(r.lgid)}">
            <td><span class="expand-chevron">&#9656;</span></td>
            <td class="mono">${escapeHtml(r.lgid_code || r.lgid)}</td>
            <td>${escapeHtml(r.province)}</td>
            <td>${escapeHtml(r.district)}</td>
            <td>${escapeHtml(r.lg_name)}</td>
            <td><a class="site-link" href="${escapeHtml(r.url)}" target="_blank" rel="noopener" onclick="event.stopPropagation()">${escapeHtml(r.domain)}</a></td>
            <td>${statusBadge(r.status)}</td>
            <td>${respBadge(r.response_ms)}</td>
            <td class="mono">${escapeHtml(r.server_ip || '—')}</td>
        </tr>`;
        return mainRow + (isExpanded ? detailRow(r) : '');
    }).join('');

    els.tableBody.querySelectorAll('tr.expandable').forEach(tr => {
        tr.addEventListener('click', () => {
            const lgid = tr.dataset.lgid;
            expandedLgid = expandedLgid === lgid ? null : lgid;
            render();
        });
    });

    renderPagination(currentPage, totalPages);
}

function renderPagination(page, totalPages) {
    if (totalPages <= 1) {
        els.pagination.innerHTML = '';
        return;
    }
    const btn = (label, target, opts = {}) => {
        const { active = false, disabled = false } = opts;
        return `<button class="page-btn ${active ? 'active' : ''}" data-page="${target}" ${disabled ? 'disabled' : ''}>${label}</button>`;
    };
    let html = btn('&lsaquo;', page - 1, { disabled: page === 1 });
    const windowSize = 2;
    const pages = new Set([1, totalPages]);
    for (let p = page - windowSize; p <= page + windowSize; p++) {
        if (p >= 1 && p <= totalPages) pages.add(p);
    }
    const sorted = [...pages].sort((a, b) => a - b);
    let prev = 0;
    for (const p of sorted) {
        if (p - prev > 1) html += `<span class="page-ellipsis">&hellip;</span>`;
        html += btn(String(p), p, { active: p === page });
        prev = p;
    }
    html += btn('&rsaquo;', page + 1, { disabled: page === totalPages });
    els.pagination.innerHTML = html;

    els.pagination.querySelectorAll('.page-btn').forEach(b => {
        b.addEventListener('click', () => {
            const target = parseInt(b.dataset.page, 10);
            if (!isNaN(target)) {
                currentPage = target;
                expandedLgid = null;
                render();
                window.scrollTo({ top: els.tableBody.offsetTop - 150, behavior: 'smooth' });
            }
        });
    });
}

function renderModulesPanel() {
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
    els.modulesPanel.innerHTML = `<div class="modules-panel-label">LG API Modules</div>${rows}`;
}

els.modulesBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    els.modulesPanel.classList.toggle('open');
});
document.addEventListener('click', (e) => {
    if (!els.modulesPanel.contains(e.target) && e.target !== els.modulesBtn) {
        els.modulesPanel.classList.remove('open');
    }
});

function onFilterChange() { currentPage = 1; expandedLgid = null; refreshFilterOptions(); render(); }

els.province.addEventListener('change', onFilterChange);
els.district.addEventListener('change', onFilterChange);
els.lg.addEventListener('change', () => { currentPage = 1; expandedLgid = null; render(); });
els.status.addEventListener('change', () => { currentPage = 1; expandedLgid = null; render(); });
els.serverIp.addEventListener('change', () => { currentPage = 1; expandedLgid = null; render(); });
els.sortBy.addEventListener('change', () => { currentPage = 1; render(); });
els.search.addEventListener('input', () => {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => { currentPage = 1; expandedLgid = null; render(); }, 250);
});
els.reset.addEventListener('click', () => {
    els.province.value = '';
    els.district.value = '';
    els.lg.value = '';
    els.status.value = '';
    els.serverIp.value = '';
    els.sortBy.value = 'lg_name';
    els.search.value = '';
    currentPage = 1;
    expandedLgid = null;
    refreshFilterOptions();
    render();
});
els.pageSize.addEventListener('change', () => {
    pageSize = parseInt(els.pageSize.value, 10) || 60;
    currentPage = 1;
    render();
});

renderModulesPanel();
refreshFilterOptions();
buildServerSummary();

// Allow deep-linking in from server_summary.php, e.g.
// website_directory.php?province=...&server_ip=...
(function applyUrlParams() {
    const params = new URLSearchParams(window.location.search);
    if (params.has('province')) els.province.value = params.get('province');
    refreshFilterOptions(); // district/LG options depend on province, so refresh before setting them
    if (params.has('district')) els.district.value = params.get('district');
    refreshFilterOptions();
    if (params.has('lg')) els.lg.value = params.get('lg');
    if (params.has('status')) els.status.value = params.get('status');
    if (params.has('server_ip')) els.serverIp.value = params.get('server_ip');
})();

render();
</script>

</body>
</html>
