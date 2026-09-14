<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=86400');

$resource = (string) ($_GET['resource'] ?? 'states');
$url = 'https://servicodados.ibge.gov.br/api/v1/localidades/estados?orderBy=nome';

if ($resource === 'cities') {
    $uf = strtoupper(trim((string) ($_GET['uf'] ?? '')));
    if (!preg_match('/^[A-Z]{2}$/', $uf)) {
        http_response_code(400);
        echo json_encode(['error' => 'UF inválida.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $url = 'https://servicodados.ibge.gov.br/api/v1/localidades/estados/' . rawurlencode($uf) . '/municipios?orderBy=nome';
} elseif ($resource === 'specialties') {
    $url = 'https://servicodados.ibge.gov.br/api/v2/cnae/subclasses';
} elseif ($resource !== 'states') {
    http_response_code(400);
    echo json_encode(['error' => 'Recurso inválido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$context = stream_context_create([
    'http' => ['timeout' => 8],
    'https' => ['timeout' => 8],
]);
$response = @file_get_contents($url, false, $context);

if ($response === false) {
    http_response_code(502);
    echo json_encode(['error' => 'Não foi possível consultar o IBGE.'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo $response;
