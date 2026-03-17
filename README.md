# LAB 1: Executar página web estática em container Docker

Objetivo: subir uma página estática (`index.html`) com imagem usando Docker, atendendo os requisitos do laboratório.

## Requisitos do LAB (atendidos)

1. Imagem criada com `Dockerfile` usando **NGINX**.
2. Execução com `docker run`.
3. Sem Docker Compose.
4. Imagem da página servida via **bind mount** (sem `COPY` no `Dockerfile`).

## Estrutura esperada

```text
.
├── Dockerfile
├── README.md
└── site/
		├── index.html
		└── docker-start.svg
```

## Passo a passo para montar e validar

### 1) Clonar o repositório

```bash
git clone <URL_DO_REPOSITORIO>
cd Lab1-Docker
```

### 2) Build da imagem Docker

```bash
docker build -t lab1-web:latest .
```

### 3) Subir o container com bind mount

```bash
docker rm -f lab1-nginx 2>/dev/null || true
docker run --name lab1-nginx \
	-p 8080:80 \
	-v "$(pwd)/site:/usr/share/nginx/html:ro" \
	-d lab1-web:latest
```

Explicação do mount:
- `$(pwd)/site`: conteúdo estático local (`index.html` e imagens/SVGs).
- `/usr/share/nginx/html`: pasta padrão servida pelo NGINX.
- `:ro`: somente leitura.

### 4) Validar execução

Abra no navegador:

```text
http://localhost:8080
```

Validação via terminal:

```bash
curl -I http://localhost:8080
```

Esperado: status `HTTP/1.1 200 OK`.

### 5) Encerrar ambiente

```bash
docker stop lab1-nginx
docker rm lab1-nginx
```

## Troubleshooting rápido

- Erro `125` ao subir container:
	- O container já existe: `docker rm -f lab1-nginx`
	- Porta 8080 ocupada: usar `-p 8081:80` e acessar `http://localhost:8081`
- Página não atualiza:
	- Confira se alterou arquivos dentro de `site/`
	- Recarregue o navegador (bind mount reflete alteração local)