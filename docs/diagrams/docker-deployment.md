# Implantação — ambientes Docker

Duas stacks Compose **independentes**, que podem rodar ao mesmo tempo. Tudo escuta apenas em `127.0.0.1`. Não há servidor público, domínio nem TLS. Procedimentos: [Ambientes Docker](../AMBIENTES_DOCKER.md).

```mermaid
flowchart LR
    NAV[Navegador no host]

    subgraph DEV [Projeto Compose foodorder — desenvolvimento · rede foodorder_default]
        direction TB
        DAPP["app<br/>php artisan serve :8000<br/>código editável (bind mount .:/app)"]
        DV1[("volume foodorder_vendor_data → /app/vendor")]
        DV2[("volume foodorder_sqlite_data → /data<br/>database.sqlite de desenvolvimento")]
        DAPP --- DV1
        DAPP --- DV2
    end

    subgraph PROD [Projeto Compose foodorder-prod — produção · rede foodorder-prod_default]
        direction TB
        PWEB["web — Nginx (nginx-unprivileged) :8080<br/>public/ copiado da imagem · raiz somente leitura"]
        PAPP["app — PHP-FPM :9000 (não publicado)<br/>código incorporado à imagem · usuário app · raiz somente leitura"]
        PV[("volume foodorder-prod_sqlite_data → /data<br/>database.sqlite de produção")]
        PWEB -- "FastCGI app:9000" --> PAPP
        PAPP --- PV
    end

    NAV -- "127.0.0.1:8082 (DEV_PORT)" --> DAPP
    NAV -- "127.0.0.1:8081 (PROD_PORT)" --> PWEB
```

Valores de porta: em produção `PROD_PORT=8081`; em desenvolvimento o `.env.example` traz `DEV_PORT=8080` (neste ambiente de demonstração o `.env` local usa 8082). Redes, volumes, bancos SQLite e `APP_KEY` são distintos entre as stacks.

Voltar: [índice](../README.md)
