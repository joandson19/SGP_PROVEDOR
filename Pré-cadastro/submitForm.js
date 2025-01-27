const fetchVendedores = async () => {
    try {
        // Requisição para obter a lista de vendedores
        const vendedoresResponse = await fetch(`${CONFIG.apiUrl}/api/precadastro/vendedor/list`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ app: CONFIG.app, token: CONFIG.token })
        });

        const vendedores = await vendedoresResponse.json();

        // Preencher o campo select com a lista de vendedores
        const vendedorSelect = document.getElementById('vendedor');
        vendedores.forEach(vendedor => {
            const option = document.createElement('option');
            option.value = vendedor.id;
            option.textContent = vendedor.nome;
            vendedorSelect.appendChild(option);
        });
    } catch (error) {
        console.error('Erro ao buscar vendedores:', error);
    }
};

fetchVendedores();

const fetchData = async () => {
	try {
		// Fetch planos
		const planosResponse = await fetch(`${CONFIG.apiUrl}/api/precadastro/plano/list`, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ app: CONFIG.app, token: CONFIG.token })
		});
		const planos = await planosResponse.json();

		const planoSelect = document.getElementById('plano');
		planos.forEach(plano => {
			const option = document.createElement('option');
			option.value = plano.id;
			option.textContent = `${plano.descricao} - R$ ${plano.valor}`;
			planoSelect.appendChild(option);
		});

		// Fetch vencimentos
		const vencimentosResponse = await fetch(`${CONFIG.apiUrl}/api/precadastro/vencimento/list`, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ app: CONFIG.app, token: CONFIG.token })
		});
		const vencimentos = await vencimentosResponse.json();

		const vencimentoSelect = document.getElementById('vencimento');
		vencimentos.forEach(vencimento => {
			const option = document.createElement('option');
			option.value = vencimento.id;
			option.textContent = `Dia ${vencimento.dia}`;
			vencimentoSelect.appendChild(option);
		});
	} catch (error) {
		console.error('Erro ao buscar dados:', error);
	}
};

fetchData();

// Função para validar CPF ou CNPJ
function validarCPFouCNPJ(cpfcnpj) {
	cpfcnpj = cpfcnpj.replace(/[^\d]+/g, ''); // Remove caracteres não numéricos

	// Validação do CPF (11 dígitos)
	if (cpfcnpj.length === 11) {
		// CPF
		let soma = 0;
		for (let i = 0; i < 9; i++) {
			soma += parseInt(cpfcnpj.charAt(i)) * (10 - i);
		}
		let resto = soma % 11;
		let digito1 = resto < 2 ? 0 : 11 - resto;

		soma = 0;
		for (let i = 0; i < 10; i++) {
			soma += parseInt(cpfcnpj.charAt(i)) * (11 - i);
		}
		resto = soma % 11;
		let digito2 = resto < 2 ? 0 : 11 - resto;

		return digito1 === parseInt(cpfcnpj.charAt(9)) && digito2 === parseInt(cpfcnpj.charAt(10));
	}

	// Validação do CNPJ (14 dígitos)
	if (cpfcnpj.length === 14) {
		// CNPJ
		let soma = 0;
		let pos = 0;
		const multiplicador1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
		const multiplicador2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

		// Primeiro dígito verificador
		for (let i = 0; i < 12; i++) {
			soma += parseInt(cpfcnpj.charAt(i)) * multiplicador1[i];
		}
		let resto = soma % 11;
		let digito1 = resto < 2 ? 0 : 11 - resto;

		// Segundo dígito verificador
		soma = 0;
		for (let i = 0; i < 13; i++) {
			soma += parseInt(cpfcnpj.charAt(i)) * multiplicador2[i];
		}
		resto = soma % 11;
		let digito2 = resto < 2 ? 0 : 11 - resto;

		return digito1 === parseInt(cpfcnpj.charAt(12)) && digito2 === parseInt(cpfcnpj.charAt(13));
	}

	return false;
}

// Função para exibir a mensagem de erro
function mostrarErro(campo, mensagem) {
	let erroElemento = document.getElementById(campo + '-erro');
	if (!erroElemento) {
		erroElemento = document.createElement('div');
		erroElemento.id = campo + '-erro';
		erroElemento.style.color = 'red';
		erroElemento.style.marginTop = '5px';
		document.getElementById(campo).after(erroElemento);
	}
	erroElemento.textContent = mensagem;
}

// Evento de validação no campo de entrada
document.getElementById('cpfcnpj').addEventListener('blur', function () {
	const cpfcnpj = this.value;

	if (!validarCPFouCNPJ(cpfcnpj)) {
		mostrarErro('cpfcnpj', 'CPF ou CNPJ inválido! Por favor, verifique.');
	} else {
		const erroElemento = document.getElementById('cpfcnpj-erro');
		if (erroElemento) {
			erroElemento.remove(); // Remove a mensagem de erro se o CPF/CNPJ for válido
		}
	}
});

// Manipulador de envio do formulário
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('registrationForm');
    const enableMapCheckbox = document.getElementById('enableMap');
    const mapContainer = document.getElementById('mapContainer');
    const mapElement = document.getElementById('map');
    const mapInput = document.getElementById('map_ll');

    let map; // Variável para armazenar o mapa
    let marker; // Variável para armazenar o marcador

    enableMapCheckbox.addEventListener('change', () => {
        if (enableMapCheckbox.checked) {
            mapContainer.style.display = 'block';

            if (!map) {
                // Inicializar o mapa no Leaflet se ainda não foi criado
                const [latitude, longitude] = CONFIG.coordenadasIni.split(',').map(Number);
                map = L.map(mapElement).setView([latitude, longitude], 14);

                // Adicionar camada de mapa
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 18,
                    attribution: '© OpenStreetMap'
                }).addTo(map);

                // Adicionar marcador
                marker = L.marker([latitude, longitude], { draggable: true }).addTo(map);

                // Atualizar as coordenadas no campo oculto quando o marcador for arrastado
                marker.on('dragend', () => {
                    const { lat, lng } = marker.getLatLng();
                    const coordinates = `${lat.toFixed(6)},${lng.toFixed(6)}`;
                    mapInput.value = coordinates; // Salva no campo oculto
                });

                // Atualizar a posição do marcador ao clicar no mapa
                map.on('click', (e) => {
                    const { lat, lng } = e.latlng;
                    marker.setLatLng([lat, lng]);
                    const coordinates = `${lat.toFixed(6)},${lng.toFixed(6)}`;
                    mapInput.value = coordinates; // Salva no campo oculto
                });
            }
        } else {
            mapContainer.style.display = 'none';
            mapInput.value = ''; // Limpa o campo de coordenadas
        }
    });
    // Manipulador de envio do formulário
    form.addEventListener('submit', async (event) => {
        event.preventDefault(); // Impede o envio padrão do formulário

        // Desativa o botão para evitar envios duplicados
        const submitButton = form.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        submitButton.textContent = 'Enviando...'; // Feedback visual opcional

        const formData = new FormData(form);
		
		// Formatar a data de nascimento
		const datanascInput = document.getElementById('datanasc').value;
		if (datanascInput) {
			formData.set('datanasc', datanascInput); // Garante que o formato seja "YYYY-MM-DD"
		}

        // Converte os dados do formulário em um objeto JSON
        const formDataObject = {};
        formData.forEach((value, key) => {
            formDataObject[key] = value;
        });

        // Adiciona app e token aos dados do formulário
        const dataToSend = {
            ...formDataObject, // Adiciona todos os dados do formulário
            app: CONFIG.app,
            token: CONFIG.token
        };

        try {
            // Envio dos dados do formulário para a API
            const response = await fetch(`${CONFIG.apiUrl}/api/precadastro/F`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(dataToSend) // Envia o objeto correto
            });

            if (response.ok) {
                // Sucesso
                alert('Cadastro realizado com sucesso!');
                // Redireciona para a página de sucesso
                window.location.href = 'cadastro-sucesso.html';
            } else {
                // Falha
                alert('Erro ao cadastrar. Tente novamente.');
            }
        } catch (error) {
            console.error('Erro ao enviar o formulário:', error);
            alert('Erro de conexão. Tente novamente.');
        } finally {
            // Reativa o botão após a tentativa de envio
            submitButton.disabled = false;
            submitButton.textContent = 'Cadastrar'; // Restaura o texto original
        }
    });
});
