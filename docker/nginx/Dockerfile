FROM nginx:1.27-alpine
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY backend/public /var/www/html/public
RUN ln -sf /var/www/html/storage/app/public /var/www/html/public/storage
