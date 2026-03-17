# LAB 2: PHP + MySQL em containers Docker (sem Compose)

Objetivo: executar uma aplicação PHP simples conectada ao MySQL, com ambos em containers Docker, usando `docker run` e volume para persistência.

## O que foi implementado

- App PHP em `app/index.php` (jogo Docker Stacker reaproveitado do Lab1).
- Dashboard no **Game Over** com dados persistidos no MySQL:
  - melhor score,
  - total de partidas,
  - média,
  - top 5 pontuações.
- Banco MySQL em container com volume Docker para persistência.

## Estrutura

```text
lab2/
├── Dockerfile
├── README.md
└── app/
    ├── docker-start.svg
    └── index.php
```

## Requisitos do LAB (atendidos)

1. Imagem da aplicação criada com `Dockerfile`.
2. Containers executados com `docker run`.
3. Docker Compose não utilizado.
4. MySQL provisionado em container Docker.
5. Persistência com Docker Volume no MySQL.

## Passo a passo para executar

### 1) Entrar na pasta do lab2

```bash
cd lab2
```

### 2) Criar rede Docker (app ↔ mysql)

```bash
docker network create lab2-net
```

### 3) Criar volume de persistência do MySQL

```bash
docker volume create lab2-mysql-data
```

### 4) Subir MySQL em container

```bash
docker rm -f lab2-mysql 2>/dev/null || true
docker run -d --name lab2-mysql \
  --network lab2-net \
  -e MYSQL_ROOT_PASSWORD=root123 \
  -e MYSQL_DATABASE=lab2db \
  -e MYSQL_USER=lab2user \
  -e MYSQL_PASSWORD=lab2pass \
  -v lab2-mysql-data:/var/lib/mysql \
  mysql:8.0
```

### 5) Build da imagem da aplicação PHP

```bash
docker build -t lab2-php-app:latest .
```

### 6) Subir aplicação PHP em container

```bash
docker rm -f lab2-php 2>/dev/null || true
docker run -d --name lab2-php \
  --network lab2-net \
  -p 8082:80 \
  -e DB_HOST=lab2-mysql \
  -e DB_PORT=3306 \
  -e DB_NAME=lab2db \
  -e DB_USER=lab2user \
  -e DB_PASSWORD=lab2pass \
  lab2-php-app:latest
```

### 7) Validar

Abra no navegador:

```text
http://localhost:8082
```

Teste rápido da API do dashboard:

```bash
curl -s http://localhost:8082/?api=dashboard
```

A saída deve ser JSON com `ok: true` e os campos do dashboard.

## Como validar persistência do volume

1. Jogue algumas partidas (gera scores no banco).
2. Remova **apenas** os containers:

```bash
docker rm -f lab2-php lab2-mysql
```

3. Suba novamente os dois containers (passos 4 e 6).
4. Ao perder novamente, o dashboard deve mostrar histórico anterior (dados persistidos no volume `lab2-mysql-data`).

## Limpeza completa (opcional)

```bash
docker rm -f lab2-php lab2-mysql 2>/dev/null || true
docker volume rm lab2-mysql-data
docker network rm lab2-net
```
