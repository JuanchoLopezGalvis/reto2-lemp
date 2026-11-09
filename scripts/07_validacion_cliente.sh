#!/usr/bin/env bash
# Fase 7 — Validación desde el SEGUNDO NODO (cliente). Ejecutar en el cliente, no en el servidor.
# Requiere en /etc/hosts del cliente:  <IP_TAILSCALE_SERVIDOR> sitio1.local sitio2.local
SERVER_IP="${1:-100.79.166.31}"
sec() { echo; echo "===== $* ====="; }
run() { echo "\$ $*"; bash -c "$*" 2>&1; }
sec "Nodo";                  run "hostname; date '+%F %T %Z'; tailscale ip -4"
sec "Resolución";            run "getent hosts sitio1.local sitio2.local"
sec "Conectividad";          run "ping -c 2 $SERVER_IP"
sec "Contenido distinto";    run "curl -sk https://sitio1.local/ | grep -oE '<title>[^<]*|<h1>[^<]*'"; run "curl -sk https://sitio2.local/ | grep -oE '<title>[^<]*|<h1>[^<]*'"
sec "Redirección 301";       run "curl -sI http://sitio1.local/ | grep -iE '^(HTTP|location)'"; run "curl -sI http://sitio2.local/ | grep -iE '^(HTTP|location)'"
sec "Encabezados seguridad"; run "curl -skI https://sitio1.local/ | grep -iE '^(HTTP|server|x-powered|strict|x-frame|x-content|referrer|content-security)'"
sec "TLS (lo que ofrece el servidor)"; run "nmap -Pn --script ssl-enum-ciphers -p 443 sitio1.local | grep -E 'TLSv|least strength'"
sec "Archivos protegidos";   run "curl -sk -o /dev/null -w '/.env -> %{http_code}\n' https://sitio1.local/.env"; run "curl -sk -o /dev/null -w '/phpinfo.php -> %{http_code}\n' https://sitio1.local/phpinfo.php"
sec "Puertos expuestos";     run "nmap -Pn -p 22,80,443,3306 $SERVER_IP"
