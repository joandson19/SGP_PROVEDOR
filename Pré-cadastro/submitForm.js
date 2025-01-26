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
const form = document.getElementById('registrationForm');
form.addEventListener('submit', async (event) => {
	event.preventDefault();  // Impede o envio padrão do formulário

	// Desativa o botão para evitar envios duplicados
	const submitButton = form.querySelector('button[type="submit"]');
	submitButton.disabled = true;
	submitButton.textContent = 'Enviando...'; // Feedback visual opcional

	const formData = new FormData(form);

	// Converta os dados do formulário em um objeto JSON
	const formDataObject = {};
	formData.forEach((value, key) => {
		formDataObject[key] = value;
	});

	// Adiciona app e token aos dados do formulário
	const dataToSend = {
		...formDataObject,  // Adiciona todos os dados do formulário
		app: CONFIG.app,
		token: CONFIG.token
	};

	try {
		// Envio dos dados do formulário para a API
		const response = await fetch(`${CONFIG.apiUrl}/api/precadastro/F`, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify(dataToSend)  // Envia o objeto correto
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