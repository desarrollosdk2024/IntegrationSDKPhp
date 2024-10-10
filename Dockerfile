# Usar la imagen oficial de PHP
FROM php:8.0-cli

# Definir el directorio de trabajo dentro del contenedor
WORKDIR /app

# Copiar tu aplicación PHP a la carpeta /app dentro del contenedor
COPY . /app

# Exponer el puerto que usa tu aplicación (5050 en este caso)
EXPOSE 5050

# Comando para iniciar tu aplicación
CMD [ "php", "./example/server.php" ]
