<?php
/**
 * E57 Meshify — alternate Hostinger entry (identical to index.php).
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/api-proxy.php';

if (handle_api_request()) {
    exit;
}

require __DIR__ . '/views/landing.php';
