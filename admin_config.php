<?php
// ============================================================
// Admin console access.
//
// CHANGE THIS PASSWORD before putting fetch_console.php anywhere
// reachable outside your own machine. This is a lightweight gate
// (a shared password, no per-user accounts) - enough to stop a
// random visitor from mass-triggering fetches, not a substitute
// for real authentication if this ever needs to be public-facing.
// ============================================================

return [
    'admin_password' => 'change-me-please',
];
