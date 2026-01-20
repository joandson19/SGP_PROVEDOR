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

Toda a configuração sensível e estrutural do sistema está centralizada no arquivo:
📂 **`config/conf.php`**

Abra este arquivo e ajuste conforme seu ambiente:

### 1. API do SGP
Configure o acesso à API do seu sistema de gestão:
```php
'sgp' => [
    'base_url' => 'https://seu-sgp.com.br', // URL base do seu SGP
    'token'    => 'SEU_TOKEN_DE_API',       // Gere um token no SGP
    'app'      => 'mapa',                   // Nome do app registrado
],
```

### 2. Google Maps
Insira sua chave de API válida (necessário habilitar Maps JavaScript API, Directions API e Geometry):
```php
'google_maps_api_key' => 'SUA_CHAVE_GOOGLE_MAPS',
```

### 3. Ajustes do Mapa e Filtros
Defina o centro inicial do mapa e os filtros de busca de clientes:
```php
'central_latitude'  => '-12.1289', // Latitude inicial
'central_longitude' => '-38.4066', // Longitude inicial
'pesquisa' => [
    'status' => 'ATIVO', // Filtrar por status do cliente
    'uf'     => 'BA',    // Filtrar por Estado
],
```

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
