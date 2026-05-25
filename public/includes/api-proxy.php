<?php
/**
 * API proxy — forwards browser requests to Flask. Always returns valid JSON for API routes.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';

function proxy_json_response(int $httpCode, string $body): void
{
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo $body;
    exit;
}

function proxy_error_json(int $httpCode, string $message): void
{
    proxy_json_response($httpCode, json_encode(['error' => $message], JSON_UNESCAPED_SLASHES));
}

function ensure_valid_json_body(string $body, int $httpCode): array
{
    $trimmed = trim($body);
    if ($trimmed === '') {
        return [
            'code' => $httpCode >= 400 ? $httpCode : 502,
            'body' => json_encode([
                'error' => 'Empty response from Flask backend. Ensure the API is running at '
                    . backend_base()
                    . ' (python app.py).',
            ], JSON_UNESCAPED_SLASHES),
        ];
    }

    json_decode($trimmed);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $preview = strlen($trimmed) > 200 ? substr($trimmed, 0, 200) . '…' : $trimmed;
        return [
            'code' => 502,
            'body' => json_encode([
                'error' => 'Backend returned non-JSON response. Flask may have crashed or returned HTML.',
                'detail' => $preview,
            ], JSON_UNESCAPED_SLASHES),
        ];
    }

    return ['code' => $httpCode ?: 200, 'body' => $trimmed];
}

function curl_request(string $method, string $path, array $opts = []): array
{
    if (!function_exists('curl_init')) {
        return [
            'code' => 500,
            'body' => json_encode(['error' => 'PHP cURL extension is not enabled on this server.']),
        ];
    }

    $url = backend_base() . $path;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => $opts['timeout'] ?? 120,
        CURLOPT_HTTPHEADER => $opts['headers'] ?? ['Accept: application/json'],
    ]);

    if (!empty($opts['post_fields'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $opts['post_fields']);
    }

    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        return [
            'code' => 502,
            'body' => json_encode([
                'error' => "Cannot reach Flask at {$url}. {$err}",
                'hint' => 'Run: cd Shubh-Ps-main && python app.py',
            ], JSON_UNESCAPED_SLASHES),
        ];
    }

    return ensure_valid_json_body((string) $body, $code);
}

/**
 * Handle ?api= requests. Returns true if the request was handled (script should exit).
 */
function handle_api_request(): bool
{
    $api = $_GET['api'] ?? null;
    if ($api === null || $api === '') {
        return false;
    }

    switch ($api) {
        case 'health':
            $res = curl_request('GET', '/health', ['timeout' => 10]);
            proxy_json_response($res['code'], $res['body']);
            break;

        case 'status':
            $jobId = $_GET['job_id'] ?? '';
            if ($jobId === '' || !preg_match('/^[a-f0-9\-]{36}$/i', $jobId)) {
                proxy_error_json(400, 'Invalid job_id.');
            }
            $res = curl_request('GET', '/process/status/' . rawurlencode($jobId), ['timeout' => 30]);
            proxy_json_response($res['code'], $res['body']);
            break;

        case 'mesh':
            $file = $_GET['file'] ?? '';
            if ($file === '' || preg_match('/[\/\\\\]/', $file)) {
                http_response_code(400);
                header('Content-Type: text/plain; charset=utf-8');
                echo 'Invalid mesh file.';
                exit;
            }
            if (!function_exists('curl_init')) {
                http_response_code(500);
                echo 'cURL not available.';
                exit;
            }
            $url = backend_base() . '/static/meshes/' . rawurlencode($file);
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 300,
            ]);
            $data = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: 'model/obj';
            curl_close($ch);
            if ($data === false || $code >= 400) {
                http_response_code(502);
                header('Content-Type: text/plain; charset=utf-8');
                echo 'Failed to fetch mesh from backend at ' . $url;
                exit;
            }
            header('Content-Type: ' . $type);
            header('Content-Disposition: attachment; filename="' . basename($file) . '"');
            echo $data;
            exit;

        case 'process':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                proxy_error_json(405, 'POST required.');
            }
            if (!isset($_FILES['file'])) {
                proxy_error_json(400, 'No file field in request.');
            }
            $uploadErr = $_FILES['file']['error'];
            if ($uploadErr !== UPLOAD_ERR_OK) {
                $messages = [
                    UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize in php.ini.',
                    UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE in form.',
                    UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
                    UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
                    UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder on server.',
                    UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                    UPLOAD_ERR_EXTENSION => 'Upload blocked by PHP extension.',
                ];
                proxy_error_json(400, $messages[$uploadErr] ?? "Upload error code: {$uploadErr}");
            }

            $post = [
                'file' => new CURLFile(
                    $_FILES['file']['tmp_name'],
                    $_FILES['file']['type'] ?: 'application/octet-stream',
                    $_FILES['file']['name']
                ),
                'quality' => $_POST['quality'] ?? 'Medium',
                'contamination' => $_POST['contamination'] ?? '',
                'ball_radius_multiplier' => $_POST['ball_radius_multiplier'] ?? '',
                'smoothing_iterations' => $_POST['smoothing_iterations'] ?? '',
            ];

            $res = curl_request('POST', '/process', [
                'post_fields' => $post,
                'timeout' => 600,
            ]);
            proxy_json_response($res['code'], $res['body']);
            break;

        default:
            proxy_error_json(404, 'Unknown API endpoint.');
    }

    return true;
}
