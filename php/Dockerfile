FROM php:8.3-cli-alpine

WORKDIR /app

RUN apk add --no-cache ca-certificates

COPY public ./public
COPY src ./src

EXPOSE 8000

CMD ["php", "-S", "0.0.0.0:8000", "-t", "public", "public/index.php"]
