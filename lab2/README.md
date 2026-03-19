# LAB 2 - Passo a passo de execução

## Executar

1. Entrar na pasta do lab:

```bash
cd lab2
```

2. Criar a rede:

```bash
docker network create lab2-net
```

3. Criar o volume do MySQL:

```bash
docker volume create lab2-mysql-data
```

4. Subir o MySQL:

```bash
docker rm -f lab2-mysql 2>/dev/null || true
docker run -d --name lab2-mysql --network lab2-net -e MYSQL_ROOT_PASSWORD=root123 -e MYSQL_DATABASE=lab2db -e MYSQL_USER=lab2user -e MYSQL_PASSWORD=lab2pass -v lab2-mysql-data:/var/lib/mysql mysql:8.0
```

5. Construir a imagem da aplicação:

```bash
docker build -t lab2-php-app:latest .
```

6. Subir a aplicação:

```bash
docker rm -f lab2-php 2>/dev/null || true
docker run -d --name lab2-php --network lab2-net -p 8082:80 -e DB_HOST=lab2-mysql -e DB_PORT=3306 -e DB_NAME=lab2db -e DB_USER=lab2user -e DB_PASSWORD=lab2pass lab2-php-app:latest
```

7. Abrir no navegador:

```text
http://localhost:8082
```

## Parar e limpar (opcional)

```bash
docker rm -f lab2-php lab2-mysql
docker volume rm lab2-mysql-data
docker network rm lab2-net
```
