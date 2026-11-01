# Informe de auditoría — RadioEdu Conecta

> **Actualización 2026-11-01.** Después de la auditoría se trataron todos sus hallazgos, con un commit por hallazgo en `feat/wp71-apis`. El código corregido final es `5b36de5`:
>
> - **24 hallazgos:**
>   - 22 corregidos, S-02 incluido, con el límite elevado a 20 propuestas por hora;
>   - F-02 y F-22 cerrados por decisión del responsable y documentados.
> - **Desviaciones frente a AGENTS.md:**
>   - Q-02 y Q-06 corregidas;
>   - Q-01 resuelta ajustando la norma;
>   - Q-03 resuelta con un inventario de excepciones;
>   - Q-04 ejecutada;
>   - queda pendiente Q-05.
> - **Segunda revisión independiente:** confirmó cinco problemas en las correcciones (R-01 a R-05), también corregidos.
> - **Verificación reiniciada:** una tercera revisión dejó abiertos S-01 (creaciones simultáneas), F-04 (índices), F-06 (selector), una opción en instalaciones con varias redes, una afirmación sobre el «cursor estable» y el ZIP. Todos están corregidos o rectificados. S-02, que se había aceptado como riesgo, también se corrigió después.
>
> El estado, la verificación, los matices y lo pendiente están en [Seguimiento de correcciones](#seguimiento-de-correcciones-2026-11-01). El resto del informe describe el código auditado: `922c042` con los cambios locales que después fueron `dc245a9`. Sus referencias de línea ya no coinciden con el código corregido.

## Contexto de revisión

| Campo | Valor |
| --- | --- |
| Fecha | 2026-10-31, Europe/Madrid |
| Revisor | Codex, revisión asistida con lectura manual y comprobaciones locales |
| Objetivo | Revisión profunda solicitada de documentación, código, seguridad, lógica y buenas prácticas |
| Repositorio | C:\radioedu-conecta |
| Commit base | 922c04280ed5e8826149bf7ba659fa2393755399 |
| Árbol de trabajo | Incluye cambios previos en assets/css/directorio.css, src/Frontend/DirectorioShortcode.php, templates/directorio.php, templates/theme-single-centro.php, tests/DirectorioBlockTest.php y tests/SingleCentroProfileTest.php |
| Identificación exacta | `evidencias/inventario.csv`: SHA-256 de cada fichero versionado, incluyendo cambios locales |
| Alcance | Código propio, documentación, configuración, tests, build, traducciones, distribución y dependencias declaradas |
| Exclusiones | Auditoría interna completa de WordPress y dependencias de terceros; sistemas remotos/producción |
| Pruebas autorizadas | Revisión y verificaciones locales necesarias; runtime solo en entorno desechable con datos ficticios |
| Versiones declaradas | Plugin 1.1; PHP >=8.1; WordPress >=7.1; contrastadas con documentación oficial |
| Runtime ejecutado | Core 7.1.4, PHP 8.1.34, MariaDB 10.11, WP-CLI 2.12.0, PCP 2.1.0, Edge 156 |
| Herramientas host | Node 24.12.0, npm 11.7.0, Composer 2.9.5, PHP 8.2.12; Node inferior a engines declarado |
| Limitaciones | PHPStan, PHPUnit completo y PHPCompatibilityWP no instalados; detalle en cobertura y verificaciones |

## Estado persistente

Revisión concluida dentro del alcance descrito, con 2 hallazgos de seguridad de severidad baja y 22 defectos funcionales/de cumplimiento documentados. Los casos no ejecutados permanecen expresamente pendientes; no se certifica el DoD ni la publicación. Plan exhaustivo creado en `PLAN-DE-REVISION.md` antes de revisar la implementación. No se ha modificado código funcional, tests originales ni ZIP.

El contenido auditado incluye los cambios locales iniciales. Durante la revisión se registraron en el commit `dc245a9711f113fbb332c570056bc0ee609bd842`; la comparación SHA-256 final con el inventario inicial no detecta cambios en ningún archivo fuente. La auditoría no creó ese commit.

### Prioridad de actuación

1. **P1:** privacidad de respuestas y reutilización del contacto borrado (F-01/F-02), sobrescritura demo (F-03), migración marcada como completada tras fallo (F-04), confusión de destinatario del panel (F-11) y licencia Leaflet antes de distribuir (F-17).
2. **P2:** cuotas S-01/S-02, paginación/centros gestionados F-05/F-06, errores y notificación F-07/F-08, afinidad/identidad visual F-09/F-10, mapa/reflow/navegación/embeds F-12…F-15 y Multisite/retención F-18/F-19/F-21/F-22.
3. **P3:** comunicación sin JS y residuos de preferencias F-16/F-20, junto con mejoras de robustez y alineación de requisitos indicadas más abajo.

Los P1 son prioridades de corrección, no vulnerabilidades de severidad alta. No se confirmó una vulnerabilidad crítica en el alcance revisado; esto no equivale a ausencia universal de vulnerabilidades.

### Registro de avance

1. Leído briefing: aplicación con CPT de centros, directorio público, propuestas privadas, Multisite, REST, editores y datos personales.
2. Identificadas reglas locales y cambios previos que se preservarán.
3. Cargada metodología de auditoría completa: inventario, fronteras, búsqueda, refutación de candidatos y cobertura.

## Hallazgos de seguridad confirmados

### S-01 — Baja — Un autor puede superar «Radios por usuario» restaurando un centro

- **Ubicación:** `src/Capabilities/CentroLimit.php:42,57,89` y `src/PostTypes/CentroPostType.php:75`.
- **Frontera:** autor limitado a una radio → REST core de su centro → control aplicado solo a `create_posts` → cupo impuesto por administrador → dos centros publicados.
- **Flujo:** con límite 1, el autor envía su radio a papelera (no cuenta), crea otra y actualiza la primera a `publish` mediante `POST /wp/v2/centros/{id}`. La actualización comprueba edición, no creación; no existe control de transición de estado que aplique el cupo.
- **Precondiciones:** rol Autor con capacidades del plugin y acceso legítimo a REST (nonce de su sesión o contraseña de aplicación). No permite editar radios ajenas ni elevarse a administrador.
- **Evidencia ejecutada:** `runtime-checks.txt`, `limit_restore`: DELETE 200, creación 201, restauración 200, `active_count=2` con límite 1. Se ejercitó el dispatcher REST con usuario actual; esta prueba no valida por sí sola la autenticación HTTP/nonce.
- **Impacto:** evasión del límite administrativo. Severidad baja: efecto acotado a centros propios, sin demostrar escalada ni indisponibilidad. También revisar transición de auto-draft y concurrencia antes de corregir.
- **Corrección:** validar el cupo cuando un centro pasa de estado no contabilizado a contabilizado, en REST (`rest_pre_insert_radioedu_centro`) y en restauración/edición clásica; descontar el propio centro si ya contaba. Para garantía estricta, reservar el cupo de forma atómica y liberar la reserva si falla la escritura. Mantener exención de gestores globales.
- **Regresión propuesta:** límite 1: crear → papelera → crear → restaurar debe rechazarse; editar el único centro existente debe seguir permitido; probar REST, admin, auto-drafts y concurrencia.
- **Referencia:** [Capacidades de tipos de contenido](https://developer.wordpress.org/reference/functions/register_post_type/), [rest_pre_insert](https://developer.wordpress.org/reference/hooks/rest_pre_insert_this-post_type/).

### S-02 — Baja — El límite horario admite envíos concurrentes por encima del máximo

- **Ubicación:** `src/Rest/ColaboracionesController.php:309–326,411–420`.
- **Frontera:** coordinador autenticado → creación de propuestas → contador transitorio de 10/h → lectura e incremento no atómicos.
- **Evidencia:** `extended-checks.php` interpone determinísticamente una segunda petición después de leer el contador de la primera y antes de su INSERT. Con contador inicial 9, ambas devuelven 201 con IDs distintos y el contador termina en 10: se aceptan dos plazas cuando quedaba una. Es simulación de intercalado, no prueba de carga simultánea por HTTP.
- **Impacto acotado:** evasión de una defensa contra spam por cuentas con permiso; no concede derechos sobre radios ajenas. No se ha demostrado indisponibilidad del servicio.
- **Corrección:** reserva atómica de cuota por usuario/ventana, con rollback si la inserción falla. No usar un transient con read-modify-write como garantía estricta; la caché persistente tampoco asegura atomicidad de esa secuencia.
- **Regresión:** dos peticiones con una plaza restante deben producir exactamente un 201 y un 429; probar también con caché persistente y sin ella.

## Defectos funcionales y de cumplimiento

Las prioridades P1/P2/P3 de esta sección ordenan correcciones; no son severidades de vulnerabilidades.

### F-01 — P1 — El borrador omite respuestas escritas por el solicitante y declara que no retiene datos

- **Ubicación:** `src/Privacy/PrivacyPolicy.php:152,190,275,298`; tabla en `src/Database/ColaboracionesTable.php:67`.
- **Flujo:** las propuestas se vinculan solo a `usuario_emisor_id`; no se registra quién escribe `respuesta_mensaje`. Exportar/borrar busca exclusivamente al emisor. El coordinador receptor puede haber escrito datos personales en su respuesta, pero su solicitud no encuentra esa fila.
- **Evidencia ejecutada:** B respondió a una propuesta de A. Exportar B no incluyó su respuesta; borrarla conservó literalmente `Audit B authored personal response` y devolvió `items_retained=false, done=true`.
- **Impacto:** exportación incompleta y señal de borrado engañosa. El aviso sobre respuestas retenidas solo se produce al borrar propuestas enviadas por el solicitante; no cubre a quien únicamente respondió.
- **Corrección:** añadir identificador de autor de respuesta y migración; exportar y anonimizar sus contribuciones por separado. Para histórico sin atribución, definir y comunicar un procedimiento de revisión, sin atribuir automáticamente una respuesta al coordinador actual. No declarar `items_retained=false` cuando existen datos personales reconocidos que no se borran.
- **Regresión:** usuario solo receptor, solo emisor, ambos, cambio de coordinador y más de 100 filas. Referencia: [borradores de datos de WordPress](https://developer.wordpress.org/plugins/privacy/adding-the-personal-data-eraser-to-your-plugin/).

### F-02 — P1 — Tras borrar el contacto, se vuelve a obtener el email personal desde el autor

- **Ubicación:** `src/Privacy/PrivacyPolicy.php:258` y `src/Emails/NotificationService.php:33–46`; uso en `src/Admin/ColaboracionesAdminPage.php:189`.
- **Flujo:** el borrador elimina nombre/email de coordinación, pero no registra que el contacto fue retirado. `get_center_contact_email()` interpreta esa ausencia como razón para usar `post_author.user_email`. Los avisos y los enlaces de contacto de matches aceptados vuelven a usarlo.
- **Evidencia ejecutada:** tras borrar los datos de B, `resolved_contact_after_erase=author-b@example.invalid`.
- **Impacto:** retirar los datos de contacto no detiene su uso en el plugin. No se propone borrar la cuenta de WordPress como remedio.
- **Corrección:** distinguir «sin contacto configurado» de «contacto retirado por privacidad», con un estado persistido que desactive el fallback y los envíos; documentar qué identidad institucional se conserva. Probar aceptación/listado después del borrado.

### F-03 — P1 — El importador puede sobrescribir una radio real por coincidencia de URL

- **Ubicación:** `src/Admin/DemoDataImporter.php:216–245,299–304`.
- **Flujo:** un slug coincidente y la misma URL del podcast bastan para tratar una radio sin marcador demo como propiedad del importador. La URL es editable por coordinadores; la afirmación del comentario «only this importer writes» es falsa. Se sustituyen título, descripción, contenido, taxonomías, disponibilidad y otros datos.
- **Evidencia ejecutada:** ficha manual sin marcador, slug de Sierra de Gata y URL coincidente. Importar reemplazó el título, eliminó su contenido original y añadió `_radioedu_conecta_demo=1`.
- **Impacto:** pérdida de contenido legítimo al pulsar un botón que la documentación promete que nunca sobrescribirá centros reales. Requiere una acción administrativa legítima; no se clasifica como escalada de permisos.
- **Corrección:** actualizar automáticamente solo registros con propiedad demostrada por marcador/registro de importación. La adopción de registros antiguos debe ser explícita y mostrar las diferencias antes de sobrescribir. La heurística de adopción de páginas de `:278` necesita el mismo tratamiento.
- **Regresión:** ficha manual con slug y URL coincidentes debe conservarse; ficha demo marcada debe seguir actualizándose.

### F-04 — P1 — Una migración fallida se marca como completada

- **Ubicación:** `src/Database/ColaboracionesTable.php:87–89`; reintento condicionado en `src/Plugin.php:144`.
- **Flujo:** se llama a `dbDelta()` y se escribe incondicionalmente la versión. Si DDL falla, `maybe_update_db_schema()` ya no reintenta porque la opción afirma que el esquema está actualizado.
- **Evidencia ejecutada:** fallo DDL simulado con filtro `query` en base desechable: la tabla no existe, pero la versión persistida es `1.0.2`. Se recreó después. Es inyección controlada de fallo, no un error observado en la instalación normal.
- **Impacto:** propuestas y listados rotos tras falta de permisos DDL o fallo de base de datos; recuperación automática bloqueada.
- **Corrección:** comprobar tabla, columnas e índices requeridos tras dbDelta; persistir versión únicamente con postcondiciones satisfechas. Devolver/reportar WP_Error y permitir reintento seguro; no exponer SQL al visitante.

### F-05 — P2 — El historial de colaboraciones termina en 100 registros sin forma de consultar el resto

- **Ubicación:** `src/Database/ColaboracionesTable.php:142–169`, `src/Rest/ColaboracionesController.php:64,208` y `src/Admin/ColaboracionesAdminPage.php:133`.
- **Evidencia ejecutada:** con 102 filas visibles, GET devuelve 100; `page=2` devuelve los mismos IDs. Admin usa la misma función y no contiene controles de paginación.
- **Impacto:** propuestas antiguas, incluso pendientes, desaparecen de los flujos normales de consulta/respuesta. No hay contador de truncado.
- **Corrección:** declarar `page/per_page` validados, count con el mismo predicado de autorización, orden estable `created_at DESC, id DESC` y paginación admin. No sustituir el límite por una consulta sin límites.

### F-06 — P2 — Los centros gestionados se recortan silenciosamente y la UI pierde centros autorizados

- **Ubicación:** `src/Rest/ColaboracionesController.php`, método `get_user_managed_center_ids()` (50 por autor/email; 100 para gestores globales); uso en directorio y listados de propuestas.
- **Evidencia ejecutada:** autor con 53 centros publicados: listado de 51 (50 por autor y uno adicional por email); 2 omitidos pese a `user_can_manage_center=true`.
- **Impacto:** selector de emisor incompleto y propuestas recibidas por centros omitidos ausentes de los listados. Los límites de 50/100 son de consulta, no límites del producto: el ajuste por defecto permite centros ilimitados.
- **Corrección:** selector con búsqueda/paginación; para autorizar listados de propuestas, consultar relación/autoría de cada participante en SQL o recorrer todos los IDs en lotes, sin usar una primera página como conjunto completo de autorización.

### F-07 — P2 — Un error SQL al responder se presenta como propuesta ya respondida

- **Ubicación:** `src/Database/ColaboracionesTable.php:184–206` y `src/Rest/ColaboracionesController.php:519`.
- **Evidencia:** `extended-checks.txt`, fallo UPDATE inyectado solo en la base desechable: respuesta `radioedu_already_resolved`/409, mientras el estado persistido sigue `pendiente`.
- **Causa:** `resolve_pending()` reduce error SQL y cero filas actualizadas al mismo booleano.
- **Corrección:** distinguir error de almacenamiento (500, reintentable y registrado sin PII) de conflicto de estado (409); mantener el UPDATE condicional que protege las respuestas repetidas.

### F-08 — P2 — Se promete una notificación aunque esté desactivada o falle

- **Ubicación:** `src/Rest/ColaboracionesController.php:422–430`, `src/Emails/NotificationService.php`, métodos `send_proposal_email()` y `send_status_update_email()`.
- **Evidencia:** con `notify_on_proposal=0`, POST 201 afirma «La emisora receptora recibirá una notificación». El retorno de correo no se incorpora al resultado. Un `wp_mail()` verdadero tampoco garantiza entrega.
- **Corrección:** separar propuesta registrada de notificación solicitada/enviada/fallida, usar texto fiel al estado y considerar cola/reintento si el correo es parte esencial del flujo. Evitar duplicar la propuesta al reintentar la notificación.

### F-09 — P2 — La afinidad del administrador usa una radio arbitraria pese a la exclusión prevista

- **Ubicación:** `src/Rest/CentrosController.php:145–155,215–224`.
- **Evidencia:** administrador sin `afines_a`; radios [A referencia, B sin relación, Z relacionada] se ordenan [A,Z,B]. `get_items()` deja referencia 0 para gestores globales, pero el helper vuelve a seleccionar el primer centro gestionado.
- **Impacto:** prioridad de resultados condicionada por las etiquetas de la primera radio alfabética, sin selección del gestor. El test actual usa radios sin etiquetas, por lo que no detecta el defecto.
- **Corrección:** resolver referencia una sola vez; 0 debe representar ausencia de referencia, sin fallback oculto. Añadir test con etiquetas distintas.

### F-10 — P2 — Una ficha real puede recibir imágenes ficticias por coincidir parcialmente su slug

- **Ubicación:** `src/Admin/DemoDataImporter.php`, `get_demo_image_url()`; `src/Rest/CentrosController.php:269–276` y equivalente del perfil.
- **Evidencia:** ficha `unrelated-san-jose`, sin marcador demo ni imagen, recibe `assets/images/demo/logo-san-jose.jpg` en la API. La búsqueda usa fragmentos de slug sin verificar propiedad demo.
- **Impacto:** identidad visual incorrecta para radios reales. Las imágenes son ilustrativas, según `assets/images/demo/README.txt`.
- **Corrección:** limitar fallback a fichas explícitamente marcadas; usar placeholder neutro para el resto. Definir también una forma de quitar expresamente un logo demo/sincronizado: guardar logo_id=0 vuelve a activar el fallback.

### F-11 — P1 — Una respuesta de envío atrasada mezcla el ID del destinatario antiguo con el nombre del nuevo

- **Ubicación:** `assets/js/directorio.js:805–846`, apertura/cierre `:581–651`.
- **Reproducción en navegador:** abrir propuesta hacia A (ID 4), enviarla, demorar la respuesta, cerrar panel, abrir B (ID 5), completar respuesta de A. El campo visible muestra «Audit B» pero el hidden recupera 4. Evidencias `browser-checks.json` y `drawer-race.png`.
- **Método:** Playwright, interfaz real y bundle versionado; se interceptó únicamente la respuesta de creación con un 201 simulado para controlar el tiempo, sin enviar correos ni propuestas reales.
- **Impacto:** la siguiente propuesta puede dirigirse a A cuando el usuario cree que escribe a B, con riesgo de compartir su texto con otro centro. También se resetea el borrador nuevo.
- **Corrección:** vincular cada solicitud a una instancia/versión del formulario, ignorar respuestas de instancias cerradas o reemplazadas, y mantener juntos ID y etiqueta del destinatario. Cancelar temporizadores de cierre anteriores al reabrir. No basta con deshabilitar el botón de enviar.
- **Regresión:** enviar→cerrar→abrir otro→resolver primera; conservar nuevo destinatario y borrador. Probar éxito/error y cierre/reapertura rápida.

### F-12 — P2 — El mapa muestra una fracción de los resultados sin permitir paginar

- **Ubicación:** `assets/js/directorio.js:329–377,902,927–940`.
- **Evidencia ejecutada:** 60 radios públicas con coordenadas, 24 tarjetas iniciales; mapa con 24 marcadores, contador «60 centros» y botón «cargar más» oculto.
- **Corrección:** consulta de marcadores por límites geográficos con paginación/agrupación o carga por lotes explícita; comunicar cuántos centros se muestran. No resolver descargando sin límites todos los registros.
- **Control positivo:** cero peticiones a teselas antes del consentimiento. Tras aceptarlo se intentan cargar teselas; las pruebas las bloquearon para no transmitir datos al servicio externo. Los PNG de marcadores sí están incluidos en el paquete.

### F-13 — P2 — El directorio no se reajusta a 320 píxeles de ancho

- **Ubicación:** `assets/css/directorio.css:285–310` (controles y `minmax(320px,1fr)`).
- **Evidencia:** Twenty Twenty-Five, viewport 320, ancho de documento 366, contenedor de tarjetas 228 y tarjeta 320. Imagen `mobile-320.png`: controles y tarjetas recortados horizontalmente.
- **Impacto:** lectura/acciones requieren desplazamiento lateral en móvil y al ampliar. Afecta al requisito de reflow de WCAG 1.4.10; no constituye auditoría WCAG completa.
- **Corrección:** columnas con mínimo acotado al contenedor, controles que se apilen y botones/textos que se ajusten. Verificar 320 CSS px y zoom 400 %.

### F-14 — P2 — La navegación de vuelta depende de un slug fijo

- **Ubicación:** `src/Frontend/DirectorioShortcode.php:511–512`.
- **Evidencia estática:** el enlace del perfil busca exclusivamente `traspasando-fronteras`; no usa `radioedu_conecta_directory_page_id` ni detecta la página elegida para el bloque. Si no existe devuelve la portada. Las instrucciones permiten insertar el directorio en cualquier página.
- **Corrección:** opción de página canónica validada, con fallback y filtro documentado; comprobar página movida, slug cambiado, bloque y shortcode.

### F-15 — P2 — El escapado del contenido ya renderizado elimina reproductores embebidos

- **Ubicación:** `templates/single-centro.php:153`, filtro de contenido en `src/Frontend/DirectorioShortcode.php:58,514`.
- **Evidencia estática y prueba de función:** el perfil recibe el HTML después de los filtros de bloques/embeds y aplica `wp_kses_post()` al conjunto. Una figura con iframe de reproductor queda como `<figure></figure>` en la prueba local. No se contactaron proveedores externos para probar cada embed.
- **Impacto:** bloques/shortcodes que generan iframes autorizados por Core pierden el reproductor en la ficha.
- **Corrección:** separar contenido editorial validado por Core de datos propios escapados; definir política segura de embeds y compatibilidad. No añadir una allowlist ilimitada de iframes ni retirar validación de entradas.

### F-16 — P3 — Sin JavaScript el directorio comunica cero centros aunque sí existen

- **Ubicación:** `templates/directorio.php`, resultados iniciales; `DirectorioShortcode::render_shortcode()`.
- **Evidencia ejecutada:** navegador con JS desactivado, 60 radios publicadas: cero tarjetas y «Resultados (0 centros)». No hay listado básico ni aviso noscript.
- **Corrección:** render inicial paginado y navegación básica, o aviso claro de dependencia de JS y de error de carga. Ajustar la documentación sobre degradación sin build.

### F-17 — P1 para distribución — Falta el texto de licencia exigido por Leaflet

- **Ubicación:** `assets/js/leaflet.js:1–8`, `assets/css/leaflet.css:1–5`, `readme.txt:42`, ZIP existente.
- **Evidencia:** se conserva nombre BSD-2-Clause, autores y enlace, pero no aparecen condiciones ni descargo; no existe LICENSE/NOTICE de Leaflet en fuentes ni en los 100 ficheros del ZIP.
- **Corrección:** distribuir la licencia íntegra de Leaflet 1.9.4 junto a JS, CSS e imágenes; mantener avisos y enlazar ese fichero desde readme. La licencia es compatible; el defecto es la ausencia de sus avisos obligatorios. [Licencia original](https://raw.githubusercontent.com/Leaflet/Leaflet/v1.9.4/LICENSE), [directrices WordPress.org 1 y 2](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/).

### F-18 — P2 — La caché de cupo mezcla sitios dentro de una misma petición

- **Ubicación:** `src/Capabilities/CentroLimit.php:43–47,178–204`.
- **Evidencia Multisite:** mismo autor/límite 1, sitio principal sin centros y subsite con uno: primera consulta false, tras switch sigue false; vaciar caché devuelve true. Se restaura correctamente el contexto al terminar.
- **Alcance:** requiere comprobar cupo en varios sitios dentro de la misma petición (integraciones/lotes); no demuestra que la UI ordinaria de una sola petición por sitio permita por sí sola cruzar el cupo.
- **Corrección:** clave `blog_id:user_id:limit` y estrategia explícita de invalidación; test que cambie de sitio en ambos sentidos.

### F-19 — P2 — Dos sincronizaciones concurrentes crean dos fichas del mismo sitio

- **Ubicación:** `src/Network/NetworkSync.php:194–242,376–425`.
- **Evidencia:** intercalado determinista mediante `wp_insert_post_data` entre búsqueda e inserción; ambos sync_site del mismo blog devuelven `created`, IDs 85 y 86, ambos con el mismo SOURCE_META. La sincronización secuencial sí es idempotente.
- **Impacto:** directorio duplicado y propuestas/contactos repartidos en fichas diferentes; dos pestañas o dos gestores pueden iniciarlo. No es escalada de privilegios.
- **Corrección:** reserva exclusiva por red/sitio con caducidad segura, o relación única persistente; reconsultar dentro de la sección protegida. Reconciliar duplicados existentes sin borrar conversaciones arbitrariamente.

### F-20 — P3 — La desinstalación conserva preferencias del editor creadas por el plugin

- **Ubicación:** `assets/js/centro-panel.js`, preferencia `radioedu-conecta/centroPanelOpened`; `uninstall.php` sin limpieza de ese namespace en usermeta.
- **Evidencia:** tanto en sitio único como en Multisite quedan dos registros de preferencias que contienen `radioedu-conecta`, correspondientes a los dos usuarios E2E. Centros, opciones, capacidades de roles y tablas de sitios sí se eliminan en las muestras.
- **Corrección:** retirar únicamente las claves del plugin dentro de las preferencias persistidas de Core, por lotes y preservando las de otros editores/plugins. Ajustar la promesa «sin residuos».
- **Nota de entorno:** `wp_pc_radioedu_colaboraciones` es una tabla de la instalación temporal de Plugin Check, no un sitio normal de la red; se elimina con todo el volumen de auditoría y no se usa como evidencia de un fallo de uninstall.

### F-21 — P2 — El informe de fichas huérfanas ignora silenciosamente las posteriores a 1.000

- **Ubicación:** `src/Network/NetworkSync.php:51,264–280`.
- **Evidencia estática:** `MAX_ORPHAN_CHECK=1000`, consulta sin cursor/página y sin indicación de truncado. Aunque la sincronización avanza en lotes, el informe final solo inspecciona una página fija de fichas.
- **Corrección:** paginar la detección de huérfanas con cursor estable y acumular/consultar resultados; mostrar progreso y cobertura. No se creó una red de más de 1.000 sitios para medir tiempos.

### F-22 — P2 de requisitos — No hay política de retención definida para propuestas

- **Ubicación:** `src/Privacy/PrivacyPolicy.php:38–54`, `readme.txt`, modelo de colaboraciones.
- **Evidencia:** se documentan datos y destinatarios, pero no plazo/criterio de conservación ni mecanismo de purga. Solo se borran/anonimizan por solicitud o al desinstalar. Incumple el requisito explícito de retención de AGENTS.md; no es por sí solo un dictamen de infracción legal.
- **Corrección:** acordar finalidad/plazo con el responsable del tratamiento, documentarlo y aplicar una limpieza por lotes si procede, respetando conversaciones compartidas y obligaciones de conservación.

## Candidatos que necesitan validación

| Candidato | Hecho pendiente | Validación propuesta; sin severidad asignada |
| --- | --- | --- |
| Reutilización de ficha manual por URL de subsite | El sincronizador conserva al autor de una ficha con URL coincidente aunque difiera del administrador de origen. La reutilización está documentada; falta determinar si una ficha sincronizada debe acreditar oficialmente la identidad del centro | Fijar política de titularidad; probar preexistencia de ficha con autor distinto y requerir revisión de adopción si el vínculo concede confianza institucional |
| Redes múltiples | Uninstall recorre sitios sin filtrar red, pero elimina `radioedu_conecta_sync_grant_author` solo de la red actual | Probar dos network_id; verificar limpieza de opciones de cada red y política de alcance. No se desplegó un fixture multi-network |
| Sitios con privacidad adicional | Sync excluye archivados/spam/borrados, pero no reglas de plugins de acceso privado | `public=0` por sí solo no equivale a sitio privado en Core. Solo evaluar fuga con el mecanismo real de privacidad instalado; no se confirmó una |
| Consumo de recursos a gran escala | Consultas de afinidad correlacionadas, términos sin límite y tareas de red/uninstall síncronas | Medir volumen real, caché e índices; no llamar DoS a un coste potencial sin reproducción |

## Candidatos descartados

- **IDOR básico de propuestas:** autor ajeno no puede usar otro emisor ni responder como receptor; 403 en casos ejecutados. Subscriber con contacto coincidente tampoco tiene la capacidad básica requerida según código/tests.
- **CSRF REST por falta de nonce propio en controller:** la autenticación cookie de Core se ocupa del nonce. Con cookie y payload válido, sin nonce devuelve 401; nonce incorrecto 403. Las contraseñas de aplicación tienen otro mecanismo de autenticación y no necesitan ese nonce.
- **SQLi por IN dinámico:** las interpolaciones contienen únicamente placeholders generados internamente y todos los valores pasan por prepare; identificadores con %i. Los ignores de esas líneas tienen justificación concreta.
- **XSS DOM directo en tarjetas/mapa:** datos pasan por textContent/createElement y URLs limitadas a http/https; no se encontró flujo ejecutable desde esas entradas. SVG registrados son locales y pasan allowlist.
- **Filtración pública del email del coordinador:** omitido en API propia y meta restringida en Core; muestra pública sin contactos, acceso edit ajeno denegado. F-01/F-02 corresponden a otra frontera.
- **Respuesta duplicada/replay de aceptación:** UPDATE condicionado a pendiente; segundo intento 409. F-07 trata la confusión con error SQL, no una repetición aceptada.
- **Carga de OSM antes de consentimiento:** cero conexiones a teselas en navegador antes de aceptar. Las imágenes de marcadores existen; no hay un fallo por ausencia de esos archivos.
- **Asignación del rol Autor en sync como escalada automática no autorizada:** requiere superadministrador y una opción visible, documentada y desactivable. No se calificó como vulnerabilidad por sí misma.
- **Admin de sitio con sync 200:** el primer fixture `audit_e2e` se convirtió en superadministrador durante `multisite-convert`. Una cuenta nueva, sin privilegios de red, devuelve 403 (`multisite-extra.txt`). El 200 inicial no prueba bypass.
- **Primer E2E fallido como regresión del plugin:** el 404 era `/favicon.ico`; la ficha del otro test quedaba tras las primeras 24. Con fixture limpio ambos tests pasan sin modificar sus fuentes.

## Recomendaciones de endurecimiento

- Usar un cursor estable `(fecha,id)` o `id` donde se pagina; algunos listados solo ordenan por fecha/título. El offset de sync puede saltarse sitios si el conjunto cambia entre lotes.
- Añadir identificador de idempotencia a creación de propuesta para evitar duplicados tras timeout/reintento; conservar la atomicidad ya existente de respuesta.
- Separar favoritos por sitio (`blog_id` o URL canónica): con subdirectorios, la clave actual `radioedu_favoritos` comparte IDs entre sitios del mismo origen. Ofrecer retirada del consentimiento del mapa y documentar que dura la sesión.
- Reducir JS/mapa cargado cuando no se usa, respetar `prefers-reduced-motion`, probar foco con teclado y lector de pantalla. La trampa de foco existe; la auditoría visual no certifica WCAG completa.
- En admin, rechazar una respuesta demasiado larga de manera explícita en lugar de truncarla con mb_substr; comprobar consistencia con el rechazo 400 de REST.
- Paginar consultas masivas de términos en render/uninstall y considerar tareas en segundo plano para redes grandes; introducir índices compuestos solo tras medir consultas reales.
- Fijar versiones reproducibles de entorno/CI y revisar el helper `bin/install-wp-tests.sh`: usa HTTP para resolver `latest`, no verifica hash de descargas, usa rutas no entrecomilladas en rm y reutiliza una instalación existente sin verificar versión. Es herramienta de desarrollo excluida del ZIP; no un sumidero remoto del plugin.
- Ampliar E2E más allá de los dos casos actuales: envío/respuesta, filtros/paginación, errores/reintentos, privacidad, cuota, mapa y sincronización. Los tests PHP se leyeron íntegramente; varios llaman helpers/dispatcher y no prueban autenticación HTTP real.

### Desviaciones frente a AGENTS.md que no debe ocultar un linter verde

| ID | Requisito local / situación | Tratamiento recomendado |
| --- | --- | --- |
| Q-01 | Idioma base/comentarios en inglés; gran parte del código y sus cadenas están en español, decidido en README/arquitectura | Alinear explícitamente el contrato del proyecto o planificar migración de cadenas; no es prohibición general de WordPress.org |
| Q-02 | Registro de bloques mediante block.json; implementación PHP-only `supports.autoRegister` | Documentar excepción del proyecto o migrar al formato exigido. La API PHP-only sí está soportada por Core y el E2E pasa |
| Q-03 | Prefijo exacto radioedu_conecta_ para hooks/opciones; existen identificadores históricos `radioedu_*`, y PHPDoc @since no es uniforme | Inventariar excepciones y compatibilidad antes de renombrar; no romper meta/rutas existentes por limpieza cosmética |
| Q-04 | PHPCompatibilityWP y PHPStan sin errores, PHPUnit y matriz completa exigidos | No validados en esta sesión por dependencias ausentes; no declarar terminado un parche con esos controles pendientes |
| Q-05 | PHP 8.1 figura como mínimo y entorno local | Mantener compatibilidad mínima no implica recomendarlo para producción: está fuera de soporte upstream; añadir pruebas/recomendación para PHP con soporte |
| Q-06 | Paquete limpio | El ZIP existente está limpio de dev deps, pero futuros builds deben excluir esta carpeta de auditoría y ficheros de entorno; `.distignore` no lo contempla |

Los ignores de SQL, marcado de cabecera de tema, argumentos heredados y carga de traducciones fueron trazados. Sus justificaciones tienen sentido en esos lugares; un ignore de consulta «bounded» no elimina el coste del filtrado previo al LIMIT. No se eliminaron ni añadieron ignores.

## Verificaciones realizadas

| Verificación | Resultado y alcance | Evidencia |
| --- | --- | --- |
| WPCS WordPress/Extra/Docs | 28/28 PHP, exit 0; configuración copiada omitiendo solo PHPCompatibilityWP ausente | phpcs-wpcs.txt |
| Plugin Check 2.1.0 | Exit 0, No errors found, copia aislada activa; HTTP externo bloqueado | pcp-runtime.txt |
| JS / CSS lint | Ambos exit 0 | lint-js.txt, lint-css.txt |
| Build aislado | Exit 0; ocho archivos idénticos a build versionado | build.txt, build-comparison.json |
| E2E existentes | 2/2 pasan, 25,9 s con fixture limpio; primera ejecución conserva sus dos fallos de entorno/datos | e2e-clean.txt, e2e.txt |
| Composer/npm audit | Cero advisories; Composer sin abandoned. Primer intento de red de Composer falló y el autorizado terminó bien | composer-audit-escalated.txt, npm-audit.json |
| Runtime REST/privacidad/límites | Casos negativos, errores inyectados y reproducciones descritas | runtime-checks.txt, extended-checks.txt |
| Navegador | Mapa, modal, 320px, sin JS; cookie sin nonce 401 y nonce inválido 403 | browser-checks.json y PNG |
| Multisite/lifecycle | Red/por sitio, nuevos sitios, contexto restaurado, tabla al borrar sitio, deactivación conserva datos; F-18/F-19 | multisite-checks.txt, multisite-extra.txt |
| Uninstall | Sitio único y red: limpia CPT/opciones/roles/tablas de sitios; preferencias residuales F-20 | uninstall-single.txt, uninstall-multisite.txt |
| Traducciones | POT 444 mensajes + cabecera; sin faltantes/obsoletos. 4 PO/MO completos y coherentes con 16 JSON y 4 catálogos .l10n.php | pot-comparison.json, translations-check.json, l10n-check.txt |
| ZIP existente | 100 archivos; todos coinciden con fuentes; sin dev deps | archive-comparison.csv |
| Depuración | Tres warnings de Core por comprobaciones de actualización bloqueadas por el guard HTTP; ninguno atribuible al plugin en el registro capturado | wp-debug.log |
| PHPStan / PHPUnit / PHPCompatibilityWP | **No ejecutados**: faltan paquetes dev y test library. Los intentos Composer fallan por ejecutable ausente | phpstan.txt, phpcs.txt |

Las reproducciones por intercalado simulan carreras de forma determinista; no son benchmark ni prueba simultánea de varios procesos HTTP. Los fallos DDL/UPDATE se provocaron deliberadamente en la copia desechable. No se enviaron correos reales ni se modificaron los sitios de trabajo 8090/8091. Un fallo temporal del servicio de revisión automática de permisos se resolvió al continuar; no quedó acción bloqueada por ese incidente. Se retiraron los dos contenedores, volumen y redes temporales; `evidencias/cleanup.txt` registra los nombres.

### Compatibilidad contrastada en fuentes oficiales

El mínimo declarado WP 7.1 es coherente con el uso de la API pública de iconos. Se probó 7.1.4, no toda la matriz. [API de iconos 7.1](https://make.wordpress.org/core/2026/07/24/registering-and-rendering-svg-icons-in-wordpress-7-1/), [registro PHP de bloques](https://developer.wordpress.org/block-editor/getting-started/fundamentals/registration-of-a-block/).

WordPress 7.1 admite PHP 7.4 como mínimo del Core; el plugin eleva su mínimo a 8.1. La compatibilidad sintáctica no sustituye al soporte de seguridad: PHP 8.1 ya no aparece entre ramas soportadas. Hosting recomienda 8.4/8.5 para nuevas instalaciones de WP 7.1. [Compatibilidad oficial 7.1](https://make.wordpress.org/hosting/handbook/compatibility/version/7-1/), [ramas de PHP soportadas](https://www.php.net/supported-versions.php).

## Cobertura y limitaciones

Consultar `COBERTURA.md`. Perfil: profundo, sin certificación de seguridad ni aprobación de publicación implícita. Toda prueba no ejecutada se declarará expresamente.

## Continuidad y correcciones posteriores

Leer primero las prioridades de este informe, después `COBERTURA.md` y `ANEXO-ARQUITECTURA-Y-PRUEBAS.md`. Cada hallazgo tiene causa, evidencia, alcance y solución propuesta. Antes de implementar, presentar arquitectura y migraciones según AGENTS.md; añadir las regresiones correspondientes y completar controles pendientes. Esta entrega es una auditoría, no una corrección del plugin.

## Seguimiento de correcciones (2026-11-01)

Claude (asistente de programación) hizo las correcciones a petición del responsable del proyecto, en la rama `feat/wp71-apis`. Cada hallazgo tiene:

- su propio commit con test de regresión;
- su entrada en el changelog de `readme.txt` y `README.md`;
- cambios en `ARCHITECTURE.md` y `TESTING-PROTOCOL.md` cuando procedía.

Las cadenas nuevas o modificadas se tradujeron al catalán, valenciano, gallego y euskera. Las correcciones están en la rama `feat/wp71-apis`, que todavía no se ha fusionado con `main`. El ZIP `radioedu-conecta_1.1.zip` se regeneró con el código de `5b36de5`.

**Resumen:**

| Grupo | Total | Resultado |
| --- | --- | --- |
| Hallazgos de la auditoría (S-01, S-02, F-01 a F-22) | 24 | 22 corregidos (S-02 incluido, con el límite a 20 por hora); F-02 y F-22 cerrados por decisión documentada |
| Desviaciones frente a AGENTS.md (Q-01 a Q-06) | 6 | Q-02 y Q-06 corregidas; Q-01 con la norma ajustada; Q-03 con excepciones inventariadas; Q-04 ejecutada; **Q-05 pendiente** |
| Segunda revisión (R-01 a R-05) | 5 | Los 5 corregidos |
| Verificación reiniciada (S-01, F-04, F-06, varias redes, cursor, ZIP) | 6 | Corregidos, salvo el cursor, que se rectificó en la documentación. S-02 también se corrigió después |
| Candidatos por validar | 4 | El de varias redes, confirmado y corregido; los otros 3, sin abordar |
| Recomendaciones de endurecimiento | 8 | Sin abordar, salvo parte de la de E2E (ver «Pendiente») |

### Ajustes a la revisión inicial

Antes de corregir se contrastó el informe con el código. Los 24 hallazgos se confirmaron, con estos ajustes:

- **Prioridades.**
  - F-10 pasó a P1: la coincidencia por fragmento de slug (`san-jose`, `la-mar`) alcanza a centros reales creados por la sincronización de red.
  - F-02 dejó de tratarse como defecto. El correo que se usa es el de la cuenta de WordPress del autor, que el borrador de datos de WordPress no elimina, y el responsable decidió mantenerlo.
  - F-11 se trató como P1, aunque solo ocurre si se cierra y reabre el panel mientras el envío está en curso.
- **Contexto que faltaba.**
  - F-03: las URL de podcast de las demos usan el dominio real `radioedu.educarex.es`, lo que hace más probable la colisión con una ficha real.
  - F-06: el caso realista no es un autor con 53 radios, sino un editor en una red con más de 100.
- **Referencias de línea erróneas.**
  - F-07 apuntaba a `ColaboracionesController.php:519`; era la 505.
  - F-04 apuntaba a `Plugin.php:144`; la comprobación está en las líneas 129–131.
  - F-10 apuntaba a `CentrosController.php:269–276`; eran las 262–268.
- **Evidencias.** 26 ficheros de `evidencias/` estaban en UTF-16 LE, por la redirección de PowerShell, y Git los trataba como binarios. Se convirtieron a UTF-8 sin BOM. Una conversión de vuelta a UTF-16 LE reproduce cada original byte a byte, así que el contenido no cambia. Siguen en UTF-8 con BOM, que Git lee como texto: `archive-comparison.csv`, `build-comparison.json`, `inventario.csv` y `phpcs-audit.xml`.
- **Herramientas (Q-04).** PHPStan, PHPUnit y PHPCompatibilityWP, que la auditoría no pudo ejecutar, se pasaron sobre `dc245a9` con las herramientas de QA locales. No dieron errores.

### Estado por hallazgo

| ID | Prioridad | Estado | Commit | Verificación |
| --- | --- | --- | --- | --- |
| Q-06 | Distribución | Corregido | `fbb1942` | `.distignore` excluye `docs`, `.env` y `.env.*`; comprobado en la copia distribuible |
| F-17 | P1 | Corregido | `64bf6a8` | `assets/leaflet-LICENSE.txt` con la licencia de la etiqueta v1.9.4 |
| F-10 | P1 (revisada) | Corregido | `c677eb9` | `test_demo_images_only_fill_in_for_marked_demo_centros` |
| F-03 | P1 | Corregido | `9fdb109` | Tres tests en `SecurityRegressionTest`: demo de 1.0.0, ficha real con slug y URL de demo, página existente |
| F-04 | P1 | Corregido | `ca97e73`, `bb0c834` | `test_a_failed_schema_update_is_not_recorded_and_is_retried`; migración 1.0.2 → 1.0.3 sobre una tabla real en el entorno de QA. Los índices se exigen desde V-01. |
| F-01 | P1 | Corregido | `164d53b` | `PrivacyRepliesTest` |
| F-07 | P2 | Corregido | `6e58016` | `test_a_storage_error_while_answering_is_not_reported_as_already_answered` |
| F-11 | P1 | Corregido | `183c697` | Navegador con respuesta retrasada: falla con el build anterior y pasa con el nuevo |
| F-02 | P1 → decisión | Se mantiene; documentado | `d78e419` | — |
| F-22 | P2 → decisión | Sin purga automática; documentado | `53bcda0` | Párrafo de conservación en el texto sugerido de la política de privacidad |
| S-01 | Baja | Corregido | `8810283`, `0db0982`, `0814b22` | Tres tests en `CentroLimitTest`; los dos de evasión fallan sin el arreglo. El editor clásico se cubrió después (R-01), y las creaciones simultáneas con un bloqueo por autor (verificación reiniciada). |
| S-02 | Baja | Corregido; límite a 20/h | `8cd26ec`, `5b36de5` | `test_proposals_are_limited_to_twenty_per_hour`. Primero se aceptó como riesgo; después, con un bloqueo por usuario, dos envíos simultáneos con una plaza dan un 201 y un 429 (falla sin el arreglo). |
| F-05 | P2 | Corregido | `3f48467`, `4ca647d`, `aedfd3d` | `ProposalPaginationTest`. Una página enorme se resolvió después (R-05). |
| F-06 | P2 | Corregido | `72f9279`, `4ca647d`, `89f13e1` | `ManagedCentersTest`: 53 centros, email sin distinguir mayúsculas, editores y una red de 1.001 radios en el selector (tope de 2.000). |
| F-08 | P2 | Corregido | `00547fc` | `NotificationResultTest` |
| F-09 | P2 | Corregido | `646d532` | `test_global_managers_get_no_affinity_from_an_arbitrary_centro`; falla sin el arreglo |
| F-12 | P2 | Corregido | `95db313`, `ab17b8a` | Navegador: 66 marcadores para 66 resultados (antes, 24). Los filtros mezclados entre páginas se resolvieron después (R-04). |
| F-13 | P2 | Corregido | `8968551` | Navegador a 320 px con Twenty Twenty-Five y YOOtheme: nada desborda |
| F-14 | P2 | Corregido | `1cb7cec`, `6e6454d` | `DirectoryUrlTest`. Los patrones sincronizados se cubrieron después (R-03). |
| F-15 | P2 | Corregido | `3953f46` | `test_the_profile_keeps_the_embedded_players_of_the_description`; falla sin el arreglo |
| F-18 | P2 | Corregido | `b88ece8`, `eccd3a7` | Test multisitio en los dos sentidos; falla sin el arreglo |
| F-19 | P2 | Corregido | `0042b4e`, `818593d` | Test con una segunda sincronización intercalada; falla sin el bloqueo. El bloqueo pasó a `GET_LOCK` después (R-02). |
| F-21 | P2 | Corregido | `c5d9c28` | Test con 1.001 fichas; falla con la consulta anterior |
| F-16 | P3 | Corregido | `361b008` | Test PHP, y navegador con JavaScript desactivado y activado |
| F-20 | P3 | Corregido | `63d9fbc` | Desinstalación real en el entorno de QA |
| Q-04 | Calidad | Ejecutado | — | Ver «Verificación del estado final» |
| Q-01 | Requisito | Norma ajustada | `da10b82` | AGENTS.md §6: cadenas de origen en español con las cuatro traducciones; identificadores en inglés; comentarios nuevos en inglés |
| Q-03 | Requisito | Excepciones inventariadas | `08a7ee5` | AGENTS.md §1: tabla cerrada de identificadores con el prefijo corto y qué se rompería al renombrarlos; todo lo nuevo usa `radioedu_conecta_` |
| Q-02 | Requisito | Corregido | `040fcc3` | `blocks/directorio/block.json`; test de registro; el editor del entorno de QA muestra el bloque y su vista previa |

Dos commits son correcciones de seguimiento:

- `b88ece8` se commiteó con 4 fallos en la suite multisitio. Los causaba el propio test nuevo, que alteraba las reglas de reescritura de los tests siguientes; `eccd3a7` lo corrige.
- `4ca647d` añade una exclusión justificada para un aviso de Plugin Check en las consultas de F-05 y F-06.

### Matices y riesgos residuales

- **F-01.** Las respuestas anteriores a 1.1 no registran quién las escribió.
  - El borrador las conserva, las declara retenidas y pide revisarlas.
  - El plugin no tiene herramienta para editarlas: habría que hacerlo en la base de datos.
  - El exportador no las incluye.
- **F-02.** Tras un borrado de datos, los avisos y el contacto compartido al aceptar usan el correo de la cuenta del autor de la ficha. Es una decisión del responsable, no una corrección.
- **F-03.** Las demos de 1.0.0 se reconocen por `_radioedu_cover_url`, un metadato interno que solo escribe el importador. Una página del directorio creada por 1.0.0 ya no se adopta como demo, así que la desinstalación la deja.
- **F-04.** La versión solo se guarda con todas las columnas y todos los índices (V-01). El fallo de migración solo queda registrado con `WP_DEBUG_LOG`; no hay aviso en el escritorio. Mientras la base de datos rechace el cambio, se reintenta como mucho cada 5 minutos.
- **F-05.** Tras responder una propuesta desde el escritorio se vuelve a la primera página del listado.
- **F-06.** Hay un cambio de comportamiento: los editores ven todas las propuestas, coherente con que ya podían responderlas. El selector de centro emisor tiene un tope de 2.000, el doble de la red más grande prevista (unas 1.000 radios), y no tiene buscador.
- **F-08.** `sent` significa que `wp_mail()` aceptó el correo, no que llegara. No hay cola ni reintento.
- **F-12.** El mapa muestra hasta 1.000 marcadores, el tamaño de la red más grande prevista, sin agrupación ni carga por zona. Por encima, una nota indica cuántos se muestran.
- **F-13.** Se comprobó a 320 px. No se probó el zoom al 400 % por separado ni se hizo una auditoría WCAG completa.
- **F-14.** Si el directorio está en varias páginas, se guarda la primera que se muestre, mientras siga siendo válida.
- **F-15.** La descripción se imprime como `the_content()`, así que depende de que el core filtre con kses al guardar. Quien tiene `unfiltered_html` puede publicar cualquier HTML, como en el resto de WordPress.
- **F-16.** La alternativa sin JavaScript es un enlace al archivo del tipo de contenido, que genera el tema. No es un directorio paginado con filtros.
- **F-19.** Desde R-02, el bloqueo es un bloqueo con nombre de MySQL/MariaDB (`GET_LOCK`) ligado a la conexión, sin caducidad.
  - Los proxies de base de datos que comparten conexiones entre peticiones, como ProxySQL con multiplexación, deben mantener cada petición en una sola conexión.
  - Las fichas duplicadas que ya existan no se fusionan.
- **F-21.** La revisión completa se hace en la última petición de la sincronización. No se midió el tiempo en redes muy grandes.
- **F-22.** Las propuestas no caducan. El responsable lo decidió de forma provisional («en principio»), y el texto sugerido de la política de privacidad lo indica.
- **S-01.** Las comprobaciones y escrituras simultáneas del mismo autor se serializan con un bloqueo `GET_LOCK` por autor y sitio. Una petición espera hasta 10 segundos a la otra y, si no termina, rechaza el guardado. Con un proxy de base de datos que comparta conexiones aplica la misma condición que en F-19.
- **S-02.** El límite es estricto: la comprobación, el guardado y el contador se hacen bajo un bloqueo `GET_LOCK` por usuario y sitio. Una propuesta espera hasta 10 segundos a otra del mismo usuario y, si no termina, recibe el 429. El correo de aviso se envía ya fuera del bloqueo. Con un proxy de base de datos que comparta conexiones aplica la misma condición que en F-19.
- **Contratos.** Cambian firmas públicas que podría usar un desarrollo externo:
  - `respond_to_proposal()` devuelve el estado del aviso;
  - `get_for_user()` y `count_for_user()` ya no reciben IDs de centros;
  - `NotificationService` devuelve `sent`, `failed` o `disabled`;
  - `get_demo_image_url()` recibe el ID del centro.

  Además, `NetworkSync::LOCK_PREFIX` pasa a ser el prefijo del nombre del bloqueo de base de datos, ya no de una opción.

  También hay novedades:
  - esquema 1.0.3;
  - campo `notificacion` en las respuestas REST de propuestas;
  - `page` y `per_page` en `GET /colaboraciones`;
  - el filtro `radioedu_conecta_directory_url`;
  - `blocks/directorio/block.json`;
  - `NetworkSync::lock_name()` y `CentroLimit::quota_lock_name()`;
  - topes de 2.000 centros en el selector y 1.000 marcadores en el mapa.

### Verificación del estado final

Sobre `5b36de5`, el último commit con cambios de código, en el entorno de QA local (WordPress 7.1.4, PHP 8.1, MariaDB 10.11):

| Verificación | Resultado |
| --- | --- |
| PHPUnit, sitio único | 150 tests: 129 ejecutados sin fallos y 21 omitidos por ser solo de multisitio |
| PHPUnit, multisitio | 150 tests sin fallos (947 aserciones) |
| PHPCS (WordPress, Extra, Docs, PHPCompatibilityWP 8.1+) | Sin errores; PHPCompatibilityWP también sobre `tests/` |
| PHPStan nivel 6 | Sin errores |
| Lint JS y CSS | Sin errores |
| Plugin Check 2.1.0 | Sin errores ni avisos, sobre el plugin instalado desde el ZIP regenerado |
| Pruebas de la verificación reiniciada | Pasan todas: S-01, S-02, F-04, F-06 y varias redes |
| ZIP | `radioedu-conecta_1.1.zip` regenerado: 102 ficheros, idénticos byte a byte a `HEAD`, con la licencia de Leaflet y `block.json`, sin `docs`, `tests` ni `vendor`; instalado en QA desde el propio ZIP |
| Navegador (Edge con Playwright) | Repetidas sobre el código final: F-11 (panel), F-13 (320 px), F-16 (sin JavaScript), Q-02 (bloque en el editor) y R-04 (mapa con 210 radios y filtros). F-12 se comprobó con 66 radios en su commit, y el mapa completo vuelve a cubrirlo R-04. |
| Desinstalación | Real, en el entorno de QA (F-20) |
| Migración | Esquema 1.0.2 → 1.0.3 sobre una tabla real en el entorno de QA (F-01, F-04) |

La suite E2E del propio repositorio (`tests/e2e`) no se ha vuelto a ejecutar en local tras los cambios; la ejecutará la CI. Los scripts de navegador están en `evidencias/seguimiento-2026-11-01/`. Usan el entorno desechable del puerto 8091 y su cuenta de pruebas, y no deben ejecutarse contra otros sitios.

### Segunda revisión: R-01 a R-05

Otra sesión revisó de forma independiente el estado `63d9fbc`; su trabajo está en `docs/auditoria/2026-11-01-revision/`, sin versionar y en curso. Confirmó cinco problemas en las correcciones. Sus propias pruebas (`evidencias/SecondReviewTest.php` de esa carpeta) fallaban con el código de entonces, y R-04 se reprodujo en el navegador. Se corrigieron con un commit cada uno:

| ID | Origen | Problema | Commit | Corrección y verificación |
| --- | --- | --- | --- | --- |
| R-01 | S-01 | El editor clásico publicaba un borrador automático abierto antes de llegar al límite. | `0db0982` | `keep_uncounted_status()` (`wp_insert_post_data`) mantiene el estado sin contar en todo guardado que no venga de REST, y la pantalla de edición muestra el aviso. Pasan su prueba y la nueva de `CentroLimitTest`. |
| R-02 | F-19 | El bloqueo caducaba a los 60 s aunque su petición siguiera viva, y se liberaba sin comprobar quién lo tenía. | `818593d` | Bloqueo `GET_LOCK` ligado a la conexión, sin caducidad. Los tests usan una segunda conexión como otra petición. Pasa su prueba de duplicados. |
| R-03 | F-14 | No encontraba el directorio dentro de un patrón sincronizado. | `6e6454d` | Se abren los patrones sincronizados, hasta cinco niveles y sin bucles. Pasan su prueba y tres nuevas. |
| R-04 | F-12 (y "Cargar más") | Las páginas del mapa y de tarjetas leían los filtros del formulario en ese momento. | `ab17b8a` | Cada búsqueda guarda sus filtros y su orden; una búsqueda nueva cancela el mapa. En el navegador con 210 radios: 210 marcadores; con el build anterior, 100. |
| R-05 | F-05 | Un número de página enorme provocaba un `TypeError`. | `aedfd3d` | Una página posterior a la última devuelve una lista vacía. Pasan su prueba y la nueva de `ProposalPaginationTest`. |

Con el código actual, de las 9 pruebas de su fichero solo falla `test_old_lock_owner_does_not_release_replacement_lock`. Esa prueba exige que una segunda sincronización pueda sustituir un bloqueo «caducado» de la misma petición, algo que el diseño nuevo impide a propósito: el bloqueo ya no caduca y solo lo libera la conexión que lo tiene. Lo que esa prueba quería proteger (que la petición antigua no libere el bloqueo de la nueva) se comprueba en `test_a_slow_sync_keeps_its_lock_until_it_ends`.

Esa segunda revisión seguía en curso al cerrar este seguimiento: puede aportar más hallazgos. Sus cuatro candidatos sin confirmar quedaron así:

- **Comprobación del esquema basada solo en nombres de columnas.** Su propia prueba con un esquema antiguo real pasa.
- **Liberación del bloqueo sin comprobar quién lo tiene.** Confirmado y corregido en R-02.
- **Vías fuera de REST para saltarse el cupo.** Confirmado y corregido en R-01.
- **Fidelidad del estado del aviso cuando se envían varios correos.** Cada acción envía un solo correo, así que no aplica hoy.

### Verificación reiniciada: V-01 a V-03 y cierres parciales

Una tercera revisión independiente (`docs/auditoria/2026-11-01-reinicio/`, sin versionar) verificó el estado `70cba6a`. Confirmó R-01 a R-05 y señaló seis puntos abiertos. Sus pruebas se reprodujeron con el código de entonces y se corrigieron con un commit cada uno:

| Punto | Problema | Commit | Corrección y verificación |
| --- | --- | --- | --- |
| S-01 | Dos creaciones simultáneas con una sola plaza dejaban dos radios. | `0814b22` | Bloqueo `GET_LOCK` por autor y sitio alrededor de la comprobación y la escritura, más reservas en memoria dentro de la misma petición. La prueba de la revisión da 201 y 403 con una sola radio. |
| F-04 (V-01) | Si fallaba solo el índice nuevo, la migración se daba por hecha. | `bb0c834` | `has_current_schema()` exige también los índices (`SHOW INDEX`). Pasan la prueba de la revisión y una nueva que comprueba el reintento. |
| F-06 | El selector omitía las emisoras a partir de la 501. | `89f13e1` | Tope de 2.000, el doble de las unas 1.000 radios previstas. Test con 1.001 radios; pasa la prueba de la revisión. |
| Mapa (F-12) | Con 1.000 radios, el mapa solo dibujaba 500. | `55acf5b` | Tope de 1.000 marcadores; comprobado en el navegador. |
| Desinstalación (V-03) | La opción de red quedaba en la segunda red de una instalación con varias. | `7c9c6d7` | Se borra en todas las redes (`get_networks()`, `delete_network_option()`). Pasa la prueba de la revisión, que se ejecuta fuera de la suite principal porque lanza `uninstall.php`. |
| Documentación (V-02) | Se afirmaba un «cursor estable», pero se pagina por desplazamiento. | `33d18c8` | ARCHITECTURE.md y este seguimiento describen ahora orden determinista con `OFFSET`/`paged`; el cursor estable vuelve a «Pendiente». |
| Distribución | El ZIP era anterior a las correcciones. | — | Regenerado e instalado en QA desde el propio paquete (ver «Verificación del estado final»). El ZIP no se versiona. |

S-02, que se había aceptado como riesgo, se corrigió después a petición del responsable (`5b36de5`) con el mismo mecanismo que S-01: la prueba de la revisión que exige un límite estricto pasa ahora. Quedan pendientes las validaciones de compatibilidad.

### Pendiente

**De la auditoría, sin abordar:**

- Tres de los cuatro candidatos que necesitan validación (el de varias redes se confirmó y corrigió en V-03):
  - titularidad de las fichas reutilizadas;
  - sitios con privacidad adicional;
  - consumo a gran escala.
- La desviación Q-05 frente a AGENTS.md: pruebas y recomendación para versiones de PHP con soporte. Q-01, Q-02 y Q-03 se resolvieron después; ver la tabla de estado.
- Las recomendaciones de endurecimiento:
  - Cursor estable: pendiente. El listado de propuestas y la revisión de fichas huérfanas tienen un orden determinista (fecha e ID, o ID), pero paginan por desplazamiento (`OFFSET`, `paged`), igual que la sincronización por lotes. Si el conjunto cambia entre dos páginas, un elemento puede saltarse o repetirse. Una versión anterior de este seguimiento lo daba por hecho; era inexacto.
  - Clave de idempotencia al crear propuestas: pendiente.
  - Favoritos por sitio y retirada del consentimiento del mapa: pendiente.
  - Carga reducida del mapa, `prefers-reduced-motion` y prueba con lector de pantalla: pendiente.
  - Respuestas demasiado largas en el escritorio, que se truncan en vez de rechazarse: pendiente.
  - Paginación de términos en el render y en la desinstalación: pendiente.
  - Entorno y CI reproducibles, y revisión de `bin/install-wp-tests.sh`: pendiente.
  - Más E2E: parcial. Las pruebas de navegador de este seguimiento no están en `tests/e2e`.

**Derivado de las correcciones:**

- Pasar las comprobaciones de navegador de F-11, F-12, F-13, F-16, Q-02 y R-04 a `tests/e2e` (Playwright), para que la CI las ejecute.
- Automatizar la prueba de desinstalación de F-20 en un entorno desechable.
- Avisar en el escritorio cuando falla la migración (F-04).
- Crear una herramienta para revisar las respuestas sin autor anteriores a 1.1 (F-01).
- Fusionar de forma asistida las fichas duplicadas que ya existan (F-19).
- Revisar el plazo de conservación de propuestas con el responsable del tratamiento (F-22).
- Incorporar las conclusiones finales de la segunda revisión cuando termine, y adaptar su prueba del bloqueo al diseño de R-02.

**Antes de publicar:**

- Revisar el resultado de la CI remota de la rama y fusionar con `main` mediante un PR. El ZIP ya está regenerado con el código de `5b36de5`; si cambia el código, hay que volver a generarlo.
- Pedir una revisión nativa de las traducciones, en especial de las 13 cadenas nuevas o modificadas en este seguimiento:
  - las 12 de las correcciones;
  - la palabra clave «podcast» del bloque.

  Las demás cadenas de `block.json` reutilizan traducciones que ya existían, ahora con contexto.
- Probar la matriz de PHP 8.2–8.5 y WordPress 7.1.0. La CI cubre PHP 8.1 y 8.3 con WordPress 7.1 y la última versión.
- Decidir qué hacer con `docs/auditoria/2026-11-01-revision/`, la carpeta de la segunda revisión, que sigue sin versionar. La de esta auditoría ya está versionada, con las evidencias en UTF-8.
