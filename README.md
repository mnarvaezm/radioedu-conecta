# RadioEdu Conecta

> Directorio interactivo y sistema de colaboraciones (**"Traspasando Fronteras"**) para conectar radios escolares de Extremadura y de todo el mundo.

- **Contribuidores:** radioedu (Consejería de Educación, Ciencia y Formación Profesional - Junta de Extremadura)
- **Etiquetas:** radio-escolar, podcast, educacion, directorio, colaboracion, multisite, rgpd
- **Versión mínima de WordPress:** 7.1
- **Probado hasta:** 7.1
- **Versión mínima de PHP:** 8.1
- **Versión estable:** 1.1
- **Licencia:** GPL-2.0-or-later
- **URI de licencia:** [GNU General Public License v2](https://www.gnu.org/licenses/gpl-2.0.html)

---

## Descripción

**RadioEdu Conecta** es el plugin oficial de la iniciativa **Traspasando Fronteras** de RadioEdu (Junta de Extremadura). Permite a los centros educativos con emisoras de radio escolar:

1. **Directorio Facetado Interactivo (bloque *Directorio Traspasando Fronteras* o shortcode `[radioedu_directorio]`)**:
   - Búsqueda instantánea combinando **Ubicación**, **Idioma**, **Etapa educativa**, **Temas de interés**, **Formato de programa** y texto libre.
   - Ordenación inteligente por **Más afines** (priorizando emisoras disponibles y coincidencia de etiquetas con el centro del usuario), **Más recientes** o **Alfabético (A-Z)**, con paginación dinámica (*"Cargar más centros"*).
   - Sistema de favoritos persistente en el navegador (icono de estrella).
   - Iconos SVG de la API de iconos de WordPress 7.1 (colección `core` y colección propia `radioedu-conecta`), que heredan el color del tema y se ocultan a los lectores de pantalla cuando son decorativos.
2. **Mapa Geográfico Interactivo (Leaflet Local + RGPD)**:
   - Librería Leaflet 1.9.4 empaquetada localmente (cero CDNs externas) y carga de cartografía de OpenStreetMap bajo consentimiento explícito del usuario.
   - Marcadores geolocalizados con popups construidos mediante nodos DOM seguros (`createElement` / `textContent`).
3. **Ficha de Perfil Individual de la Emisora (`single-radioedu_centro`)**:
   - Diseño dedicado con imagen de cabecera a ancho completo, insignia de disponibilidad, logotipo circular superpuesto, nombre del centro, bandera y localización, píldoras de colores por categoría, tarjetas de edades e idiomas, enlaces al Podcast/Streaming y Web oficial, texto descriptivo y botón para **Proponer match directamente desde el perfil**.
4. **Sistema de Propuestas de Colaboración ("Matches") y Privacidad RGPD**:
   - Envío de propuestas mediante panel lateral desplegable (*Drawer*) desde el directorio o desde la ficha de cada emisora, con validación anti-suplantación (anti-IDOR) y límite de peticiones (*Rate Limiting* de 10 propuestas/hora).
   - **Privacidad asimétrica**: Los correos electrónicos de los docentes coordinadores nunca se exponen en el HTML ni en la REST API pública. El contacto se revela únicamente por correo interno cuando el centro receptor acepta la propuesta.
   - **Panel de gestión en WP Admin (`RadioEdu Centros → Colaboraciones (Matches)`)**: Permite a los coordinadores y administradores consultar las solicitudes recibidas/enviadas y **Aceptar** o **Declinar** propuestas con un mensaje de respuesta.
   - Integración nativa con las herramientas de **Privacidad de WordPress** (exportador y borrador de datos personales RGPD y texto sugerido para la política de privacidad).
5. **Emisoras de Demostración Incluidas**:
   - Incluye 6 emisoras de ejemplo completas (*IES Sierra de Gata Radio*, *Ràdio Institut Montjuïc*, *CEIP La Mar*, *CEIP San José*, *IES Valle de Tena* y *Colegio Internacional San Patricio*) con fotografías de cabecera, logotipos, taxonomías y coordenadas sincronizables en un clic desde **RadioEdu Centros → Ajustes**.

---

## Instalación

1. Sube el archivo `radioedu-conecta_1.1.zip` desde **Plugins → Añadir nuevo plugin → Subir plugin** en WordPress, o descomprime la carpeta `radioedu-conecta` en `/wp-content/plugins/`.
2. Activa el plugin desde el menú **Plugins**. La activación no publica contenido: si quieres las 6 emisoras de demostración y la página `/traspasando-fronteras/` con el shortcode `[radioedu_directorio]`, cárgalas desde **RadioEdu Centros → Ajustes**.
3. Para mostrar el directorio en cualquier otra página, inserta el bloque **Directorio Traspasando Fronteras** (categoría *Widgets*) o el shortcode `[radioedu_directorio]`. Solo puede haber un directorio por página.
4. Gestiona los centros desde **RadioEdu Centros**, responde a propuestas desde **RadioEdu Centros → Colaboraciones (Matches)** y configura el mapa o las notificaciones en **RadioEdu Centros → Ajustes**.

---

## Desarrollo

El JavaScript fuente (`assets/js/admin.js`, `assets/js/directorio.js`) usa `@wordpress/i18n` y `@wordpress/a11y`, así que el navegador solo puede ejecutar la versión compilada en `build/`. Esa carpeta se versiona y la CI comprueba que está al día:

```bash
npm ci
npm run build    # regenera build/*.js y build/*.asset.php; haz commit del resultado
```

Si falta `build/`, el plugin no falla: el directorio y el editor de centros se muestran sin la parte interactiva.

El bloque del directorio no necesita JavaScript de editor: se registra solo con PHP (`supports.autoRegister`) y WordPress lo previsualiza en el editor con ServerSideRender.

Pruebas de extremo a extremo (Playwright) en un sitio desechable con [`wp-env`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/) (requiere Docker):

```bash
npx playwright install chromium
npm run wp-env start   # sitio de pruebas en http://localhost:8889 (admin / password)
npm run test:e2e
```

Las pruebas completas están en [TESTING-PROTOCOL.md](TESTING-PROTOCOL.md).

---

## Preguntas Frecuentes (FAQ)

### ¿Es compatible con WordPress Multisite?
Sí. Soporta tanto activación individual en un sitio concreto como activación para toda la red (*Network Activate*), aprovisionamiento automático de nuevos sitios (`wp_initialize_site`), limpieza al borrar sitios (filtro `wpmu_drop_tables`) y desinstalación completa en toda la red mediante `uninstall.php`.

### ¿Se publican los correos electrónicos de los docentes en la web?
No. Por privacidad y cumplimiento RGPD, las direcciones de email nunca se exponen en el DOM ni en el endpoint público `/wp-json/radioedu-conecta/v1/centros`. Las notificaciones se gestionan internamente en el servidor mediante `wp_mail()` y solo se comparten los datos de contacto cuando un centro acepta formalmente un match.

---

## Changelog

### 1.1
* Requiere WordPress 7.1.
* Nuevo bloque **Directorio Traspasando Fronteras** registrado solo con PHP (`supports.autoRegister`), equivalente al shortcode `[radioedu_directorio]`.
* Iconos SVG de la API de iconos de WordPress 7.1 en lugar de emojis en el directorio, la ficha, el panel lateral y la pantalla de Colaboraciones.
* Metadatos del centro registrados con `register_post_meta()`: se sanean en cualquier escritura y no se pueden leer ni modificar desde la REST API del core.
* La activación ya no crea contenido de demostración: las emisoras de ejemplo y la página del directorio se importan solo desde **RadioEdu Centros → Ajustes** y nunca sobrescriben un centro real.
* Seguridad: las propuestas exigen una capacidad de RadioEdu, solo el centro receptor puede responderlas y los centros protegidos con contraseña quedan fuera del directorio y de las propuestas.
* El directorio ordena por afinidad antes de paginar, cancela las búsquedas obsoletas, anuncia los errores de carga con opción de reintentar y funciona con enlaces permanentes simples.
* La exportación y el borrado de datos personales se hacen por lotes, y las tareas de red recorren los sitios en lotes de 100.
* Scripts compilados con `@wordpress/scripts` y traducidos con `wp_set_script_translations()`. Sin `build/`, las páginas cargan igualmente, sin interactividad.
* Accesibilidad: foco visible, contraste AA en el color de acento y en los estados, y el botón *Subir / Elegir Logo* conserva su texto como nombre accesible (WCAG 2.5.3).
* Avisos de administración con `wp_admin_notice()`.
* Pruebas E2E con Playwright y `wp-env` (selección del logo en el editor con lienzo en iframe de WordPress 7.1 y bloque del directorio).

### 1.0.0
* Lanzamiento completo del directorio interactivo `[radioedu_directorio]`, CPT `radioedu_centro` y 5 taxonomías facetadas con mapeo completo de capacidades.
* Nueva plantilla dedicada para el perfil individual de emisoras escolares (`templates/single-centro.php`) con envío directo de propuestas de match.
* Nuevo panel de administración **Colaboraciones (Matches)** (`ColaboracionesAdminPage`) para consultar, aceptar y declinar propuestas.
* Refuerzo integral de seguridad: mitigación anti-IDOR en `POST /colaboraciones`, validación estricta de URLs y nodos DOM en `directorio.js`, consultas SQL preparadas con `%i` y consentimiento previo RGPD para teselas de OpenStreetMap.
* Integración con los exportadores y borradores de datos personales de WordPress (`PrivacyPolicy`) y limpieza integral en `uninstall.php`.
