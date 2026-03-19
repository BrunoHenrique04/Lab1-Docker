# LAB 1 - Passo a passo de execução


## Executar

1. Entrar na pasta do lab:

```bash
cd lab1
```

2. Construir a imagem:

```bash
docker build -t lab1-web:latest .
```

3. Subir o container:

```bash
docker rm -f lab1-nginx 2>/dev/null || true
docker run --name lab1-nginx -p 8080:80 -v "$(pwd)/site:/usr/share/nginx/html:ro" -d lab1-web:latest
```

4. Abrir no navegador:

```text
http://localhost:8080
```

## Parar

```bash
docker rm -f lab1-nginx
```