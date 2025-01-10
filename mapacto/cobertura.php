<?php
require_once("config/conf.php");
session_start();

// Verificar se o captcha foi validado
if (!isset($_SESSION['validated']) || $_SESSION['validated'] !== true) {
    header('Location: validar.php');
    exit;
}

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
	<!--meta http-equiv="refresh" content="10" /-->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mapa de Cobertura FTTH</title>
    <script async defer src="https://maps.googleapis.com/maps/api/js?key=<?php require_once("config/conf.php"); echo $googleMapsApiKey; ?>&libraries=geometry"></script>
    <link rel="stylesheet" href="css/style.css"> <!-- Adiciona o link para o CSS -->
</head>
<body>
    <div id="map"></div>

    <script>
	// Refresh the page
	//setTimeout("location.reload();",10*1000);
        // Dados das cto obtidas do PHP (JSON convertido para JavaScript)
            const ctos = <?php echo json_encode($ctos); ?>;
            const markers = [];
            const ctoLocations = [];

        // Configuração inicial do mapa
	function initMap() {
		const map = new google.maps.Map(document.getElementById("map"), {
			center: new google.maps.LatLng(<?php echo $centralLatitude; ?>, <?php echo $centralLongitude; ?>),
			zoom: 15,
		});

		const service = new google.maps.DirectionsService();

		ctos.forEach(cto => {
			const coords = cto.map_ll.split(",");
			const lat = parseFloat(coords[0].trim());
			const lng = parseFloat(coords[1].trim());
			const marker = new google.maps.Marker({
				position: { lat: lat, lng: lng },
				map: map,
				title: cto.ident,
					icon: {
						url: 'images/cto.png', // Caminho para o seu ícone personalizado
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
                            </div>
                        `
                    });

                    // Exibe o balão ao clicar no marcador
                    marker.addListener('click', () => {
                        infoWindow.open(map, marker);
                    });
					
		// Calcula rotas em várias direções para formar o polígono
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
                travelMode: 'TRANSIT', // Ajustável para "DRIVING" ou outros
            };

            directionsRequests.push(service.route(request));
        });

        Promise.all(directionsRequests).then(results => {
            const path = results.map(result => result.routes[0].legs[0].end_location);

			/* Adiciona círculo com raio de 250 metros
			const circle = new google.maps.Circle({
				map: map,
				radius: 250, // Raio em metros
				center: { lat: lat, lng: lng },
				fillColor: "#00FF00", // Cor de preenchimento (verde)
				fillOpacity: 0.3, // Opacidade do preenchimento
				strokeColor: "#00FF00", // Cor da borda
				strokeOpacity: 0.8, // Opacidade da borda
				strokeWeight: 2, // Largura da borda
			});*/
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
        }).catch(err => console.error("Erro ao calcular rotas:", err));
    });
}


        // Inicializa o mapa
        window.onload = initMap;
    </script>
</body>
</html>
