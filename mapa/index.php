<?php
// Inicio da função auth
require_once("auth.php");

// Exibir pop-up de boas-vindas apenas no primeiro login
$nomeUsuario = $_SESSION['user_info']['nome'] ?? 'Usuário';

$mostrarPopup = isset($_SESSION['welcome_shown']) && $_SESSION['welcome_shown'] === false;

if ($mostrarPopup) {
    $_SESSION['welcome_shown'] = true; // Evita que o pop-up apareça novamente após o login
}
// Fim da função auth

require_once("config/conf.php");
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Mapa SGP</title>

    <!-- CSS existente do sistema -->
    <link rel="stylesheet" href="css/style.css?v=1107022025" />

    <!-- Leaflet CSS -->
    <link
		rel="stylesheet"
		href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
		integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
		crossorigin=""
	/>
</head>

<body>

<?php if ($mostrarPopup): ?>
<script>
    window.onload = function() {
        alert('Bem-vindo, ' + <?php echo json_encode($nomeUsuario); ?> + '!');
    };
</script>
<?php endif; ?>


<!-- Indicador de carregamento -->
<div
    id="loading"
    style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 9999;"
>
    <img src="images/loading.gif" alt="Carregando...">
</div>


<!-- Mapa -->
<div id="map"></div>


<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- Leaflet JS -->
<script
    src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
    crossorigin=""
></script>


<script>

<?php require_once("config/conf.php"); ?>


/*
|--------------------------------------------------------------------------
| VARIÁVEIS DO MAPA
|--------------------------------------------------------------------------
*/

var map;
var markers = [];


/*
|--------------------------------------------------------------------------
| INICIALIZAÇÃO DO MAPA
|--------------------------------------------------------------------------
*/

function initMap() {

    /*
     * Cria o mapa usando as coordenadas configuradas no conf.php.
     *
     * Equivalente ao:
     * new google.maps.Map(...)
     */

    map = L.map('map').setView(
        [
            <?php echo $centralLatitude; ?>,
            <?php echo $centralLongitude; ?>
        ],
        15
    );


    /*
     * Camada OpenStreetMap
     */

    L.tileLayer(
        'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        {
            maxZoom: 19,
            attribution:
                '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank">OpenStreetMap</a>'
        }
    ).addTo(map);


    /*
     * Carrega os marcadores
     */

    refreshMarkers();
}


/*
|--------------------------------------------------------------------------
| ATUALIZAÇÃO DOS MARCADORES
|--------------------------------------------------------------------------
*/

function refreshMarkers() {

    $.ajax({
        url: 'atualizar_marcadores.php',
        dataType: 'json',

        success: function(data) {

            clearMarkers();

            addMarkers(data);

            document.getElementById('loading').style.display = 'none';
        },

        error: function(xhr, status, error) {

            console.error(
                'Erro ao buscar dados atualizados:',
                error
            );

            document.getElementById('loading').style.display = 'none';
        }
    });
}


/*
|--------------------------------------------------------------------------
| REMOVER MARCADORES EXISTENTES
|--------------------------------------------------------------------------
*/

function clearMarkers() {

    for (var i = 0; i < markers.length; i++) {

        /*
         * No Google Maps era:
         *
         * markers[i].setMap(null);
         *
         * No Leaflet:
         */

        map.removeLayer(markers[i]);
    }

    markers = [];
}


/*
|--------------------------------------------------------------------------
| ADICIONAR MARCADORES
|--------------------------------------------------------------------------
*/

function addMarkers(data) {

    data.forEach(function(item) {

        /*
         * Verifica se latitude e longitude são válidas
         */

        if (
            item.latitude === undefined ||
            item.latitude === null ||
            item.latitude === '' ||
            item.longitude === undefined ||
            item.longitude === null ||
            item.longitude === ''
        ) {
            return;
        }


        /*
         * Define o ícone conforme o status
         */

        var statusIconUrl = item.online
            ? 'images/green-icon.png'
            : 'images/red-icon.png';


        /*
         * Ícone personalizado do Leaflet
         *
         * Mantido em 32x32, igual ao Google Maps.
         */

        var markerIcon = L.icon({

            iconUrl: statusIconUrl,

            iconSize: [32, 32],

            /*
             * O ponto inferior central do ícone fica
             * exatamente sobre a coordenada.
             */
            iconAnchor: [16, 32],

            /*
             * Onde o popup começa em relação ao ícone.
             */
            popupAnchor: [0, -32]
        });


        /*
         * Cria o marcador
         */

        var marker = L.marker(
            [
                parseFloat(item.latitude),
                parseFloat(item.longitude)
            ],
            {
                icon: markerIcon,
                title: item.nome || ''
            }
        );


        /*
         * Guarda informações adicionais no próprio marcador.
         *
         * Isso substitui o getTitle() utilizado anteriormente.
         */

        marker.sgpData = item;


        /*
         |--------------------------------------------------------------------------
         | CONTEÚDO DO POPUP
         |--------------------------------------------------------------------------
         */

        var content = `
            <div class="custon-infowindow">

                <h3>
                    <strong>${item.nome || ''}</strong>
                </h3>

                <p>
                    Vlan: ${item.vlan || "-"}
                </p>

                <p>
                    Última conexão: ${item.acct || "-"}
                </p>

                ${
                    item.stop
                    ?
                    `<p>
                        <span style="color: red;">
                            Desconectado desde:
                        </span>
                        ${item.stop}
                    </p>`
                    :
                    ""
                }

                ${
                    item.cto
                    ?
                    `<p>
                        CTO: ${item.cto} ⬅️
                    </p>`
                    :
                    ""
                }

                ${
                    item.ctoport
                    ?
                    `<p>
                        CTO Porta: ${item.ctoport} ⬅️
                    </p>`
                    :
                    ""
                }

                ${
                    item.info_rx
                    ?
                    `<p>
                        Sinal RX: ${item.info_rx} ⬅️
                    </p>`
                    :
                    ""
                }

                <p>
                    IP:
                    <a
                        target="_blank"
                        href="http://${encodeURIComponent(item.ip)}:<?php echo $port; ?>"
                    >
                        ${item.ip || ''}
                    </a>
                </p>

                ${
                    item.ipv6
                    ?
                    `<p>
                        IPv6: ${item.ipv6}
                    </p>`
                    :
                    ""
                }


                <?php if ($tecnologia == 1): ?>

                    <footer>
                        ${
                            item.cto
                            ? "<p>Cliente Fibra</p>"
                            : "<p>Cliente UTP</p>"
                        }
                    </footer>

                <?php endif; ?>


                <?php if ($ConsutaOnuAtiva == 1): ?>

                    ${
                        item.cto
                        ?
                        `
                        <div style="text-align: center;">

                            <button
                                class="btn-search-onu"
                                onclick="buscarONU('${item.onuid || ''}')"
                            >
                                🔍 Consultar ONU
                            </button>

                        </div>
                        `
                        :
                        ""
                    }

                <?php endif; ?>

            </div>
        `;


        /*
         * Vincula o popup ao marcador.
         *
         * Equivalente ao InfoWindow do Google Maps.
         */

        marker.bindPopup(content);


        /*
         * Adiciona ao mapa
         */

        marker.addTo(map);


        /*
         * Guarda no array global.
         */

        markers.push(marker);

    });
}


/*
|--------------------------------------------------------------------------
| CONSULTAR ONU
|--------------------------------------------------------------------------
*/

function buscarONU(identificador) {

    if (!identificador) {

        alert("Identificador da ONU inválido!");

        return;
    }


    /*
     * Localiza o botão correspondente.
     */

    let botao = document.querySelector(
        `button[onclick="buscarONU('${identificador}')"]`
    );


    if (botao) {

        botao.classList.add("loading");

        botao.textContent = "Buscando...";
    }


    /*
     * URL da API SGP
     */

    let url =
        `<?php echo $url; ?>/api/fttx/onu/${identificador}/info/?token=<?php echo $token; ?>&app=<?php echo $app; ?>`;


    fetch(url)

        .then(response => response.json())

        .then(data => {

            if (data.result) {

                let formattedResult =
                    formatarRetorno(data.result);

                document.getElementById(
                    "jsonOutput"
                ).textContent = formattedResult;

            } else {

                document.getElementById(
                    "jsonOutput"
                ).textContent =
                    "Nenhum dado retornado.";
            }


            /*
             * Abre o modal.
             */

            openCustomModal();

        })

        .catch(error => {

            console.error(
                "Erro ao buscar a ONU:",
                error
            );

            alert(
                "Erro ao buscar a ONU."
            );

        })

        .finally(() => {

            if (botao) {

                botao.classList.remove("loading");

                botao.textContent =
                    "🔍 Consultar ONU";
            }
        });
}


/*
|--------------------------------------------------------------------------
| ABRIR MODAL
|--------------------------------------------------------------------------
*/

function openCustomModal() {

    document.getElementById(
        "customJsonModal"
    ).style.display = "flex";
}


/*
|--------------------------------------------------------------------------
| FECHAR MODAL
|--------------------------------------------------------------------------
*/

function closeCustomModal() {

    document.getElementById(
        "customJsonModal"
    ).style.display = "none";
}


/*
|--------------------------------------------------------------------------
| FORMATAR RETORNO DA ONU
|--------------------------------------------------------------------------
*/

function formatarRetorno(texto) {

    return texto
        .replace(
            /(\r\n|\r|\n)/g,
            '\n'
        )
        .trim();
}


/*
|--------------------------------------------------------------------------
| BUSCAR CLIENTE
|--------------------------------------------------------------------------
*/

function searchClient() {

    var searchInput =
        document
            .getElementById("search-input")
            .value
            .toLowerCase()
            .trim();


    /*
     * Percorre os marcadores existentes.
     */

    for (var i = 0; i < markers.length; i++) {

        var marker = markers[i];


        /*
         * No Google Maps:
         *
         * marker.getTitle()
         *
         * No Leaflet usamos o dado salvo no marcador.
         */

        var markerTitle =
            (
                marker.sgpData &&
                marker.sgpData.nome
            )
            ?
            String(marker.sgpData.nome).toLowerCase()
            :
            '';


        /*
         * Encontrou o cliente.
         */

        if (markerTitle.includes(searchInput)) {

            /*
             * Centraliza no cliente.
             */

            map.setView(
                marker.getLatLng(),
                18
            );


            /*
             * Abre o popup do cliente.
             */

            marker.openPopup();


            return;
        }
    }


    /*
     * Cliente não encontrado.
     */

    alert(
        "Cliente não encontrado."
    );
}


/*
|--------------------------------------------------------------------------
| INICIALIZAÇÃO
|--------------------------------------------------------------------------
*/

window.addEventListener(
    'load',
    function() {

        initMap();

    }
);


/*
|--------------------------------------------------------------------------
| ATUALIZAÇÃO AUTOMÁTICA
|--------------------------------------------------------------------------
|
| Mantido exatamente em 30 segundos.
|
*/

setInterval(
    refreshMarkers,
    30000
);

</script>


<!--
|--------------------------------------------------------------------------
| CONTROLES
|--------------------------------------------------------------------------
-->

<div id="search-container">

    <!-- Busca de cliente -->

    <input
        type="text"
        id="search-input"
        placeholder="Buscar cliente"
    >

    <button
        id="search-button"
        onclick="searchClient()"
    >
        Buscar
    </button>


    <!-- Atualização manual -->

    <button
        id="manual-update-btn"
        onclick="refreshMarkers()"
    >
        Refresh
    </button>


    <!-- Mapa de CTO -->

    <button
        id="new-table-btn"
        onclick="redirectToNewTable()"
    >
        Mapa de CTO
    </button>


    <!-- Mapa de cobertura -->

    <button
        id="new-table-btn"
        onclick="redirectTocoveragemap()"
    >
        Mapa de Cobertura
    </button>


    <!-- Listar desconexões -->

    <button
        id="new-table-btn"
        onclick="redirectTolistdesconect()"
    >
        Listar Desconexões
    </button>


    <!-- Logout -->

    <button id="logoutButton">
        Sair
    </button>

</div>


<script>

/*
|--------------------------------------------------------------------------
| LOGOUT
|--------------------------------------------------------------------------
*/

document
    .getElementById('logoutButton')
    .addEventListener(
        'click',
        function() {

            fetch('../logout.php')

                .then(response => {

                    if (response.ok) {

                        alert(
                            'Logout realizado com sucesso. Redirecionando para a página de login...'
                        );

                        window.location.href =
                            '../login.php';

                    } else {

                        alert(
                            'Erro ao tentar sair. Tente novamente.'
                        );
                    }
                })

                .catch(error => {

                    console.error(
                        'Erro:',
                        error
                    );

                });
        }
    );


/*
|--------------------------------------------------------------------------
| REDIRECIONAMENTOS
|--------------------------------------------------------------------------
*/

function redirectToNewTable() {

    window.open(
        "cto/",
        "_blank"
    );
}


function redirectTocoveragemap() {

    window.open(
        "cto/cobertura.php",
        "_blank"
    );
}


function redirectTolistdesconect() {

    window.open(
        "desconexoes/index.php",
        "_blank"
    );
}

</script>


<!--
|--------------------------------------------------------------------------
| MODAL DOS DADOS DA ONU
|--------------------------------------------------------------------------
-->

<div
    id="customJsonModal"
    class="custom-modal"
>

    <div
        class="custom-modal-content"
    >

        <div
            class="custom-modal-header"
        >

            <span
                class="custom-close"
                onclick="closeCustomModal()"
            >
                &times;
            </span>

            <h5>
                Dados da ONU
            </h5>

        </div>


        <div
            class="custom-modal-body"
        >

            <!-- Indicador de carregamento -->

            <div
                id="loadingOnu"
                class="loading-onu"
            >
                Carregando...
            </div>


            <pre id="jsonOutput"></pre>

        </div>

    </div>

</div>


<!-- Bootstrap JavaScript -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
></script>


</body>
</html>
