<?php
require_once("config/conf.php");
session_start();

// Verificar se o captcha foi validado
if (!isset($_SESSION['validated']) || $_SESSION['validated'] !== true) {
    header('Location: validar.php');
    exit;
}

// Função para obter dados da API com tratamento de erros
function getApiData($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        die("Erro ao conectar à API: " . curl_error($ch));
    }
    curl_close($ch);
    return $response;
}

// URL da API
$apiUrl = "$url/api/fttx/splitter/all/?token=$token&app=$app";

// Obter e decodificar os dados da API
$response = getApiData($apiUrl);
$ctos = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    die("Erro ao decodificar JSON: " . json_last_error_msg());
}

if (!$ctos || !is_array($ctos)) {
    die("Erro ao obter dados da API ou JSON inválido.");
}

// Filtrar e sanitizar dados das CTOs
$filteredCtos = array_map(function($cto) {
    return [
        'ident' => htmlspecialchars($cto['ident'] ?? '', ENT_QUOTES, 'UTF-8'),
        'map_ll' => htmlspecialchars($cto['map_ll'] ?? '', ENT_QUOTES, 'UTF-8'),
        'ports' => intval($cto['ports'] ?? 0),
        'busy_ports' => array_map('intval', $cto['busy_ports'] ?? []),
        'note' => htmlspecialchars($cto['note'] ?? '', ENT_QUOTES, 'UTF-8'),
        'id' => intval($cto['id'] ?? 0)
    ];
}, $ctos);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mapa de Cobertura FTTH</title>
    <script async defer src="https://maps.googleapis.com/maps/api/js?key=<?php echo $googleMapsApiKey; ?>&libraries=geometry"></script>
    <link rel="stylesheet" href="css/style.css">
    <style>
        #loading {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div id="map" style="height: 100vh;"></div>
    <div id="loading">Calculando cobertura...</div>

    <script>
        // Dados das CTOs obtidas do PHP (JSON convertido para JavaScript)
        const ctos = <?php echo json_encode($filteredCtos); ?>;
        const markers = [];
        const ctoLocations = [];

        // Função para inicializar o mapa
        function initMap() {
            const map = new google.maps.Map(document.getElementById("map"), {
                center: new google.maps.LatLng(<?php echo $centralLatitude; ?>, <?php echo $centralLongitude; ?>),
                zoom: 15,
            });

            const service = new google.maps.DirectionsService();

            // Adiciona marcadores e calcula cobertura para cada CTO
            ctos.forEach(cto => {
                if (cto.map_ll) {
                    const coords = cto.map_ll.split(",");
                    const lat = parseFloat(coords[0].trim());
                    const lng = parseFloat(coords[1].trim());

                    const marker = createMarker(cto, map, lat, lng);
                    markers.push(marker);
                    ctoLocations.push({ lat, lng, marker });

                    const infoWindow = createInfoWindow(cto, marker, map);
                    marker.addListener('click', () => {
                        infoWindow.open(map, marker);
                    });

                    calculateCoverage(cto, lat, lng, service, map);
                }
            });
        }

        // Função para criar um marcador de CTO
        function createMarker(cto, map, lat, lng) {
            return new google.maps.Marker({
                position: { lat, lng },
                map: map,
                title: cto.ident,
                icon: {
                    url: 'images/cto.png',
                    scaledSize: new google.maps.Size(30, 30),
                    origin: new google.maps.Point(0, 0),
                    anchor: new google.maps.Point(15, 15)
                }
            });
        }

        // Função para criar um InfoWindow
        function createInfoWindow(cto, marker, map) {
            return new google.maps.InfoWindow({
                content: `
                    <div>
                        <h2>➡ ${cto.ident} ⬅</h2>
                        <p><strong>Número de Portas:</strong> ${cto.ports}</p>
                        <p><strong>Portas ocupadas:</strong><font color="red"> ${cto.busy_ports ? cto.busy_ports.join(', ') : 'Nenhuma'}</font></p>
                        <p><strong>Observações:</strong> ${cto.note || 'Nenhuma'}</p>
                    </div>
                `
            });
        }

		// Função para calcular a cobertura de uma CTO
		function calculateCoverage(cto, lat, lng, service, map) {
			const directionsRequests = [];
			const angles = [0, 90, 180, 270]; // Norte, Leste, Sul, Oeste
			const distance = 250; // 250 metros

			angles.forEach(angle => {
				const destination = google.maps.geometry.spherical.computeOffset(
					new google.maps.LatLng(lat, lng),
					distance,
					angle
				);

				const request = {
					origin: new google.maps.LatLng(lat, lng),
					destination: destination,
					travelMode: 'WALKING', // Usar "WALKING" para rotas terrestres
				};

				directionsRequests.push(service.route(request));
			});

			Promise.all(directionsRequests)
				.then(results => {
					const path = results.map(result => result.routes[0].legs[0].end_location);

					// Cria um polígono baseado nas rotas
					const polygon = new google.maps.Polygon({
						map: map,
						paths: path,
						fillColor: "#00FF00",
						fillOpacity: 0.3,
						strokeColor: "#00FF00",
						strokeOpacity: 0.8,
						strokeWeight: 2,
					});
				})
				.catch(err => console.error("Erro ao calcular rotas:", err));
		}
        // Inicializa o mapa
        window.onload = initMap;
    </script>
</body>
</html>