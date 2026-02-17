<?php

declare(strict_types=1);

$psPromptsCandidates = [
    __DIR__ . '/../src/prompts.php',
    __DIR__ . '/src/prompts.php',
];
$psPromptsLoaded = false;
foreach ($psPromptsCandidates as $psPromptsPath) {
    if (is_file($psPromptsPath)) {
        require_once $psPromptsPath;
        $psPromptsLoaded = true;
        break;
    }
}
if (!$psPromptsLoaded) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Missing prompts.php (expected ../src/prompts.php or ./src/prompts.php)';
    exit;
}

function ps_load_dotenv(string $path): void
{
    if (!is_file($path)) return;
    $raw = @file_get_contents($path);
    if (!is_string($raw) || $raw === '') return;

    foreach (preg_split("/\\r\\n|\\n|\\r/", $raw) as $line) {
        if (!is_string($line)) continue;
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (str_starts_with($line, 'export ')) $line = trim(substr($line, 7));

        $pos = strpos($line, '=');
        if ($pos === false || $pos < 1) continue;

        $key = trim(substr($line, 0, $pos));
        if ($key === '' || !preg_match('/^[A-Z0-9_]+$/', $key)) continue;

        $existing = getenv($key);
        if ($existing !== false && trim($existing) !== '') continue;
        $existing = $_SERVER[$key] ?? null;
        if (is_string($existing) && trim($existing) !== '') continue;
        $existing = $_ENV[$key] ?? null;
        if (is_string($existing) && trim($existing) !== '') continue;

        $value = trim(substr($line, $pos + 1));
        if ($value === '') {
            putenv($key . '=');
            $_ENV[$key] = '';
            $_SERVER[$key] = '';
            continue;
        }

        $quote = $value[0] ?? '';
        if (($quote === '"' || $quote === "'") && str_ends_with($value, $quote)) {
            $value = substr($value, 1, -1);
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

function ps_get_env(string $name): ?string
{
    $v = getenv($name);
    if (is_string($v) && trim($v) !== '') return $v;
    $v = $_SERVER[$name] ?? null;
    if (is_string($v) && trim($v) !== '') return $v;
    $v = $_ENV[$name] ?? null;
    if (is_string($v) && trim($v) !== '') return $v;
    return null;
}

function ps_json_response(int $status, array $data): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ps_html_response(int $status, string $html): never
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo $html;
    exit;
}

function ps_get_header(string $name): ?string
{
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    $v = $_SERVER[$key] ?? null;
    if (!is_string($v) || trim($v) === '') return null;
    return $v;
}

function ps_normalize_lang(?string $value): ?string
{
    if ($value === null) return null;
    $v = strtolower(trim($value));
    if ($v === '') return null;
    if (in_array($v, ['uk', 'uk-ua', 'ua', 'ua-ua'], true)) return 'uk';
    if (in_array($v, ['en', 'en-us', 'en-gb'], true)) return 'en';
    $primary = explode('-', $v, 2)[0] ?? $v;
    if ($primary === 'ua') return 'uk';
    if ($primary === 'uk') return 'uk';
    if ($primary === 'en') return 'en';
    return null;
}

function ps_parse_accept_language(string $headerValue): array
{
    $out = [];
    foreach (explode(',', $headerValue) as $raw) {
        $part = trim($raw);
        if ($part === '') continue;
        $bits = array_values(array_filter(array_map('trim', explode(';', $part)), fn($b) => $b !== ''));
        $tag = strtolower($bits[0] ?? '');
        if ($tag === '') continue;
        $q = 1.0;
        foreach (array_slice($bits, 1) as $b) {
            if (str_starts_with(strtolower($b), 'q=')) {
                $qRaw = explode('=', $b, 2)[1] ?? '';
                $qVal = floatval($qRaw);
                if ($qVal > 0) $q = $qVal;
            }
        }
        $out[] = [$tag, $q];
    }
    usort($out, fn($a, $b) => ($b[1] <=> $a[1]));
    return $out;
}

function ps_pick_lang(?string $explicitLang, ?string $acceptLanguage): string
{
    $explicit = ps_normalize_lang($explicitLang);
    if ($explicit !== null) return $explicit;

    if ($acceptLanguage !== null) {
        foreach (ps_parse_accept_language($acceptLanguage) as $entry) {
            $tag = $entry[0];
            $normalized = ps_normalize_lang($tag);
            if ($normalized !== null) return $normalized;
        }
    }

    return 'uk';
}

function ps_get_int_env(string $name, int $default): int
{
    $raw = ps_get_env($name);
    if ($raw === null || trim($raw) === '') return $default;
    $v = intval($raw);
    return $v > 0 ? $v : $default;
}

function ps_http_post_json(string $url, array $headers, array $payload, int $timeoutSeconds): array
{
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($body)) {
        throw new RuntimeException('Failed to encode JSON payload');
    }

    $headerLines = [];
    foreach ($headers as $k => $v) {
        $headerLines[] = $k . ': ' . $v;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headerLines),
            'content' => $body,
            'timeout' => $timeoutSeconds,
            'ignore_errors' => true,
        ],
    ]);

    $resp = @file_get_contents($url, false, $context);
    $respBody = is_string($resp) ? $resp : '';

    $status = 0;
    $hdr0 = $http_response_header[0] ?? '';
    if (is_string($hdr0) && preg_match('#HTTP/\\S+\\s(\\d{3})#', $hdr0, $m)) {
        $status = intval($m[1]);
    }

    return [$status, $respBody];
}

function ps_extract_output_text(array $response): string
{
    $ot = $response['output_text'] ?? null;
    if (is_string($ot) && trim($ot) !== '') return $ot;

    $out = $response['output'] ?? null;
    if (!is_array($out)) return '';

    $chunks = [];
    foreach ($out as $item) {
        if (!is_array($item)) continue;
        $content = $item['content'] ?? null;
        if (!is_array($content)) continue;
        foreach ($content as $part) {
            if (!is_array($part)) continue;
            if (($part['type'] ?? '') !== 'output_text') continue;
            $text = $part['text'] ?? null;
            if (is_string($text) && $text !== '') $chunks[] = $text;
        }
    }
    return implode("\n", $chunks);
}

function ps_extract_json_from_text(string $text): array
{
    $raw = trim($text);
    $data = json_decode($raw, true);
    if (is_array($data)) return $data;

    $start = strpos($raw, '{');
    $end = strrpos($raw, '}');
    if ($start === false || $end === false || $end <= $start) {
        throw new RuntimeException('Failed to parse JSON from model output');
    }
    $slice = substr($raw, $start, $end - $start + 1);
    $data2 = json_decode($slice, true);
    if (!is_array($data2)) {
        throw new RuntimeException('Failed to parse JSON from model output');
    }
    return $data2;
}

function ps_trim_answer_text(string $answer, int $maxLen = 280, int $maxSentences = 2): string
{
    $text = trim(preg_replace('/\s+/', ' ', $answer));
    if ($text === '') return '';

    $parts = preg_split('/(?<=[\.\!\?])\s+/', $text);
    if (is_array($parts)) {
        $clean = [];
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part !== '') $clean[] = $part;
        }
        if ($clean) {
            $text = implode(' ', array_slice($clean, 0, $maxSentences));
        }
    }

    if (strlen($text) <= $maxLen) return $text;

    $short = substr($text, 0, $maxLen - 1);
    $lastSpace = strrpos($short, ' ');
    if ($lastSpace === false) {
        return substr($text, 0, $maxLen - 1) . '…';
    }
    return substr($text, 0, $lastSpace) . '…';
}

function ps_build_ai_fallback_answer(array $results, string $lang): ?string
{
    if ($results === []) return null;

    $parts = [];
    foreach (array_slice($results, 0, 2) as $item) {
        if (!is_array($item)) continue;
        $title = trim(strval($item['title'] ?? ''));
        $snippet = trim(strval($item['snippet'] ?? ''));
        $line = trim($title . '. ' . $snippet);
        if ($line !== '.') $parts[] = $line;
    }

    if (!$parts) return null;

    $prefix = $lang === 'uk' ? 'На основі знайдених сторінок: ' : 'Based on found pages: ';
    $answer = $prefix . implode(' ', $parts);
    $trimmed = ps_trim_answer_text($answer);
    return $trimmed === '' ? null : $trimmed;
}

function ps_domain_from_url(string $url): ?string
{
    $parts = parse_url($url);
    $host = $parts['host'] ?? null;
    return is_string($host) && $host !== '' ? $host : null;
}

function ps_truncate(string $value, int $maxLen): string
{
    if ($maxLen <= 0) return '';
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLen);
    }
    return substr($value, 0, $maxLen);
}

function ps_is_path_in_dir(string $path, string $dir): bool
{
    $dirReal = realpath($dir);
    $pathReal = realpath($path);
    if (!is_string($dirReal) || $dirReal === '') $dirReal = $dir;
    if (!is_string($pathReal) || $pathReal === '') $pathReal = $path;

    $dirNorm = rtrim(str_replace('\\', '/', $dirReal), '/') . '/';
    $pathNorm = str_replace('\\', '/', $pathReal);

    if (DIRECTORY_SEPARATOR === '\\') {
        $dirNorm = strtolower($dirNorm);
        $pathNorm = strtolower($pathNorm);
    }

    return str_starts_with($pathNorm, $dirNorm);
}

function ps_guess_mime_type(string $filePath): string
{
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    return match ($ext) {
        'html' => 'text/html; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
        'css' => 'text/css; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'map' => 'application/json; charset=utf-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'ico' => 'image/x-icon',
        'txt' => 'text/plain; charset=utf-8',
        'woff2' => 'font/woff2',
        'woff' => 'font/woff',
        'ttf' => 'font/ttf',
        'otf' => 'font/otf',
        default => 'application/octet-stream',
    };
}

function ps_public_root(): string
{
    $root = realpath(__DIR__);
    return is_string($root) && $root !== '' ? $root : __DIR__;
}

function ps_realpath_in_public(string $requestPath): ?string
{
    $root = ps_public_root();
    $path = rawurldecode($requestPath);
    $path = str_replace("\0", '', $path);
    if ($path === '' || $path[0] !== '/') $path = '/' . $path;

    $base = basename($path);
    if ($base !== '' && str_starts_with($base, '.')) return null;
    if (str_ends_with(strtolower($base), '.php')) return null;
    if ($base === '.env') return null;

    $candidate = $root . DIRECTORY_SEPARATOR . ltrim($path, '/');
    if (is_dir($candidate)) {
        $candidate = rtrim($candidate, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'index.html';
    }
    if (!is_file($candidate)) return null;

    $real = realpath($candidate);
    if ($real === false) return null;
    if (!ps_is_path_in_dir($real, $root)) return null;

    return $real;
}

function ps_send_file(string $filePath, string $mime, string $cacheControl, string $method): never
{
    http_response_code(200);
    header('Content-Type: ' . $mime);
    header('Cache-Control: ' . $cacheControl);
    header('X-Content-Type-Options: nosniff');

    $size = @filesize($filePath);
    if (is_int($size) && $size >= 0) header('Content-Length: ' . $size);

    if ($method === 'HEAD') exit;

    $fh = @fopen($filePath, 'rb');
    if ($fh === false) ps_html_response(500, '<h1>500</h1><p>Failed to read file.</p>');
    fpassthru($fh);
    fclose($fh);
    exit;
}

function ps_serve_spa(string $method): never
{
    $indexPath = ps_realpath_in_public('/index.html');
    if ($indexPath === null) {
        ps_html_response(
            500,
            '<h1>Frontend build not found</h1>'
                . '<p>Build the frontend and copy <code>frontend/dist</code> into <code>php/public</code>.</p>',
        );
    }

    ps_send_file($indexPath, 'text/html; charset=utf-8', 'no-cache, no-store, must-revalidate', $method);
}

function ps_handle_search(): never
{
    $acceptLanguage = ps_get_header('Accept-Language');
    $payloadRaw = file_get_contents('php://input');
    $payload = json_decode($payloadRaw ?: '', true);
    if (!is_array($payload)) {
        ps_json_response(400, ['detail' => 'Invalid JSON body']);
    }

    $query = $payload['query'] ?? null;
    $query = is_string($query) ? trim($query) : '';
    $query = $query !== '' ? $query : '';

    $images = $payload['images'] ?? [];
    if ($images === null) $images = [];
    if (!is_array($images)) {
        ps_json_response(422, ['detail' => 'images must be an array']);
    }

    $images = array_values(array_filter(array_map(fn($v) => is_string($v) ? trim($v) : '', $images), fn($v) => $v !== ''));
    if (count($images) > 6) $images = array_slice($images, 0, 6);
    foreach ($images as $img) {
        if (!str_starts_with($img, 'data:image/')) {
            ps_json_response(422, ['detail' => 'images must be data:image/* URLs']);
        }
    }

    if ($query === '' && count($images) === 0) {
        ps_json_response(422, ['detail' => 'query or images is required']);
    }

    $lang = ps_pick_lang(is_string($payload['lang'] ?? null) ? $payload['lang'] : null, $acceptLanguage);

    $limit = $payload['limit'] ?? 8;
    $limit = is_int($limit) ? $limit : intval(is_string($limit) ? $limit : 8);
    if ($limit < 1) $limit = 1;
    if ($limit > 10) $limit = 10;
    $maxResults = ps_get_int_env('MAX_RESULTS', 8);
    if ($limit > $maxResults) $limit = $maxResults;

    $apiKey = ps_get_env('OPENAI_API_KEY');
    if ($apiKey === null || trim($apiKey) === '') {
        ps_json_response(500, [
            'detail' => $lang === 'en' ? 'OPENAI_API_KEY is not set' : 'Не задано OPENAI_API_KEY',
        ]);
    }
    $apiKey = trim($apiKey);

    $model = ps_get_env('OPENAI_MODEL');
    $model = $model !== null && trim($model) !== '' ? trim($model) : 'gpt-4o-mini';

    $userContent = [
        ['type' => 'input_text', 'text' => ps_user_prompt($query, $lang, $limit, count($images))],
    ];
    foreach ($images as $img) {
        $userContent[] = ['type' => 'input_image', 'image_url' => $img];
    }

    $started = microtime(true);
    try {
        [$status, $body] = ps_http_post_json(
            'https://api.openai.com/v1/responses',
            [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $apiKey,
            ],
            [
                'model' => $model,
                'input' => [
                    ['role' => 'system', 'content' => ps_system_prompt()],
                    ['role' => 'user', 'content' => $userContent],
                ],
                'tools' => [['type' => 'web_search_preview']],
                'temperature' => 0.2,
            ],
            60,
        );
    } catch (Throwable) {
        ps_json_response(502, [
            'detail' => $lang === 'en' ? 'Search failed. Please try again.' : 'Пошук не вдався. Спробуйте ще раз.',
        ]);
    }

    if ($status < 200 || $status >= 300) {
        ps_json_response(502, [
            'detail' => $lang === 'en' ? 'Search failed. Please try again.' : 'Пошук не вдався. Спробуйте ще раз.',
        ]);
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        ps_json_response(502, [
            'detail' => $lang === 'en' ? 'Search failed. Please try again.' : 'Пошук не вдався. Спробуйте ще раз.',
        ]);
    }

    $outputText = ps_extract_output_text($decoded);
    if (trim($outputText) === '') {
        ps_json_response(502, [
            'detail' => $lang === 'en' ? 'Search failed. Please try again.' : 'Пошук не вдався. Спробуйте ще раз.',
        ]);
    }

    try {
        $data = ps_extract_json_from_text($outputText);
    } catch (Throwable) {
        ps_json_response(502, [
            'detail' => $lang === 'en' ? 'Search failed. Please try again.' : 'Пошук не вдався. Спробуйте ще раз.',
        ]);
    }

    $items = $data['results'] ?? [];
    if (!is_array($items)) $items = [];

    $results = [];
    $seen = [];
    foreach ($items as $item) {
        if (count($results) >= $limit) break;
        if (!is_array($item)) continue;

        $title = trim(strval($item['title'] ?? ''));
        $url = trim(strval($item['url'] ?? ''));
        $snippet = trim(strval($item['snippet'] ?? ''));
        $source = $item['source'] ?? null;
        $source = is_string($source) ? trim($source) : null;

        if ($title === '' || $url === '' || $snippet === '') continue;
        if (!str_starts_with($url, 'https://') && !str_starts_with($url, 'http://')) continue;
        if (isset($seen[$url])) continue;
        $seen[$url] = true;

        if ($source === null || $source === '') $source = ps_domain_from_url($url);

        $results[] = [
            'title' => ps_truncate($title, 200),
            'url' => ps_truncate($url, 2048),
            'snippet' => ps_truncate($snippet, 600),
            'source' => $source !== null ? ps_truncate($source, 120) : null,
        ];
    }

    $rawAnswer = $data['answer'] ?? null;
    $answer = null;
    if (is_string($rawAnswer)) {
        $answer = ps_trim_answer_text(trim($rawAnswer));
        if ($answer === '') {
            $answer = null;
        }
    }

    if ($answer === null) {
        $answer = ps_build_ai_fallback_answer($results, $lang);
    }

    $tookMs = (int) round((microtime(true) - $started) * 1000);
    ps_json_response(200, [
        'query' => $query,
        'lang' => $lang,
        'answer' => $answer,
        'results' => $results,
        'took_ms' => $tookMs,
    ]);
}

ps_load_dotenv(__DIR__ . '/.env');
ps_load_dotenv(dirname(__DIR__) . '/.env');

$method = $_SERVER['REQUEST_METHOD'] ?? '';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = is_string($path) ? $path : '/';
if ($path === '') $path = '/';
if ($path[0] !== '/') $path = '/' . $path;

if (!str_starts_with($path, '/.well-known') && (str_starts_with($path, '/.') || str_contains($path, '/.'))) {
    ps_json_response(404, ['detail' => 'Not found']);
}

if (PHP_SAPI === 'cli-server') {
    $static = ps_realpath_in_public($path);
    if ($static !== null) return false;
}

if ($path === '/api/health') {
    if ($method !== 'GET') ps_json_response(405, ['detail' => 'Method not allowed']);
    ps_json_response(200, ['status' => 'ok']);
}

if ($path === '/api/search') {
    if ($method !== 'POST') ps_json_response(405, ['detail' => 'Method not allowed']);
    ps_handle_search();
}

if (!str_starts_with($path, '/api/')) {
    if ($method !== 'GET' && $method !== 'HEAD') ps_json_response(405, ['detail' => 'Method not allowed']);

    $static = ps_realpath_in_public($path);
    if ($static !== null) {
        if (basename($static) === 'index.html') {
            ps_send_file($static, 'text/html; charset=utf-8', 'no-cache, no-store, must-revalidate', $method);
        } else {
            $cache = str_starts_with($path, '/assets/')
                ? 'public, max-age=31536000, immutable'
                : 'public, max-age=3600';
            ps_send_file($static, ps_guess_mime_type($static), $cache, $method);
        }
    }

    ps_serve_spa($method);
}

ps_json_response(404, ['detail' => 'Not found']);
