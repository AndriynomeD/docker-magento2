FROM nginx:1.24

LABEL maintainer="Nick Jones <nick@nicksays.co.uk>"

ADD etc/vhost.conf /etc/nginx/conf.d/default.conf
COPY etc/multi_vhost/* /etc/nginx/multi_vhost/
COPY etc/certs/ /etc/nginx/ssl/
ADD bin/* /usr/local/bin/

EXPOSE 443

ENV UPLOAD_MAX_FILESIZE 64M
ENV FPM_HOST fpm
ENV FPM_PORT 9000
ENV MAGENTO_ROOT /var/www/magento
ENV DEBUG false

RUN ["chmod", "+x", "/usr/local/bin/docker-environment"]

ENTRYPOINT ["/usr/local/bin/docker-environment"]
CMD ["nginx", "-g", "daemon off;"]
