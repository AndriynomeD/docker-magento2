#!/bin/bash

# TODO: check if nginx-proxy works for multi-domains with this configs

DOMAINS="$1"
if [ -z "$DOMAINS" ]; then
  echo "<domains> is empty"
  exit 11
fi
IFS=',' read -ra DOMAINS <<< $DOMAINS
MAIN_DOMAIN=($(echo "${DOMAINS[0]}"))
SUBJECT_ALT_NAME=""
for element in "${DOMAINS[@]}"
do
    SUBJECT_ALT_NAME=$SUBJECT_ALT_NAME"DNS:$element,"
done
SUBJECT_ALT_NAME=($(echo $SUBJECT_ALT_NAME | sed 's/,$//g'))

NGINXPROXYPATH="$2"
if [ -z "$NGINXPROXYPATH" ]; then
  echo "<nginx-proxy-path> is empty"
  exit 22
fi

openssl req -x509 -nodes -days 730 \
    -subj  "/C=US/ST=TX/L=Austin/O=Magento/OU=Magento Docker/CN=$MAIN_DOMAIN" \
     -newkey rsa:2048 -keyout ${NGINXPROXYPATH}etc/nginx/certs/$MAIN_DOMAIN.key \
     -out ${NGINXPROXYPATH}etc/nginx/certs/$MAIN_DOMAIN.crt \
     -extensions san -config \
        <(echo "[req]"
            echo distinguished_name=req;
            echo "[san]";
            echo "subjectAltName=$SUBJECT_ALT_NAME"
        );
