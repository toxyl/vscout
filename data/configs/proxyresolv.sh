#!/bin/sh
# This script is called by proxychains to resolve DNS names

# DNS server used to resolve names
DNS_SERVER=${PROXYRESOLV_DNS:-4.2.2.2}

if [ $# = 0 ] ; then
        echo "  usage:"
        echo "          proxyresolv <hostname> "
        exit
fi

export LD_PRELOAD=libproxychains.so.3

IP=$(echo "SELECT ip FROM domain WHERE name='$1'" | sqlite3 {{ DATA_DIR }}/data.db | tr -d "\n")
if [ "$IP" = "" ] ; then
        IP=$(tor-resolve $1 | tr -d "\n")
fi

# uncomment to allow usage of dig as fallback
# if [ "$IP" = "" ] ; then
#     IP=$(dig $1 @$DNS_SERVER +tcp | awk '/A.+[0-9]+\.[0-9]+\.[0-9]/{print $5;}')
# fi

echo $IP
