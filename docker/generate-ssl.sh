#!/bin/bash
set -e

# FROM: https://gist.github.com/komuw/076231fd9b10bb73e40f
export TARGET_DIR="certificates"
export DAYS=50000
export SSL_HOST_CLUSTER="node1.mongodb.cluster.local"
export SSL_HOST="mongodb-ssl"

# Cleanup
rm -rf $TARGET_DIR
mkdir $TARGET_DIR
cd $TARGET_DIR

########################################################
################## CREATE CERTIFICATES #################
########################################################

# Create the CA Key and Certificate for signing Client Certs
openssl genrsa -out ca-key.pem 4096
openssl req -subj "/CN=invalidCNCa" -new -x509 -days $DAYS -key ca-key.pem -out ca-cert.pem

# Create the Cluster Server Key, CSR, and Certificate
openssl genrsa -out mongodb-cluster-key.pem 4096
openssl req -subj "/CN=${SSL_HOST_CLUSTER}" -new -key mongodb-cluster-key.pem -out mongodb-cluster.csr -addext "subjectAltName = DNS:localhost,DNS:${SSL_HOST_CLUSTER}"

# We're self signing our own cluster server cert here.  This is a no-no in production.
openssl x509 -req -days $DAYS -sha256 -in mongodb-cluster.csr -CA ca-cert.pem -CAkey ca-key.pem -set_serial 01 -out mongodb-cluster-cert.pem

# Create the Server Key, CSR, and Certificate
openssl genrsa -out mongodb-key.pem 4096
openssl req -subj "/CN=${SSL_HOST}" -new -key mongodb-key.pem -out mongodb.csr -addext "subjectAltName = DNS:localhost,DNS:${SSL_HOST}"

# We're self signing our own server cert here.  This is a no-no in production.
openssl x509 -req -days $DAYS -sha256 -in mongodb.csr -CA ca-cert.pem -CAkey ca-key.pem -set_serial 01 -out mongodb-cert.pem

# Create the Client Key and CSR
openssl genrsa -out client-key.pem 4096
openssl req -subj "/CN=-client" -new -key client-key.pem -out client-cert.csr

# Sign the client certificate with our CA cert.  Unlike signing our own server cert, this is what we want to do.
# Serial should be different from the server one, otherwise curl will return NSS error -8054
openssl x509 -req -days $DAYS -in client-cert.csr -CA ca-cert.pem -CAkey ca-key.pem -set_serial 02 -out client-cert.pem

# Verify Cluster Server Certificate
openssl verify -purpose sslserver -CAfile ca-cert.pem mongodb-cluster-cert.pem

# Verify Server Certificate
openssl verify -purpose sslserver -CAfile ca-cert.pem mongodb-cert.pem

# Verify Client Certificate
openssl verify -purpose sslclient -CAfile ca-cert.pem client-cert.pem

cat ca-cert.pem ca-key.pem > ca.pem
cat client-cert.pem client-key.pem > client-cert-and-key.pem
cat mongodb-cluster-cert.pem mongodb-cluster-key.pem > mongodb-cluster.pem
cat mongodb-cert.pem mongodb-key.pem > mongodb.pem
