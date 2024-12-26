<?php
require_once("../config/conf.php");
// API URL
$url = "$url/api/fttx/splitter/all/?token=$token&app=$app";

// Inicia o cURL para obter os dados
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

// Decodifica o JSON
$ctos = json_decode($response, true);

if (!$ctos || !is_array($ctos)) {
    die("Erro ao obter dados da API ou JSON inválido.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mapa de CTOs</title>
    <script src="https://maps.googleapis.com/maps/api/js?key=<?php require_once("../config/conf.php"); echo $googleMapsApiKey; ?>&libraries=geometry"></script>
    <link rel="stylesheet" href="../css/style.css"> <!-- Adiciona o link para o CSS -->
    <script>
        let directionsRenderer = null;  // Variável global para armazenar o DirectionsRenderer
        let routeInfoWindow = null;    // Variável global para armazenar o InfoWindow da rota
        let currentMeasurement = null; // Variável para armazenar a medição atual
        let currentMarker = null;      // Variável para armazenar o marcador da medição atual

        function initMap() {
            // Inicializa o mapa
            const map = new google.maps.Map(document.getElementById('map'), {
                center: new google.maps.LatLng(<?php echo $centralLatitude; ?>, <?php echo $centralLongitude; ?>), // Coordenadas padrão
                zoom: 15
            });

            // Dados das CTOs
            const ctos = <?php echo json_encode($ctos); ?>;
            const markers = [];
            const ctoLocations = [];

            // Adiciona os marcadores ao mapa e armazena as localizações
            ctos.forEach(cto => {
                if (cto.map_ll) {
                    const coords = cto.map_ll.split(",");
                    const lat = parseFloat(coords[0].trim());
                    const lng = parseFloat(coords[1].trim());

                    const marker = new google.maps.Marker({
                        position: { lat: lat, lng: lng },
                        map: map,
                        title: cto.ident,
                        icon: {
                            url: '../images/cto.png', // Caminho para o seu ícone personalizado
                            scaledSize: new google.maps.Size(30, 30), // Tamanho do ícone (opcional)
                            origin: new google.maps.Point(0, 0), // Posição inicial do ícone (opcional)
                            anchor: new google.maps.Point(15, 15) // Onde o ícone será ancorado (opcional)
                        }
                    });

                    // Armazena a localização e o marcador
                    ctoLocations.push({ lat, lng, marker });

                    // Adiciona o conteúdo do balão de informações
                    const infoWindow = new google.maps.InfoWindow({
                        content: `
                            <div>
                                <h2>➡ ${cto.ident} ⬅</h2>
                                <p><strong>Número de Portas:</strong> ${cto.ports}</p>
                                <p><strong>Portas ocupadas:</strong><font color="red"> ${cto.busy_ports ? cto.busy_ports.join(', ') : 'Nenhuma'}</font></p>
                                <p><strong>Observações:</strong> ${cto.note || 'Nenhuma'}</p>
								<p><strong>OLT PON:</strong> ${cto.pon}</p>
                            </div>
                        `
                    });

                    // Exibe o balão ao clicar no marcador
                    marker.addListener('click', () => {
                        infoWindow.open(map, marker);
                    });
                }
            });

            // Função para calcular a rota para o marcador mais próximo
			map.addListener('click', (event) => {
				const clickedLocation = event.latLng;

				if (currentMeasurement) {
					clearMeasurements(); // Limpa a medição anterior
				}

				// Armazenar a medição atual
				currentMeasurement = clickedLocation;

				// Adiciona marcador no local clicado
				currentMarker = new google.maps.Marker({
					position: clickedLocation,
					map: map,
					icon: {
						url: 'images/green-icon.png',
						scaledSize: new google.maps.Size(30, 30),
						origin: new google.maps.Point(0, 0),
						anchor: new google.maps.Point(15, 15)
					},
					draggable: true // Torna o marcador arrastável
				});

				// Atualiza a medição ao arrastar o marcador
				google.maps.event.addListener(currentMarker, 'dragend', function() {
					currentMeasurement = currentMarker.getPosition();
					updateRoute(clickedLocation); // Atualiza a rota
				});

				const directionsService = new google.maps.DirectionsService();
				let shortestRoute = null;
				let shortestDistance = Infinity;

				// Calcula rotas para todas as CTOs
				const calculateRoutes = async () => {
					const routePromises = ctoLocations.map(location => {
						const request = {
							origin: clickedLocation,
							destination: new google.maps.LatLng(location.lat, location.lng),
							travelMode: google.maps.TravelMode.DRIVING
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
					}
				};
				calculateRoutes();
			});

        }

        // Função para limpar as medições (rota e InfoWindow)
        function clearMeasurements() {
            if (directionsRenderer) {
                directionsRenderer.setDirections({ routes: [] }); // Limpa a rota
            }
            if (routeInfoWindow) {
                routeInfoWindow.close(); // Fecha o InfoWindow da rota
            }

            if (currentMarker) {
                currentMarker.setMap(null); // Remove o marcador da medição anterior
                currentMarker = null;  // Limpa o marcador atual
                currentMeasurement = null; // Limpa a medição atual
            }
        }

        // Função para atualizar a rota com a nova posição do marcador
        function updateRoute(startLocation) {
            const directionsService = new google.maps.DirectionsService();
            const request = {
                origin: startLocation,
                destination: currentMarker.getPosition(),
                travelMode: google.maps.TravelMode.DRIVING
            };

            directionsService.route(request, (result, status) => {
                if (status === google.maps.DirectionsStatus.OK) {
                    directionsRenderer.setDirections(result);

                    // Exibe a distância e o tempo estimado da rota
                    const route = result.routes[0];
                    const leg = route.legs[0];
                    const distanceInMeters = leg.distance.value;
                    const duration = leg.duration.text;

                    routeInfoWindow.setContent(`
                        <div>
                            <h3>Distância: ${distanceInMeters} metros</h3>
                            <!--h3>Duração estimada: ${duration}</h3-->
                        </div>
                    `);

                    routeInfoWindow.setPosition(leg.end_location);
                    routeInfoWindow.open(directionsRenderer.getMap());
                } else {
                    console.error('Erro ao calcular a rota:', status);
                    alert('Não foi possível calcular a rota. Verifique o console para mais detalhes.');
                }
            });
        }
    </script>
</head>
<body onload="initMap()">
    <div id="map" style="height: 100vh;"></div>
	<div id="search-container">
    <!-- Botão para limpar medições -->
    <button id="new-table-btn" onclick="clearMeasurements()">Limpar Medições</button>
	</div>
</body>
</html>
