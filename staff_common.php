<?php
// ============================================================
// Shared helpers used by both fetch_staff.php (the streaming
// fetcher) and lg_staff_report.php (the page that renders the
// directory from the cached JSON file).
// ============================================================

define('LG_LIST_FILE', __DIR__ . '/LG_code_JSON.json');
define('STAFF_CACHE_FILE', __DIR__ . '/staff_data.json');

/**
 * Load the master list of Local Governments.
 */
function load_lg_list()
{
    $raw = @file_get_contents(LG_LIST_FILE);
    if ($raw === false) {
        return [];
    }
    $list = json_decode($raw, true);
    return is_array($list) ? $list : [];
}

/**
 * Read the first present, non-empty value out of $lg for a list of
 * candidate keys. Lets us support both the old Gandaki-only field
 * names (e.g. 'lg-name-ne') and the new national schema (e.g.
 * 'lg_name_ne') without caring which one a given LG_code_JSON.json
 * was built with.
 */
function lg_field($lg, array $keys, $default = '')
{
    foreach ($keys as $key) {
        if (isset($lg[$key]) && $lg[$key] !== '') {
            return $lg[$key];
        }
    }
    return $default;
}

/**
 * Newer LG_code_JSON.json files list domains without a scheme
 * (e.g. "phaktanglungmun.gov.np"); older ones included it
 * ("https://chumanuwrimun.gov.np"). Normalize both to a full URL.
 */
function normalize_domain($domain)
{
    $domain = trim($domain, "/ \t\n\r\0\x0B");
    if ($domain === '') {
        return '';
    }
    if (!preg_match('~^https?://~i', $domain)) {
        $domain = 'https://' . $domain;
    }
    return $domain;
}

/**
 * Build the staff-api URL for a given LG.
 */
function staff_api_url($lg)
{
    $domain = normalize_domain($lg['domain'] ?? '');
    return $domain . '/staff-api';
}

/**
 * Pull the `src="..."` attribute out of the HTML <img> snippet
 * returned by the staff API's "Photo" field.
 */
function extract_photo_src($photoHtml)
{
    if (empty($photoHtml)) {
        return '';
    }
    if (preg_match('/src="([^"]+)"/u', $photoHtml, $m)) {
        return $m[1];
    }
    return '';
}

/**
 * Turn a raw staff-api JSON body into our normalized staff record
 * array. Blank/placeholder entries (no name) are skipped.
 */
function build_staff_list($lg, $decoded)
{
    $staff = [];
    if (!is_array($decoded)) {
        return $staff;
    }
    foreach ($decoded as $row) {
        $name = trim($row['Title'] ?? '');
        if ($name === '') {
            continue;
        }
        $staff[] = [
            'name'        => $name,
            'designation' => trim($row['Designation'] ?? ''),
            'section'     => trim($row['Section'] ?? ''),
            'email'       => trim($row['Email'] ?? ''),
            'phone'       => trim($row['Phone'] ?? ''),
            'tenure'      => trim($row['Tenure'] ?? ''),
            'photo'       => extract_photo_src($row['Photo'] ?? ''),
        ];
    }
    return $staff;
}

/**
 * Build the normalized "entry" for one LG, given the raw curl
 * result for its staff-api call. Always returns an entry with a
 * 'status' of 'ok' or 'failed'.
 */
function process_lg_response($lg, $content, $curlErr, $httpCode)
{
    $base = [
        'lgid'        => (string) $lg['lgid'],
        // Nepali display values (used by the UI's filters/sort) with English fallbacks
        'province'    => lg_field($lg, ['province_name_ne', 'province-name', 'province_name_en', 'Province-name-en']),
        'district'    => lg_field($lg, ['district_name_ne', 'district-name ne', 'district_name_en', 'district-name en']),
        'lg_name'     => lg_field($lg, ['lg_name_complete_ne', 'lg-name-np-full', 'lg_name_complete_en', 'lg-name-en-full', 'lg_name_ne', 'lg-name-ne']),
        'lg_type'     => lg_field($lg, ['lg_type_ne', 'lg-type-ne', 'lg_type_en', 'lg-type-en']),
        // English variants kept alongside, for future use (national-scale search, English toggle, etc.)
        'province_en' => lg_field($lg, ['province_name_en', 'Province-name-en']),
        'district_en' => lg_field($lg, ['district_name_en', 'district-name en']),
        'lg_name_en'  => lg_field($lg, ['lg_name_complete_en', 'lg-name-en-full', 'lg_name_en', 'lg-name-en']),
        'ward_count'  => lg_field($lg, ['lg_ward_count'], null),
        'domain'      => $lg['domain'] ?? '',
        'fetched_at'  => date('Y-m-d H:i:s'),
    ];

    if ($content === false || $content === null || $content === '' || $curlErr) {
        $base['status'] = 'failed';
        $base['error'] = $curlErr ?: "Empty response (HTTP {$httpCode})";
        $base['staff'] = [];
        return $base;
    }

    $decoded = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        $base['status'] = 'failed';
        $base['error'] = 'Invalid JSON in response (HTTP ' . $httpCode . ')';
        $base['staff'] = [];
        return $base;
    }

    $base['status'] = 'ok';
    $base['error'] = null;
    $base['staff'] = build_staff_list($lg, $decoded);
    return $base;
}

/**
 * Load the JSON cache file. Always returns a well-formed
 * structure even if the file doesn't exist yet.
 */
function load_cache($path = STAFF_CACHE_FILE)
{
    if (!file_exists($path)) {
        return ['generated_at' => null, 'by_lg' => []];
    }
    $raw = @file_get_contents($path);
    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data['by_lg'])) {
        return ['generated_at' => null, 'by_lg' => []];
    }
    return $data;
}

/**
 * Persist the cache structure to disk (atomic-ish via a temp
 * file + rename, so a reader never sees a half-written file).
 */
function save_cache($cache, $path = STAFF_CACHE_FILE)
{
    $tmp = $path . '.tmp';
    file_put_contents($tmp, json_encode($cache, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    rename($tmp, $path);
}
