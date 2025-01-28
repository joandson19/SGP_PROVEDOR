<!DOCTYPE html>
<html>
<head>
    <title>Mapa dos Clientes</title>
    <!-- Inclua o CSS do Leaflet -->
	<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css" />
    <link rel="stylesheet" href="css/style.css" />
</head>
<body>
<div id="loading" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);">
    <img src="images/loading.gif" alt="Carregando...">
</div>

<div id="map"></div>

<script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
    <?php require_once("config/conf.php"); ?>
    
    var highlightedMarker = null;

    // Função para atualização manual dos marcadores
    function manualUpdate() {
        refreshMarkers();
    }

	// Função para atualizar os marcadores no mapa
	function refreshMarkers() {
		// Exibir o GIF de carregamento
		document.getElementById('loading').style.display = 'block';

		$.ajax({
			url: 'atualizar_marcadores.php',
			dataType: 'json',
			success: function(data) {
				var highlightedPopupContent = null;

				if (highlightedMarker) {
					highlightedPopupContent = highlightedMarker.getPopup().getContent();
				}

				clearMarkers();
				addMarkers(data);

				markers.forEach(function(marker) {
					marker.highlighted = false;

					marker.setIcon(L.icon({
						iconUrl: marker.options.icon.options.iconUrl,
						iconSize: [32, 32],
						iconAnchor: [16, 32]
					}));

					if (highlightedPopupContent && marker.getPopup().getContent() === highlightedPopupContent) {
						marker.setIcon(L.icon({
							iconUrl: "images/highlight-icon.png",
							iconSize: [32, 32],
							iconAnchor: [16, 32]
						}));

						highlightedMarker = marker;
					}
				});

				document.getElementById('loading').style.display = 'none';
			},
			error: function(xhr, status, error) {
				console.error('Erro ao buscar dados atualizados:', error);
				document.getElementById('loading').style.display = 'none';
			}
		});
	}


    // Função para remover os marcadores existentes
    function clearMarkers() {
        map.eachLayer(function (layer) {
            if (layer instanceof L.Marker) {
                map.removeLayer(layer);
            }
        });
    }

	var markers = [];

	// Função para adicionar marcadores com base nos dados
	function addMarkers(data) {
		clearMarkers(); // Limpa marcadores existentes, se necessário.

		for (var i = 0; i < data.length; i++) {
			if (data[i].latitude !== "" && data[i].longitude !== "") {
				var statusIconUrl = data[i].statusIcon;
				var markerIcon = L.icon({
					iconUrl: statusIconUrl,
					iconSize: [32, 32],       // Tamanho do ícone
					iconAnchor: [16, 32]      // Ponto de ancoragem do ícone
				});

				var marker = L.marker([data[i].latitude, data[i].longitude], { icon: markerIcon }).addTo(map);

				// Configuração do conteúdo da popup
				var content = `
					<div>
						<strong>${data[i].nome}</strong><br>
						Vlan: ${data[i].vlan}<br>
						Última conexão: ${data[i].acct}<br>
						<span style='color: red;'>Última desconexão:</span> ${data[i].stop}<br>
						IP: <a target='_blank' href="http://${data[i].ip}:<?php echo $port; ?>">${data[i].ip}</a>
					</div>
				`;

				// Adiciona o conteúdo à popup
				marker.bindPopup(content, { offset: L.point(0, -20) });

				markers.push(marker); // Adiciona o marcador à lista de marcadores
			}
		}
	}

	// Função buscar cliente
	function searchClient() {
		var searchInput = document.getElementById("search-input").value;

		for (var i = 0; i < markers.length; i++) {
			var marker = markers[i];
			var popupContent = marker.getPopup().getContent();

			if (popupContent.toLowerCase().includes(searchInput.toLowerCase())) {
				map.setView(marker.getLatLng(), 18);

				marker.setIcon(L.icon({
					iconUrl: "images/highlight-icon.png",
					iconSize: [32, 32],
					iconAnchor: [16, 32]
				}));

				highlightedMarker = marker;

				return;
			}
		}

		alert("Cliente não encontrado.");
	}

    // Crie o mapa com as coordenadas da localização fixa definidas em conf.php
    var map = L.map('map').setView([<?php echo $centralLatitude; ?>, <?php echo $centralLongitude; ?>], 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

    map.on('load', function () {
        document.getElementById('loading').style.display = 'none';
        document.getElementById('map').style.display = 'block';
    });

    setInterval(refreshMarkers, 30000);

    refreshMarkers();
</script>

<button id="manual-update-btn" onclick="manualUpdate()">Refresh</button>
<div id="search-container">
    <input type="text" id="search-input" placeholder="Buscar cliente">
    <button id="search-button" onclick="searchClient()">Buscar</button>
</div>

</body>
</html>


