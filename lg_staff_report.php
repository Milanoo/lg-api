<?php
// ============================================================
// Gandaki Province LG Staff Directory (public viewer)
//
// Read-only. Loads staff_data.json written by fetch_staff.php
// and lets people browse/filter/paginate it. This page cannot
// trigger a fetch itself - that lives in the password-gated
// admin/fetch_console.php, so a public visitor can never cause
// all ~753 LG sites to be hit at once.
// ============================================================

require_once __DIR__ . '/staff_common.php';

$cache = load_cache();
$byLg = $cache['by_lg'] ?? [];
$generatedAt = $cache['generated_at'];

$okCount = 0;
$failCount = 0;
$totalStaff = 0;
foreach ($byLg as $entry) {
    if (($entry['status'] ?? '') === 'failed') {
        $failCount++;
    } else {
        $okCount++;
        $totalStaff += count($entry['staff'] ?? []);
    }
}
$hasCache = $generatedAt !== null;

$modules = require __DIR__ . '/modules_config.php';
$currentModuleKey = 'staff-api';

$byLgJson = json_encode(
    $byLg,
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
<title>LG Staff Directory - Nepal</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Noto+Sans+Devanagari:wght@400;500;600&display=swap" rel="stylesheet">
<link href="assets/site.css" rel="stylesheet">
</head>
<body>

<div class="gov-bar">
  <strong>DATA from LG Website</strong> &nbsp;·&nbsp; Disclaimer &nbsp;·&nbsp; The records are fetched from LGs websites using publicly available APIs
</div>

<header>
  <div class="header-inner">
    <div class="header-logo"><span>ने</span></div>
    <div class="header-text">
      <div class="header-eyebrow">Nepal · Local Governments</div>
      <div class="header-title">LG Staff Directory</div>
      <div class="header-sub">स्थानीय तह कर्मचारी विवरण &nbsp;·&nbsp; Staff of all Local Governments</div>
    </div>
    <div class="header-badge">
      <span id="total-count-badge"><?= $totalStaff ?></span>
      <small>Staff Records</small>
    </div>
    <div class="modules-launcher">
      <button class="modules-btn" id="modulesBtn" type="button">
        <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><rect x="1" y="1" width="6" height="6" rx="1"/><rect x="9" y="1" width="6" height="6" rx="1"/><rect x="1" y="9" width="6" height="6" rx="1"/><rect x="9" y="9" width="6" height="6" rx="1"/></svg>
        Modules
      </button>
      <div class="modules-panel" id="modulesPanel"></div>
    </div>
  </div>
</header>

<div class="toolbar">
  <div class="toolbar-inner">
    <div class="search-box">
      <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="6.5" cy="6.5" r="5"/><path d="M10 10 L14 14" stroke-linecap="round"/></svg>
      <input type="text" id="filterSearch" placeholder="Search name, section, phone, email…">
    </div>
    <div class="filter-multiselect" id="provinceFilterWrap">
      <button class="filter-multiselect-trigger" id="filterProvinceToggle" type="button" aria-expanded="false">
        <span id="filterProvinceLabel">All Provinces</span>
        <span class="filter-multiselect-caret">▾</span>
      </button>
      <div class="filter-multiselect-panel" id="filterProvincePanel">
        <div class="filter-multiselect-header">
          <input type="text" id="filterProvinceSearch" placeholder="Search province..." autocomplete="off">
          <button class="filter-multiselect-selectall" id="filterProvinceSelectAll" type="button">Select all</button>
          <button class="filter-multiselect-clear" id="filterProvinceClear" type="button">Reset</button>
        </div>
        <div class="filter-multiselect-options" id="filterProvinceOptions"></div>
      </div>
    </div>
    <div class="filter-multiselect" id="districtFilterWrap">
      <button class="filter-multiselect-trigger" id="filterDistrictToggle" type="button" aria-expanded="false">
        <span id="filterDistrictLabel">All Districts</span>
        <span class="filter-multiselect-caret">▾</span>
      </button>
      <div class="filter-multiselect-panel" id="filterDistrictPanel">
        <div class="filter-multiselect-header">
          <input type="text" id="filterDistrictSearch" placeholder="Search district..." autocomplete="off">
          <button class="filter-multiselect-selectall" id="filterDistrictSelectAll" type="button">Select all</button>
          <button class="filter-multiselect-clear" id="filterDistrictClear" type="button">Reset</button>
        </div>
        <div class="filter-multiselect-options" id="filterDistrictOptions"></div>
      </div>
    </div>
    <div class="filter-multiselect" id="lgFilterWrap">
      <button class="filter-multiselect-trigger" id="filterLGToggle" type="button" aria-expanded="false">
        <span id="filterLGLabel">All LGs</span>
        <span class="filter-multiselect-caret">▾</span>
      </button>
      <div class="filter-multiselect-panel" id="filterLGPanel">
        <div class="filter-multiselect-header">
          <input type="text" id="filterLGSearch" placeholder="Search LG..." autocomplete="off">
          <button class="filter-multiselect-selectall" id="filterLGSelectAll" type="button">Select all</button>
          <button class="filter-multiselect-clear" id="filterLGClear" type="button">Reset</button>
        </div>
        <div class="filter-multiselect-options" id="filterLGOptions"></div>
      </div>
    </div>
    <div class="filter-multiselect" id="designationFilterWrap">
      <button class="filter-multiselect-trigger" id="filterDesignationToggle" type="button" aria-expanded="false">
        <span id="filterDesignationLabel">All Designations</span>
        <span class="filter-multiselect-caret">▾</span>
      </button>
      <div class="filter-multiselect-panel" id="filterDesignationPanel">
        <div class="filter-multiselect-header">
          <input type="text" id="filterDesignationSearch" placeholder="Search designation..." autocomplete="off">
          <button class="filter-multiselect-selectall" id="filterDesignationSelectAll" type="button">Select all</button>
          <button class="filter-multiselect-clear" id="filterDesignationClear" type="button">Reset</button>
        </div>
        <div class="filter-multiselect-options" id="filterDesignationOptions"></div>
      </div>
    </div>
    <button id="resetFilters" class="btn-reset" type="button">Reset</button>
  </div>
</div>

<div class="status-bar">
  <span class="stat-pill"><span class="stat-dot ok"></span>LGs with data: <b id="statOk"><?= $okCount ?></b></span>
  <span class="status-sep"></span>
  <span class="stat-pill"><span class="stat-dot fail"></span>Failed: <b id="statFail"><?= $failCount ?></b></span>
  <span class="status-sep"></span>
  <span class="stat-pill" id="lastUpdated">
    <?= $hasCache ? 'Updated ' . htmlspecialchars($generatedAt) : 'No cached data yet - an admin needs to run a fetch.' ?>
  </span>

  <div class="status-actions">
    <select id="pageSize" class="page-size-select">
      <option value="30">30 / page</option>
      <option value="60" selected>60 / page</option>
      <option value="120">120 / page</option>
    </select>
    <div class="view-toggle">
      <button class="vbtn active" id="btnGallery" type="button">
        <svg width="12" height="12" viewBox="0 0 12 12"><rect x="0" y="0" width="5" height="5" rx="1" fill="currentColor"/><rect x="7" y="0" width="5" height="5" rx="1" fill="currentColor"/><rect x="0" y="7" width="5" height="5" rx="1" fill="currentColor"/><rect x="7" y="7" width="5" height="5" rx="1" fill="currentColor"/></svg>
        Gallery
      </button>
      <button class="vbtn" id="btnList" type="button">
        <svg width="12" height="12" viewBox="0 0 12 12"><rect x="0" y="1" width="12" height="2" rx="1" fill="currentColor"/><rect x="0" y="5" width="12" height="2" rx="1" fill="currentColor"/><rect x="0" y="9" width="12" height="2" rx="1" fill="currentColor"/></svg>
        List
      </button>
    </div>
  </div>
</div>

<div class="wrap">
  <div class="result-count" id="resultCount"></div>
  <div id="grid" class="gallery"></div>
  <div class="no-results" id="noResults">No matching staff found.</div>
  <div class="pagination" id="pagination"></div>
</div>

<footer>
  <strong>LG Staff Directory</strong> &nbsp;·&nbsp; Nepal &nbsp;·&nbsp; Office of Chief Minister and Council of Ministers, Gandaki Province &nbsp;·&nbsp; PLGSP
</footer>

<script>
// by-LG cache written by PHP on page load: { "<lgid>": { lgid, province, district,
// lg_name, lg_type, status: 'ok'|'stale'|'failed', error, staff: [...] }, ... }
const BY_LG = <?= $byLgJson ?: '{}' ?>;
const MODULES = <?= $modulesJson ?: '[]' ?>;
const CURRENT_MODULE = <?= json_encode($currentModuleKey) ?>;

const els = {
    province: document.getElementById('filterProvinceToggle'),
    provincePanel: document.getElementById('filterProvincePanel'),
    provinceSearch: document.getElementById('filterProvinceSearch'),
    provinceOptions: document.getElementById('filterProvinceOptions'),
    provinceSelectAll: document.getElementById('filterProvinceSelectAll'),
    provinceClear: document.getElementById('filterProvinceClear'),
    provinceLabel: document.getElementById('filterProvinceLabel'),
    district: document.getElementById('filterDistrictToggle'),
    districtPanel: document.getElementById('filterDistrictPanel'),
    districtSearch: document.getElementById('filterDistrictSearch'),
    districtOptions: document.getElementById('filterDistrictOptions'),
    districtSelectAll: document.getElementById('filterDistrictSelectAll'),
    districtClear: document.getElementById('filterDistrictClear'),
    districtLabel: document.getElementById('filterDistrictLabel'),
    lg: document.getElementById('filterLGToggle'),
    lgPanel: document.getElementById('filterLGPanel'),
    lgSearch: document.getElementById('filterLGSearch'),
    lgOptions: document.getElementById('filterLGOptions'),
    lgSelectAll: document.getElementById('filterLGSelectAll'),
    lgClear: document.getElementById('filterLGClear'),
    lgLabel: document.getElementById('filterLGLabel'),
    designation: document.getElementById('filterDesignationToggle'),
    designationPanel: document.getElementById('filterDesignationPanel'),
    designationSearch: document.getElementById('filterDesignationSearch'),
    designationOptions: document.getElementById('filterDesignationOptions'),
    designationSelectAll: document.getElementById('filterDesignationSelectAll'),
    designationClear: document.getElementById('filterDesignationClear'),
    designationLabel: document.getElementById('filterDesignationLabel'),
    search: document.getElementById('filterSearch'),
    reset: document.getElementById('resetFilters'),
    grid: document.getElementById('grid'),
    noResults: document.getElementById('noResults'),
    resultCount: document.getElementById('resultCount'),
    pagination: document.getElementById('pagination'),
    pageSize: document.getElementById('pageSize'),
    btnGallery: document.getElementById('btnGallery'),
    btnList: document.getElementById('btnList'),
    modulesBtn: document.getElementById('modulesBtn'),
    modulesPanel: document.getElementById('modulesPanel'),
};

let currentView = 'gallery';
let currentPage = 1;
let pageSize = 60;
let searchDebounce = null;
let selectedProvinces = [];
let selectedDistricts = [];
let selectedLgs = [];
let selectedDesignations = [];

/* ── Soft avatar palette, matched to the GIOMS design system ── */
const PALETTES = [
    {bg:"#dbe8f5",fg:"#1e3a5f"}, {bg:"#d6eae0",fg:"#1a4731"}, {bg:"#e8e0f5",fg:"#3b1f6b"},
    {bg:"#f5e0e0",fg:"#6b1f1f"}, {bg:"#f5eddb",fg:"#6b4b10"}, {bg:"#dbeef5",fg:"#1a4a5f"},
    {bg:"#f5dbe8",fg:"#6b1f4a"}, {bg:"#e0f5e8",fg:"#1a5f31"},
];
function palette(name) {
    let h = 0;
    for (const c of (name || '')) h = ((h << 5) - h) + c.charCodeAt(0);
    return PALETTES[Math.abs(h) % PALETTES.length];
}
function initials(name) {
    return (name || '').trim().split(/\s+/).slice(0, 2).map(w => w[0]).join('').toUpperCase();
}

// Flattened once per data load (not per keystroke) - cheap to keep
// around since we only filter/slice it, never rebuild DOM from all
// of it at once.
let ALL_STAFF = [];
function buildFlatStaff() {
    const rows = [];
    Object.values(BY_LG).forEach(entry => {
        (entry.staff || []).forEach(s => {
            rows.push({
                lgid: entry.lgid, province: entry.province, district: entry.district,
                lg_name: entry.lg_name, lg_type: entry.lg_type, status: entry.status,
                ...s,
            });
        });
    });
    return rows;
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

function syncMultiSelectLabel(name, selectedValues) {
    const label = name === 'province' ? 'All Provinces' : name === 'district' ? 'All Districts' : name === 'lg' ? 'All LGs' : 'All Designations';
    const element = name === 'province' ? els.provinceLabel : name === 'district' ? els.districtLabel : name === 'lg' ? els.lgLabel : els.designationLabel;

    if (!selectedValues.length) {
        element.textContent = label;
        return;
    }
    element.textContent = selectedValues.length === 1 ? selectedValues[0] : `${selectedValues.length} selected`;
}

function renderMultiSelectOptions(name, values, selectedValues) {
    const allValues = uniqueSorted(values);
    const search = (els[`${name}Search`].value || '').trim().toLowerCase();
    const filtered = search
        ? allValues.filter(v => v.toLowerCase().includes(search))
        : allValues;
    const selectAllButton = els[`${name}SelectAll`];
    const optionsContainer = els[`${name}Options`];

    const allVisibleSelected = filtered.length > 0 && filtered.every(value => selectedValues.includes(value));
    selectAllButton.textContent = allVisibleSelected ? 'Clear visible' : 'Select all';
    selectAllButton.disabled = filtered.length === 0;

    if (!filtered.length) {
        optionsContainer.innerHTML = '<div class="filter-empty-state">No results found</div>';
        return;
    }

    optionsContainer.innerHTML = filtered.map(value => {
        const checked = selectedValues.includes(value) ? 'checked' : '';
        return `
            <label class="filter-option-item">
                <input type="checkbox" class="filter-option-check" value="${escapeHtml(value)}" ${checked}>
                <span>${escapeHtml(value)}</span>
            </label>
        `;
    }).join('');

    optionsContainer.querySelectorAll('.filter-option-check').forEach(checkbox => {
        checkbox.addEventListener('change', (event) => {
            const value = event.target.value;
            if (event.target.checked) {
                if (!selectedValues.includes(value)) selectedValues.push(value);
            } else {
                const idx = selectedValues.indexOf(value);
                if (idx !== -1) selectedValues.splice(idx, 1);
            }
            syncMultiSelectLabel(name, selectedValues);
            if (name === 'province') {
                const provincePool = selectedProvinces.length ? ALL_STAFF.filter(s => selectedProvinces.includes(s.province)) : ALL_STAFF;
                const validDistricts = uniqueSorted(provincePool.map(s => s.district));
                selectedDistricts = selectedDistricts.filter(v => validDistricts.includes(v));
                const validLgs = uniqueSorted((selectedDistricts.length ? provincePool.filter(s => selectedDistricts.includes(s.district)) : provincePool).map(s => s.lg_name));
                selectedLgs = selectedLgs.filter(v => validLgs.includes(v));
            }
            if (name === 'district') {
                const provincePool = selectedProvinces.length ? ALL_STAFF.filter(s => selectedProvinces.includes(s.province)) : ALL_STAFF;
                const validLgs = uniqueSorted((selectedDistricts.length ? provincePool.filter(s => selectedDistricts.includes(s.district)) : provincePool).map(s => s.lg_name));
                selectedLgs = selectedLgs.filter(v => validLgs.includes(v));
            }
            currentPage = 1;
            refreshFilterOptions();
            render();
        });
    });
}

function refreshFilterOptions() {
    const provinceValues = uniqueSorted(ALL_STAFF.map(s => s.province));
    selectedProvinces = selectedProvinces.filter(value => provinceValues.includes(value));

    const provincePool = selectedProvinces.length ? ALL_STAFF.filter(s => selectedProvinces.includes(s.province)) : ALL_STAFF;
    const districtValues = uniqueSorted(provincePool.map(s => s.district));
    selectedDistricts = selectedDistricts.filter(value => districtValues.includes(value));

    const districtPool = selectedDistricts.length ? provincePool.filter(s => selectedDistricts.includes(s.district)) : provincePool;
    const lgValues = uniqueSorted(districtPool.map(s => s.lg_name));
    selectedLgs = selectedLgs.filter(value => lgValues.includes(value));

    renderMultiSelectOptions('province', provinceValues, selectedProvinces);
    syncMultiSelectLabel('province', selectedProvinces);
    renderMultiSelectOptions('district', districtValues, selectedDistricts);
    syncMultiSelectLabel('district', selectedDistricts);
    renderMultiSelectOptions('lg', lgValues, selectedLgs);
    syncMultiSelectLabel('lg', selectedLgs);

    const designations = uniqueSorted(ALL_STAFF.map(s => s.designation));
    selectedDesignations = selectedDesignations.filter(value => designations.includes(value));
    renderMultiSelectOptions('designation', designations, selectedDesignations);
    syncMultiSelectLabel('designation', selectedDesignations);
}

function getFiltered() {
    const provinceSet = new Set(selectedProvinces);
    const districtSet = new Set(selectedDistricts);
    const lgSet = new Set(selectedLgs);
    const designationSet = new Set(selectedDesignations);
    const search = els.search.value.trim().toLowerCase();

    return ALL_STAFF.filter(s => {
        if (provinceSet.size > 0 && !provinceSet.has(s.province)) return false;
        if (districtSet.size > 0 && !districtSet.has(s.district)) return false;
        if (lgSet.size > 0 && !lgSet.has(s.lg_name)) return false;
        if (designationSet.size > 0 && !designationSet.has(s.designation)) return false;
        if (search) {
            const haystack = [s.name, s.section, s.phone, s.email, s.designation].join(' ').toLowerCase();
            if (!haystack.includes(search)) return false;
        }
        return true;
    });
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
}

function photoOrAvatar(s, sizeClass) {
    if (s.photo) {
        return `<img src="${escapeHtml(s.photo)}" alt="${escapeHtml(s.name)}" loading="lazy">`;
    }
    const { bg, fg } = palette(s.name);
    return `<div class="${sizeClass}" style="background:${bg};color:${fg};">${initials(s.name)}</div>`;
}

function gCard(s) {
    const staleTag = s.status === 'stale' ? `<span class="stale-dot">Stale</span>` : '';
    const phoneRow = s.phone
        ? `<a class="cl" href="tel:${escapeHtml(s.phone)}"><span class="cl-ic"><svg viewBox="0 0 16 16" fill="none" stroke="#2c5282" stroke-width="1.6"><path d="M14.7 11.3l-2-.7a1 1 0 00-1 .25l-1 1A10 10 0 014.1 5.3l1-1a1 1 0 00.25-1L4.7 1.3A1 1 0 003.7.5L2 .8A1 1 0 001 1.8C1 9.1 6.9 15 14.2 15a1 1 0 001-1l.3-1.7a1 1 0 00-.8-1z"/></svg></span><span class="txt">${escapeHtml(s.phone)}</span></a>`
        : '';
    const emailRow = s.email
        ? `<a class="cl" href="mailto:${escapeHtml(s.email)}"><span class="cl-ic"><svg viewBox="0 0 16 16" fill="none" stroke="#2c5282" stroke-width="1.5"><rect x="1" y="3" width="14" height="10" rx="1.5"/><path d="M1 5l7 5 7-5"/></svg></span><span class="txt">${escapeHtml(s.email)}</span></a>`
        : '';
    return `<div class="card">
        <div class="card-photo">${photoOrAvatar(s, 'initials-avatar')}${staleTag}</div>
        <div class="card-body">
            <div class="card-name">${escapeHtml(s.name)}</div>
            <div class="card-designation">${escapeHtml(s.designation) || '&mdash;'}</div>
            <div class="card-meta">${escapeHtml(s.section) ? escapeHtml(s.section) + ' &middot; ' : ''}${escapeHtml(s.lg_name)}, ${escapeHtml(s.district)}</div>
            <div class="contact-links">${phoneRow}${emailRow}</div>
        </div>
    </div>`;
}

function lRow(s) {
    const emailRow = s.email
        ? `<a class="lc" href="mailto:${escapeHtml(s.email)}"><svg width="10" height="10" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="1" y="3" width="14" height="10" rx="1.5"/><path d="M1 5l7 5 7-5"/></svg>${escapeHtml(s.email)}</a>`
        : '';
    const phoneRow = s.phone
        ? `<a class="lc" href="tel:${escapeHtml(s.phone)}"><svg width="10" height="10" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14.7 11.3l-2-.7a1 1 0 00-1 .25l-1 1A10 10 0 014.1 5.3l1-1a1 1 0 00.25-1L4.7 1.3A1 1 0 003.7.5L2 .8A1 1 0 001 1.8C1 9.1 6.9 15 14.2 15a1 1 0 001-1l.3-1.7a1 1 0 00-.8-1z"/></svg>${escapeHtml(s.phone)}</a>`
        : '';
    return `<div class="lrow">
        <div class="lrow-avatar">${photoOrAvatar(s, 'initials-sm')}</div>
        <div class="lrow-name">${escapeHtml(s.name)}<span>${escapeHtml(s.designation) || '&mdash;'}</span></div>
        <div class="lrow-meta"><strong>${escapeHtml(s.lg_name)}</strong><br>${escapeHtml(s.district)}${s.section ? ' &middot; ' + escapeHtml(s.section) : ''}</div>
        <div class="lrow-contacts">${phoneRow}${emailRow}</div>
    </div>`;
}

// ------------------------------------------------------------
// Pagination - this is the fix for the "Page Unresponsive"
// freeze: render() only ever builds DOM nodes for ONE page's
// worth of records (30/60/120), never the full filtered set,
// no matter how large the underlying dataset is.
// ------------------------------------------------------------
function render() {
    const filtered = getFiltered();
    const total = filtered.length;
    const totalPages = Math.max(1, Math.ceil(total / pageSize));
    currentPage = Math.min(Math.max(1, currentPage), totalPages);

    const start = (currentPage - 1) * pageSize;
    const pageItems = filtered.slice(start, start + pageSize);

    els.resultCount.textContent = total === 0
        ? `0 results`
        : `Showing ${start + 1}-${Math.min(start + pageSize, total)} of ${total} staff records`;
    els.noResults.style.display = total === 0 ? 'block' : 'none';

    els.grid.className = currentView;
    els.grid.innerHTML = pageItems.map(currentView === 'gallery' ? gCard : lRow).join('');

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
                render();
                window.scrollTo({ top: els.grid.offsetTop - 100, behavior: 'smooth' });
            }
        });
    });
}

function setView(view) {
    currentView = view;
    els.btnGallery.classList.toggle('active', view === 'gallery');
    els.btnList.classList.toggle('active', view === 'list');
    currentPage = 1;
    render();
}

// ------------------------------------------------------------
// Modules switcher
// ------------------------------------------------------------
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

// ------------------------------------------------------------
// Wire up filters
// ------------------------------------------------------------
function closeAllFilterPanels(exceptName = null) {
    const panels = ['province', 'district', 'lg', 'designation'];
    panels.forEach(name => {
        if (name === exceptName) return;
        const panel = els[`${name}Panel`];
        if (panel) panel.classList.remove('open');
        const trigger = els[name];
        if (trigger) trigger.setAttribute('aria-expanded', 'false');
    });
}

function toggleFilterPanel(name) {
    const panel = els[`${name}Panel`];
    const trigger = els[name];
    const isOpen = panel.classList.contains('open');
    closeAllFilterPanels(name);
    panel.classList.toggle('open', !isOpen);
    trigger.setAttribute('aria-expanded', String(!isOpen));
    if (!isOpen) {
        els[`${name}Search`].focus();
    }
}

const filterConfig = [
    { name: 'province', values: () => uniqueSorted(ALL_STAFF.map(s => s.province)), selected: () => selectedProvinces },
    { name: 'district', values: () => uniqueSorted((selectedProvinces.length ? ALL_STAFF.filter(s => selectedProvinces.includes(s.province)) : ALL_STAFF).map(s => s.district)), selected: () => selectedDistricts },
    { name: 'lg', values: () => uniqueSorted((selectedProvinces.length ? ALL_STAFF.filter(s => selectedProvinces.includes(s.province)) : ALL_STAFF).filter(s => selectedDistricts.length ? selectedDistricts.includes(s.district) : true).map(s => s.lg_name)), selected: () => selectedLgs },
    { name: 'designation', values: () => uniqueSorted(ALL_STAFF.map(s => s.designation)), selected: () => selectedDesignations },
];

function bindMultiSelect(name) {
    const trigger = els[name];
    const panel = els[`${name}Panel`];
    const searchInput = els[`${name}Search`];
    const clearButton = els[`${name}Clear`];
    const selectAllButton = els[`${name}SelectAll`];

    trigger.addEventListener('click', (event) => {
        event.stopPropagation();
        toggleFilterPanel(name);
    });

    searchInput.addEventListener('input', () => {
        const values = filterConfig.find(item => item.name === name).values();
        const selected = filterConfig.find(item => item.name === name).selected();
        renderMultiSelectOptions(name, values, selected);
    });

    selectAllButton.addEventListener('click', (event) => {
        event.stopPropagation();
        const values = filterConfig.find(item => item.name === name).values();
        const selected = filterConfig.find(item => item.name === name).selected();
        const query = (searchInput.value || '').trim().toLowerCase();
        const visible = query ? values.filter(value => value.toLowerCase().includes(query)) : values;
        const allVisibleSelected = visible.length > 0 && visible.every(value => selected.includes(value));

        if (allVisibleSelected) {
            selected.splice(0, selected.length, ...selected.filter(value => !visible.includes(value)));
        } else {
            visible.forEach(value => {
                if (!selected.includes(value)) selected.push(value);
            });
        }
        renderMultiSelectOptions(name, values, selected);
        syncMultiSelectLabel(name, selected);
        currentPage = 1;
        render();
    });

    clearButton.addEventListener('click', (event) => {
        event.stopPropagation();
        const selected = filterConfig.find(item => item.name === name).selected();
        selected.splice(0, selected.length);
        searchInput.value = '';
        const values = filterConfig.find(item => item.name === name).values();
        renderMultiSelectOptions(name, values, selected);
        syncMultiSelectLabel(name, selected);
        currentPage = 1;
        render();
    });

    document.addEventListener('click', (event) => {
        if (!panel.contains(event.target) && !trigger.contains(event.target)) {
            panel.classList.remove('open');
            trigger.setAttribute('aria-expanded', 'false');
        }
    });
}

bindMultiSelect('province');
bindMultiSelect('district');
bindMultiSelect('lg');
bindMultiSelect('designation');

els.search.addEventListener('input', () => {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => { currentPage = 1; render(); }, 250);
});
els.reset.addEventListener('click', () => {
    selectedProvinces = [];
    selectedDistricts = [];
    selectedLgs = [];
    selectedDesignations = [];
    els.provinceSearch.value = '';
    els.districtSearch.value = '';
    els.lgSearch.value = '';
    els.designationSearch.value = '';
    els.search.value = '';
    currentPage = 1;
    refreshFilterOptions();
    render();
});
els.pageSize.addEventListener('change', () => {
    pageSize = parseInt(els.pageSize.value, 10) || 60;
    currentPage = 1;
    render();
});
els.btnGallery.addEventListener('click', () => setView('gallery'));
els.btnList.addEventListener('click', () => setView('list'));

ALL_STAFF = buildFlatStaff();
renderModulesPanel();
refreshFilterOptions();
render();
</script>

</body>
</html>
