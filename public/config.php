<?php
/**
 * E57 Meshify — shared configuration
 * Deploy config.php + includes/ alongside index.php in public_html.
 */
declare(strict_types=1);

// ═══════════════════════════════════════════════════════════════════════════════
// BACKEND URL — Flask API (run from project /backend folder)
//
// LOCAL:  http://127.0.0.1:5000
// VPS:    http://YOUR.PUBLIC.IP:5000   ← swap before Hostinger deploy
// ═══════════════════════════════════════════════════════════════════════════════
const FLASK_BACKEND_URL = 'http://127.0.0.1:5000';

const UPLOAD_MAX_BYTES = 1073741824; // 1024 MB (match php.ini)

function backend_base(): string
{
    $env = getenv('FLASK_BACKEND_URL');
    return rtrim($env !== false && $env !== '' ? $env : FLASK_BACKEND_URL, '/');
}

function entry_script(): string
{
    $script = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
    return preg_match('/^[a-zA-Z0-9_\-\.]+\.php$/', $script) ? $script : 'index.php';
}
