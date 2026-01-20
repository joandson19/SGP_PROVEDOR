<?php
require_once("../config/conf.php");

// Inicio da função auth
require_once("../auth.php");

// Exibir pop-up de boas-vindas apenas no primeiro login
$nomeUsuario = $_SESSION['user_info']['nome'] ?? 'Usuário';

$mostrarPopup = isset($_SESSION['welcome_shown']) && $_SESSION['welcome_shown'] === false;

if ($mostrarPopup) {
    $_SESSION['welcome_shown'] = true; // Evita que o pop-up apareça novamente após o login
}
// Fim a função auth

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
        'note' => htmlspecialchars($cto['note'] ?? '', ENT_QUOTES, 'UTF-8'),
        'id' => intval($cto['id'] ?? 0)
    ];
}, $ctos);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mapa de Cobertura FTTH</title>

    <!-- CSS do Leaflet -->
	<link rel="stylesheet" href="css/leaflet.css">
    <link rel="stylesheet" href="css/style.css?v=<?= filemtime('css/style.css'); ?>">
</head>

<body class="mapa-page">
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
    <div id="loading">Calculando cobertura...</div>

<!-- Inclua a biblioteca Leaflet -->
<script src="js/leaflet.js"></script>

<div id="search-container">
	<button id="new-table-btn" onclick="redirMapaclientes()">Mapa de Clientes</button>
	<button id="new-table-btn" onclick="redirMapacto()">Mapa de CTO</button>
	<button id="logoutButton">Sair</button>
</div>
	
<script>
	// Dados das CTOs obtidas do PHP (JSON convertido para JavaScript)
	const ctos = <?php echo json_encode($filteredCtos); ?>;
	const markers = [];
	const ctoLocations = [];

	// Função para inicializar o mapa
	function initMap() {
		const map = L.map('map').setView([<?php echo $centralLatitude; ?>, <?php echo $centralLongitude; ?>], 15);

		// Adiciona o tile layer (mapa base)
		L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
			attribution: '&copy; OpenStreetMap contributors'
		}).addTo(map);

		// Adiciona marcadores e calcula cobertura para cada CTO
		ctos.forEach(cto => {
			if (cto.map_ll) {
				const coords = cto.map_ll.split(",");
				const lat = parseFloat(coords[0].trim());
				const lng = parseFloat(coords[1].trim());

				const marker = createMarker(cto, map, lat, lng);
				markers.push(marker);
				ctoLocations.push({ lat, lng, marker });

				createPopup(cto, marker);
				calculateCoverage(cto, lat, lng, map);
			}
		});
	}

	// Função para criar um marcador de CTO
	function createMarker(cto, map, lat, lng) {
		let markerIcon = L.icon({
			iconUrl: 'images/cto.png',
			iconSize: [30, 30],
			iconAnchor: [15, 15]
		});

		return L.marker([lat, lng], { icon: markerIcon, title: cto.ident }).addTo(map);
	}

	// Função para criar um Popup com informações da CTO
	function createPopup(cto, marker) {
		let popupContent = `
			<div class="infowindow-content">
				<h2>➡ ${cto.ident} ⬅</h2>
				<p><strong>Número de Portas:</strong> ${cto.ports}</p>
				<p><strong>Portas ocupadas:</strong><font color="red"> ${cto.busy_ports && cto.busy_ports.length > 0 ? [...cto.busy_ports].sort((a, b) => a - b).join(', ') : 'Nenhuma'}</font></p>
				<p><strong>Observações:</strong> ${cto.note || 'Nenhuma'}</p>
			</div>
		`;

		marker.bindPopup(popupContent);
	}

	// Função para calcular a cobertura de uma CTO
	function calculateCoverage(cto, lat, lng, map) {
		const distance = 200; // Raio de 200 metros

		// Cria um círculo ao redor da CTO
		L.circle([lat, lng], {
			color: "#00FF00",
			fillColor: "#00FF00",
			fillOpacity: 0.08,
			radius: distance
		}).addTo(map);
	}

	// Inicializa o mapa
	window.onload = initMap;
</script>
<script>
	// Função para redirecionar para o mapa de cobertura
	function redirMapaclientes() {
		window.open("../", "_blank");
	}	
	// Função para redirecionar para o mapa de cobertura
	function redirMapacto() {
		window.open(".", "_blank");		
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
</script>

</body>
</html>
