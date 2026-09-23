<?php
// ============================================================
// Single-site check (used by the "Check now" button on each row
// of website_directory.php).
//
// Deliberately NOT behind the admin login: this probes exactly
// ONE LG's site (plus its old. legacy copy), which is no more
// load on that site than a person opening it in their browser -
// a fundamentally different risk profile from the bulk fetch,
// which is why that one stays admin-gated and this doesn't.
//
// The lgid is validated against LG_code_JSON.json so this can
// only ever be pointed at a real, known LG - not an arbitrary URL.
// ============================================================

require_once __DIR__ . '/website_status_common.php';

header('Content-Type: application/json; charset=UTF-8');

$lgid = (string) ($_GET['lgid'] ?? '');
$checkLegacy = ($_GET['check_legacy'] ?? '1') !== '0';

if ($lgid === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing lgid']);
    exit;
}

$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!rate_limit_ok($clientIp, 20, 60)) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many checks from this address - please wait a moment and try again.']);
    exit;
}

$lgList = load_lg_list();
$lg = null;
foreach ($lgList as $row) {
    if ((string) $row['lgid'] === $lgid) {
        $lg = $row;
        break;
    }
}

if ($lg === null) {
    http_response_code(404);
    echo json_encode(['error' => 'Unknown lgid']);
    exit;
}

$entry = probe_one_lg_sync($lg, 20, 10, $checkLegacy);

// Persist this one result into the shared cache so a page reload
// later reflects it too, same as if it came from a bulk run.
$cache = load_cache(WEBSITE_STATUS_CACHE_FILE);
$cache['by_lg'][$entry['lgid']] = $entry;
save_cache($cache, WEBSITE_STATUS_CACHE_FILE);

echo json_encode($entry, JSON_UNESCAPED_UNICODE);