<?php
require_once("config/conf.php");

$data = array("app" => "$app", "token" => "$token", "limit" => 999, "uf" => "$uf", "status" => "$status", "last_session" => true );
$data_string = json_encode($data);

$ch = curl_init($url . '/ws/radius/radacct/list/all/');
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/json',
    'Content-Length: ' . strlen($data_string))
);

$json = curl_exec($ch);

if ($json === false) {
    echo json_encode(array("error" => "Erro cURL: " . curl_error($ch)));
} else {
    $obj = json_decode($json);
    $clientData = [];

    if ($obj !== null) {
        foreach ($obj->result as $cadastro) {
            if (isset($cadastro->endereco_longitude) && isset($cadastro->endereco_latitude)) {
                $longitude = $cadastro->endereco_longitude;
                $latitude = $cadastro->endereco_latitude;
				$nome = $cadastro->nome;
                $online = $cadastro->online;
                $statusIcon = ($online == true) ? 'images/green-icon.png' : 'images/red-icon.png';
				$nasPortId = null; // Inicialize como null

				// Verifique se há dados no array radacct
				if (isset($cadastro->radacct) && is_array($cadastro->radacct) && count($cadastro->radacct) > 0) {
					$nasPortId = $cadastro->radacct[0]->nasportid;
					$acctime = $cadastro->radacct[0]->acctstarttime;
					$stoptime = $cadastro->radacct[0]->acctstoptime;
					$ip = $cadastro->radacct[0]->framedipaddress;
				}
                $clientData[] = ["latitude" => $latitude, "longitude" => $longitude, "nome" => $nome, "statusIcon" => $statusIcon, "vlan" => $nasPortId, "acct" => $acctime, "stop" => $stoptime, "ip" => $ip];
            }
        }
    } else {
        echo json_encode(array("error" => "Erro ao decodificar a resposta JSON."));
    }

    curl_close($ch);
    echo json_encode($clientData);
}

?>