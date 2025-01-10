# Projeto: Mapa de CTOs Ativas no SGP

Este projeto exibe um mapa com informações básicas das CTOs ativas no SGP, como:
- Nome da CTO
- Número total de portas
- Portas em uso
- Comentários e outras informações relevantes

📽️ **Vídeo Demonstrativo**: [Clique aqui para assistir](https://youtu.be/eDK37tSjgfQ)

---

## 🚀 Configuração e Uso

Siga os passos abaixo para configurar e executar o projeto:

### 1. Configuração da API do Google Maps
1. Acesse [Google Cloud Console](https://console.cloud.google.com/).
2. Crie um projeto e configure suas **APIs e credenciais**.
3. Adicione o serviço **Google Maps** ao projeto.
4. Acesse a seção **APIs e Serviços** e ative a **Directions API** para habilitar o traçado de rotas.

### 2. Token no SGP
1. Gere um token no sistema SGP.
2. Ative a função de mapeamento de usuários no SGP, pois as CTOs só serão carregadas com essa função habilitada.

### 3. Credenciais Turnstile da Cloudflare
1. Acesse o [site da Cloudflare](https://dash.cloudflare.com/login).
2. Crie as credenciais do Turnstile para proteger o projeto contra acessos automatizados.

### 4. Configuração do Arquivo `conf.php`
1. Navegue até a pasta `config` do projeto.
2. Edite o arquivo `conf.php` com suas credenciais e configurações personalizadas.

---

## 📋 Sugestões e Melhorias

Caso tenha sugestões ou melhorias, não hesite em abrir uma **issue** ou enviar um **pull request**. Toda contribuição é bem-vinda!

---

