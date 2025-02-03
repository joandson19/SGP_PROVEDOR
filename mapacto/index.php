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
$apiUrl = "$url/api/fttx/splitter/all/?show_busy_ports=1&token=$token&app=$app";

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
        'onu_count' => intval($cto['onu_count'] ?? 0),
        'pon' => htmlspecialchars($cto['pon'] ?? '', ENT_QUOTES, 'UTF-8'),
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
    <title>Mapa de CTOs</title>
    <script async defer src="https://maps.googleapis.com/maps/api/js?key=<?php echo $googleMapsApiKey; ?>&libraries=geometry&callback=initMap"></script>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
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
<body class="mapa-page" onload="initMap()">
    <div id="map" style="height: 100vh;"></div>
    <div id="search-container">
        <button id="new-table-btn" onclick="clearMeasurements()">Limpar Medições</button>
        <button id="new-table-btn" onclick="redirectTocoveragemap()">Mapa de Cobertura</button>
    </div>
    <div id="loading">Calculando rotas...</div>

    <script>
        let directionsRenderer = null;
        let routeInfoWindow = null;
        let currentMeasurement = null;
        let currentMarker = null;
        let currentInfoWindow = null;

		// Função para inicializar o mapa
        function initMap() {
            const map = new google.maps.Map(document.getElementById('map'), {
                center: new google.maps.LatLng(<?php echo $centralLatitude; ?>, <?php echo $centralLongitude; ?>),
                zoom: 15
            });

            const ctos = <?php echo json_encode($filteredCtos); ?>;
            const markers = [];
            const ctoLocations = [];

            // Adiciona marcadores ao mapa
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
                        if (currentInfoWindow) {
                            currentInfoWindow.close();
                        }
                        currentInfoWindow = infoWindow;
                        infoWindow.open(map, marker);
                    });
                }
            });

            // Evento de clique no mapa para calcular rotas
            map.addListener('click', (event) => {
                const clickedLocation = event.latLng;

                if (currentMeasurement) {
                    clearMeasurements();
                }

                currentMeasurement = clickedLocation;
                currentMarker = createMeasurementMarker(clickedLocation, map);

                google.maps.event.addListener(currentMarker, 'dragend', () => {
                    currentMeasurement = currentMarker.getPosition();
                    updateRoute(clickedLocation);
                });

                calculateShortestRoute(clickedLocation, ctoLocations, map);
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
			const buttonHTML = (cto.onu_count > 0
				? `<button id="new-table-btn" onclick="redirectToOnu(${cto.id})">Ver Sinal</button>`
				: '') +
				(cto.busy_ports.length < cto.ports
					? `<button id="new-table-btn" onclick="redirectToOnuAuth(${cto.id}, ${cto.ports}, '${cto.busy_ports}', ${cto.pon})">Autorizar ONU</button>`
					: `<button id="new-table-btn" disabled title="CTO Lotada - Não é possível autorizar novas ONUs." style="opacity: 0.5; cursor: not-allowed;">Autorizar ONU</button>`);
		
			return new google.maps.InfoWindow({
				content: `
					<div>
						<p style="${cto.busy_ports.length >= cto.ports ? 'color: red; animation: blink 1s infinite; text-align: center;' : ''}">
							${cto.busy_ports.length >= cto.ports ? 'CTO LOTADA' : ''}
						</p>
						<h2>➡ ${cto.ident} ⬅</h2>
						<p><strong>Número de Portas:</strong> ${cto.ports}</p>
						<p><strong>Portas ocupadas:</strong><font color="red"> ${cto.busy_ports && cto.busy_ports.length > 0 ? cto.busy_ports.join(', ') : 'Nenhuma'}</font></p>
						<p><strong>Observações:</strong> ${cto.note || 'Nenhuma'}</p>
						<p><strong>OLT PON:</strong> ${cto.pon}</p>
						${buttonHTML}
					</div>
					<style>
						@keyframes blink {
							0% { opacity: 1; }
							50% { opacity: 0; }
							100% { opacity: 1; }
						}
					</style>
				`
			});
        }

        // Função para criar um marcador de medição
        function createMeasurementMarker(position, map) {
            return new google.maps.Marker({
                position: position,
                map: map,
                icon: {
                    url: 'images/green-icon.png',
                    scaledSize: new google.maps.Size(30, 30),
                    origin: new google.maps.Point(0, 0),
                    anchor: new google.maps.Point(15, 15)
                },
                draggable: false
            });
        }

        // Função para calcular a rota mais curta
        async function calculateShortestRoute(clickedLocation, ctoLocations, map) {
            showLoading();

            const directionsService = new google.maps.DirectionsService();
            const routePromises = ctoLocations.map(location => {
                const request = {
                    origin: clickedLocation,
                    destination: new google.maps.LatLng(location.lat, location.lng),
                    travelMode: google.maps.TravelMode.WALKING
                };

                return new Promise((resolve, reject) => {
                    directionsService.route(request, (response, status) => {
                        if (status === google.maps.DirectionsStatus.OK) {
                            resolve({ response, distance: response.routes[0].legs[0].distance.value });
                        } else {
                            reject(`Erro ao calcular rota para ${location.marker.getTitle()}: ${status}`);
                        }
                    });
                });
            });

            try {
                const results = await Promise.all(routePromises);
                const shortestRoute = results.reduce((shortest, current) =>
                    current.distance < shortest.distance ? current : shortest, { distance: Infinity }
                );

                if (shortestRoute.response) {
                    directionsRenderer = new google.maps.DirectionsRenderer({ map: map });
                    directionsRenderer.setDirections(shortestRoute.response);

                    const leg = shortestRoute.response.routes[0].legs[0];
                    const distanceInMeters = leg.distance.value;

                    routeInfoWindow = new google.maps.InfoWindow({
                        content: `<div><h3>Distância: ${distanceInMeters} metros</h3></div>`
                    });

                    routeInfoWindow.setPosition(leg.end_location);
                    routeInfoWindow.open(map);
                }
            } catch (error) {
                console.error(error);
            } finally {
                hideLoading();
            }
        }

        // Função para redirecionar para Ver Sinal
        function redirectToOnu(ctoId) {
            window.open(`onu.php?cto=${ctoId}`, '_blank');
        }

        // Função para redirecionar para Autorizar ONU
        function redirectToOnuAuth(ctoId, ports, busy_ports, pon) {
            window.open(`onuauth.php?olt_id=3&cto=${ctoId}&ports=${ports}&occupied_ports=${busy_ports}&ctopon=${pon}`, '_blank');
        }

        // Função para limpar medições
        function clearMeasurements() {
            if (directionsRenderer) {
                directionsRenderer.setDirections({ routes: [] });
            }
            if (routeInfoWindow) {
                routeInfoWindow.close();
            }
            if (currentMarker) {
                currentMarker.setMap(null);
                currentMarker = null;
                currentMeasurement = null;
            }
        }

        // Função para mostrar o indicador de carregamento
        function showLoading() {
            document.getElementById('loading').style.display = 'block';
        }

        // Função para esconder o indicador de carregamento
        function hideLoading() {
            document.getElementById('loading').style.display = 'none';
        }

        // Função para redirecionar para o mapa de cobertura
        function redirectTocoveragemap() {
            window.open("cobertura.php", "_blank");
        }
    </script>
</body>
</html>