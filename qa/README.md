# Pruebas de M.A.I.T.S

Herramientas usadas para probar el sistema antes de producción. El resultado
está en [INFORME-QA.md](INFORME-QA.md).

> **No usar en producción.** Estos scripts crean agentes y tickets de prueba
> con contraseñas conocidas. Úsalos solo en una copia de pruebas. La carpeta
> tiene `.htaccess` que bloquea el acceso web y los `.php` solo corren desde
> la terminal.

## Requisitos

- Una instalación de prueba de M.A.I.T.S (osTicket 1.18.4) funcionando.
- PHP CLI con las mismas extensiones del servidor.
- Python 3 con `requests` (y `openpyxl` si quieres abrir los Excel generados).

## Pasos

```bash
# 1. Datos de prueba (desde la raíz del sitio o con OST_ROOT=/ruta/del/sitio)
php qa/datos_agentes.php                 # agente1 (Mantenimiento, acceso limitado) y agente2 (Soporte)
for w in 0 1 2 3; do php qa/datos_tickets.php 5000 $((w*5000)) & done; wait   # 20 000 tickets

# 2. Dar a agente2 el permiso "Crear y editar equipos"
#    (Administración -> Agentes -> Roles -> All Access -> pestaña M.A.I.T.S)

# 3. Pruebas por web (ajusta la URL y el usuario administrador en los scripts)
MAITS_URL=http://localhost:8081 python3 qa/test_reportes.py
MAITS_URL=http://localhost:8081 python3 qa/test_equipos.py

# 4. Medir tiempo y memoria de un reporte
php -d memory_limit=128M qa/perfil_reporte.php adminqa xlsx
php qa/perfil_reporte.php adminqa pdf rango 2026-10-01 2026-10-31
```

Notas:

- `test_equipos.py` consulta la base `maits` con el cliente `mysql` para
  confirmar que no se crean equipos inválidos.
- Si el servidor no tiene DNS, desactiva la verificación de correos
  (`verify_email_addrs`) para poder crear tickets de prueba.
