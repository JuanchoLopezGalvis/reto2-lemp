# Reto 2 — Infraestructura web segura (LEMP)

Proyecto Final de Sistemas Operacionales 2026-2 · Universidad El Bosque
Equipo: Juan Diego López, Luis Camilo Vivas Cruz, Dylan Mancipe · Docente: Ing. Samuel Galindo Pinzón

Nginx 1.28.3 + PHP-FPM 8.5 + MariaDB 11.8.6 sobre Ubuntu Server 26.04.1 LTS, con dos virtual hosts
(`sitio1.local` Panel de Administradores, `sitio2.local` Portal de Lectores), un pool PHP-FPM por sitio,
HTTPS con redirección 301, UFW restringido a Tailscale y validación desde un segundo nodo.

## Contenido

| Ruta | Qué hay |
|---|---|
| `entregables/` | Informe técnico, ficha técnica y presentación (PDF), y bitácora técnica |
| `app/` | Código PHP desplegado en cada sitio |
| `evidencias/configs_finales/` | Configuración final tomada del servidor (vhosts, snippets, pools, sshd, PHP, cron, logrotate, UFW) |
| `evidencias/capturas/` | Evidencias E01–E27 del informe |
| `scripts/` | Monitoreo (`monitor_lemp.sh`), guion del incidente (`06_incidente.md`) y validación desde el cliente |

## Nodos

| Rol | Host | IP Tailscale |
|---|---|---|
| Servidor | `srv-lemp` (VM VirtualBox) | 100.79.166.31 |
| Cliente / segundo nodo | portátil del equipo | 100.95.110.30 |
