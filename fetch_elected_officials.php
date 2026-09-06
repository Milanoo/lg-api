<?php
require_once __DIR__ . '/elected_officials_common.php';

$MODULE_API_URL_FN = 'elected_officials_api_url';
$MODULE_CACHE_FILE = ELECTED_OFFICIALS_CACHE_FILE;
$MODULE_RECORD_KEY = 'staff'; // process_lg_response() stores records under this key regardless of module

require __DIR__ . '/fetch_engine.php';
