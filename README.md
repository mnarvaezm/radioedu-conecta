# RadioEdu Conecta

> Directorio interactivo y sistema de colaboraciones (**"Traspasando Fronteras"**) para conectar radios escolares de Extremadura y de todo el mundo.

- **Contribuidores:** radioedu (Consejería de Educación, Ciencia y Formación Profesional - Junta de Extremadura)
- **Etiquetas:** radio-escolar, podcast, educacion, directorio, colaboracion, multisite, rgpd
- **Versión mínima de WordPress:** 6.5
- **Probado hasta:** 6.7
- **Versión mínima de PHP:** 8.1
- **Versión estable:** 1.0.0
- **Licencia:** GPL-2.0-or-later
- **URI de licencia:** [GNU General Public License v2](https://www.gnu.org/licenses/gpl-2.0.html)

---

## Descripción

**RadioEdu Conecta** es el plugin oficial de la iniciativa **Traspasando Fronteras** de RadioEdu (Junta de Extremadura). Permite a los centros educativos con emisoras de radio escolar:

1. **Directorio Facetado Interactivo (`[radioedu_directorio]`)**:
   - Búsqueda instantánea combinando **Ubicación**, **Idioma**, **Etapa educativa**, **Temas de interés**, **Formato de programa** y texto libre.
   - Ordenación inteligente por **Más afines** (priorizando emisoras disponibles y coincidencia de etiquetas con el centro del usuario), **Más recientes** o **Alfabético (A-Z)**, con paginación dinámica (*"Cargar más centros"*).
   - Sistema de favoritos persistente en el navegador (`❤️`).
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

1. Sube el archivo `radioedu-conecta.zip` desde **Plugins → Añadir nuevo plugin → Subir plugin** en WordPress, o descomprime la carpeta `radioedu-conecta` en `/wp-content/plugins/`.
2. Activa el plugin desde el menú **Plugins** (en instalaciones individuales se cargarán automáticamente las 6 emisoras de demostración y la página `/traspasando-fronteras/` con el shortcode `[radioedu_directorio]`).
3. Gestiona los centros desde **RadioEdu Centros**, responde a propuestas desde **RadioEdu Centros → Colaboraciones (Matches)** y configura el mapa o las notificaciones en **RadioEdu Centros → Ajustes**.

---

## Preguntas Frecuentes (FAQ)

### ¿Es compatible con WordPress Multisite?
Sí. Soporta tanto activación individual en un sitio concreto como activación para toda la red (*Network Activate*), aprovisionamiento automático de nuevos sitios (`wp_initialize_site`), limpieza al borrar sitios (`wp_uninitialize_site`) y desinstalación completa en toda la red mediante `uninstall.php`.

### ¿Se publican los correos electrónicos de los docentes en la web?
No. Por privacidad y cumplimiento RGPD, las direcciones de email nunca se exponen en el DOM ni en el endpoint público `/wp-json/radioedu-conecta/v1/centros`. Las notificaciones se gestionan internamente en el servidor mediante `wp_mail()` y solo se comparten los datos de contacto cuando un centro acepta formalmente un match.

---

## Changelog

### 1.0.0
* Lanzamiento completo del directorio interactivo `[radioedu_directorio]`, CPT `radioedu_centro` y 5 taxonomías facetadas con mapeo completo de capacidades.
* Nueva plantilla dedicada para el perfil individual de emisoras escolares (`templates/single-centro.php`) con envío directo de propuestas de match.
* Nuevo panel de administración **Colaboraciones (Matches)** (`ColaboracionesAdminPage`) para consultar, aceptar y declinar propuestas.
* Refuerzo integral de seguridad: mitigación anti-IDOR en `POST /colaboraciones`, validación estricta de URLs y nodos DOM en `directorio.js`, consultas SQL preparadas con `%i` y consentimiento previo RGPD para teselas de OpenStreetMap.
* Integración con los exportadores y borradores de datos personales de WordPress (`PrivacyPolicy`) y limpieza integral en `uninstall.php`.
