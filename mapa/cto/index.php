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

// Filtrar dados das CTOs.
// O escape de HTML agora é feito no JavaScript (escapeHtml), assim os valores
// "crus" continuam corretos ao serem enviados por POST para onu.php / onuauth.php.
$filteredCtos = array_map(function($cto) {
    return [
        'ident'      => (string)($cto['ident'] ?? ''),
        'map_ll'     => (string)($cto['map_ll'] ?? ''),
        'ports'      => intval($cto['ports'] ?? 0),
        'busy_ports' => array_map('intval', is_array($cto['busy_ports'] ?? null) ? $cto['busy_ports'] : []),
        'onu_count'  => intval($cto['onu_count'] ?? 0),
        'pon'        => (string)($cto['pon'] ?? ''),
        'note'       => (string)($cto['note'] ?? ''),
        'id'         => intval($cto['id'] ?? 0)
    ];
}, $ctos);

$ctosJson = json_encode(
    $filteredCtos,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mapa de CTOs</title>

    <!-- Leaflet (sem API key). Para ficar 100% independente de CDN, baixe os arquivos e hospede localmente. -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime('css/style.css'); ?>">
    <style>
        /* Garante que os botões e o "carregando" fiquem acima do mapa Leaflet */
        #search-container, #loading { z-index: 1100; }
    </style>
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
    <div id="search-container">
        <button id="new-table-btn" onclick="clearMeasurements()">Limpar Medições🧹</button>
        <button id="new-table-btn" onclick="redirMapaclientes()">Mapa de Clientes</button>
        <button id="new-table-btn" onclick="redirectToCoverageMap()">Mapa de Cobertura</button>
        <button id="logoutButton">Sair</button>
    </div>
    <div id="loading">Calculando rotas...</div>

    <script>
        // ===== Configurações =====
        // Servidor de rotas a pé (OSM). Pode ser trocado por um OSRM/GraphHopper/ORS próprio.
        const ROUTING_URL = 'https://routing.openstreetmap.de/routed-foot/route/v1/foot';
        // Quantas CTOs mais próximas (em linha reta) terão a rota real calculada
        const MAX_ROUTE_CANDIDATES = 8;

        // ===== Estado =====
        let map = null;
        let routeLayer = null;      // rota a pé (substitui o DirectionsRenderer)
        let aerialPath = null;      // linha aérea
        let routeInfoPopup = null;  // popup com a distância total
        let currentMeasurement = null;
        let currentMarker = null;
        let routeRequestId = 0;     // evita que um cálculo antigo sobrescreva um novo

        // Ícones
        const ctoIcon = L.icon({
            iconUrl: 'images/cto.png',
            iconSize: [30, 30],
            iconAnchor: [15, 15],
            popupAnchor: [0, -15]
        });
        const measureIcon = L.icon({
            iconUrl: 'images/green-icon.png',
            iconSize: [30, 30],
            iconAnchor: [15, 15]
        });

        // Escapa HTML para uso seguro no conteúdo dos popups
        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        // Função para inicializar o mapa
        function initMap() {
            map = L.map('map').setView([<?php echo $centralLatitude; ?>, <?php echo $centralLongitude; ?>], 15);

            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
            }).addTo(map);

            const ctos = <?php echo $ctosJson; ?>;
            const ctoLocations = [];

            // Adiciona marcadores ao mapa
            ctos.forEach(cto => {
                if (!cto.map_ll) return;

                const coords = cto.map_ll.split(",");
                const lat = parseFloat(coords[0]);
                const lng = parseFloat(coords[1]);
                if (isNaN(lat) || isNaN(lng)) return;

                const marker = createMarker(cto, lat, lng);
                marker.bindPopup(() => createPopupContent(cto), { maxWidth: 320 });
                ctoLocations.push({ lat, lng, marker, cto });
            });

            // Evento de clique no mapa para calcular rotas
            map.on('click', (event) => {
                const clickedLocation = event.latlng;

                if (currentMeasurement) {
                    clearMeasurements();
                }

                currentMeasurement = clickedLocation;
                currentMarker = createMeasurementMarker(clickedLocation);

                calculateShortestRoute(clickedLocation, ctoLocations);
            });
        }

        // Função para criar um marcador de CTO
        function createMarker(cto, lat, lng) {
            return L.marker([lat, lng], { icon: ctoIcon, title: cto.ident }).addTo(map);
        }

        // Função para criar o conteúdo do popup da CTO (equivalente ao InfoWindow)
        function createPopupContent(cto) {
            const busyCount = cto.busy_ports?.length ?? 0;
            const lotada = busyCount >= cto.ports;
            const sortedBusy = [...(cto.busy_ports || [])].sort((a, b) => a - b).join(', ');

            const verSinalAttrs = cto.onu_count > 0
                ? ''
                : 'disabled class="disabled-btn" title="CTO não possui ONU vinculada."';
            const autorizarAttrs = busyCount < cto.ports
                ? ''
                : 'disabled class="disabled-btn" title="CTO Lotada - Não é possível autorizar novas ONUs."';

            const container = document.createElement('div');
            container.innerHTML = `
                <p class="${lotada ? 'cto-lotada' : ''}">
                    ${lotada ? '🚨 CTO LOTADA 🚨' : ''}
                </p>
                <h2>➡️ ${escapeHtml(cto.ident)} ⬅️</h2>
                <p><strong>Número de Portas:</strong> ${cto.ports}</p>
                ${busyCount > 0
                    ? `<p><strong>Total ocupadas:</strong>
                        <font color="${(busyCount + 1 === cto.ports) ? 'orange' : 'green'}">
                            <span class="${busyCount === cto.ports ? 'blink' : ''}">${busyCount}</span>
                        </font>
                       </p>`
                    : ''}
                <p><strong>Portas ocupadas:</strong>
                    <span style="color: red;">${busyCount > 0 ? sortedBusy : 'Nenhuma'}</span>
                </p>
                ${cto.note ? `<p><strong>Observações:</strong> ${escapeHtml(cto.note)}</p>` : ''}
                <p><strong>OLT PON:</strong> ${escapeHtml(cto.pon)}</p>
                <button id="new-table-btn" data-action="sinal" ${verSinalAttrs}>Ver Sinal</button>
                <button id="new-table-btn" data-action="auth" ${autorizarAttrs}>Autorizar ONU</button>
            `;

            const btnSinal = container.querySelector('[data-action="sinal"]');
            const btnAuth = container.querySelector('[data-action="auth"]');

            if (!btnSinal.disabled) {
                btnSinal.addEventListener('click', () => redirectToOnu(cto.id));
            }
            if (!btnAuth.disabled) {
                btnAuth.addEventListener('click', () =>
                    redirectToOnuAuth(cto.id, cto.ports, cto.busy_ports.join(','), cto.pon, cto.ident)
                );
            }

            return container;
        }

        // Função para criar um marcador de medição
        function createMeasurementMarker(position) {
            return L.marker(position, { icon: measureIcon, draggable: false }).addTo(map);
        }

        // Busca a rota a pé entre dois pontos (OSRM)
        async function fetchWalkingRoute(from, to) {
            const url = `${ROUTING_URL}/${from.lng},${from.lat};${to.lng},${to.lat}` +
                        `?overview=full&geometries=geojson`;
            const resp = await fetch(url);
            if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
            const data = await resp.json();
            if (data.code !== 'Ok' || !data.routes?.length) {
                throw new Error(data.code || 'Sem rota');
            }
            return {
                route: data.routes[0],
                snappedStart: data.waypoints[0].location // [lng, lat]
            };
        }

        // Função para calcular a rota mais curta
        async function calculateShortestRoute(clickedLocation, ctoLocations) {
            const requestId = ++routeRequestId;
            showLoading();

            // Pré-seleciona as CTOs mais próximas em linha reta, para não sobrecarregar o servidor de rotas
            const candidates = ctoLocations
                .map(loc => ({ loc, aerial: map.distance(clickedLocation, [loc.lat, loc.lng]) }))
                .sort((a, b) => a.aerial - b.aerial)
                .slice(0, MAX_ROUTE_CANDIDATES);

            try {
                const settled = await Promise.allSettled(
                    candidates.map(async ({ loc }) => {
                        const result = await fetchWalkingRoute(
                            { lat: clickedLocation.lat, lng: clickedLocation.lng },
                            { lat: loc.lat, lng: loc.lng }
                        );
                        return { ...result, distance: result.route.distance, location: loc };
                    })
                );

                // Se o usuário clicou de novo/limpou durante o cálculo, descarta este resultado
                if (requestId !== routeRequestId) return;

                const results = settled.filter(r => r.status === 'fulfilled').map(r => r.value);
                settled.filter(r => r.status === 'rejected')
                       .forEach(r => console.error('Erro ao calcular rota:', r.reason));

                if (!results.length) {
                    routeInfoPopup = L.popup({ autoClose: false, closeOnClick: false })
                        .setLatLng(clickedLocation)
                        .setContent('<div><h3>Não foi possível calcular a rota.</h3></div>')
                        .addTo(map);
                    return;
                }

                const shortestRoute = results.reduce((shortest, current) =>
                    current.distance < shortest.distance ? current : shortest
                );

                // Desenha a rota terrestre (azul)
                const routeCoords = shortestRoute.route.geometry.coordinates.map(c => [c[1], c[0]]);
                routeLayer = L.polyline(routeCoords, {
                    color: "#0000FF",
                    opacity: 1.0,
                    weight: 4
                }).addTo(map);

                // Desenha a linha aérea (do ponto da rua mais próximo até o ponto clicado)
                const snapped = L.latLng(shortestRoute.snappedStart[1], shortestRoute.snappedStart[0]);
                aerialPath = L.polyline([snapped, clickedLocation], {
                    color: "#0000FF",
                    opacity: 1.0,
                    weight: 4
                }).addTo(map);

                const aerialDistance = map.distance(snapped, clickedLocation);
                const totalDistance = shortestRoute.distance + aerialDistance;

                routeInfoPopup = L.popup({ autoClose: false, closeOnClick: false })
                    .setLatLng(clickedLocation)
                    .setContent(`<div><h3>Distância total: ${totalDistance.toFixed(2)} metros</h3></div>`)
                    .addTo(map);
            } catch (error) {
                console.error('Erro ao calcular rota:', error);
            } finally {
                if (requestId === routeRequestId) hideLoading();
            }
        }

        // Cria um formulário POST dinâmico e submete em nova aba
        function postToNewTab(action, fields) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = action;
            form.target = '_blank'; // Abre em uma nova aba

            Object.entries(fields).forEach(([name, value]) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value;
                form.appendChild(input);
            });

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }

        // Função para redirecionar para Ver Sinal
        function redirectToOnu(ctoId) {
            postToNewTab('onu.php', { cto: ctoId });
        }

        // Função para redirecionar para Autorizar ONU
        function redirectToOnuAuth(ctoId, ports, busy_ports, pon, ident) {
            postToNewTab('onuauth.php', {
                olt_id: 3,
                cto: ctoId,
                ports: ports,
                occupied_ports: busy_ports,
                ctopon: pon,
                ctoident: ident
            });
        }

        // Função para limpar medições
        function clearMeasurements() {
            routeRequestId++; // cancela qualquer cálculo em andamento
            hideLoading();

            if (routeLayer) {
                routeLayer.remove();
                routeLayer = null;
            }
            if (routeInfoPopup) {
                routeInfoPopup.remove();
                routeInfoPopup = null;
            }
            if (currentMarker) {
                currentMarker.remove();
                currentMarker = null;
                currentMeasurement = null;
            }
            if (aerialPath) {
                aerialPath.remove();
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

        // Inicia o mapa
        initMap();
    </script>
</body>
</html>