<?php
// Fake Temenos T24 REST API for tests (php -S router). Basic auth t24user / t24pass. IRIS-style paging: page_size, page_start; records under "body".
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
header('Content-Type: application/json');
if (($_SERVER['PHP_AUTH_USER'] ?? '') !== 't24user' || ($_SERVER['PHP_AUTH_PW'] ?? '') !== 't24pass') {
    http_response_code(401);
    echo json_encode(['header' => ['status' => 'failed'], 'error' => 'unauthorised']);
    return true;
}
$size = max(1, (int)($_GET['page_size'] ?? 100));
$start = max(1, (int)($_GET['page_start'] ?? 1));
if ($path === '/api/v1.0.0/party/customers') {
    $all = [];
    foreach ([['700001', 'API Customer One', '1001', 'LY', ['1001'], ['BRANCH'], 'N'], ['700002', 'API Customer Two', '2002', 'TN', ['FT', 'TT'], ['AGENT'], 'N'], ['700003', 'API Customer Three', '1001', 'LY', ['1001'], ['MOBILE'], 'Y'],
        ['700004', 'API Customer Four', '2001', 'LY', ['21070'], ['INTERNET'], 'N'], ['700005', 'API Customer Five', '4001', 'LY', ['1001'], ['BRANCH'], 'N']] as [$id, $nm, $sec, $res, $prods, $chans, $pep]) {
        $all[] = ['customerId' => $id, 'customerName' => $nm, 'sector' => $sec, 'residence' => $res, 'nationality' => $res, 'accounts' => array_map(fn($p) => ['category' => $p], $prods), 'channels' => $chans, 'pep' => $pep];
    }
    $page = array_slice($all, ($start - 1) * $size, $size);
    echo json_encode(['header' => ['status' => 'success', 'page_start' => $start, 'page_size' => $size, 'total_size' => count($all)], 'body' => $page]);
    return true;
}
if ($path === '/api/v1.0.0/accounts/glBalances') {
    echo json_encode(['header' => ['status' => 'success'], 'body' => $start > 1 ? [] : [['period' => date('Ym', strtotime('first day of last month')), 'plCategory' => '69100', 'amount' => '-777.250', 'narrative' => 'API loss']]]);
    return true;
}
http_response_code(404);
echo json_encode(['error' => 'not found']);
return true;
