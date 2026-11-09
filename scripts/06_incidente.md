# Fase 6 — Incidente simulado: "sitio2 responde 502 y sitio1 funciona"

> Requisito previo: Fases 3 y 4 terminadas (un pool PHP-FPM por sitio, vhosts con sockets por sitio).
> Este guion sirve para tres cosas: provocarlo para las capturas, escribir la sección 11 del informe
> y **ensayar la sustentación** (4 min, la parte con más peso).
> Necesitan dos terminales: **T-SRV** (servidor) y **T-CLI** (segundo nodo).

---

## 0. Estado sano (antes)
T-CLI:
```bash
curl -sk -o /dev/null -w 'sitio1 -> %{http_code}\n' https://sitio1.local/
curl -sk -o /dev/null -w 'sitio2 -> %{http_code}\n' https://sitio2.local/
```
Esperado: 200 y 200.

## 1. Provocar la falla (T-SRV)
```bash
sudo cp -a /etc/php/8.5/fpm/pool.d/sitio2.conf /etc/php/8.5/fpm/pool.d/sitio2.conf.bak.incidente
sudo sed -i 's/^listen.owner = www-data/listen.owner = www-sitio2/; s/^listen.group = www-data/listen.group = www-sitio2/' /etc/php/8.5/fpm/pool.d/sitio2.conf
sudo php-fpm8.5 -t          # la sintaxis es válida: el error es de lógica, no de sintaxis
sudo systemctl restart php8.5-fpm
```
Narrativa para el informe: *"un administrador, buscando 'aislar mejor' sitio2, cambió el dueño del socket al
usuario del pool sin considerar que quien se conecta al socket es Nginx."* (Es un error realista.)

## 2. Síntoma observado (T-CLI) — captura E22
```bash
curl -sk -o /dev/null -w 'sitio1 -> %{http_code}\n' https://sitio1.local/
curl -sk -o /dev/null -w 'sitio2 -> %{http_code}\n' https://sitio2.local/
curl -sk https://sitio2.local/ | head -5
```
Esperado: sitio1 → **200**, sitio2 → **502** (`502 Bad Gateway`). Captura también del navegador en sitio2.
Observación clave: **falla un solo sitio** → no es la red, ni Nginx caído, ni el certificado (comparten todo eso).

## 3. Hipótesis 1: "PHP-FPM está caído" → se DESCARTA (T-SRV) — captura E23
```bash
systemctl status php8.5-fpm --no-pager | head -5
ps -eo user,pid,cmd | grep 'pool sitio' | grep -v grep
```
Esperado: `active (running)` y workers de `pool sitio1` (www-sitio1) **y** `pool sitio2` (www-sitio2) vivos.
Conclusión: el proceso existe; el problema está entre Nginx y el pool.

## 4. Hipótesis 2: Nginx no logra conectarse al socket de sitio2 → logs (T-SRV) — captura E24
```bash
sudo tail -n 5 /var/log/nginx/sitio2.error.log
```
Esperado:
`connect() to unix:/run/php/php8.5-fpm-sitio2.sock failed (13: Permission denied) while connecting to upstream`
Lectura del error: el socket **sí existe** (si no, sería `2: No such file or directory`, como en el incidente
anterior del informe); el errno 13 apunta a **permisos**.

## 5. Confirmar la causa (T-SRV) — captura E25
```bash
ls -l /run/php/
ps -o user= -C nginx | sort | uniq -c          # workers de Nginx corren como www-data
sudo -u www-data test -w /run/php/php8.5-fpm-sitio2.sock && echo "www-data PUEDE escribir" || echo "www-data NO puede escribir"
grep -E '^listen\.(owner|group|mode)' /etc/php/8.5/fpm/pool.d/sitio2.conf
```
Esperado: `srw-rw---- www-sitio2 www-sitio2 ... php8.5-fpm-sitio2.sock` vs. `srw-rw---- www-data www-data ... sitio1.sock`;
Nginx corre como `www-data`, que no es dueño ni pertenece al grupo → con modo 660 no puede escribir.

**Causa raíz:** `listen.owner/listen.group` del pool sitio2 asignan el socket al usuario del pool en vez de al
usuario que se conecta (Nginx). El aislamiento del pool lo da `user/group` (quién *ejecuta* PHP), no el dueño del socket.

## 6. Solución (T-SRV) — captura E26
```bash
sudo sed -i 's/^listen.owner = www-sitio2/listen.owner = www-data/; s/^listen.group = www-sitio2/listen.group = www-data/' /etc/php/8.5/fpm/pool.d/sitio2.conf
sudo php-fpm8.5 -t
sudo systemctl restart php8.5-fpm
ls -l /run/php/php8.5-fpm-sitio2.sock
```
(Alternativa válida para mencionar en la sustentación: dejar el dueño y usar `listen.acl_users = www-data`.)

## 7. Verificación (T-CLI y T-SRV) — captura E27
```bash
# T-CLI
curl -sk -o /dev/null -w 'sitio1 -> %{http_code}\n' https://sitio1.local/
curl -sk -o /dev/null -w 'sitio2 -> %{http_code}\n' https://sitio2.local/
# T-SRV
sudo tail -n 3 /var/log/nginx/sitio2.access.log     # peticiones nuevas con 200 y urt= con tiempo
sudo monitor_lemp.sh
```
Esperado: 200 / 200, sin errores nuevos en `sitio2.error.log`, monitor `RESUMEN=OK`.

---

### Formato para el informe (sección 11) y la ficha
Síntoma → Hipótesis (1 descartada, 2 confirmada) → Diagnóstico (comandos y salidas) → Causa raíz →
Solución → Verificación → **Lección**: "el dueño del socket debe ser quien se conecta (Nginx), el usuario del
pool es quien ejecuta; confundirlos da 502 con errno 13".

### Preguntas probables del docente (preparar respuesta)
- ¿Por qué 502 y no 500 o 504? (502: el proxy no obtuvo respuesta válida del upstream; 504: el upstream tardó demasiado; 500: el error ocurrió dentro de la aplicación.)
- ¿Cómo distinguen "socket ausente" de "sin permisos"? (errno 2 vs 13 en `error.log`).
- ¿Por qué sitio1 seguía funcionando? (pool y socket independientes).
- ¿Qué pasaría con `listen.mode = 0666`? (funciona, pero cualquier usuario local podría ejecutar PHP como www-sitio2: peor seguridad).
