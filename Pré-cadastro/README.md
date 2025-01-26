# Formulário de Pré Cadastro de Clientes

Este projeto é uma aplicação web simples para o Pré-cadastro de clientes, com validação de campos e integração com uma API [SGP](https://www.tsmx.net.br/sgp/). A aplicação foi projetada para ser responsiva e funcional em dispositivos móveis e navegadores modernos.

## 🎯 Funcionalidades

- Formulário com campos para informações do cliente, como:
  - Nome
  - CPF/CNPJ
  - E-mail
  - Celular
  - Endereço
  - Plano e dia de vencimento <-> Coletados na API do SGP
- Validação de CPF e CNPJ diretamente no front-end.
- Preenchimento dinâmico de opções de planos e vencimentos a partir de uma API.
- Feedback visual para envios bem-sucedidos e mensagens de erro.
- Redirecionamento para uma página de sucesso após o cadastro.

## 📂 Estrutura do Projeto
.├── index.html # Página principal com o formulário de cadastro 
├── cadastro-sucesso.html # Página exibida após o envio bem-sucedido 
├── styles.css # Arquivo de estilos (CSS) 
├── config.js # Configurações de API (URL, app, token) 
├── submitForm.js # Lógica de envio e validação do formulário 


## 🚀 Como Usar

### 1. Clonar o Repositório
Clone o projeto para sua máquina local

### 2. Configuração
Edite o arquivo config.js para definir a URL da API e o TOKEN de autenticação:
