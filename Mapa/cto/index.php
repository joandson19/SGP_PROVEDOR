<?php
// Inicio da função auth
require_once("../auth.php");

// Exibir pop-up de boas-vindas apenas no primeiro login
$nomeUsuario = $_SESSION['user_info']['nome'] ?? 'Usuário';

$mostrarPopup = isset($_SESSION['welcome_shown']) && $_SESSION['welcome_shown'] === false;

if ($mostrarPopup) {
    $_SESSION['welcome_shown'] = true; // Evita que o pop-up apareça novamente após o login
}
// Fim a função auth

require_once("../config/conf.php");

// Controlar o cache do navegador
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

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
    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime('css/style.css'); ?>">

</head>
<body class="mapa-page" onload="initMap()">
    <!-- Inicio do JavaScript para exibir o pop-up de boas-vindas -->
    <?php if ($mostrarPopup): ?>
    <script>
        window.onload = function() {
            alert('Bem-vindo, ' + <?php echo json_encode($nomeUsuario); ?> + '!');
        };
    </script>
    <?php endif; ?>
    <!-- Fim do JavaScript para exibir o pop-up de boas-vindas -->	
    <div id="map" style="height: 100vh;"></div>
    <div id="search-container">
        <button id="new-table-btn" onclick="clearMeasurements()">Limpar Medições🧹</button>
		<button id="new-table-btn" onclick="redirMapaclientes()">Mapa de Clientes</button>
        <button id="new-table-btn" onclick="redirectToCoverageMap()">Mapa de Cobertura</button>
		<button id="logoutButton">Sair</button>
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
					const buttonHTML = `
						<button id="new-table-btn"
							${cto.onu_count > 0 
								? `onclick="redirectToOnu(${cto.id})"` 
								: 'disabled class="disabled-btn" title="CTO não possui ONU vinculada."'}>
							Ver Sinal
						</button>
						<button id="new-table-btn"
							${cto.busy_ports?.length < cto.ports 
								? `onclick="redirectToOnuAuth(${cto.id}, ${cto.ports}, '${cto.busy_ports}', ${cto.pon}, '${cto.ident}')"`
								: 'disabled class="disabled-btn" title="CTO Lotada - Não é possível autorizar novas ONUs."'}>
							Autorizar ONU
						</button>
					`;		
			return new google.maps.InfoWindow({
				content: `
					<div>
						<p class="${cto.busy_ports?.length >= cto.ports ? 'cto-lotada' : ''}">
							${cto.busy_ports?.length >= cto.ports ? '🚨 CTO LOTADA 🚨' : ''}
						</p>
						<h2>➡️ ${cto.ident} ⬅️</h2>
						<p><strong>Número de Portas:</strong> ${cto.ports}</p>
						${(cto.busy_ports?.length > 0) 
						  ? `<p><strong>Total ocupadas:</strong> 
							  <font color="${(cto.busy_ports?.length + 1 === cto.ports) ? 'orange' : 'green'}">
								<span class="${cto.busy_ports?.length === cto.ports ? 'blink' : ''}">
								  ${cto.busy_ports?.length}
								</span>
							  </font>
							</p>` 
						  : ''}
						<p><strong>Portas ocupadas:</strong>
							<span style="color: red;">
								${cto.busy_ports && cto.busy_ports.length > 0 
									? [...cto.busy_ports].sort((a, b) => a - b).join(', ') 
									: 'Nenhuma'}
							</span>
						</p>
						${cto.note ? `<p><strong>Observações:</strong> ${cto.note}</p>` : ''}
						<p><strong>OLT PON:</strong> ${cto.pon}</p>
						${buttonHTML}
					</div>
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
							resolve({ response, distance: response.routes[0].legs[0].distance.value, location });
						} else {
							reject(`Erro ao calcular rota para ${location.marker.getTitle()}: ${status}`);
						}
					});
				});
			});

			try {
				const results = await Promise.all(routePromises);
				const shortestRoute = results.reduce((shortest, current) => {
					return current.distance < shortest.distance ? current : shortest;
				}, { distance: Infinity });

				if (shortestRoute.response) {
					directionsRenderer = new google.maps.DirectionsRenderer({
						map: map,
						polylineOptions: {
							strokeColor: "#0000FF", // Cor da rota terrestre (azul)
							strokeOpacity: 1.0,
							strokeWeight: 4
						}
					});
					directionsRenderer.setDirections(shortestRoute.response);

					const leg = shortestRoute.response.routes[0].legs[0];
					const distanceInMeters = leg.distance.value;
					
					// Desenha a linha aérea
					aerialPath = new google.maps.Polyline({
						path: [leg.start_location, clickedLocation],
						geodesic: true,
						strokeColor: "#0000FF",
						strokeOpacity: 1.0,
						strokeWeight: 4,
						map: map
					});

					// Calculando a distância aérea corretamente
					const aerialDistance = google.maps.geometry.spherical.computeDistanceBetween(
						leg.start_location, clickedLocation
					);

					const totalDistance = distanceInMeters + aerialDistance;

					routeInfoWindow = new google.maps.InfoWindow({
						content: `<div><h3>Distância total: ${totalDistance.toFixed(2)} metros</h3></div>`
					});

					routeInfoWindow.setPosition(clickedLocation);
					routeInfoWindow.open(map);
				}
			} catch (error) {
				console.error('Erro ao calcular rota:', error);
			} finally {
				hideLoading();
			}
		}
        // Função para redirecionar para Ver Sinal
		function redirectToOnu(ctoId) {
			// Cria um formulário dinamicamente
			const form = document.createElement('form');
			form.method = 'POST';
			form.action = 'onu.php';
			form.target = '_blank'; // Abre em uma nova aba

			// Cria um input hidden para enviar o ctoId
			const input = document.createElement('input');
			input.type = 'hidden';
			input.name = 'cto';
			input.value = ctoId;

			// Adiciona o input ao formulário
			form.appendChild(input);

			// Adiciona o formulário ao corpo do documento
			document.body.appendChild(form);

			// Submete o formulário
			form.submit();

			// Remove o formulário do DOM após a submissão
			document.body.removeChild(form);
		}

        // Função para redirecionar para Autorizar ONU
		function redirectToOnuAuth(ctoId, ports, busy_ports, pon, ident) {
			// Cria um formulário dinâmico
			const form = document.createElement('form');
			form.method = 'POST';
			form.action = 'onuauth.php';
			form.target = '_blank'; // Abre em uma nova aba

			// Adiciona os campos ao formulário
			const addField = (name, value) => {
				const input = document.createElement('input');
				input.type = 'hidden';
				input.name = name;
				input.value = value;
				form.appendChild(input);
			};

			addField('olt_id', 3);
			addField('cto', ctoId);
			addField('ports', ports);
			addField('occupied_ports', busy_ports);
			addField('ctopon', pon);
			addField('ctoident', ident);

			// Adiciona o formulário ao corpo do documento e submete
			document.body.appendChild(form);
			form.submit();

			// Remove o formulário após a submissão
			document.body.removeChild(form);
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
			if (aerialPath) {
				aerialPath.setMap(null);
				aerialPath = null;
			}
		}
		
		// Função logout.
		document.getElementById('logoutButton').addEventListener('click', function() {
			fetch('../logout.php') // Chama o script PHP que destrói a sessão
				.then(response => {
					if (response.ok) {
						// Redireciona o usuário após o logout
						alert('Logout realizado com sucesso. Redirecionando para a página de login...');
						window.location.href = '../login.php'; // Página de login ou outra página
					} else {
						alert('Erro ao tentar sair. Tente novamente.');
					}
				})
				.catch(error => {
					console.error('Erro:', error);
				});
		});
        // Função para mostrar o indicador de carregamento
        function showLoading() {
            document.getElementById('loading').style.display = 'block';
        }

        // Função para esconder o indicador de carregamento
        function hideLoading() {
            document.getElementById('loading').style.display = 'none';
        }

        // Função para redirecionar para o mapa de cobertura
        function redirectToCoverageMap() {
            window.open("cobertura.php", "_blank");
        }
		// Função redireciona para mapa de clientes
		function redirMapaclientes() {
			window.open("../", "_blank");
		}	
    </script>
</body>
</html>