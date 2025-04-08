<?php
// Inicio da função auth
require_once("auth.php");

require_once("config/conf.php");

// Função para buscar dados da API via GET com suporte a paginação (apenas para a primeira consulta)
function fetchApiDataWithPagination($url, $data) {
    $offset = 0;
    $limit = isset($data['limit']) ? (int)$data['limit'] : 100; 
    $allResults = [];
    if ($limit > 100) {
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

        // Debug: Exibe a resposta da API
        error_log("Resposta da API: " . $json);

        $response = json_decode($json, true);

        // Verifica se a resposta da API é válida
        if ($response === null) {
            die(json_encode(["error" => "Resposta da API não é um JSON válido"]));
        }

        // Verifica se a resposta contém um erro
        if (isset($response['error'])) {
            die(json_encode(["error" => "Erro na API: " . $response['error']]));
        }

        // Verifica se a resposta contém dados
        if (!isset($response['result'])) {
            // Se não houver 'result', mas a resposta não for um erro, trata como uma resposta vazia
            error_log("Aviso: Campo 'result' ausente na resposta da API. Retornando dados vazios.");
            return json_encode(["result" => [], "paggination" => ["total" => 0]]);
        }

        // Verifica se a paginação está presente
        if (!isset($response['paggination'])) {
            die(json_encode(["error" => "Paginação ausente na resposta da API"]));
        }

        // Adiciona os resultados ao array
        $allResults = array_merge($allResults, $response['result']);
        $offset = $response['paggination']['offset'] + $response['paggination']['returned'];
        $total = $response['paggination']['total'];

    } while ($offset < $total);

    return json_encode(["result" => $allResults, "paggination" => ["total" => $total]]);
}

// Função para buscar dados da API via GET sem paginação (para a segunda consulta)
function fetchApiDataWithoutPagination($url, $data) {
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

    // Debug: Exibe a resposta da API
    error_log("Resposta da API (sem paginação): " . $json);

    $response = json_decode($json, true);

    // Verifica se a resposta da API é válida
    if ($response === null) {
        die(json_encode(["error" => "Resposta da API não é um JSON válido"]));
    }

    // Verifica se a resposta contém um erro
    if (isset($response['error'])) {
        die(json_encode(["error" => "Erro na API: " . $response['error']]));
    }

    return $json;
}

// Função para processar os dados da primeira API
function parseClientData($json) {
    $obj = json_decode($json, true);
    $clientData = [];

    if ($obj !== null && isset($obj['result'])) {
        foreach ($obj['result'] as $cadastro) {
            // Verifica se os campos obrigatórios estão presentes e não estão vazios
            if (!empty($cadastro['endereco_longitude']) && !empty($cadastro['endereco_latitude'])) {
                $longitude = $cadastro['endereco_longitude'];
                $latitude = $cadastro['endereco_latitude'];
                $nome = trim($cadastro['nome']);
                $online = $cadastro['online'] ?? false;
                
                $nasPortId = $acctime = $stoptime = $ip = $username = null;

                if (!empty($cadastro['radacct']) && isset($cadastro['radacct'][0]['username'])) {
                    $username = strtolower(trim($cadastro['radacct'][0]['username']));
                    $nasPortId = $cadastro['radacct'][0]['nasportid'] ?? null;
                    $acctime = $cadastro['radacct'][0]['acctstarttime'] ?? null;
                    $stoptime = $cadastro['radacct'][0]['acctstoptime'] ?? null;
                    $ip = $cadastro['radacct'][0]['framedipaddress'] ?? null;
					$ipv6 = $cadastro['radacct'][0]['delegatedipv6prefix'] ?? null;
                }

                $clientData[] = [
                    "latitude" => $latitude,
                    "longitude" => $longitude,
                    "nome" => $nome,
                    "username" => $username,
                    "vlan" => $nasPortId,
                    "acct" => formatarData($acctime),
                    "stop" => formatarData($stoptime),
                    "ip" => $ip,
					"ipv6" => $ipv6,
					"online" => $online
                ];
            } else {
                // Log para depuração
                error_log("Cadastro ignorado: campos 'endereco_longitude' ou 'endereco_latitude' ausentes ou vazios.");
            }
        }
    }

    return $clientData;
}

function formatarData($dataISO) {
    if (!$dataISO) {
        return ""; // Retorna um traço se a data for nula ou vazia
    }
    
    $data = DateTime::createFromFormat('Y-m-d\TH:i:s', $dataISO);
    
    return $data ? $data->format('d/m/Y H:i:s') : "-";
}

// Função para buscar e processar os dados da segunda API
function fetchOnuData($url, $data) {
    $json = fetchApiDataWithoutPagination($url, $data);
    $onuData = [];

    if ($json !== false) {
        $obj = json_decode($json, true);
        if ($obj !== null) {
            foreach ($obj as $onu) {
                if (!empty($onu['service_login'])) {
                    $serviceLogin = strtolower(trim($onu['service_login']));
                    $onuData[$serviceLogin] = [
                        "cto" => $onu['cto'] ?? null,
                        "ctoport" => $onu['ctoport'] ?? null,
                        "info_rx" => isset($onu['info_rx']) ? trim($onu['info_rx']) : null,
                        "onuid" => $onu['id'] ?? null
                    ];
                }
            }
        }
    }

    return $onuData;
}

// Função para combinar os dados das duas APIs
function combineData($clientData, $onuData) {
    $combinedData = [];

    foreach ($clientData as $client) {
        $username = strtolower(trim($client['username'] ?? ""));
        $encontrado = false;

        if ($username !== "" && isset($onuData[$username])) {
            $combinedData[] = array_merge($client, $onuData[$username]);
            $encontrado = true;
        }

        if (!$encontrado) {
            $combinedData[] = $client;
        }
    }

    return $combinedData;
}

// Dados para a primeira API
$dataFirstApi = [
    "app" => $app,
    "token" => $token,
    "limit" => 999,
    "uf" => $uf,
    "status" => $status,
    "last_session" => true
];

// Dados para a segunda API
$dataSecondApi = [
    "app" => $app,
    "token" => $token
];

// Busca dados da primeira API (com paginação)
$jsonFirstApi = fetchApiDataWithPagination($url . '/api/radius/radacct/list/all/', $dataFirstApi);

if ($jsonFirstApi === false) {
    echo json_encode(["error" => "Erro cURL na primeira API."]);
    exit;
}

// Processa os dados da primeira API
$clientData = parseClientData($jsonFirstApi);

// Busca dados da segunda API (sem paginação)
$onuData = fetchOnuData($url . '/api/fttx/onu/list/', $dataSecondApi);

// Combina os dados das duas APIs
$combinedData = combineData($clientData, $onuData);

// Retorna o JSON combinado
header('Content-Type: application/json');
echo json_encode($combinedData);
?>