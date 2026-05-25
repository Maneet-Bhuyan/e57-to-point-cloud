<?php
/**
 * E57 Meshify — primary Hostinger entry (use index.php or default.php).
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/api-proxy.php';

if (handle_api_request()) {
    exit;
}

require __DIR__ . '/views/landing.php';
