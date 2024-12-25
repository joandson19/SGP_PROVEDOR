<?php
require_once("config/conf.php");

if (!isset($_GET['token']) || $_GET['token'] !== $validToken) {
    // Token inválido, encerrar a execução
    http_response_code(403); // Código de resposta "Proibido"
    echo "Acesso não autorizado.";
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Mapa SGP</title>
    <!-- Inclua o CSS do Leaflet -->
    <link rel="stylesheet" href="css/style.css" />
</head>
<body>
<!-- Adicione um elemento de carregamento -->
<div id="loading" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);">
    <img src="images/loading.gif" alt="Carregando...">
</div>

<!-- Crie um elemento div para o mapa -->
<div id="map"></div>

<!-- Inclua a biblioteca Google Maps API -->
<script src="https://maps.googleapis.com/maps/api/js?key=<?php require_once("config/conf.php"); echo $googleMapsApiKey; ?>"></script>
<!-- Inclua a biblioteca jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
    <?php require_once("config/conf.php"); ?>
    
    var map; // Variável para o objeto do mapa
    var markers = []; // Array para armazenar os marcadores
	var infowindow = new google.maps.InfoWindow();


    // Função para inicializar o mapa
    function initMap() {
        // Configurações iniciais do mapa
        var mapOptions = {
            center: new google.maps.LatLng(<?php echo $centralLatitude; ?>, <?php echo $centralLongitude; ?>),
            zoom: 15
        };

        // Criação do mapa
        map = new google.maps.Map(document.getElementById('map'), mapOptions);

        // Carrega os marcadores
        refreshMarkers();
    }

	// Função para atualizar os marcadores no mapa
	function refreshMarkers() {
		// Exibir o GIF de carregamento
		//document.getElementById('loading').style.display = 'block';

		$.ajax({
			url: 'atualizar_marcadores.php?mptoken=<?php echo $validToken; ?>',
			dataType: 'json',
			success: function(data) {
				clearMarkers(); // Remove os marcadores existentes
				addMarkers(data); // Adiciona novos marcadores com base nos dados atualizados

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
        for (var i = 0; i < markers.length; i++) {
            markers[i].setMap(null);
        }
        markers = [];
    }

    // Função para adicionar marcadores com base nos dados
	function addMarkers(data) {
		for (var i = 0; i < data.length; i++) {
			if (data[i].latitude !== "" && data[i].longitude !== "") {
				var statusIconUrl = data[i].statusIcon;
				var markerIcon = {
					url: statusIconUrl,
					scaledSize: new google.maps.Size(32, 32)
				};

				var marker = new google.maps.Marker({
					position: new google.maps.LatLng(data[i].latitude, data[i].longitude),
					map: map,
					icon: markerIcon,
					title: data[i].nome
				});

				// Configuração do conteúdo da infowindow
				var content = "<div><strong>"
								+ data[i].nome + 
								"</strong><br>Vlan: " + data[i].vlan + 
								"<br>Última conexão: " + data[i].acct + 
								"<br><span style='color: red;'>Última desconexão:</span> " + data[i].stop +
								"<br>IP: <a target='_blank' href=http://" + data[i].ip + ":<?php echo $port; ?>>" + data[i].ip + "</a></div>";
				
				marker.addListener("click", (function(marker, content) {
					return function() {
						infowindow.setContent(content);
						infowindow.open(map, marker);
					};
				})(marker, content)); // Criação de uma função anônima (closure)

				markers.push(marker);
			}
		}
	}

	// Função buscar cliente
	function searchClient() {
		var searchInput = document.getElementById("search-input").value.toLowerCase();

		for (var i = 0; i < markers.length; i++) {
			var marker = markers[i];
			var markerTitle = marker.getTitle().toLowerCase();

			if (markerTitle.includes(searchInput)) {
				// Centraliza o mapa no marcador correspondente
				map.setCenter(marker.getPosition());
				map.setZoom(18);

				// Cria um novo InfoWindow com o conteúdo do nome do cliente
				var infoWindow = new google.maps.InfoWindow({
					content: marker.getTitle()
				});

				// Abre o InfoWindow no marcador correspondente
				infoWindow.open(map, marker);

				return; // Para a busca após encontrar o primeiro resultado
			}
		}

		// Se nenhum resultado for encontrado, exiba uma mensagem ao usuário
		alert("Cliente não encontrado.");
	}


    // Inicializa o mapa quando a página estiver carregada
    google.maps.event.addDomListener(window, 'load', initMap);

    // Atualiza os marcadores a cada 30 segundos
    setInterval(refreshMarkers, 30000);
</script>
<div id="search-container">
<!-- Adicione uma busca de cliente no mapa -->

    <input type="text" id="search-input" placeholder="Buscar cliente">
    <button id="search-button" onclick="searchClient()">Buscar</button>
	
<!-- Adicione o botão de atualização manual no canto direito abaixo do mapa -->
<button id="manual-update-btn" onclick="refreshMarkers()">Refresh</button>

<!-- Adicione o novo botão de redirecionamento -->
<button id="new-table-btn" onclick="redirectToNewTable()">Mapa de CTO</button>

</div>
<script>
    // Função para redirecionar para o link desejado
    function redirectToNewTable() {
		window.open("cto.php", "_blank");
    }
</script>
</body>
</html>
