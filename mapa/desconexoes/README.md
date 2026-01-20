# Desconexões — Login OK (RADIUS)

Pequeno painel (HTML) + API (JSON) para agrupar os registros **"Login OK"** do endpoint de logs do SGP, contabilizando por usuário.

## O que foi profissionalizado

- Requisição HTTP com **cURL**, timeout e tratamento de erro.
- Cache simples em arquivo para reduzir chamadas na API.
- Separação: painel (`index.php`) e API (`api.php`).
- Endpoint legado preservado (`index2.php`) no formato JSON antigo.

## Requisitos

- PHP 7.4+ (recomendado 8.x)
- Extensão `curl` habilitada

## Configuração

Este módulo usa a **mesma configuração do projeto principal** (arquivo único):

- `../config/conf.php`

Ajustes do módulo (endpoint, threshold, cache, etc.) ficam na chave:

- `$CONFIG['desconexoes']`

## Uso

- **Painel**: `index.php`
- **API (recomendada)**: `api.php`
  - Parâmetros:
    - `threshold` (int)
    - `min_count` (int)
    - `limit` (int)
- **Legado**: `index2.php` (mantém o JSON antigo)

## Notas importantes

- Se o endpoint real do seu SGP for diferente, ajuste `endpoint_path` em `$CONFIG['desconexoes']`.
- O cache padrão é de 60s e fica em `./cache`.
