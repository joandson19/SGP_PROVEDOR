# Formulário de Pré Cadastro de Clientes

Este projeto é uma aplicação web simples para o Pré-cadastro de clientes, com validação de campos e integração com uma API [SGP](https://www.tsmx.net.br/sgp/). A aplicação foi projetada para ser responsiva e funcional em dispositivos móveis e navegadores modernos.

## 🎯 Funcionalidades

- Formulário com campos para informações do cliente, como:
  - Nome
  - CPF/CNPJ
  - E-mail
  - Celular
  - Endereço
  - Data de nascimento
  - Plano e dia de vencimento <-> Coletados na API do SGP
  - Envio de coordenadas geográficas direto no Formulário.
    
- Validação de CPF e CNPJ diretamente no front-end.
- Preenchimento dinâmico de opções de planos e vencimentos a partir de uma API.
- Feedback visual para envios bem-sucedidos e mensagens de erro.
- Redirecionamento para uma página de sucesso após o cadastro.

## 🚀 Como Usar

## Antes de começar a confugurar o projeto no servidor será necessário antes de tudo configurar o seu nginx ou apache2 para aceitar coleta de localização do usuario.

### No nginx
```
# nano /etc/nginx/SEUARQUIVODECONFIGURAÇÃO.conf

add_header Permissions-Policy "geolocation=(self)";
```
```
# service nginx restart
```
### No apache2
```
# nano /etc/apache2/SEUARQUIVODECONFIGURAÇÃO.conf

<IfModule mod_headers.c>
    Header set Permissions-Policy "geolocation=(self)"
</IfModule>
```
```
# service apache2 restart
```
## Agora podemos baixar o projeto e configurar.
### 1. Clonar o Repositório
Clone o projeto para sua máquina local

### 2. Configuração
Edite o arquivo config.js para definir a URL da API e o TOKEN de autenticação:

## Segue exemplo.

![Vídeo sem título ‐ Feito com o Clipchamp](https://github.com/user-attachments/assets/731103a7-9bbd-49be-85a8-0a103126a644)



