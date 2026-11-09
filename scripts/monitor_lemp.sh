#!/usr/bin/env bash
# monitor_lemp.sh — Estado básico del stack LEMP. Instalar en /usr/local/bin/monitor_lemp.sh
# Uso manual:  sudo monitor_lemp.sh
# Programado:  cada 5 min con cron  ->  */5 * * * * root /usr/local/bin/monitor_lemp.sh >> /var/log/lemp-monitor.log 2>&1
TS=$(date '+%F %T')
estado=0
for s in nginx php8.5-fpm mariadb; do
  st=$(systemctl is-active "$s")
  [ "$st" = active ] || estado=1
  printf '%s servicio=%s estado=%s\n' "$TS" "$s" "$st"
done
for n in 1 2; do
  sock=/run/php/php8.5-fpm-sitio$n.sock
  [ -S "$sock" ] && s_ok=presente || { s_ok=AUSENTE; estado=1; }
  code=$(curl -sk -o /dev/null -w '%{http_code}' --max-time 5 --resolve "sitio$n.local:443:127.0.0.1" "https://sitio$n.local/")
  [ "$code" = 200 ] || estado=1
  printf '%s sitio=sitio%s.local http=%s socket=%s\n' "$TS" "$n" "$code" "$s_ok"
done
errs=$(tail -n 200 /var/log/nginx/sitio*.error.log 2>/dev/null | grep -c -E '\[(error|crit|alert|emerg)\]')
printf '%s errores_nginx_ultimas_200_lineas=%s\n' "$TS" "$errs"
[ "$estado" = 0 ] && echo "$TS RESUMEN=OK" || echo "$TS RESUMEN=ALERTA"
exit $estado
