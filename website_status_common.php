<?php
// ============================================================
// Website Directory / Uptime Monitor module config.
//
// Unlike staff-api / elected-officials-api, this module doesn't
// call an API on each LG's site - it probes the site itself
// (HTTP status, timing, resolved IP, Server header) using curl's
// transfer-info, not JSON parsing. Kept separate from
// staff_common.php since the record shape and probing logic are
// entirely different.
//
// Also semantically different in one important way: the person-
// directory modules preserve old "good" data when a refresh fails
// (better to show slightly stale staff than none). For an uptime
// monitor that's actively misleading - if a site just went down,
// showing yesterday's "up" status defeats the point. So this
// module always overwrites with the latest probe result.
// ============================================================

require_once __DIR__ . '/staff_common.php'; // reuse load_lg_list(), lg_field(), normalize_domain(), load_cache(), save_cache()

define('WEBSITE_STATUS_CACHE_FILE', __DIR__ . '/website_status_data.json');
date_default_timezone_set('Asia/Kathmandu');

// ------------------------------------------------------------
// Known infrastructure.
//
// Most LG sites sit on one of two older Drupal servers; the
// ministry is gradually moving sites to a new Django server.
// When a site moves, the old Drupal copy is NOT torn down - it
// stays reachable at old.<domain>. Update this map if servers
// change or new ones are added.
// ------------------------------------------------------------
define('NEW_SERVER_IP', '103.69.127.59');

define('KNOWN_SERVERS', [
    '103.69.124.140' => ['label' => 'Old Server 1', 'platform' => 'Drupal', 'role' => 'legacy'],
    '103.69.127.8'   => ['label' => 'Old Server 2', 'platform' => 'Drupal', 'role' => 'legacy'],
    NEW_SERVER_IP    => ['label' => 'New Server',   'platform' => 'Django', 'role' => 'migration_target'],
]);

/**
 * Human-readable label for a server IP, e.g. "103.69.127.59 (New
 * Server · Django)" - falls back to the bare IP if unrecognized.
 */
function server_label($ip)
{
    if (!$ip) {
        return 'Unreachable';
    }
    if (isset(KNOWN_SERVERS[$ip])) {
        $s = KNOWN_SERVERS[$ip];
        return "{$ip} ({$s['label']} \xC2\xB7 {$s['platform']})";
    }
    return $ip;
}

/**
 * Platform guess for a server IP ('Drupal' / 'Django' / null if
 * the IP isn't one of the known infrastructure addresses).
 */
function server_platform($ip)
{
    return $ip && isset(KNOWN_SERVERS[$ip]) ? KNOWN_SERVERS[$ip]['platform'] : null;
}

/**
 * Build the old.<host> legacy URL for a normalized site URL.
 * Returns null if the URL can't be parsed.
 */
function legacy_domain_url($url)
{
    $parts = parse_url($url);
    if (!$parts || empty($parts['host'])) {
        return null;
    }
    $scheme = $parts['scheme'] ?? 'https';
    return $scheme . '://old.' . $parts['host'];
}

/**
 * Pull the Server: header value out of a raw HTTP header block.
 */
function extract_server_header($headersRaw)
{
    if ($headersRaw && preg_match('/^Server:\s*(.+)$/mi', $headersRaw, $m)) {
        return trim($m[1]);
    }
    return null;
}

/**
 * Build a normalized status record for one LG from a completed
 * curl probe. $curlInfo is the full curl_getinfo() array.
 *
 * status:
 *   'ok'    - HTTP 2xx/3xx received, site is up and responding
 *   'down'  - a response came back, but it's an error (4xx/5xx)
 *   'error' - curl itself failed (DNS, timeout, connection refused) -
 *             we never got as far as an HTTP response at all
 */
function build_website_status_record($lg, $curlInfo, $curlErr, $headersRaw)
{
    $httpCode = (int) ($curlInfo['http_code'] ?? 0);

    if ($curlErr !== '') {
        $status = 'error';
        $error = $curlErr;
    } elseif ($httpCode >= 200 && $httpCode < 400) {
        $status = 'ok';
        $error = null;
    } else {
        $status = 'down';
        $error = "HTTP {$httpCode}";
    }

    return [
        'lgid'          => (string) $lg['lgid'],
        'province'      => lg_field($lg, ['province_name_ne', 'province-name', 'province_name_en', 'Province-name-en']),
        'district'      => lg_field($lg, ['district_name_ne', 'district-name ne', 'district_name_en', 'district-name en']),
        'lg_name'       => lg_field($lg, ['lg_name_complete_ne', 'lg-name-np-full', 'lg_name_complete_en', 'lg-name-en-full', 'lg_name_ne', 'lg-name-ne']),
        'lg_type'       => lg_field($lg, ['lg_type_ne', 'lg-type-ne', 'lg_type_en', 'lg-type-en']),
        'lgid_code'     => (string) $lg['lgid'],
        'domain'        => $lg['domain'] ?? '',
        'url'           => normalize_domain($lg['domain'] ?? ''),
        'status'        => $status,
        'http_code'     => $httpCode,
        'error'         => $error,
        'response_ms'   => isset($curlInfo['total_time']) ? (int) round($curlInfo['total_time'] * 1000) : null,
        'dns_ms'        => isset($curlInfo['namelookup_time']) ? (int) round($curlInfo['namelookup_time'] * 1000) : null,
        'connect_ms'    => isset($curlInfo['connect_time']) ? (int) round($curlInfo['connect_time'] * 1000) : null,
        'ssl_ms'        => isset($curlInfo['appconnect_time']) ? (int) round($curlInfo['appconnect_time'] * 1000) : null,
        'ttfb_ms'       => isset($curlInfo['starttransfer_time']) ? (int) round($curlInfo['starttransfer_time'] * 1000) : null,
        'server_ip'     => $curlInfo['primary_ip'] ?? null,
        'server_port'   => $curlInfo['primary_port'] ?? null,
        'server_label'  => server_label($curlInfo['primary_ip'] ?? null),
        'platform'      => server_platform($curlInfo['primary_ip'] ?? null),
        'migrated_to_new' => ($curlInfo['primary_ip'] ?? null) === NEW_SERVER_IP,
        'ssl_verified'  => isset($curlInfo['ssl_verify_result']) ? ($curlInfo['ssl_verify_result'] === 0) : null,
        'redirect_url'  => $curlInfo['redirect_url'] ?? null,
        'content_bytes' => isset($curlInfo['size_download']) ? (int) $curlInfo['size_download'] : null,
        'server_header' => extract_server_header($headersRaw),
        // Legacy (old.<domain>) fields - filled in by the caller when a
        // legacy check is performed; null means "not checked this run",
        // distinct from false ("checked, and it's not there").
        'legacy_exists'     => null,
        'legacy_http_code'  => null,
        'legacy_url'        => null,
        'checked_at'    => date('Y-m-d H:i:s'),
    ];
}

/**
 * Probe the old.<domain> legacy copy of a site. Deliberately
 * lightweight (HEAD-style, no header capture) - we only need to
 * know whether it still responds, not its full diagnostics.
 * Returns ['legacy_exists' => bool, 'legacy_http_code' => int, 'legacy_url' => string|null].
 */
function probe_legacy_domain($mainUrl, $timeout, $connectTimeout)
{
    $legacyUrl = legacy_domain_url($mainUrl);
    if (!$legacyUrl) {
        return ['legacy_exists' => null, 'legacy_http_code' => null, 'legacy_url' => null];
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $legacyUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_NOBODY => true,
        CURLOPT_CONNECTTIMEOUT => $connectTimeout,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_USERAGENT => 'PLGSP-LG-Website-Monitor/1.0 (+Gandaki Province OCMCM)',
    ]);
    curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $exists = $err === '' && $httpCode >= 200 && $httpCode < 400;

    return [
        'legacy_exists'    => $exists,
        'legacy_http_code' => $httpCode,
        'legacy_url'       => $legacyUrl,
    ];
}

/**
 * Synchronously probe ONE LG (main site, optionally + legacy copy).
 * Used by the per-row "Check now" button - a single ad hoc request,
 * not the bulk parallel fetch. Returns the combined record; does
 * NOT write to the cache (the caller decides whether to persist it).
 */
function probe_one_lg_sync($lg, $timeout = 20, $connectTimeout = 10, $checkLegacy = true)
{
    $mainUrl = normalize_domain($lg['domain'] ?? '');

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $mainUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_CONNECTTIMEOUT => $connectTimeout,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'PLGSP-LG-Website-Monitor/1.0 (+Gandaki Province OCMCM)',
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $curlInfo = curl_getinfo($ch);
    curl_close($ch);

    $headerSize = $curlInfo['header_size'] ?? 0;
    $headersRaw = $raw !== false ? substr($raw, 0, $headerSize) : '';

    $entry = build_website_status_record($lg, $curlInfo, $err, $headersRaw);

    if ($checkLegacy) {
        $legacy = probe_legacy_domain($mainUrl, $timeout, $connectTimeout);
        $entry = array_merge($entry, $legacy);
    }

    return $entry;
}

/**
 * Simple file-based rate limiter (sliding window). Used to stop
 * check_single_site.php - which is deliberately not behind the
 * admin login, since it's meant for one-off single-site checks -
 * from being scripted into an unthrottled mass-refresh that
 * bypasses the whole point of gating the bulk fetch behind admin
 * auth. Fails OPEN (allows the request) if the lock file can't be
 * opened, so a filesystem hiccup never breaks the feature outright.
 */
function rate_limit_ok($key, $maxRequests = 20, $windowSeconds = 60)
{
    $storeFile = __DIR__ . '/.rate_limit.json';
    $fp = @fopen($storeFile, 'c+');
    if (!$fp) {
        return true;
    }

    flock($fp, LOCK_EX);
    $raw = stream_get_contents($fp);
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $data = [];
    }

    $now = time();
    $timestamps = $data[$key] ?? [];
    $timestamps = array_values(array_filter($timestamps, function ($t) use ($now, $windowSeconds) {
        return $t > $now - $windowSeconds;
    }));

    $allowed = count($timestamps) < $maxRequests;
    if ($allowed) {
        $timestamps[] = $now;
    }
    $data[$key] = $timestamps;

    // Keep the file from growing forever - drop keys with no recent activity.
    foreach ($data as $k => $v) {
        if (empty($v)) {
            unset($data[$k]);
        }
    }

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($data));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    return $allowed;
}