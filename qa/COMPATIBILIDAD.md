# Compatibilidad al actualizar PHP, Ubuntu u osTicket

Fecha: 29/10/2026 · Base: rama `qa/pruebas-produccion`

## Respuesta corta

- **Actualizar PHP no corrompe los datos.** PHP solo ejecuta el código. Los
  tickets están en la base de datos y no cambian.
  - M.A.I.T.S funciona con **PHP 8.2, 8.3, 8.4 y 8.5**: se probó con la misma
    base de datos de 20 051 tickets.
  - La versión oficialmente soportada por osTicket 1.18 es **8.2 a 8.4**, y la
    recomendada es 8.4.
- **Actualizar Ubuntu es seguro si se queda en 24.04.** Pasar a Ubuntu 26.04
  cambia PHP a 8.5, que osTicket todavía no soporta oficialmente, aunque en
  las pruebas funcionó.
- **Actualizar osTicket es el único punto delicado.**
  - El método oficial es copiar la nueva versión encima de la actual, y eso
    **borra las personalizaciones de M.A.I.T.S** en 18 archivos del núcleo.
  - Además, la pestaña "Equipos" daría un error fatal.
  - Los datos no se pierden, pero el actualizador modifica la base de datos y
    ese paso **no tiene marcha atrás sin un respaldo**.

## 1. PHP

### Resultados de las pruebas

Se usó Apache con mod_php en contenedores Docker oficiales (`php:X-apache`),
la misma base MariaDB 10.11 y `memory_limit=128M`:

| PHP | Reportes (21) | Equipos (12) | Páginas (53) | Excel 20 051 tickets | PDF 1 431 tickets |
|---|---|---|---|---|---|
| 8.2 | 21/21 | 12/12 | 0 errores | 4.3 s | 9.5 s |
| 8.3 (Ubuntu 24.04) | 21/21 | 12/12 | 0 errores | 4.2 s | 11.8 s |
| 8.4 | 21/21 | 12/12 | 0 errores | 3.7 s | 9.8 s |
| 8.5 | 21/21 | 12/12 | 0 errores | 4.3 s | 9.4 s |

Las imágenes de prueba no tenían la extensión `gd`, así que en 8.2, 8.4 y 8.5
los logos del PDF no se probaron. En producción hay que instalar `php-gd`,
igual que en 8.3.

### Avisos de obsolescencia (deprecations)

Para encontrarlos se activó `E_ALL`, porque osTicket los oculta por defecto en
`bootstrap.php`.

- **En el código de M.A.I.T.S:** había un solo aviso, en PHP 8.4 y 8.5:
  `fputcsv(): the $escape parameter must be provided`. **Ya está corregido**
  en esta rama, y el CSV que se genera es idéntico byte por byte.
- **En el núcleo de osTicket 1.18.4:** hay cientos de miles de avisos en todas
  las versiones. Los principales:
  - `strftime()`: obsoleta desde PHP 8.1.
  - Propiedades dinámicas en el ORM.
  - `strtotime(null)`.
  - En 8.5 aparecen además `Using null as an array offset` y
    `Cannot use bool as array`.

  Ninguno cambia el resultado hoy. Pero `strftime()` y las propiedades
  dinámicas **dejarán de funcionar en PHP 9**. Es trabajo de los
  desarrolladores de osTicket, y no conviene parcharlo aquí.

### Fin de soporte ([php.net](https://www.php.net/supported-versions.php))

| PHP | Soporte activo | Parches de seguridad hasta |
|---|---|---|
| 8.2 | terminó | **31/12/2026** (en 2 meses) |
| 8.3 | terminó | 31/12/2027 |
| 8.4 | 31/12/2026 | 31/12/2028 |
| 8.5 | 31/12/2027 | 31/12/2029 |

El PHP que trae Ubuntu recibe parches de Canonical mientras la versión de
Ubuntu tenga soporte, aunque php.net ya lo haya retirado.

## 2. Ubuntu

| Ubuntu | PHP de fábrica | ¿Soportado por osTicket 1.18? | Recomendación |
|---|---|---|---|
| 22.04 LTS | 8.1 | **No**: osTicket 1.18 pide 8.2 o mayor | Actualizar |
| 24.04 LTS | 8.3 | Sí | **Recomendado hoy** |
| 26.04 LTS | 8.5 ([fuente](https://computingforgeeks.com/install-php-8-5-ubuntu-2604/)) | No oficialmente | Esperar a que osTicket lo soporte, o probar antes en una copia |

Cuidados al actualizar Ubuntu:

- `do-release-upgrade` reemplaza PHP por la versión nueva y **desactiva los
  módulos de la versión anterior**. Después hay que revisar:
  - Que `libapache2-mod-phpX.Y` esté activo.
  - Que estén instaladas las extensiones `php-mysql`, `php-gd`, `php-intl`,
    `php-mbstring`, `php-xml`, `php-zip` y `php-apcu`.
  - Que se copien a la nueva versión los ajustes de `php.ini`: memoria y
    tiempo.
- MariaDB o MySQL también se actualizan. La base de datos se migra sola, pero
  **haz el respaldo antes**.
- Revisa que el cron de osTicket siga activo.

## 3. osTicket

### Qué se pierde al copiar una versión nueva encima

Estos archivos del núcleo tienen cambios de M.A.I.T.S y se sobrescriben:

```
include/class.nav.php                       (pestaña Equipos, submenú Crear reporte)
scp/staff.inc.php                           (carga class.maits.php: sin esto, Equipos da error fatal)
include/staff/header.inc.php                (encabezado, estilos y JS de M.A.I.T.S)
include/staff/login.header.php, login.tpl.php
include/staff/ticket-view.inc.php
include/staff/templates/navigation.tmpl.php, queue-tickets.tmpl.php
include/client/header.inc.php, login.inc.php, open.inc.php, tickets.inc.php, view.inc.php
index.php
assets/default/css/theme.css, css/thread.css, scp/css/login.css, scp/css/scp.css
```

Los archivos propios no se pierden porque osTicket no los trae:

- `include/maits/`
- `include/class.maits.php`
- `scp/equipos.php` y `scp/reportes.php`
- `include/staff/maits-*.inc.php`
- Los CSS y JS `maits-*`
- `images/reporte/`
- `include/plugins/auth-oauth2.phar`
- `include/i18n/es_MX.phar`

**Dependencias internas que podrían cambiar en una versión mayor** (1.19 o
2.0). El código propio usa estas funciones de osTicket:

- `CustomQueue::getExportableFields()`, `getExportColumns()`, `mangleQuery()`
  y `QueueColumn::from_query()`.
- `Staff::getTicketsVisibility()`.
- `Team::update()` y `StaffDeptAccess`.
- `RolePermission::register()` y `mPDFWithLocalImages`.

Entre versiones 1.18.x normalmente no cambian. Aun así, hay que correr
`qa/test_reportes.py` y `qa/test_equipos.py` después de cada actualización.

### Procedimiento seguro para actualizar osTicket

1. **Copia de pruebas primero.** Restaura el respaldo de producción en un
   servidor de pruebas y haz ahí toda la actualización.
2. **Lleva osTicket original en una rama aparte del repositorio**, por
   ejemplo `upstream`. Para actualizar, combina la nueva versión con
   `git merge` en lugar de copiar archivos:

   ```bash
   # Una sola vez: rama con el osTicket original
   git checkout -b upstream 20be12c         # o el tarball oficial de la versión instalada
   # En cada actualización: copiar el nuevo osTicket (sin setup/) en la rama upstream
   git checkout upstream && <copiar osTicket nuevo> && git commit -am "osTicket vX.Y.Z"
   git checkout main && git merge upstream   # Git conserva los cambios de M.A.I.T.S
   ```

   Los conflictos solo pueden aparecer en los 18 archivos de la lista
   anterior, y todos llevan la marca `[M.A.I.T.S]`.
3. En la copia de pruebas, entra a `/scp`. El actualizador hace los cambios
   en la base de datos.
4. Corre las pruebas:
   - `qa/test_reportes.py`
   - `qa/test_equipos.py`
   - Una revisión manual del portal, de abrir un ticket y de responder un
     ticket.
5. **En producción:**
   1. Pon el sistema fuera de línea: Administración → Configuración →
      "Helpdesk Status: Offline".
   2. Haz el respaldo.
   3. Despliega la rama ya probada.
   4. Entra a `/scp` para que corra el actualizador.
   5. Pon el sistema en línea otra vez.

### Respaldo y marcha atrás

```bash
# Respaldo (antes de cualquier actualización de PHP, Ubuntu u osTicket)
mysqldump --single-transaction --routines --triggers maits | gzip > maits-$(date +%F).sql.gz
tar czf maits-codigo-$(date +%F).tgz /var/www/maits     # incluye include/ost-config.php
# Si los adjuntos se guardan en disco (plugin de archivos), respaldar también esa carpeta

# Marcha atrás
gunzip < maits-AAAA-MM-DD.sql.gz | mysql maits
tar xzf maits-codigo-AAAA-MM-DD.tgz -C /
```

- **Para PHP y Ubuntu**, la marcha atrás es reinstalar la versión anterior del
  paquete o restaurar la instantánea (*snapshot*) del servidor, si es una
  máquina virtual. Es lo más rápido: toma una antes de actualizar.
- **Para osTicket**, la marcha atrás requiere restaurar la base de datos,
  porque el actualizador ya cambió su estructura. Por eso el respaldo es
  obligatorio.
