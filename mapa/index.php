<?php
// Inicio da função auth
require_once("auth.php");

// Exibir pop-up de boas-vindas apenas no primeiro login
$nomeUsuario = $_SESSION['user_info']['nome'] ?? 'Usuário';

$mostrarPopup = isset($_SESSION['welcome_shown']) && $_SESSION['welcome_shown'] === false;

if ($mostrarPopup) {
    $_SESSION['welcome_shown'] = true; // Evita que o pop-up apareça novamente após o login
}
// Fim a função auth

require_once("config/conf.php");

?>

<!DOCTYPE html>
<html>
<head>
    <title>Mapa SGP</title>
    <!-- Inclua o CSS do Leaflet -->
    <link rel="stylesheet" href="css/style.css?v=1107022025" />
</head>
<body>
    <!-- Inicio do JavaScript para exibir o pop-up de boas-vindas -->
    <?php if ($mostrarPopup): ?>
    <script>
        window.onload = function() {
            alert('Bem-vindo, ' + <?php echo json_encode($nomeUsuario); ?> + '!');
        };
    </script>
    <?php endif; ?>
    <!-- Fim do JavaScript para exibir o pop-up de boas-vindas -->	
<!-- Adicione um elemento de carregamento -->
<div id="loading" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);">
    <img src="images/loading.gif" alt="Carregando...">
</div>

<!-- Crie um elemento div para o mapa -->
<div id="map"></div>

<!-- Inclua a biblioteca Google Maps API -->
<script src="https://maps.googleapis.com/maps/api/js?key=<?php require_once("config/conf.php"); echo $googleMapsApiKey; ?>"></script>
<!-- Inclua a biblioteca jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

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
			url: 'atualizar_marcadores.php',
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
		data.forEach((item) => {
			// Verifica se latitude e longitude são válidas
			if (!item.latitude || !item.longitude) return;

			let statusIconUrl = item.online ? 'images/green-icon.png' : 'images/red-icon.png';
			let markerIcon = {
				url: statusIconUrl,
				scaledSize: new google.maps.Size(32, 32)
			};

			let marker = new google.maps.Marker({
				position: new google.maps.LatLng(item.latitude, item.longitude),
				map: map,
				icon: markerIcon,
				title: item.nome
			});

			// Criando conteúdo da InfoWindow
			let content = `
				<div class="custon-infowindow">
					<h3><strong>${item.nome}</strong></h3>
					<p>Vlan: ${item.vlan || "-"}</p>
					<p>Última conexão: ${item.acct || "-"}</p>
					${item.stop ? `<p><span style='color: red;'>Desconectado desde:</span> ${item.stop}</p>` : ""}
					${item.cto ? `<p>CTO: ${item.cto} ⬅️</p>` : ""}
					${item.ctoport ? `<p>CTO Porta: ${item.ctoport} ⬅️</p>` : ""}
					${item.info_rx ? `<p>Sinal RX: ${item.info_rx} ⬅️</p>` : ""}
					<p>IP: <a target="_blank" href="http://${encodeURIComponent(item.ip)}:<?php echo $port; ?>">${item.ip}</a></p>
					${item.ipv6 ? `<p>IPv6: ${item.ipv6}</p>` : ""}
					
					<?php if ( $tecnologia == 1 ) { echo '<footer>
								${item.cto ? "<p>Cliente Fibra</p>" : "<p>Cliente UTP</p>"}
					</footer>'; }; ?>
					<?php if ($ConsutaOnuAtiva == 1) { ?>
						${item.cto ? `
							<div style="text-align: center;">
								<button class="btn-search-onu" onclick="buscarONU('${item.onuid}')">
									🔍 Consultar ONU
								</button>
							</div>
						` : ""}
					<?php } ?>
				</div>
			`;
			// Adiciona evento diretamente sem closure
			marker.addListener("click", () => {
				infowindow.setContent(content);
				infowindow.open(map, marker);
			});

			markers.push(marker);
		});
	}
	function buscarONU(identificador) {
		if (!identificador) {
			alert("Identificador da ONU inválido!");
			return;
		}

		let botao = document.querySelector(`button[onclick="buscarONU('${identificador}')"]`);
		if (botao) {
			botao.classList.add("loading");
			botao.textContent = "Buscando...";
		}

		let url = `<?php echo $url; ?>/api/fttx/onu/${identificador}/info/?token=<?php echo $token; ?>&app=<?php echo $app; ?>`;

		fetch(url)
		.then(response => response.json())
		.then(data => {
			if (data.result) {
				let formattedResult = formatarRetorno(data.result);
				document.getElementById("jsonOutput").textContent = formattedResult;
			} else {
				document.getElementById("jsonOutput").textContent = "Nenhum dado retornado.";
			}

			openCustomModal(); // Abre o modal personalizado
		})
		.catch(error => {
			console.error("Erro ao buscar a ONU:", error);
			alert("Erro ao buscar a ONU.");
		})
		.finally(() => {
			if (botao) {
				botao.classList.remove("loading");
				botao.textContent = "🔍 Consultar ONU";
			}
		});
	}

	// Função para abrir o modal
	function openCustomModal() {
		document.getElementById("customJsonModal").style.display = "flex";
	}

	// Função para fechar o modal
	function closeCustomModal() {
		document.getElementById("customJsonModal").style.display = "none";
	}

	// Função para formatar o JSON retornado
	function formatarRetorno(texto) {
		return texto
			.replace(/(\r\n|\r|\n)/g, '\n')  // Substitui quebras de linha
			.trim(); // Remove espaços extras
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

	<!-- Adicione o novo botão de redirecionamento -->
	<button id="new-table-btn" onclick="redirectTocoveragemap()">Mapa de Cobertura</button>

	<!-- Adicione o novo botão de redirecionamento -->
	<button id="new-table-btn" onclick="redirectTolistdesconect()">Listar Desconexões</button>	

	<!-- Adicione botão de logout -->
	<button id="logoutButton">Sair</button>
	</div>
</div>
<script>
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
    // Função para redirecionar para o link desejado
    function redirectToNewTable() {
		window.open("cto/", "_blank");
    }
	// Função para redirecionar para pagina de cobertura
    function redirectTocoveragemap() {
		window.open("cto/cobertura.php", "_blank");
    }

	// Função para redirecionar para pagina de desconexões
    function redirectTolistdesconect() {
		window.open("desconexoes/index.php", "_blank");
    }	
</script>
<!-- Modal para exibir os dados da ONU -->
<!-- Modal Personalizado -->
<div id="customJsonModal" class="custom-modal">
  <div class="custom-modal-content">
    <div class="custom-modal-header">
      <span class="custom-close" onclick="closeCustomModal()">&times;</span>
      <h5>Dados da ONU</h5>
    </div>
    <div class="custom-modal-body">
      <!-- Indicador de carregamento -->
		<div id="loadingOnu" class="loading-onu">Carregando...</div>
		<pre id="jsonOutput"></pre>
    </div>
  </div>
</div>

<!-- Bootstrap JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
