# Protocolo de Pruebas y Validación: RadioEdu Conecta

Este protocolo describe paso a paso las pruebas funcionales, de seguridad, de base de datos y de compatibilidad necesarias para validar el plugin **RadioEdu Conecta** en el entorno Dockerizado, garantizando el cumplimiento de los estándares de WordPress.org y las directrices de `AGENTS.md`.

---

## 1. Entorno de Pruebas (Docker)

El archivo [docker-compose.test.yml](file:///c:/radioedu-conecta/docker-compose.test.yml) está preconfigurado con:
* **WordPress**: 7.1 sobre PHP 8.2 (Apache). El plugin exige WordPress 7.1 o superior.
* **Base de Datos**: MariaDB 10.11.
* **Puerto asignado**: `8090` (`http://localhost:8090`).
* **Modo Depuración Activo**: `WP_DEBUG=true`, `WP_DEBUG_LOG=true`, `SCRIPT_DEBUG=true`.
* **Montaje en Vivo**: La carpeta local del plugin se monta directamente en `/var/www/html/wp-content/plugins/radioedu-conecta`.

---

## 2. Fase 1: Despliegue y Activación

### Paso 1.1: Iniciar los contenedores
```powershell
docker compose -f docker-compose.test.yml up -d
```

### Paso 1.2: Activar el plugin y sincronizar las 6 emisoras de demostración
1. Accede a `http://localhost:8090/wp-admin` e inicia sesión.
2. En **Plugins → Plugins instalados**, activa **RadioEdu Conecta**.
3. La activación no crea contenido. Carga las **6 emisoras de demostración** (con sus imágenes de cabecera y logotipos) y la página `/traspasando-fronteras/` con el shortcode `[radioedu_directorio]` desde **RadioEdu Centros → Ajustes → "Sincronizar / Cargar las 6 emisoras de ejemplo"**.
4. Puedes repetir la sincronización cuando quieras: solo actualiza los centros de demostración y nunca sobrescribe un centro real, aunque comparta el slug o la URL del podcast. Las imágenes de ejemplo solo aparecen en los centros de demostración: un centro real llamado, por ejemplo, «CEIP San José de Calasanz» no muestra el logo de «CEIP San José».

### Paso 1.3: Verificar Tabla Personalizada y Logs
```powershell
docker exec radioedu-wordpress wp db query "DESCRIBE wp_radioedu_colaboraciones;" --allow-root
```
*Criterio de éxito*: Deben figurar `id`, `centro_emisor_id`, `centro_receptor_id`, `usuario_emisor_id`, `idea_proyecto`, `curso_edades`, `idiomas`, `fechas_aprox`, `estado`, `respuesta_mensaje`, `usuario_respuesta_id`, `created_at`, `updated_at`; y `wp option get radioedu_conecta_db_version` debe devolver `1.0.3`.

---

## 3. Fase 2: Validación del Directorio, Mapa y Ficha de Perfil

| ID | Prueba | Acción | Resultado Esperado |
| :--- | :--- | :--- | :--- |
| **TC-01** | **Carga inicial del directorio** | Abrir `http://localhost:8090/traspasando-fronteras/`. | Se muestran el banner Hero, la barra de filtros, el contador de centros y la cuadrícula de tarjetas con cabeceras, logos circulares, banderas e insignias de colores. |
| **TC-02** | **Filtros facetados y búsqueda** | Filtrar por ubicación, idioma, etapa, temática, formato o texto libre. | La cuadrícula se actualiza asíncronamente vía `GET /wp-json/radioedu-conecta/v1/centros` sin recargar la página. |
| **TC-03** | **Ordenación por Afinidad** | Seleccionar *"Más afines"* en "Ordenar por". | Prioriza los centros disponibles y ordena según coincidencias de taxonomías con el centro del usuario conectado, que aparece el último. Un administrador no tiene centro propio, así que ningún centro se manda al final. |
| **TC-04** | **Vista de Mapa y Consentimiento RGPD** | Pulsar **"Vista de mapa"**. | Antes de conectar con `tile.openstreetmap.org`, se muestra el aviso RGPD. Al pulsar *"Aceptar y mostrar el mapa interactivo"*, se carga el mapa Leaflet con los marcadores y popups construidos con nodos DOM seguros. Hay un marcador por cada resultado con coordenadas, no solo por las 24 primeras tarjetas; con más de 500 resultados, una nota indica cuántos se muestran. |
| **TC-05** | **Ficha de Perfil Individual** | Pulsar **"Ver perfil"** en cualquier tarjeta (ej. `/centro-radioedu/ceip-san-jose/`). | Se muestra la ficha con imagen de cabecera arriba, insignia de disponibilidad, logo circular, nombre, bandera, ubicación, etiquetas de colores, edades, idiomas, botones de acción a la izquierda y descripción debajo. Encima de la ficha solo aparece la cabecera del tema: no se repiten el título, el autor y la fecha ni la imagen destacada (hay un único `<h1>`). |
| **TC-05b** | **Bloque del directorio** | En una página nueva, insertar el bloque **Directorio Traspasando Fronteras** (categoría *Widgets*) y publicarla. | El editor (lienzo en iframe) muestra la vista previa del directorio con sus estilos; en la web funciona igual que el shortcode. No se puede insertar un segundo directorio en la misma página. |
| **TC-05c** | **Iconos SVG** | Revisar los filtros, las tarjetas, la ficha, el panel lateral y **Colaboraciones (Matches)**. | Todos los iconos de interfaz son SVG de la API de iconos (`.radioedu-svg-icon`), heredan el color y llevan `aria-hidden="true"`. Solo las banderas siguen siendo emojis. |
| **TC-05d** | **Datos del centro en el editor** | Editar un centro: en la barra lateral, pestaña **Centro de Radio**, elegir logo, bandera y coordenadas en los paneles **Datos de la emisora**, **Ubicación en el mapa** y **Coordinación**, y pulsar **Guardar**. | No aparece la caja meta al pie del editor. El panel principal se abre solo la primera vez. Tras recargar, los valores siguen ahí y la ficha muestra el logo; el email del coordinador no aparece en la web. |
| **TC-05e** | **Privacidad de los datos en la REST del core** | Pedir `/wp-json/wp/v2/centros` sin sesión, y como coordinador `/wp-json/wp/v2/centros?context=edit`. | Sin sesión, `meta` no contiene ningún campo `_radioedu_*`. El coordinador solo ve los campos de los centros que puede editar, nunca el email de otros coordinadores. |
| **TC-05g** | **Radios por usuario** | En **Ajustes → Coordinadores**, poner *Radios por usuario* a 1. Entrar con un Autor que ya tenga una radio. Probar también con 0 y con el campo vacío. | Con 1, el Autor no ve *Añadir nuevo*, la pantalla de creación responde «no tienes permiso» y el listado muestra el aviso del máximo; al enviar su radio a la papelera, vuelve a poder crear; si entonces crea otra, no puede restaurar la primera desde la papelera (el listado no la restaura y el editor muestra el aviso del máximo). Con 0 no puede crear ninguna. Vacío, sin límite. Un administrador o editor siempre puede crear. Un valor no válido (p. ej. `-1`) muestra un error y mantiene el anterior. |
| **TC-05h** | **Traducciones incluidas** | Instalar el idioma de WordPress y elegirlo en **Ajustes → Generales → Idioma del sitio**, para Català, Valencià, Galego y Euskara (con WP-CLI: `wp language core install ca ca_valencia gl_ES eu` y `wp site switch-language ca`). Abrir el directorio, editar un centro y abrir **Ajustes** del plugin. | Todo el plugin sale en ese idioma: textos de la plantilla, tarjetas y botones que pinta el JavaScript (*Mostra el perfil*, *Afig als preferits*, *Ikusi profila*…), paneles del editor y pantalla de ajustes. Sin errores en la consola ni en `debug.log`. Si no se instala antes el idioma de WordPress, WordPress no deja elegirlo y todo sigue en castellano. |
| **TC-05f** | **Editor Clásico** | Con el Editor Clásico activo para los centros, editar un centro. | Aparece la caja *Datos de la Emisora y Coordinación* con el selector de logo y guarda igual que los paneles. |
| **TC-06** | **Solicitud de Match desde el Perfil** | Pulsar **"✈️ Proponer match"** dentro de la página de perfil individual de la emisora. | Se despliega directamente el panel lateral *"Solicitud de match"* en esa misma pantalla sin necesidad de volver al directorio. |

---

## 4. Fase 3: Flujo Completo de Match y Seguridad (Anti-IDOR / Rate Limit)

| ID | Prueba | Acción | Resultado Esperado |
| :--- | :--- | :--- | :--- |
| **TC-07** | **Envío de Propuesta** | Completar el formulario en el panel lateral y pulsar **Enviar propuesta**. | Devuelve HTTP `201`, muestra confirmación en verde y registra la propuesta en estado `pendiente`. |
| **TC-08** | **Protección Anti-IDOR** | Enviar por REST API un `centro_emisor_id` que no pertenece al coordinador autenticado. | El servidor rechaza la petición con HTTP `403 (radioedu_forbidden_center)`. |
| **TC-09** | **Gestión en WP Admin** | Ir a **RadioEdu Centros → Colaboraciones (Matches)** y pulsar **Aceptar** o **Declinar** con un mensaje. | El estado pasa a `aceptada` o `rechazada`, se envía el correo de notificación al centro emisor (incluyendo el email de contacto si fue aceptada) y se muestra el enlace `mailto:`. |
| **TC-10** | **Rate Limiting de Ventana Fija** | Intentar enviar más de 20 propuestas en menos de 1 hora. | El servidor responde con HTTP `429 (Too Many Requests)` sin reiniciar la ventana de expiración de 1 hora. |
| **TC-11** | **Privacidad RGPD** | Inspeccionar el HTML público y la respuesta JSON de `/wp-json/radioedu-conecta/v1/centros`. | Ningún correo electrónico ni nombre personal de coordinador aparece expuesto públicamente. |
| **TC-12** | **Respuesta única** | Responder (REST `PATCH` o panel de admin) a una propuesta ya aceptada o declinada. | HTTP `409 (radioedu_already_resolved)`, el estado no cambia y no se reenvía ningún correo. |
| **TC-12b** | **Respuesta y datos personales del receptor** | Como coordinador receptor, aceptar una propuesta con un mensaje. En **Herramientas → Exportar datos personales**, exportar sus datos; después, en **Borrar datos personales**, borrarlos. | La exportación incluye la propuesta con su respuesta. Tras el borrado, la respuesta queda vacía y la propuesta conserva centros, fechas y estado. Si hay respuestas anteriores a la versión 1.1 en sus centros, el borrado las conserva y lo indica en un aviso. |
| **TC-12c** | **Panel tras cambiar de centro** | En el navegador, limitar la red (DevTools, *Slow 3G*). Enviar una propuesta, cerrar el panel antes de que termine y abrir *Proponer match* en otro centro. | El panel nuevo conserva el centro elegido y lo que se escriba en él; la respuesta de la primera propuesta no lo cambia. |
| **TC-13b** | **Landmarks y contraste** | Con un tema de bloques (Twenty Twenty-Five) y con temas clásicos (Twenty Twenty, Twenty Twenty-One, YOOtheme), abrir el directorio y una ficha. Pasar axe (por ejemplo, la extensión axe DevTools) y revisar los landmarks con el lector de pantalla. | Un único `main` por página. En el directorio, las regiones «Filtros del directorio» y «Resultados (N centros)»; ningún `banner` añadido por el plugin. El enlace «Saltar al contenido» del tema lleva a la ficha. axe no muestra violaciones en elementos del plugin (`landmark-*`, `color-contrast`). |
| **TC-13** | **Panel accesible** | Abrir *Proponer match* con teclado, recorrerlo con `Tab` y cerrarlo con `Escape`. | El foco entra en el primer campo, queda atrapado en el diálogo y vuelve al botón que lo abrió. Sin sesión, el panel muestra el aviso y el enlace de inicio de sesión (sin `alert()`). |

---

## 5. Fase 4: Desinstalación Limpia (`uninstall.php`)

1. En **Plugins → Plugins instalados**, desactiva **RadioEdu Conecta** y pulsa **Borrar**.
2. Verifica en la base de datos que se han eliminado la tabla `wp_radioedu_colaboraciones`, los posts `radioedu_centro`, los términos de las 5 taxonomías, las opciones, los transients y las capacidades personalizadas.
3. En Multisite, al borrar un sitio de la red su tabla `wp_{ID}_radioedu_colaboraciones` se elimina automáticamente (filtro `wpmu_drop_tables`).

### Sincronización de fichas de la red (Multisite)

Hazla en una red desechable. WordPress no admite redes multisitio en puertos que no sean el 80 o el 443. Para probarla en el puerto 8091, instala la red con un dominio ficticio (por ejemplo `radioedu.test`) y arranca el navegador con `--host-resolver-rules="MAP radioedu.test 127.0.0.1:8091"`.

| ID | Prueba | Pasos | Resultado esperado |
| :--- | :--- | :--- | :--- |
| **TC-MS-01** | **Primera sincronización** | Crea varios sitios (uno con lema e icono y otro archivado) y una ficha manual con la web de uno de ellos. En **Administración de la red → Ajustes → RadioEdu Conecta**, pulsa el botón. | Barra de progreso y resumen en verde: una ficha por sitio, salvo el principal y el archivado, y la ficha manual reutilizada en lugar de duplicada. Cada ficha tiene el nombre, la web, el logo (icono), la descripción (lema) y el email del coordinador de su sitio, y su autor es el administrador del sitio, ahora con rol Autor en el sitio principal. Debajo del resumen, la tabla «N emisoras sincronizadas» lista cada emisora con enlace a su ficha, su sitio, el resultado y si se le ha añadido el rol Autor. |
| **TC-MS-02** | **Volver a sincronizar** | Cambia el nombre de un sitio y edita las coordenadas de su ficha. Sincroniza de nuevo. | Todas las fichas se cuentan como actualizadas. El nombre cambia y las coordenadas se conservan. |
| **TC-MS-03** | **Sitio borrado** | Borra un sitio y sincroniza. | Resumen en amarillo con la ficha del sitio borrado y un enlace para editarla. La ficha no se borra. |
| **TC-MS-05** | **Casilla del rol Autor** | Crea un sitio cuyo administrador no pertenece al portal principal y otro cuyo administrador es Suscriptor en él. Sincroniza con la casilla desmarcada, recarga la página, márcala y sincroniza otra vez. | Primera vez: fichas creadas y «0 coordinadores han recibido el rol Autor». Sin cambios de roles. Al recargar, la casilla sigue desmarcada. Segunda vez: «2 coordinadores…». El primero entra en el portal como Autor y el Suscriptor queda como Suscriptor y Autor. Un Editor o Administrador del portal no cambia. |
| **TC-MS-04** | **Permisos** | Llama a `POST /wp-json/radioedu-conecta/v1/network-sync` como administrador de un sitio (no superadministrador), sin sesión y desde un subsitio. | 403, 401 y 404, respectivamente. |

---

## 6. Fase 5: Calidad automática (DoD)

> Las pruebas que escriben o borran datos (PHPUnit, Plugin Check, desinstalación) deben ejecutarse en una pila Docker desechable, no en el contenedor `radioedu-wordpress` del puerto 8090, que monta el repositorio en vivo y conserva datos de trabajo.

```bash
composer install            # dependencias de desarrollo (PHPCS/WPCS, PHPStan, PHPUnit)
composer lint:php           # PHPCS: WordPress, WordPress-Extra, WordPress-Docs y PHPCompatibilityWP
composer stan               # PHPStan nivel 6 con szepeviktor/phpstan-wordpress
bash bin/install-wp-tests.sh wordpress_test root root 127.0.0.1 latest
vendor/bin/phpunit                    # sitio individual
WP_MULTISITE=1 vendor/bin/phpunit     # multisite
npm ci && npm run lint:js && npm run lint:css
npm run build && git diff --exit-code -- build/   # build/ versionado y al día
npx playwright install chromium && npm run wp-env start && npm run test:e2e   # E2E en http://localhost:8889
npm audit --audit-level=high && composer audit
```

Plugin Check debe ejecutarse sobre la copia distribuible (respetando `.distignore`), no sobre el repositorio completo: `wp plugin check radioedu-conecta`. Comprueba también que, sin la carpeta `build/`, el directorio y el editor de centros cargan sin errores fatales (solo pierden la interactividad).

Las pruebas E2E (`tests/e2e`) crean y borran su propio contenido. Para lanzarlas contra otro sitio desechable en lugar de `wp-env`, indica su URL y, si quieres, un navegador instalado: `WP_BASE_URL=http://localhost:8091 PLAYWRIGHT_CHANNEL=msedge npm run test:e2e`. Necesitan la cuenta `admin` / `password` con el idioma de usuario en inglés, porque las utilidades de `@wordpress/e2e-test-utils-playwright` pulsan los botones del editor por su texto en inglés. La prueba de los paneles falla si la consola del navegador muestra errores o avisos, salvo un aviso conocido del propio WordPress 7.1 sobre sus estilos globales en el editor.
