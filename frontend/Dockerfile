FROM node:20-alpine AS build

WORKDIR /app

COPY frontend/package.json ./
RUN npm install

COPY frontend/ .
RUN npm run build

FROM nginx:1.27-alpine

COPY config/nginx.conf /etc/nginx/nginx.conf
RUN apk add --no-cache openssl \
  && mkdir -p /etc/ssl/certs /etc/ssl/private \
  && if [ ! -f /etc/ssl/certs/potuzhnoshukach.online.pem ] || [ ! -f /etc/ssl/private/potuzhnoshukach.online.key ]; then \
       openssl req -x509 -nodes -newkey rsa:2048 -days 3650 \
         -subj "/CN=potuzhnoshukach.online" \
         -keyout /etc/ssl/private/potuzhnoshukach.online.key \
         -out /etc/ssl/certs/potuzhnoshukach.online.pem; \
     fi
COPY --from=build /app/dist /usr/share/nginx/html

EXPOSE 80
