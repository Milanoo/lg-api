<?php
// ============================================================
// Website status prober (Server-Sent Events).
//
// Deliberately NOT built on fetch_engine.php - that engine's
// retry-and-preserve-old-data model fits "fetch and parse an API"
// but is wrong for uptime monitoring: if a site just went down, we
// want to show that, not quietly keep showing yesterday's "up".
// So every run here overwrites each LG's record with the latest
// probe result, whatever it is.
//
// Modes (GET ?mode=):
//   full          (default) - probe every LG
//   retry_failed  - only re-probe LGs currently 'down' or 'error'
//                   (or never probed) in the cache
// ============================================================

require_once __DIR__ . '/website_status_common.php';

set_time_limit(0);
ignore_user_abort(true);

header('Content-Type: text/event-stream; charset=UTF-8');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', '0');
while (ob_get_level() > 0) {
    @ob_end_flush();
}
@ob_implicit_flush(true);

function sse_send($event, $payload)
{
    echo "event: {$event}\n";
    echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n\n";
    if (ob_get_level() > 0) {
        @ob_flush();
    }
    @flush();
}

function handle_id($ch)
{
    return is_object($ch) ? spl_object_id($ch) : (int) $ch;
}

/**
 * Probe one chunk of LGs in parallel, streaming each result the
 * moment it completes and always overwriting the cache entry
 * (see file header - freshness matters more than preservation
 * here). Returns the LGs that came back 'down' or 'error', for
 * the caller's automatic retry pass.
 *
 * When $checkLegacy is true, each LG gets TWO concurrent handles
 * (main site + old.<domain> legacy check) within the same batch,
 * so total wall-clock time stays roughly the same as a main-only
 * run - it's the per-chunk concurrency that doubles, not the
 * number of chunks.
 */
function probe_chunk(array $targets, $timeout, $connectTimeout, array &$cache, $checkLegacy)
{
    if (empty($targets)) {
        return [];
    }

    $mh = curl_multi_init();
    $handleMap = [];
    $pending = []; // lgid => ['lg' => ..., 'main' => entry|null, 'legacy' => array|null]

    foreach ($targets as $lg) {
        $lgid = (string) $lg['lgid'];
        $mainUrl = normalize_domain($lg['domain'] ?? '');
        $pending[$lgid] = ['lg' => $lg, 'main' => null, 'legacy' => $checkLegacy ? null : []];

        $chMain = curl_init();
        curl_setopt_array($chMain, [
            CURLOPT_URL => $mainUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true, // capture response headers (for Server:)
            CURLOPT_NOBODY => false,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => 'PLGSP-LG-Website-Monitor/1.0 (+Gandaki Province OCMCM)',
        ]);
        curl_multi_add_handle($mh, $chMain);
        $handleMap[handle_id($chMain)] = ['lgid' => $lgid, 'kind' => 'main'];

        if ($checkLegacy) {
            $legacyUrl = legacy_domain_url($mainUrl);
            if ($legacyUrl === null) {
                // Couldn't derive an old.<host> URL (e.g. malformed
                // domain) - nothing to probe, mark as not-checked.
                $pending[$lgid]['legacy'] = ['legacy_exists' => null, 'legacy_http_code' => null, 'legacy_url' => null];
            } else {
                $chLegacy = curl_init();
                curl_setopt_array($chLegacy, [
                    CURLOPT_URL => $legacyUrl,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_NOBODY => true, // just an existence check, don't need the body
                    CURLOPT_CONNECTTIMEOUT => $connectTimeout,
                    CURLOPT_TIMEOUT => $timeout,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS => 5,
                    CURLOPT_USERAGENT => 'PLGSP-LG-Website-Monitor/1.0 (+Gandaki Province OCMCM)',
                ]);
                curl_multi_add_handle($mh, $chLegacy);
                $handleMap[handle_id($chLegacy)] = ['lgid' => $lgid, 'kind' => 'legacy'];
            }
        }
    }

    $needsRetry = [];
    $active = null;

    do {
        curl_multi_exec($mh, $active);

        while ($info = curl_multi_info_read($mh)) {
            $ch = $info['handle'];
            $hid = handle_id($ch);
            $meta = $handleMap[$hid];
            $lgid = $meta['lgid'];

            if ($meta['kind'] === 'main') {
                $raw = curl_multi_getcontent($ch);
                $err = curl_error($ch);
                $curlInfo = curl_getinfo($ch);
                $headerSize = $curlInfo['header_size'] ?? 0;
                $headersRaw = $raw !== false ? substr($raw, 0, $headerSize) : '';
                $pending[$lgid]['main'] = build_website_status_record($pending[$lgid]['lg'], $curlInfo, $err, $headersRaw);
            } else {
                $err = curl_error($ch);
                $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $exists = $err === '' && $httpCode >= 200 && $httpCode < 400;
                $pending[$lgid]['legacy'] = [
                    'legacy_exists'    => $exists,
                    'legacy_http_code' => $httpCode,
                    'legacy_url'       => curl_getinfo($ch, CURLINFO_EFFECTIVE_URL),
                ];
            }

            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
            unset($handleMap[$hid]);

            // Finalize + emit once both halves for this LG are in
            $p = $pending[$lgid];
            if ($p['main'] !== null && $p['legacy'] !== null) {
                $entry = $p['main'];
                if (!empty($p['legacy'])) {
                    $entry = array_merge($entry, $p['legacy']);
                }
                $cache['by_lg'][$lgid] = $entry; // always overwrite - see file header

                if ($entry['status'] !== 'ok') {
                    $needsRetry[] = $p['lg'];
                }

                sse_send('lg_result', $entry);
                unset($pending[$lgid]);
            }
        }

        if ($active) {
            curl_multi_select($mh, 1.0);
        }
    } while ($active > 0);

    curl_multi_close($mh);

    return $needsRetry;
}

function probe_all_chunked(array $targets, $timeout, $connectTimeout, array &$cache, $checkLegacy, $chunkSize = 40)
{
    $needsRetry = [];
    $chunks = array_chunk($targets, $chunkSize);
    $chunkCount = count($chunks);

    foreach ($chunks as $i => $chunk) {
        if ($chunkCount > 1) {
            sse_send('chunk_progress', ['chunk' => $i + 1, 'of' => $chunkCount]);
        }
        $needsRetry = array_merge($needsRetry, probe_chunk($chunk, $timeout, $connectTimeout, $cache, $checkLegacy));

        $cache['generated_at'] = date('Y-m-d H:i:s');
        save_cache($cache, WEBSITE_STATUS_CACHE_FILE);
    }

    return $needsRetry;
}

// ------------------------------------------------------------
// Determine targets
// ------------------------------------------------------------
$lgList = load_lg_list();
$cache = load_cache(WEBSITE_STATUS_CACHE_FILE);
$mode = ($_GET['mode'] ?? 'full') === 'retry_failed' ? 'retry_failed' : 'full';

if ($mode === 'retry_failed') {
    $badLgids = [];
    foreach ($cache['by_lg'] as $lgid => $entry) {
        if (($entry['status'] ?? '') !== 'ok') {
            $badLgids[] = (string) $lgid;
        }
    }
    foreach ($lgList as $lg) {
        if (!isset($cache['by_lg'][(string) $lg['lgid']])) {
            $badLgids[] = (string) $lg['lgid'];
        }
    }
    $badLgids = array_unique($badLgids);
    $targets = array_values(array_filter($lgList, function ($lg) use ($badLgids) {
        return in_array((string) $lg['lgid'], $badLgids, true);
    }));
} else {
    $targets = $lgList;
}

sse_send('meta', ['total' => count($targets), 'mode' => $mode]);

$checkLegacy = ($_GET['check_legacy'] ?? '1') !== '0';

// Pass 1 - a normal working page usually loads well within this
$stillFailing = probe_all_chunked($targets, 30, 15, $cache, $checkLegacy, 40);

// Pass 2 - automatic retry for anything down/errored, longer window
// in case it was just a slow response, not truly down
if (!empty($stillFailing)) {
    sse_send('retry_pass', ['count' => count($stillFailing)]);
    probe_all_chunked($stillFailing, 60, 25, $cache, $checkLegacy, 40);
}

$cache['generated_at'] = date('Y-m-d H:i:s');
save_cache($cache, WEBSITE_STATUS_CACHE_FILE);

$okCount = 0;
$downCount = 0;
$errorCount = 0;
$totalResponseMs = 0;
$responseCount = 0;
$migratedCount = 0;
$legacyStillLiveCount = 0;
foreach ($cache['by_lg'] as $entry) {
    if (($entry['status'] ?? '') === 'ok') {
        $okCount++;
    } elseif (($entry['status'] ?? '') === 'down') {
        $downCount++;
    } else {
        $errorCount++;
    }
    if (isset($entry['response_ms'])) {
        $totalResponseMs += $entry['response_ms'];
        $responseCount++;
    }
    if (!empty($entry['migrated_to_new'])) {
        $migratedCount++;
    }
    if (!empty($entry['legacy_exists'])) {
        $legacyStillLiveCount++;
    }
}

sse_send('done', [
    'ok'                     => $okCount,
    'failed'                 => $downCount + $errorCount,
    'down'                   => $downCount,
    'error'                  => $errorCount,
    'avg_response_ms'        => $responseCount ? (int) round($totalResponseMs / $responseCount) : null,
    'migrated_count'         => $migratedCount,
    'legacy_still_live_count' => $legacyStillLiveCount,
    'generated_at'           => $cache['generated_at'],
]);