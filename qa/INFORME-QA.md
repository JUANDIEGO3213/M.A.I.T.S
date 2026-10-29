# Informe de pruebas de M.A.I.T.S

Rama: `qa/pruebas-produccion` · Fecha: 29/10/2026 · Base: `main` (`cb6c4b8`)

## Resumen

M.A.I.T.S es osTicket **v1.18.4** (la versión más reciente) con
personalizaciones propias: generador de reportes (Excel, PDF y CSV), la
pestaña "Equipos", y cambios de diseño en el portal y el panel de agentes.
El núcleo de osTicket está sin modificar, salvo por ajustes del instalador y
de la interfaz, así que las pruebas se centraron en el código propio.

**Hallazgo principal.** Con un volumen real de tickets, el generador de
reportes **no funcionaba**:

- Con la memoria por defecto de PHP (128 MB), Excel y CSV fallaban desde
  unos pocos miles de tickets.
- El PDF fallaba desde unos 400 tickets, y desde unos 1 500 tickets tenía un
  error fatal sin importar la memoria.

Esto quedó corregido en la rama. Además, los reportes ahora son unas 4 veces
más rápidos.

| | Antes | Después |
|---|---|---|
| Excel, 20 051 tickets | Error de memoria (necesitaba 457 MB) | 4.2 s, 87 MB |
| CSV, 20 051 tickets | Error de memoria (335 MB) | 3.3 s, 22 MB |
| Excel, 3 grupos (40 000 filas) | Error de memoria | 8.7 s |
| PDF, 1 000 tickets | 262 MB (falla con 128 MB) | 71 MB |
| PDF, 2 000 tickets | Error fatal (pcre.backtrack_limit) | 16 s, 93 MB |
| Consultas a la BD por reporte | 1 por ticket (20 000 extra) | Constantes |
| Más de 20 000 tickets | Se cortaba sin avisar y el total salía mal | Pide acotar el periodo |

**Pruebas automatizadas:** 21 de 21 en reportes y 12 de 12 en Equipos pasan
en la rama. Ver [README.md](README.md) para repetirlas.

## Entorno de pruebas

- Apache 2.4.58 + mod_php, PHP 8.3.6, MariaDB 10.11.14. Configuración de PHP
  por defecto: `memory_limit=128M` y `max_execution_time=30`.
- Instalación limpia con el instalador oficial de osTicket v1.18.4 y el
  código de la rama.
- Datos: 20 051 tickets en 3 departamentos, con fechas a lo largo de 400 días
  (40 % cerrados), 300 usuarios y 3 agentes:
  - `adminqa`: administrador.
  - `agente1`: Mantenimiento, rol de acceso limitado.
  - `agente2`: Soporte, con el permiso "Crear y editar equipos".

## Hallazgos corregidos en esta rama

| # | Severidad | Área | Hallazgo | Corrección |
|---|---|---|---|---|
| 1 | **Alta** | Reportes | Excel y CSV cargaban todos los tickets en memoria, dos veces: las filas y luego la hoja. Con 128 MB fallaban con *Allowed memory size exhausted* y el agente veía una página en blanco. | Las filas se leen con un generador y se convierten a XML o CSV en cuanto se leen. `memory_limit` sube a 512M solo durante la descarga (nunca baja). |
| 2 | **Alta** | Reportes, PDF | Toda la tabla iba en un solo `WriteHTML()`. Pasando unos 1 500 tickets, mPDF lanza `MpdfException: The HTML code size is larger than pcre.backtrack_limit`. Además usaba unos 0.27 MB por fila. | La tabla se escribe en bloques de 100 filas. El encabezado se repite en cada página igual que antes. |
| 3 | Media | Reportes | Había un tope fijo de 20 000 tickets. Si había más, el reporte se cortaba sin avisar y "Total de tickets" mostraba 20 000. | Se cuentan los tickets antes de generar. Si pasan del máximo (50 000 en Excel y CSV, 2 000 en PDF), el formulario pide un periodo más corto y sugiere Excel en lugar de PDF. |
| 4 | Media | Reportes, rendimiento | Con un agente en sesión, `SelectionField::display()` de osTicket consulta la lista de estados por cada ticket: 20 045 consultas en un reporte. | El nombre del estado se toma de la lista ya cargada. La salida es idéntica byte por byte (se comparó el CSV de 2 063 filas con todas las columnas) y es 4 veces más rápida. |
| 5 | Media | Equipos | Un POST armado a mano con un líder o miembro inexistente creaba el equipo y mostraba "creado". Por ejemplo, quedaba un equipo con `lead_id=9999` y sin miembros. La causa: `Team::update()` guarda el equipo antes de revisar a los miembros. | Se valida que todos los agentes elegidos existan y estén activos antes de guardar. |
| 6 | Baja | Equipos | Editar un equipo desde "Equipos" apagaba "sin avisos" y reactivaba los avisos de todos los miembros, aunque un administrador los hubiera desactivado. | Se conservan los avisos existentes. Los miembros nuevos sí reciben avisos. |
| 7 | Baja | Equipos | Un nombre con etiquetas HTML (`X<b>y</b>`) evitaba la revisión de duplicados, porque osTicket quita las etiquetas después de revisar. | Las etiquetas se quitan antes de revisar. |
| 8 | Baja | Reportes, seguridad | Inyección de fórmulas en CSV: un asunto como `=HYPERLINK(...)` se ejecutaría al abrir el archivo en Excel. osTicket ya rechaza `= + - @` al inicio en formularios y API, así que esto es una defensa adicional para datos que lleguen por otros medios, como el correo o datos antiguos. | Las celdas que empiezan con `= + - @` llevan un apóstrofo, como recomienda OWASP. En Excel (.xlsx) ya iban como texto (`inlineStr`), y se verificó que no hay `<f>`. |
| 9 | Baja | Código | `equipos.php` leía `$errors['err']` sin revisar si existe, lo que produce un *Warning* en PHP 8. | Se usa `empty()`. |
| 10 | Baja | Repositorio | No había `.gitignore`. Un `git add .` en el servidor subiría `include/ost-config.php`, que tiene la contraseña de la BD. | Se agregó `.gitignore`. |

## Pruebas que pasaron sin cambios

- **Permisos y visibilidad.** Un agente solo ve en los reportes los tickets de
  su departamento: `agente1` vio solo 6 683 de Mantenimiento y `agente2` solo
  6 684 de Soporte. Un agente no puede abrir un ticket de otro departamento
  cambiando el `id` en la URL.
- **Equipos.** Sin el permiso de rol, el agente ve "Mi área" pero no puede
  gestionar equipos, ni con un GET ni con un POST directo.
- **CSRF.** Los POST sin token se rechazan con 400, tanto en reportes como en
  equipos.
- **XSS.** `<script>` y `<img onerror>` en el nombre y las notas de un equipo
  salen escapados en "Equipos" y en Administración → Equipos.
- **Validaciones del formulario de reportes:**
  - Pide marcar al menos un grupo y al menos una columna.
  - Rechaza fechas inválidas y rangos invertidos.
  - Rechaza "mismo periodo que Creados" si no se marcó Creados.
  - Descarta columnas inventadas (por ejemplo `; DROP TABLE`).
  - Un formato desconocido genera Excel.
- **Archivos protegidos.** `include/` (incluido `ost-config.php`), `qa/` y
  `setup/` (ya borrada) no se sirven por web.
- **Excel.** El archivo es un ZIP válido y abre con `openpyxl`. Las 3 hojas
  traen totales correctos (12 034 abiertos + 8 017 cerrados = 20 051 creados)
  y las fechas son fechas reales de Excel.
- **Concurrencia.** 4 reportes Excel de 20 000 tickets al mismo tiempo
  terminaron en 5.1 s sin errores. Generar un reporte no bloquea otras
  pestañas del mismo agente.
- **Páginas.** 43 páginas del panel y del portal responden 200 sin errores ni
  avisos de PHP. Tiempos de respuesta:
  - Lista de tickets: 0.3–0.4 s con 12 000 abiertos.
  - Equipos y Reportes: 20–30 ms.
  - Portal: alrededor de 430 peticiones por segundo con 20 conexiones.
- **Versión.** osTicket v1.18.4 es la última publicada. Corrige
  CVE-2026-38444, un XSS almacenado por el nombre del remitente de un correo
  que afecta de v1.10 a v1.18.3.

## Recomendaciones (no se cambiaron)

1. **Permiso "Crear y editar equipos" (Media, decisión de diseño).** Quien lo
   tiene puede agregarse a cualquier equipo, y con eso ver y trabajar los
   tickets asignados a ese equipo. Otórgalo solo a supervisores. Si hace
   falta, se puede limitar para que el agente no pueda agregarse a sí mismo.
2. **Servidor de producción.**
   - **HTTPS.** Usarlo obligatoriamente. Sin HTTPS la cookie `OSTSESSID` no
     lleva `Secure`.
   - **Cabeceras.** Agregar en Apache:
     ```apache
     Header always set Strict-Transport-Security "max-age=31536000"
     Header always set X-Content-Type-Options "nosniff"
     Header always set Referrer-Policy "strict-origin-when-cross-origin"
     ```
     osTicket ya envía un `Content-Security-Policy` con `frame-ancestors 'self'`.
   - **Permisos.** `include/ost-config.php` en solo lectura (`chmod 0644` y que
     no sea de `www-data`).
   - **Configuración de Apache.** `AllowOverride All`, o al menos `AuthConfig
     Limit`, para que funcionen los `.htaccess` de `include/`, `api/` y `qa/`.
   - **PHP.**
     - `memory_limit` de 256M o más. Los reportes suben a 512M por su cuenta
       si se permite.
     - `max_execution_time` de 60 s o más.
     - Que el *timeout* del proxy o balanceador sea mayor a 30 s, para los
       PDF grandes.
   - **Cron.** Configurar el cron de osTicket (`api/cron.php` cada 5 minutos)
     para la descarga de correos y para los vencimientos de SLA.
   - **Respaldos.** Respaldo diario de la BD y de los adjuntos.
3. **Plugin de inicio de sesión con Microsoft** (`auth-oauth2.phar` v0.6).
   Confirmar en osticket.com que sea la última versión del plugin. El servidor
   necesita salida HTTPS hacia `login.microsoftonline.com`.
4. **Verificación de correos.** osTicket revisa por DNS que el dominio del
   correo exista (`verify_email_addrs`). El servidor necesita DNS funcional o
   no se podrán crear tickets.
5. **Exportación nativa de osTicket.** "Exportar esta lista (CSV)" tiene la
   misma consulta por ticket del hallazgo 4. Es código de osTicket y no se
   tocó, para no complicar futuras actualizaciones. Para listas grandes
   conviene usar "Crear reporte".
6. **Limpieza.** `assets/default/css/theme.css.save` es una copia de respaldo
   que no se usa y se puede borrar.
7. **Actualizaciones.** Al actualizar osTicket, los archivos personalizados del
   núcleo se sobrescriben y hay que volver a aplicar los cambios:
   - `include/class.nav.php`
   - `include/staff/header.inc.php`
   - `include/staff/ticket-view.inc.php`
   - `include/staff/templates/*`
   - `include/client/*`
   - `scp/staff.inc.php`

   Conviene seguir marcándolos con `[M.A.I.T.S]`, como ya se hace.

## Archivos cambiados

- `include/maits/reporte.php`: generador por filas, conteo y límites, PDF en
  bloques, CSV seguro, memoria.
- `include/maits/xlsx.php`: `filaXml()` y soporte para filas ya convertidas a
  XML.
- `scp/reportes.php`: valida el tamaño antes de generar.
- `include/class.maits.php`: validación de agentes y avisos al editar equipos.
- `scp/equipos.php`: aviso de PHP 8.
- `.gitignore`.
- `qa/`: scripts de prueba y este informe.
