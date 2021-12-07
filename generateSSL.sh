#!/bin/bash

DOMAINS="$1"
if [ -z "$DOMAINS" ]; then
  echo "<domains> is empty"
  exit 11
fi
IFS=',' read -ra DOMAINS <<< $DOMAINS

NGINXPROXYPATH="$2"
if [ -z "$NGINXPROXYPATH" ]; then
  echo "<nginx-proxy-path> is empty"
  exit 22
fi

for DOMAIN in "${DOMAINS[@]}"
do
    SUBJECT_ALT_NAME=$SUBJECT_ALT_NAME"DNS:$element,"
    openssl req -x509 -nodes -days 730 \
        -subj  "/C=US/ST=TX/L=Austin/O=Magento/OU=Magento Docker/CN=$DOMAIN" \
        -newkey rsa:2048 -keyout ${NGINXPROXYPATH}etc/nginx/certs/$DOMAIN.key \
        -out ${NGINXPROXYPATH}etc/nginx/certs/$DOMAIN.crt
done
