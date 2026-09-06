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
        'ssl_verified'  => isset($curlInfo['ssl_verify_result']) ? ($curlInfo['ssl_verify_result'] === 0) : null,
        'redirect_url'  => $curlInfo['redirect_url'] ?? null,
        'content_bytes' => isset($curlInfo['size_download']) ? (int) $curlInfo['size_download'] : null,
        'server_header' => extract_server_header($headersRaw),
        'checked_at'    => date('Y-m-d H:i:s'),
    ];
}
