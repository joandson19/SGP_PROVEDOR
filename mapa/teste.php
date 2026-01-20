<?php
require_once __DIR__ . '/config/conf.php';

function fetchApiData($url, $data) {
    $offset = 0;
    $limit = isset($data['limit']) ? (int)$data['limit'] : 100; 
    $allResults = [];
    if ($limit > 100){
        $limit = 100;
    }

    do {
        $data['offset'] = $offset;
        $queryString = http_build_query($data);
        $fullUrl = $url . '?' . $queryString;

        $ch = curl_init($fullUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

        $json = curl_exec($ch);        
        if ($json === false) {
            die(json_encode(["error" => "Erro na requisição cURL: " . curl_error($ch)]));
        }
        curl_close($ch);
        $response = json_decode($json, true);
        
        // if (!isset($response['result']) || !isset($response['paggination'])) {
        //     die(json_encode(["error" => "Resposta da API inválida"]));
        // }

        $allResults = array_merge($allResults, $response['result']);
        $offset = $response['paggination']['offset'] + $response['paggination']['returned'];
        $total = $response['paggination']['total'];

    } while ($offset < $total);

    return json_encode($allResults, JSON_PRETTY_PRINT);
}
$dataFirstApi = [
    "app" => $app,
    "token" => $token,
    "limit" => 999,
    "uf" => "BA",
    "status" => "ATIVO",
    "last_session" => true
];

$jsonFirstApi = fetchApiData($url . "/api/radius/radacct/list/all/", $dataFirstApi);
echo count(json_decode($jsonFirstApi));
echo $jsonFirstApi;
?>