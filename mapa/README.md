# Mapa SGP Integration

Sistema de visualização e gerenciamento de rede para provedores de internet (ISP), integrado via API à plataforma SGP. O sistema permite visualizar clientes e CTOs em mapas interativos (Google Maps e Leaflet), consultar níveis de sinal de ONU, provisionar equipamentos e monitorar desconexões.

## 📋 Requisitos do Sistema

Para executar este projeto, seu ambiente deve atender aos seguintes requisitos:

- **Servidor Web**: Apache, Nginx, IIS ou compatível.
- **Linguagem**: PHP 7.4 ou superior.
- **Extensões PHP Obrigatórias**:
  - `curl` (para comunicação com a API)
  - `json` (para processamento de dados)
  - `session` (para autenticação de usuários)
- **Acesso Externo**: O servidor precisa de conectividade com a internet para acessar a API do SGP e carregar bibliotecas CDN (Google Maps, Leaflet, jQuery, Bootstrap).

## 🚀 Instalação

1. **Deploy dos Arquivos**
   Copie, clone ou extraia todos os arquivos do projeto para o diretório público do seu servidor web (ex: `/var/www/html/mapa` ou `htdocs`).

2. **Permissões de Escrita**
   O módulo de desconexões utiliza um sistema de cache em arquivo para otimizar o desempenho. Garanta que o diretório de cache tenha permissões de escrita para o usuário do servidor web (geralmente `www-data` ou `apache`).

   ```bash
   chmod -R 755 desconexoes/cache
   chown -R www-data:www-data desconexoes/cache
   ```
   *(Caso o diretório `cache` não exista dentro de `desconexoes`, crie-o manualmente).*

## ⚙️ Configuração

### 1. Renomear Arquivo de Configuração
Renomeie o arquivo de exemplo `.env.example` para `.env` na raiz do projeto:

```bash
cp .env.example .env
```

### 2. Editar Variáveis de Ambiente
Abra o arquivo `.env` recém-criado e insira suas credenciais:

```ini
# Configurações do SGP
SGP_BASE_URL=https://seu-sgp.com.br
SGP_TOKEN=SEU_TOKEN_DE_API
SGP_APP=mapa

# Google Maps
GOOGLE_MAPS_API_KEY=SUA_CHAVE_GOOGLE_MAPS

# Localização Inicial do Mapa
MAPA_CENTRAL_LATITUDE=-12.1289
MAPA_CENTRAL_LONGITUDE=-38.4066
```

> **Nota de Segurança**: Certifique-se de que o arquivo `.env` não esteja acessível publicamente pelo navegador e configurado no `.gitignore` se usar versionamento.

### 3. Configuração Google Maps API 🔑
Para que os mapas funcionem corretamente (especialmente o cálculo de rotas e geometria), você precisa de uma Chave de API do Google configurada com as APIs corretas habilitadas.

1.  Acesse o [Google Cloud Console](https://console.cloud.google.com/).
2.  Crie um novo projeto.
3.  Vá em **APIs e Serviços > Biblioteca** e ative as seguintes APIs:
    *   **Maps JavaScript API** (Para exibir os mapas e usar a biblioteca de geometria).
    *   **Directions API** (Para o cálculo de rotas e distância na tela de CTO).
4.  Vá em **APIs e Serviços > Credenciais** e crie uma **Chave de API**.
5.  **Restrições de Aplicação (Recomendado)**:
    *   Edite sua chave recém-criada.
    *   Em "Restrições de aplicativos", selecione **Referenciadores HTTP (sites da Web)**.
    *   Adicione o domínio do seu sistema (ex: `https://seusistema.com/*`).
6.  Copie a chave gerada e cole no seu arquivo `.env` na variável `GOOGLE_MAPS_API_KEY`.


## 🛠️ Funcionalidades e Uso

### 1. 🗺️ Mapa de Clientes
- **Acesso**: `index.php` (Google Maps) ou `leaflet.php` (Leaflet/Satélite).
- **Descrição**: Exibe a localização geográfica dos clientes.
- **Recursos**:
  - **Status Visual**: Ícones verdes (Online) e vermelhos (Offline).
  - **Busca**: Campo para localizar clientes pelo nome.
  - **Info Window**: Ao clicar no cliente, exibe VLAN, IP, Consumo, Sinal RX e botão para consultar dados detalhados da ONU em tempo real.

### 2. 🔌 Mapa de CTOs (Caixas de Atendimento)
- **Acesso**: `/cto/index.php`
- **Descrição**: Gestão da planta externa e portas de atendimento.
- **Recursos**:
  - **Indicador de Lotação**: Ícones e alertas visuais para CTOs sem portas livres.
  - **Cálculo de Rota (Drop)**: Clique em qualquer ponto do mapa para calcular a rota a pé da CTO mais próxima e a distância linear (útil para estimar metragem de cabo).
  - **Ações**: Botões para ver ONUs conectadas ou abrir tela de provisionamento.

### 3. 📡 Mapa de Cobertura
- **Acesso**: `/cto/cobertura.php`
- **Descrição**: Desenha um raio de cobertura (padrão 200m) ao redor de cada CTO, ajudando a identificar áreas atendidas e "zonas de sombra".

### 4. 📟 Gestão de ONUs (Sinal e Provisionamento)
- **Ver Sinal**: Tabela com lista de clientes da CTO, exibindo Serial e Nível de Sinal (RX). Permite atualizar o sinal na hora.
- **Autorizar ONU**:
  - Lista ONUs não autorizadas na porta PON daquela CTO.
  - Formulário para associar ONU a um contrato.
  - Seleção de Template, Tipo de ONU e Modo (Bridge/Router).

### 5. ⚠️ Monitor de Desconexões
- **Acesso**: `/desconexoes/index.php`
- **Descrição**: Ferramenta para identificar instabilidade.
- **Funcionamento**: Analisa logs do Radius em busca de múltiplos eventos "Login OK" em curto período, o que geralmente indica que o roteador do cliente está caindo e reconectando frequentemente.

## 📂 Estrutura de Pastas

| Diretório      | Descrição |
|Bs|Bs|
| `/config`      | Arquivos de configuração global (`conf.php`). |
| `/cto`         | Módulo de gestão de CTOs, cobertura e provisionamento. |
| `/desconexoes` | Módulo de análise de logs e estabilidade. |
| `/css`         | Folhas de estilo (CSS). |
| `/js`          | Scripts JavaScript e bibliotecas locais. |
| `/images`      | Ícones de marcadores e imagens de interface. |

---
> **Nota**: Este software foi desenvolvido para integrar especificamente com a API do SGP. Alterações nos endpoints da API do fornecedor podem requerer atualizações no código em `atualizar_marcadores.php` ou `cto/`.
