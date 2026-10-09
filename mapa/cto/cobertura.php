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

// Filtrar dados das CTOs.
// O escape de HTML é feito no JavaScript (escapeHtml), evitando texto "duplamente escapado"
// em títulos e campos de busca.
$filteredCtos = array_map(function($cto) {
    return [
        'ident'      => (string)($cto['ident'] ?? ''),
        'map_ll'     => (string)($cto['map_ll'] ?? ''),
        'ports'      => intval($cto['ports'] ?? 0),
        'busy_ports' => array_map('intval', is_array($cto['busy_ports'] ?? null) ? $cto['busy_ports'] : []),
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
    <title>Mapa de Cobertura FTTH</title>

    <!-- CSS do Leaflet -->
    <link rel="stylesheet" href="css/leaflet.css">
    <link rel="stylesheet" href="css/style.css?v=<?= filemtime('css/style.css'); ?>">

    <style>
        #search-container { z-index: 1100; }

        /* ===== Painel de controles ===== */
        .cv-panel, .cv-legend {
            background: #fff;
            padding: 10px 12px;
            border-radius: 8px;
            box-shadow: 0 1px 8px rgba(0,0,0,.3);
            font: 13px/1.45 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            color: #222;
        }
        .cv-panel { width: 240px; max-width: 60vw; }
        .cv-panel .cv-title { font-weight: 600; margin-bottom: 4px; }
        .cv-panel input[type="text"] {
            width: 100%; box-sizing: border-box; padding: 6px 8px;
            border: 1px solid #bbb; border-radius: 6px; margin-bottom: 8px;
        }
        .cv-panel input[type="range"] { width: 100%; margin: 2px 0 8px; }
        .cv-panel label { display: block; margin-bottom: 6px; cursor: pointer; }
        .cv-row { display: flex; gap: 6px; margin-top: 4px; }
        .cv-btn {
            flex: 1; padding: 6px 4px; border: 1px solid #bbb; border-radius: 6px;
            background: #f5f5f5; cursor: pointer; font-size: 12px;
        }
        .cv-btn:hover { background: #e8e8e8; }
        .cv-hint { margin-top: 8px; font-size: 11.5px; color: #666; }

        /* ===== Legenda / estatísticas ===== */
        .cv-legend { min-width: 190px; }
        .cv-legend h4 { margin: 0 0 6px; font-size: 13px; }
        .cv-legend .cv-item { display: flex; align-items: center; gap: 6px; margin: 2px 0; }
        .cv-dot { width: 12px; height: 12px; border-radius: 50%; display: inline-block; flex: none; }
        .cv-legend .cv-sep { border-top: 1px solid #ddd; margin: 6px 0; }

        /* ===== Popups ===== */
        .cv-popup h2 { margin: 0 0 6px; font-size: 16px; }
        .cv-popup p { margin: 4px 0; }
        .cv-badge {
            display: inline-block; color: #fff; padding: 1px 8px; border-radius: 10px;
            font-size: 12px; font-weight: 600;
        }
        .cv-bar { height: 8px; background: #e0e0e0; border-radius: 4px; overflow: hidden; margin: 6px 0; }
        .cv-bar > div { height: 100%; }
        .cv-check h3 { margin: 0 0 6px; font-size: 14px; }
        .cv-check ul { margin: 4px 0 0; padding-left: 18px; }
    </style>
</head>

<body class="mapa-page">
    <!-- Inicio do JavaScript para exibir o pop-up de boas-vindas -->
    <?php if ($mostrarPopup): ?>
    <script>
        // addEventListener (e não window.onload =) para não ser sobrescrito por outros scripts
        window.addEventListener('load', function() {
            alert('Bem-vindo, ' + <?php echo json_encode($nomeUsuario); ?> + '!');
        });
    </script>
    <?php endif; ?>
    <!-- Fim do JavaScript para exibir o pop-up de boas-vindas -->

    <div id="map" style="height: 100vh;"></div>

    <!-- Inclua a biblioteca Leaflet -->
    <script src="js/leaflet.js"></script>

    <div id="search-container">
        <button id="new-table-btn" onclick="redirMapaclientes()">Mapa de Clientes</button>
        <button id="new-table-btn" onclick="redirMapacto()">Mapa de CTO</button>
        <button id="logoutButton">Sair</button>
    </div>

    <script>
        // ===== Configurações =====
        const DEFAULT_RADIUS = 200;   // Raio de cobertura inicial (metros)

        const STATUS = {
            livre:  { color: '#2e7d32', label: 'Com vagas' },
            quase:  { color: '#fb8c00', label: 'Quase lotada' },
            lotada: { color: '#e53935', label: 'Lotada' },
            nd:     { color: '#757575', label: 'Sem dados de portas' }
        };

        // Dados das CTOs obtidas do PHP
        const ctos = <?php echo $ctosJson; ?>;

        // ===== Estado =====
        let map = null;
        const entries = [];                 // { cto, lat, lng, marker, circle, status, free }
        const state = { radius: DEFAULT_RADIUS, onlyFree: false };
        let markersGroup = null;
        let circlesGroup = null;
        let checkMarker = null;
        let checkLine = null;
        let checkPopup = null;

        // ===== Utilitários =====
        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function formatDistance(meters) {
            return meters < 1000
                ? `${Math.round(meters)} m`
                : `${(meters / 1000).toFixed(2)} km`;
        }

        function getStatus(cto) {
            const busy = cto.busy_ports.length;
            if (cto.ports <= 0) return 'nd';
            if (busy >= cto.ports) return 'lotada';
            if (busy + 1 === cto.ports) return 'quase';
            return 'livre';
        }

        // ===== Inicialização =====
        function initMap() {
            // preferCanvas: círculos desenhados em canvas (bem mais leve com muitas CTOs)
            map = L.map('map', { preferCanvas: true, zoomControl: false })
                .setView([<?php echo $centralLatitude; ?>, <?php echo $centralLongitude; ?>], 15);

            L.control.zoom({ position: 'bottomright' }).addTo(map);
            L.control.scale({ imperial: false, position: 'bottomright' }).addTo(map);

            // Mapas base
            const osm = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors'
            });
            const satelite = L.tileLayer(
                'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                maxZoom: 19,
                attribution: 'Tiles &copy; Esri'
            });
            osm.addTo(map);

            markersGroup = L.layerGroup();
            circlesGroup = L.layerGroup();

            // Cria marcadores e áreas de cobertura
            const ctoIcon = L.icon({
                iconUrl: 'images/cto.png',
                iconSize: [30, 30],
                iconAnchor: [15, 15],
                popupAnchor: [0, -15]
            });

            let ignoradas = 0;
            ctos.forEach(cto => {
                if (!cto.map_ll) return;

                const coords = cto.map_ll.split(",");
                const lat = parseFloat(coords[0]);
                const lng = parseFloat(coords[1]);
                if (isNaN(lat) || isNaN(lng)) { ignoradas++; return; }

                const status = getStatus(cto);
                const free = Math.max(0, cto.ports - cto.busy_ports.length);
                const color = STATUS[status].color;

                const marker = L.marker([lat, lng], { icon: ctoIcon, title: cto.ident });
                const circle = L.circle([lat, lng], {
                    color: color,
                    weight: 1,
                    fillColor: color,
                    fillOpacity: 0.10,
                    radius: state.radius,
                    interactive: false   // cliques "atravessam" o círculo e chegam ao mapa
                });

                const entry = { cto, lat, lng, marker, circle, status, free };
                marker.bindPopup(() => buildPopup(entry), { maxWidth: 300 });

                markersGroup.addLayer(marker);
                circlesGroup.addLayer(circle);
                entries.push(entry);
            });

            if (ignoradas) console.warn(`${ignoradas} CTO(s) ignorada(s) por coordenadas inválidas.`);

            circlesGroup.addTo(map);
            markersGroup.addTo(map);

            // Controle de camadas (mapa base + sobreposições)
            L.control.layers(
                { '🗺️ Mapa': osm, '🛰️ Satélite': satelite },
                { '📡 CTOs': markersGroup, '🟢 Áreas de cobertura': circlesGroup },
                { position: 'topright', collapsed: true }
            ).addTo(map);

            addPanelControl();
            addLegendControl();

            // Clique no mapa: verifica a cobertura daquele ponto
            map.on('click', e => checkCoverage(e.latlng));

            // Ao fechar o popup de verificação, remove marcador e linha auxiliares
            map.on('popupclose', e => {
                if (checkPopup && e.popup === checkPopup) {
                    removeCheckLayers();
                    checkPopup = null;
                }
            });

            // Geolocalização
            map.on('locationfound', e => {
                map.flyTo(e.latlng, 17);
                checkCoverage(e.latlng);
            });
            map.on('locationerror', () => {
                alert('Não foi possível obter sua localização. Verifique a permissão do navegador.');
            });
        }

        // ===== Popup da CTO =====
        function buildPopup(entry) {
            const { cto, status, free } = entry;
            const st = STATUS[status];
            const busy = cto.busy_ports.length;
            const pct = cto.ports > 0 ? Math.min(100, Math.round(busy / cto.ports * 100)) : 0;
            const ocupadas = busy > 0
                ? [...cto.busy_ports].sort((a, b) => a - b).join(', ')
                : 'Nenhuma';

            return `
                <div class="infowindow-content cv-popup">
                    <h2>➡ ${escapeHtml(cto.ident)} ⬅</h2>
                    <span class="cv-badge" style="background:${st.color}">${st.label}</span>
                    <div class="cv-bar"><div style="width:${pct}%;background:${st.color}"></div></div>
                    <p><strong>Ocupação:</strong> ${busy}/${cto.ports} (${pct}%) &middot; <strong>${free}</strong> livre(s)</p>
                    <p><strong>Número de Portas:</strong> ${cto.ports}</p>
                    <p><strong>Portas ocupadas:</strong> <font color="red">${escapeHtml(ocupadas)}</font></p>
                    <p><strong>Observações:</strong> ${cto.note ? escapeHtml(cto.note) : 'Nenhuma'}</p>
                </div>
            `;
        }

        // ===== Verificação de cobertura ao clicar no mapa =====
        function removeCheckLayers() {
            if (checkMarker) { checkMarker.remove(); checkMarker = null; }
            if (checkLine) { checkLine.remove(); checkLine = null; }
        }

        function checkCoverage(latlng) {
            // Limpa verificação anterior (sem disparar o handler de popupclose)
            const oldPopup = checkPopup;
            checkPopup = null;
            removeCheckLayers();
            if (oldPopup) map.closePopup(oldPopup);

            if (!entries.length) return;

            const radius = state.radius;
            const sorted = entries
                .map(e => ({ e, d: map.distance(latlng, [e.lat, e.lng]) }))
                .sort((a, b) => a.d - b.d);

            const covering = sorted.filter(x => x.d <= radius);
            const coveringFree = covering.filter(x => x.e.free > 0);
            const nearestFree = sorted.find(x => x.e.free > 0);

            let headline, color;
            if (coveringFree.length) {
                headline = `✅ Coberto: ${coveringFree.length} CTO(s) com portas livres em até ${radius} m`;
                color = STATUS.livre.color;
            } else if (covering.length) {
                headline = '⚠️ Dentro do raio, mas todas as CTOs estão lotadas';
                color = STATUS.quase.color;
            } else {
                headline = `❌ Fora da cobertura (raio de ${radius} m)`;
                color = STATUS.lotada.color;
            }

            // Lista: CTOs dentro do raio (até 5) ou, se nenhuma, as 3 mais próximas
            const lista = (covering.length ? covering : sorted).slice(0, covering.length ? 5 : 3);
            const itens = lista.map(x =>
                `<li><strong>${escapeHtml(x.e.cto.ident)}</strong> &mdash; ${formatDistance(x.d)}
                 &mdash; ${x.e.free} porta(s) livre(s)</li>`
            ).join('');

            let extra = '';
            if (!coveringFree.length && nearestFree) {
                extra = `<p style="margin:8px 0 0"><strong>CTO mais próxima com vaga:</strong>
                         ${escapeHtml(nearestFree.e.cto.ident)} (${formatDistance(nearestFree.d)} em linha reta)</p>`;
            }

            checkMarker = L.circleMarker(latlng, {
                radius: 7, color: '#fff', weight: 2,
                fillColor: color, fillOpacity: 1, interactive: false
            }).addTo(map);

            if (nearestFree) {
                checkLine = L.polyline([latlng, [nearestFree.e.lat, nearestFree.e.lng]], {
                    color: '#1976d2', weight: 3, dashArray: '6 8', interactive: false
                }).addTo(map);
            }

            checkPopup = L.popup({ maxWidth: 320 })
                .setLatLng(latlng)
                .setContent(`
                    <div class="cv-check">
                        <h3>${headline}</h3>
                        <div>${covering.length ? 'CTOs no raio:' : 'CTOs mais próximas:'}</div>
                        <ul>${itens}</ul>
                        ${extra}
                        <p style="margin:8px 0 0;font-size:11.5px;color:#666">
                            Distâncias em linha reta (não consideram o caminho pelas ruas).
                        </p>
                    </div>
                `)
                .openOn(map);
        }

        // ===== Painel de controles (busca, raio, filtro) =====
        function addPanelControl() {
            const control = L.control({ position: 'topright' });

            control.onAdd = function () {
                const div = L.DomUtil.create('div', 'cv-panel');
                div.innerHTML = `
                    <div class="cv-title">🔎 Buscar CTO</div>
                    <input type="text" id="cv-search" list="cv-ctos" placeholder="Nome da CTO..." autocomplete="off">
                    <datalist id="cv-ctos"></datalist>

                    <label for="cv-radius">Raio de cobertura: <strong id="cv-radius-label">${state.radius} m</strong></label>
                    <input type="range" id="cv-radius" min="50" max="500" step="10" value="${state.radius}">

                    <label><input type="checkbox" id="cv-onlyfree"> Mostrar só CTOs com vagas</label>

                    <div class="cv-row">
                        <button type="button" class="cv-btn" id="cv-fit">Ajustar ao mapa</button>
                        <button type="button" class="cv-btn" id="cv-locate">📍 Minha localização</button>
                    </div>
                    <div class="cv-hint">Clique no mapa para verificar se um endereço tem cobertura.</div>
                `;

                // Evita que cliques/scroll no painel movam ou cliquem no mapa
                L.DomEvent.disableClickPropagation(div);
                L.DomEvent.disableScrollPropagation(div);

                // Lista de CTOs para autocompletar
                const datalist = div.querySelector('#cv-ctos');
                entries.forEach(e => {
                    const opt = document.createElement('option');
                    opt.value = e.cto.ident;
                    datalist.appendChild(opt);
                });

                const search = div.querySelector('#cv-search');
                const onlyFree = div.querySelector('#cv-onlyfree');

                const doSearch = () => {
                    const term = search.value.trim().toLowerCase();
                    if (!term) return;
                    const found = entries.find(e => e.cto.ident.toLowerCase() === term)
                               || entries.find(e => e.cto.ident.toLowerCase().includes(term));
                    if (!found) { alert('CTO não encontrada.'); return; }

                    // Se o filtro estiver escondendo essa CTO, desativa o filtro
                    if (state.onlyFree && found.free === 0) {
                        onlyFree.checked = false;
                        state.onlyFree = false;
                        applyFilter();
                    }
                    focusEntry(found);
                };
                search.addEventListener('change', doSearch);
                search.addEventListener('keydown', ev => { if (ev.key === 'Enter') doSearch(); });

                // Raio de cobertura
                const radiusInput = div.querySelector('#cv-radius');
                const radiusLabel = div.querySelector('#cv-radius-label');
                radiusInput.addEventListener('input', () => {
                    state.radius = parseInt(radiusInput.value, 10);
                    radiusLabel.textContent = `${state.radius} m`;
                    entries.forEach(e => e.circle.setRadius(state.radius));
                });

                // Filtro "só com vagas"
                onlyFree.addEventListener('change', () => {
                    state.onlyFree = onlyFree.checked;
                    applyFilter();
                });

                div.querySelector('#cv-fit').addEventListener('click', fitToContent);
                div.querySelector('#cv-locate').addEventListener('click', () => {
                    map.locate({ enableHighAccuracy: true });
                });

                return div;
            };

            control.addTo(map);
        }

        function focusEntry(entry) {
            const target = L.latLng(entry.lat, entry.lng);
            const jaAli = map.getZoom() === 18 && map.distance(map.getCenter(), target) < 1;
            if (jaAli) {
                entry.marker.openPopup();
            } else {
                map.once('moveend', () => entry.marker.openPopup());
                map.flyTo(target, 18);
            }
        }

        function applyFilter() {
            entries.forEach(e => {
                const show = !state.onlyFree || e.free > 0;
                if (show) {
                    if (!markersGroup.hasLayer(e.marker)) markersGroup.addLayer(e.marker);
                    if (!circlesGroup.hasLayer(e.circle)) circlesGroup.addLayer(e.circle);
                } else {
                    markersGroup.removeLayer(e.marker);
                    circlesGroup.removeLayer(e.circle);
                }
            });
        }

        function fitToContent() {
            const visiveis = entries.filter(e => !state.onlyFree || e.free > 0);
            if (!visiveis.length) return;
            const bounds = L.latLngBounds(visiveis.map(e => [e.lat, e.lng]));
            map.fitBounds(bounds.pad(0.1));
        }

        // ===== Legenda com estatísticas =====
        function addLegendControl() {
            const contagem = { livre: 0, quase: 0, lotada: 0, nd: 0 };
            let totalPortas = 0, totalOcupadas = 0;
            entries.forEach(e => {
                contagem[e.status]++;
                totalPortas += e.cto.ports;
                totalOcupadas += Math.min(e.cto.busy_ports.length, e.cto.ports);
            });
            const pct = totalPortas > 0 ? Math.round(totalOcupadas / totalPortas * 100) : 0;

            const control = L.control({ position: 'bottomleft' });
            control.onAdd = function () {
                const div = L.DomUtil.create('div', 'cv-legend');
                const linha = key => contagem[key] > 0 || key !== 'nd'
                    ? `<div class="cv-item"><span class="cv-dot" style="background:${STATUS[key].color}"></span>
                         ${STATUS[key].label}: <strong>${contagem[key]}</strong></div>`
                    : '';
                div.innerHTML = `
                    <h4>📡 ${entries.length} CTOs</h4>
                    ${linha('livre')}${linha('quase')}${linha('lotada')}${linha('nd')}
                    <div class="cv-sep"></div>
                    <div>Portas: <strong>${totalOcupadas}</strong>/${totalPortas} ocupadas (${pct}%)</div>
                    <div>Livres: <strong>${totalPortas - totalOcupadas}</strong></div>
                `;
                L.DomEvent.disableClickPropagation(div);
                return div;
            };
            control.addTo(map);
        }

        // Inicializa o mapa
        initMap();
    </script>

    <script>
        // Função para redirecionar para o mapa de clientes
        function redirMapaclientes() {
            window.open("../", "_blank");
        }
        // Função para redirecionar para o mapa de CTO
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