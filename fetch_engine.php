<?php
// ============================================================
// Generic streaming fetch engine (Server-Sent Events).
//
// NOT meant to be requested directly. A small per-module entry
// file sets three variables and requires this:
//
//   $MODULE_API_URL_FN   - name of a function(array $lg): string
//                           that builds this module's API URL
//                           for one LG (e.g. 'staff_api_url')
//   $MODULE_CACHE_FILE   - absolute path to this module's JSON
//                           cache file
//   $MODULE_RECORD_KEY   - the key each cache entry stores its
//                           record list under (e.g. 'staff') -
//                           only used for the final summary count
//
// See fetch_staff.php for the minimal example.
//
// Modes (GET ?mode=):
//   full          (default) - fetch every LG
//   retry_failed  - only re-fetch LGs currently marked 'failed'
//                   (or never fetched) in the cache
//
// As each LG's request finishes (in arrival order, not the order
// requests were started), its result is streamed to the browser
// immediately and written into the JSON cache - the caller does
// not have to wait for the slowest LG before seeing data. Runs in
// bounded-concurrency chunks (not all ~753 at once), and a failed
// request is automatically retried once, in-process, with a
// longer timeout, before this script finishes.
// ============================================================

if (!isset($MODULE_API_URL_FN, $MODULE_CACHE_FILE, $MODULE_RECORD_KEY)) {
    http_response_code(500);
    exit('fetch_engine.php was included without its required module variables.');
}

set_time_limit(0);
ignore_user_abort(true);

header('Content-Type: text/event-stream; charset=UTF-8');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // disable nginx proxy buffering
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
 * Run one parallel pass over $targets. Streams each result via
 * SSE as soon as that individual request completes, and merges
 * it into $cache (by reference). Returns the list of LG rows
 * that failed during this pass (so the caller can retry them).
 */
function run_pass_streaming(array $targets, $timeout, $connectTimeout, array &$cache, $urlFn)
{
    if (empty($targets)) {
        return [];
    }

    $mh = curl_multi_init();
    $handleMap = [];

    foreach ($targets as $lg) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => call_user_func($urlFn, $lg),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
        ]);
        curl_multi_add_handle($mh, $ch);
        $handleMap[handle_id($ch)] = ['ch' => $ch, 'lg' => $lg];
    }

    $stillFailed = [];
    $active = null;

    do {
        curl_multi_exec($mh, $active);

        while ($info = curl_multi_info_read($mh)) {
            $ch = $info['handle'];
            $hid = handle_id($ch);
            $lg = $handleMap[$hid]['lg'];

            $content = curl_multi_getcontent($ch);
            $err = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            $entry = process_lg_response($lg, $content, $err, $httpCode);
            $key = (string) $lg['lgid'];

            if ($entry['status'] === 'ok') {
                $cache['by_lg'][$key] = $entry;
            } else {
                // Don't let a transient failure wipe out records we
                // already have cached from a previous successful run -
                // keep the old list, just note the failed attempt.
                if (isset($cache['by_lg'][$key]) && $cache['by_lg'][$key]['status'] === 'ok') {
                    $cache['by_lg'][$key]['last_attempt'] = $entry['fetched_at'];
                    $cache['by_lg'][$key]['last_error'] = $entry['error'];
                    $entry = $cache['by_lg'][$key];
                    $entry['status'] = 'stale'; // ok data, but the latest refresh failed
                } else {
                    $cache['by_lg'][$key] = $entry;
                }
                $stillFailed[] = $lg;
            }

            sse_send('lg_result', $entry);

            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
            unset($handleMap[$hid]);
        }

        if ($active) {
            curl_multi_select($mh, 1.0);
        }
    } while ($active > 0);

    curl_multi_close($mh);

    return $stillFailed;
}

/**
 * Run a (potentially large) target list in bounded-size chunks,
 * so we never have hundreds of simultaneous cURL connections open
 * at once - kinder to local resources and to the individual LG
 * servers we're hitting. Saves the cache after every chunk so
 * progress survives even if the whole run doesn't finish.
 */
function run_all_chunked(array $targets, $timeout, $connectTimeout, array &$cache, $cacheFile, $urlFn, $chunkSize = 40)
{
    $stillFailed = [];
    $chunks = array_chunk($targets, $chunkSize);
    $chunkCount = count($chunks);

    foreach ($chunks as $i => $chunk) {
        if ($chunkCount > 1) {
            sse_send('chunk_progress', ['chunk' => $i + 1, 'of' => $chunkCount]);
        }
        $failedInChunk = run_pass_streaming($chunk, $timeout, $connectTimeout, $cache, $urlFn);
        $stillFailed = array_merge($stillFailed, $failedInChunk);

        // Persist after every chunk - a long run can take a while,
        // this way a timeout or abort part-way through doesn't lose
        // everything fetched so far.
        $cache['generated_at'] = date('Y-m-d H:i:s');
        save_cache($cache, $cacheFile);
    }

    return $stillFailed;
}

// ------------------------------------------------------------
// Determine targets for this run
// ------------------------------------------------------------
$lgList = load_lg_list();
$cache = load_cache($MODULE_CACHE_FILE);
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

// ------------------------------------------------------------
// Pass 1 - longer timeouts (many LG sites are slow), run in
// bounded-concurrency chunks (this can be ~753 LGs nationally).
// ------------------------------------------------------------
$stillFailed = run_all_chunked($targets, 90, 20, $cache, $MODULE_CACHE_FILE, $MODULE_API_URL_FN, 40);

// ------------------------------------------------------------
// Pass 2 - automatic retry, only for what just failed, with an
// even longer timeout window
// ------------------------------------------------------------
if (!empty($stillFailed)) {
    sse_send('retry_pass', ['count' => count($stillFailed)]);
    run_all_chunked($stillFailed, 120, 30, $cache, $MODULE_CACHE_FILE, $MODULE_API_URL_FN, 40);
}

// ------------------------------------------------------------
// Persist + summarize
// ------------------------------------------------------------
$cache['generated_at'] = date('Y-m-d H:i:s');
save_cache($cache, $MODULE_CACHE_FILE);

$okCount = 0;
$failCount = 0;
$totalRecords = 0;
foreach ($cache['by_lg'] as $entry) {
    if (($entry['status'] ?? '') === 'failed') {
        $failCount++;
    } else {
        $okCount++;
        $totalRecords += count($entry[$MODULE_RECORD_KEY] ?? []);
    }
}

sse_send('done', [
    'ok'           => $okCount,
    'failed'       => $failCount,
    'total_records' => $totalRecords,
    'generated_at' => $cache['generated_at'],
]);
