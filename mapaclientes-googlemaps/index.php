<!DOCTYPE html>
<html>
<head>
    <title>Mapa SGP</title>

    <link rel="stylesheet" href="css/style.css" />
</head>
<body>
<div id="loading" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);">
    <img src="images/loading.gif" alt="Carregando...">
</div>

<div id="map"></div>

<script src="https://maps.googleapis.com/maps/api/js?key=<?php require_once("config/conf.php"); echo $googleMapsApiKey; ?>"></script>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
    <?php require_once("config/conf.php"); ?>
    
    var map;
    var markers = [];
	var infowindow = new google.maps.InfoWindow();


    // Função para inicializar o mapa
    function initMap() {
        var mapOptions = {
            center: new google.maps.LatLng(<?php echo $centralLatitude; ?>, <?php echo $centralLongitude; ?>),
            zoom: 13
        };

        map = new google.maps.Map(document.getElementById('map'), mapOptions);

        refreshMarkers();
    }

	// Função para atualizar os marcadores no mapa
	function refreshMarkers() {
		document.getElementById('loading').style.display = 'block';

		$.ajax({
			url: 'atualizar_marcadores.php?mptoken=<?php echo $validToken; ?>',
			dataType: 'json',
			success: function(data) {
				clearMarkers();
				addMarkers(data);

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

				var content = "<div><strong>" + data[i].nome + "</strong><br>Vlan: " + data[i].vlan + "<br>Última conexão: " + data[i].acct + "<br>IP: <a target='_blank' href=http://" + data[i].ip + ":<?php echo $port; ?>>" + data[i].ip + "</a></div>";
				marker.addListener("click", (function(marker, content) {
					return function() {
						infowindow.setContent(content);
						infowindow.open(map, marker);
					};
				})(marker, content));

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
				map.setCenter(marker.getPosition());
				map.setZoom(18);

				var infoWindow = new google.maps.InfoWindow({
					content: marker.getTitle()
				});

				infoWindow.open(map, marker);

				return;
			}
		}

		alert("Cliente não encontrado.");
	}


    google.maps.event.addDomListener(window, 'load', initMap);

    setInterval(refreshMarkers, 30000);
</script>

<button id="manual-update-btn" onclick="refreshMarkers()">Refresh</button>
<div id="search-container">
    <input type="text" id="search-input" placeholder="Buscar cliente">
    <button id="search-button" onclick="searchClient()">Buscar</button>
</div>

</body>
</html>
