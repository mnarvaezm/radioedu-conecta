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
4. Puedes repetir la sincronización cuando quieras: solo actualiza los centros de demostración y nunca sobrescribe un centro real aunque comparta el slug.

### Paso 1.3: Verificar Tabla Personalizada y Logs
```powershell
docker exec radioedu-wordpress wp db query "DESCRIBE wp_radioedu_colaboraciones;" --allow-root
```
*Criterio de éxito*: Deben figurar `id`, `centro_emisor_id`, `centro_receptor_id`, `usuario_emisor_id`, `idea_proyecto`, `curso_edades`, `idiomas`, `fechas_aprox`, `estado`, `respuesta_mensaje`, `created_at`, `updated_at`.

---

## 3. Fase 2: Validación del Directorio, Mapa y Ficha de Perfil

| ID | Prueba | Acción | Resultado Esperado |
| :--- | :--- | :--- | :--- |
| **TC-01** | **Carga inicial del directorio** | Abrir `http://localhost:8090/traspasando-fronteras/`. | Se muestran el banner Hero, la barra de filtros, el contador de centros y la cuadrícula de tarjetas con cabeceras, logos circulares, banderas e insignias de colores. |
| **TC-02** | **Filtros facetados y búsqueda** | Filtrar por ubicación, idioma, etapa, temática, formato o texto libre. | La cuadrícula se actualiza asíncronamente vía `GET /wp-json/radioedu-conecta/v1/centros` sin recargar la página. |
| **TC-03** | **Ordenación por Afinidad** | Seleccionar *"Más afines"* en "Ordenar por". | Prioriza los centros disponibles y ordena según coincidencias de taxonomías con el centro del usuario conectado, que aparece el último. Un administrador no tiene centro propio, así que ningún centro se manda al final. |
| **TC-04** | **Vista de Mapa y Consentimiento RGPD** | Pulsar **"Vista de mapa"**. | Antes de conectar con `tile.openstreetmap.org`, se muestra el aviso RGPD. Al pulsar *"Aceptar y mostrar el mapa interactivo"*, se carga el mapa Leaflet con los marcadores y popups construidos con nodos DOM seguros. |
| **TC-05** | **Ficha de Perfil Individual** | Pulsar **"Ver perfil"** en cualquier tarjeta (ej. `/centro-radioedu/ceip-san-jose/`). | Se muestra la ficha con imagen de cabecera arriba, insignia de disponibilidad, logo circular, nombre, bandera, ubicación, etiquetas de colores, edades, idiomas, botones de acción a la izquierda y descripción debajo. |
| **TC-05b** | **Bloque del directorio** | En una página nueva, insertar el bloque **Directorio Traspasando Fronteras** (categoría *Widgets*) y publicarla. | El editor (lienzo en iframe) muestra la vista previa del directorio con sus estilos; en la web funciona igual que el shortcode. No se puede insertar un segundo directorio en la misma página. |
| **TC-05c** | **Iconos SVG** | Revisar los filtros, las tarjetas, la ficha, el panel lateral y **Colaboraciones (Matches)**. | Todos los iconos de interfaz son SVG de la API de iconos (`.radioedu-svg-icon`), heredan el color y llevan `aria-hidden="true"`. Solo las banderas siguen siendo emojis. |
| **TC-06** | **Solicitud de Match desde el Perfil** | Pulsar **"✈️ Proponer match"** dentro de la página de perfil individual de la emisora. | Se despliega directamente el panel lateral *"Solicitud de match"* en esa misma pantalla sin necesidad de volver al directorio. |

---

## 4. Fase 3: Flujo Completo de Match y Seguridad (Anti-IDOR / Rate Limit)

| ID | Prueba | Acción | Resultado Esperado |
| :--- | :--- | :--- | :--- |
| **TC-07** | **Envío de Propuesta** | Completar el formulario en el panel lateral y pulsar **Enviar propuesta**. | Devuelve HTTP `201`, muestra confirmación en verde y registra la propuesta en estado `pendiente`. |
| **TC-08** | **Protección Anti-IDOR** | Enviar por REST API un `centro_emisor_id` que no pertenece al coordinador autenticado. | El servidor rechaza la petición con HTTP `403 (radioedu_forbidden_center)`. |
| **TC-09** | **Gestión en WP Admin** | Ir a **RadioEdu Centros → Colaboraciones (Matches)** y pulsar **Aceptar** o **Declinar** con un mensaje. | El estado pasa a `aceptada` o `rechazada`, se envía el correo de notificación al centro emisor (incluyendo el email de contacto si fue aceptada) y se muestra el enlace `mailto:`. |
| **TC-10** | **Rate Limiting de Ventana Fija** | Intentar enviar más de 10 propuestas en menos de 1 hora. | El servidor responde con HTTP `429 (Too Many Requests)` sin reiniciar la ventana de expiración de 1 hora. |
| **TC-11** | **Privacidad RGPD** | Inspeccionar el HTML público y la respuesta JSON de `/wp-json/radioedu-conecta/v1/centros`. | Ningún correo electrónico ni nombre personal de coordinador aparece expuesto públicamente. |
| **TC-12** | **Respuesta única** | Responder (REST `PATCH` o panel de admin) a una propuesta ya aceptada o declinada. | HTTP `409 (radioedu_already_resolved)`, el estado no cambia y no se reenvía ningún correo. |
| **TC-13** | **Panel accesible** | Abrir *Proponer match* con teclado, recorrerlo con `Tab` y cerrarlo con `Escape`. | El foco entra en el primer campo, queda atrapado en el diálogo y vuelve al botón que lo abrió. Sin sesión, el panel muestra el aviso y el enlace de inicio de sesión (sin `alert()`). |

---

## 5. Fase 4: Desinstalación Limpia (`uninstall.php`)

1. En **Plugins → Plugins instalados**, desactiva **RadioEdu Conecta** y pulsa **Borrar**.
2. Verifica en la base de datos que se han eliminado la tabla `wp_radioedu_colaboraciones`, los posts `radioedu_centro`, los términos de las 5 taxonomías, las opciones, los transients y las capacidades personalizadas.
3. En Multisite, al borrar un sitio de la red su tabla `wp_{ID}_radioedu_colaboraciones` se elimina automáticamente (filtro `wpmu_drop_tables`).

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

Las pruebas E2E (`tests/e2e`) crean y borran su propio contenido. Para lanzarlas contra otro sitio desechable en lugar de `wp-env`, indica su URL y, si quieres, un navegador instalado: `WP_BASE_URL=http://localhost:8091 PLAYWRIGHT_CHANNEL=msedge npm run test:e2e`. Necesitan la cuenta `admin` / `password` con el idioma de usuario en inglés, porque las utilidades de `@wordpress/e2e-test-utils-playwright` pulsan los botones del editor por su texto en inglés.
