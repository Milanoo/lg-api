<?php
// ============================================================
// Elected Officials module config.
//
// elected-officials-api returns the exact same record shape as
// staff-api (Title/Designation/Email/Phone/Section/Tenure/Photo),
// so this file adds nothing new beyond staff_common.php except a
// different endpoint URL and a separate cache file - every
// parsing/caching function (build_staff_list, process_lg_response,
// load_cache, save_cache, normalize_domain, lg_field...) is reused
// as-is from staff_common.php.
// ============================================================

require_once __DIR__ . '/staff_common.php';

define('ELECTED_OFFICIALS_CACHE_FILE', __DIR__ . '/elected_officials_data.json');

/**
 * Build the elected-officials-api URL for a given LG.
 */
function elected_officials_api_url($lg)
{
    $domain = normalize_domain($lg['domain'] ?? '');
    return $domain . '/elected-officials-api';
}
