<!DOCTYPE html>
<html>
<head>
    <title>Mapa dos Clientes</title>
    <!-- Inclua o CSS do Leaflet -->
    <link rel="stylesheet" href="css/leaflet.css" />
    <link rel="stylesheet" href="css/style.css" />
</head>
<body>
<!-- Adicione um elemento de carregamento -->
<div id="loading" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);">
    <img src="images/loading.gif" alt="Carregando...">
</div>

<!-- Crie um elemento div para o mapa -->
<div id="map"></div>

<!-- Inclua o JavaScript do Leaflet -->
<script src="css/leaflet.js"></script>
<!-- Inclua a biblioteca jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
    <?php require_once("config/conf.php"); ?>
    
    // Variável para rastrear o marcador realçado
    var highlightedMarker = null;

    // Função para atualização manual dos marcadores
    function manualUpdate() {
        refreshMarkers(); // Chama a função de atualização de marcadores
    }

	// Função para atualizar os marcadores no mapa
	function refreshMarkers() {
		// Exibir o GIF de carregamento
		document.getElementById('loading').style.display = 'block';

		$.ajax({
			url: 'atualizar_marcadores.php?mptoken=<?php echo $validToken; ?>',
			dataType: 'json',
			success: function(data) {
				var highlightedPopupContent = null;

				// Salva o conteúdo do popup do marcador realçado, se houver
				if (highlightedMarker) {
					highlightedPopupContent = highlightedMarker.getPopup().getContent();
				}

				clearMarkers(); // Remove os marcadores existentes
				addMarkers(data); // Adiciona novos marcadores com base nos dados atualizados

				// Resseta o estado de realce de todos os marcadores
				markers.forEach(function(marker) {
					marker.highlighted = false;

					// Restaura o ícone original
					marker.setIcon(L.icon({
						iconUrl: marker.options.icon.options.iconUrl,
						iconSize: [32, 32],
						iconAnchor: [16, 32]
					}));

					// Verifica se o marcador está no estado realçado
					if (highlightedPopupContent && marker.getPopup().getContent() === highlightedPopupContent) {
						// Atualiza o ícone para o estado realçado
						marker.setIcon(L.icon({
							iconUrl: "images/highlight-icon.png",
							iconSize: [32, 32],
							iconAnchor: [16, 32]
						}));

						// Armazena o marcador realçado
						highlightedMarker = marker;
					}
				});

				// Ocultar o GIF de carregamento após a conclusão da solicitação
				document.getElementById('loading').style.display = 'none';
			},
			error: function(xhr, status, error) {
				console.error('Erro ao buscar dados atualizados:', error);
				// Certifique-se de ocultar o GIF mesmo em caso de erro
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

	var markers = []; // Declaração da matriz de marcadores

	// Função para adicionar marcadores com base nos dados
	function addMarkers(data) {
		clearMarkers(); // Limpa os marcadores existentes

		for (var i = 0; i < data.length; i++) {
			if (data[i].latitude !== "" && data[i].longitude !== "") {
				// Verifica se latitude e longitude não estão vazios
				var statusIconUrl = data[i].statusIcon;
				var markerIcon = L.icon({
					iconUrl: statusIconUrl,
					iconSize: [32, 32],
					iconAnchor: [16, 32]
				});

				var marker = L.marker([data[i].latitude, data[i].longitude], { icon: markerIcon }).addTo(map);
				marker.bindPopup(data[i].nome, { offset: L.point(0, -20) });

				markers.push(marker); // Armazena a referência ao marcador
			}
		}
	}

	// Função buscar cliente
	function searchClient() {
		var searchInput = document.getElementById("search-input").value;

		// Percorre os marcadores para verificar se o nome do cliente corresponde à pesquisa
		for (var i = 0; i < markers.length; i++) {
			var marker = markers[i];
			var popupContent = marker.getPopup().getContent();

			if (popupContent.toLowerCase().includes(searchInput.toLowerCase())) {
				// Centraliza o mapa no marcador correspondente e dá zoom
				map.setView(marker.getLatLng(), 18);

				// Realça o marcador com o ícone de destaque
				marker.setIcon(L.icon({
					iconUrl: "images/highlight-icon.png",
					iconSize: [32, 32],
					iconAnchor: [16, 32]
				}));

				// Armazena o marcador realçado
				highlightedMarker = marker;

				return; // Para a busca após encontrar o primeiro resultado
			}
		}

		// Se nenhum resultado for encontrado, exiba uma mensagem ao usuário
		alert("Cliente não encontrado.");
	}

    // Crie o mapa com as coordenadas da localização fixa definidas em conf.php
    var map = L.map('map').setView([<?php echo $centralLatitude; ?>, <?php echo $centralLongitude; ?>], 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

    map.on('load', function () {
        document.getElementById('loading').style.display = 'none';
        document.getElementById('map').style.display = 'block';
    });

    // Atualiza os marcadores a cada 30 segundos
    setInterval(refreshMarkers, 30000);

    // Inicialmente, carrega os marcadores
    refreshMarkers();
</script>

<!-- Adicione o botão de atualização manual no canto direito abaixo do mapa -->
<button id="manual-update-btn" onclick="manualUpdate()">Refresh</button>

<!-- Adicione uma busca de cliente no mapa -->
<div id="search-container">
    <input type="text" id="search-input" placeholder="Buscar cliente">
    <button id="search-button" onclick="searchClient()">Buscar</button>
</div>

</body>
</html>


