<?php
// Fake API for the bulk tests (php -S router). State lives in a file next to the server.
$state = sys_get_temp_dir() . '/eev-php-fake-' . getenv('EEV_FAKE_ID') . '.json';
$s = is_file($state) ? json_decode(file_get_contents($state), true) : ['polls' => 0];
$send = function (int $code, $body, string $type = 'application/json') {
    http_response_code($code);
    header('Content-Type: ' . $type);
    echo is_string($body) ? $body : json_encode($body);
};
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$m = $_SERVER['REQUEST_METHOD'];
if (($_SERVER['HTTP_X_API_KEY'] ?? '') !== 'live_key') return $send(401, ['status' => 'error', 'message' => 'Invalid API key']);
if ($m === 'POST' && $path === '/bulk/upload') {
    $f = $_FILES['file'] ?? null;
    $s['upload'] = $f ? ['name' => $f['name'], 'content' => file_get_contents($f['tmp_name'])] : null;
    file_put_contents($state, json_encode($s));
    return $send(200, ['status' => 'accepted', 'list_id' => 'abc123', 'filename' => $f['name'] ?? '', 'uploaded' => 2, 'message' => 'ok']);
}
if ($path === '/bulk/status/abc123') {
    $s['polls']++;
    file_put_contents($state, json_encode($s));
    $done = $s['polls'] >= 2;
    return $send(200, ['list_id' => 'abc123', 'status' => $done ? 'completed' : 'processing', 'progress' => $done ? 100 : 50]);
}
if ($path === '/bulk/status') return $send(200, ['status' => 'ok', 'total_lists' => 1, 'lists' => []]);
if ($path === '/bulk/download/abc123') return $send(200, "Email,Result\na@example.com,valid\n", 'text/plain');
if ($m === 'DELETE' && $path === '/bulk/abc123') return $send(200, ['status' => 'deleted', 'list_id' => 'abc123', 'message' => 'deleted']);
if ($path === '/state') return $send(200, $s);
$send(404, ['status' => 'error', 'message' => 'Not found']);
