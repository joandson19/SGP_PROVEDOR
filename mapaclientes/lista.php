<!DOCTYPE html>
<html>
<head>
    <title>Lista de Clientes Sem Coordenadas</title>
</head>
<body>
    <h1>Lista de Clientes Sem Coordenadas</h1>
    
    <ul id="client-list">
        <!-- Aqui serão adicionados os nomes dos clientes sem coordenadas -->
    </ul>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
		<?php require_once("config/conf.php"); ?>
        $(document).ready(function() {
            $.ajax({
                url: 'atualizar_marcadores.php?mptoken=<?php echo $validToken; ?>', // Substitua pelo seu token
                dataType: 'json',
                success: function(data) {
                    // Filtra os clientes que não têm coordenadas
                    var clientsWithoutCoordinates = data.filter(function(client) {
                        return client.latitude === "" || client.longitude === "";
                    });

                    // Adiciona os nomes dos clientes à lista
                    var clientList = $('#client-list');
                    clientsWithoutCoordinates.forEach(function(client) {
                        var listItem = $('<li>').text(client.nome);
                        clientList.append(listItem);
                    });
                },
                error: function(xhr, status, error) {
                    console.error('Erro ao buscar dados:', error);
                }
            });
        });
    </script>
</body>
</html>
