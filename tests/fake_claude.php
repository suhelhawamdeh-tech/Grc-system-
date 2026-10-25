<?php
// Fake Claude Messages API for tests (php -S router). Every request is saved to last_request.json in the server's working
// directory so the tests can check what was sent. The question decides the reply: REFUSEME -> refusal, FAILME -> HTTP 500.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
header('Content-Type: application/json');
$raw = (string)file_get_contents('php://input');
$hdr = ['x-api-key' => $_SERVER['HTTP_X_API_KEY'] ?? '', 'anthropic-version' => $_SERVER['HTTP_ANTHROPIC_VERSION'] ?? '', 'anthropic-beta' => $_SERVER['HTTP_ANTHROPIC_BETA'] ?? ''];
file_put_contents(getcwd() . '/last_request.json', json_encode(['path' => $path, 'headers' => $hdr, 'raw' => $raw]));
if ($path !== '/v1/messages' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(404);
    echo json_encode(['type' => 'error', 'error' => ['type' => 'not_found_error', 'message' => 'not found']]);
    return true;
}
if ($hdr['x-api-key'] !== 'test-key') {
    http_response_code(401);
    echo json_encode(['type' => 'error', 'error' => ['type' => 'authentication_error', 'message' => 'invalid x-api-key']]);
    return true;
}
$body = json_decode($raw, true);
$q = (string)($body['messages'][0]['content'] ?? '');
if (str_contains($q, 'FAILME')) {
    http_response_code(500);
    echo json_encode(['type' => 'error', 'error' => ['type' => 'api_error', 'message' => 'boom']]);
    return true;
}
$usage = ['input_tokens' => 1234, 'output_tokens' => 56];
if (str_contains($q, 'REFUSEME')) {
    echo json_encode(['id' => 'msg_1', 'type' => 'message', 'role' => 'assistant', 'model' => $body['model'] ?? '', 'content' => [], 'stop_reason' => 'refusal', 'usage' => $usage]);
    return true;
}
$text = "Apply enhanced due diligence to higher-risk customers [S1].\n- Obtain senior management approval <script>alert(1)</script> [S2]\n- Invented source [S9]";
echo json_encode(['id' => 'msg_2', 'type' => 'message', 'role' => 'assistant', 'model' => $body['model'] ?? '', 'content' => [['type' => 'text', 'text' => $text]], 'stop_reason' => 'end_turn', 'usage' => $usage]);
return true;
