#!/bin/bash
set -e

# FROM: https://gist.github.com/komuw/076231fd9b10bb73e40f
export TARGET_DIR="certificates"
export DAYS=50000
export SSL_HOST="node1.mongodb.cluster.local"

# Cleanup
rm -rf $TARGET_DIR
mkdir $TARGET_DIR
cd $TARGET_DIR

########################################################
################## CREATE CERTIFICATES #################
########################################################

# Create the CA Key and Certificate for signing Client Certs
openssl genrsa -out ca.key 4096
openssl req -subj "/CN=invalidCNCa" -new -x509 -days $DAYS -key ca.key -out ca.crt

# Create the Server Key, CSR, and Certificate
openssl genrsa -out mongodb.key 4096
openssl req -subj "/CN=${SSL_HOST}" -new -key mongodb.key -out mongodb.csr

# We're self signing our own server cert here.  This is a no-no in production.
openssl x509 -req -days $DAYS -sha256 -in mongodb.csr -CA ca.crt -CAkey ca.key -set_serial 01 -out mongodb.crt

# Verify Server Certificate
openssl verify -purpose sslserver -CAfile ca.crt mongodb.crt

cat mongodb.key mongodb.crt > mongodb.pem
cat ca.key ca.crt > ca.pem
