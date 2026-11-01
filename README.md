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
   - Envío de propuestas mediante panel lateral desplegable (*Drawer*) desde el directorio o desde la ficha de cada emisora, con validación anti-suplantación (anti-IDOR) y límite de peticiones (*Rate Limiting* de 20 propuestas/hora).
   - **Privacidad asimétrica**: Los correos electrónicos de los docentes coordinadores nunca se exponen en el HTML ni en la REST API pública. El contacto se revela únicamente por correo interno cuando el centro receptor acepta la propuesta.
   - **Panel de gestión en WP Admin (`RadioEdu Centros → Colaboraciones (Matches)`)**: Permite a los coordinadores y administradores consultar las solicitudes recibidas/enviadas y **Aceptar** o **Declinar** propuestas con un mensaje de respuesta.
   - Integración nativa con las herramientas de **Privacidad de WordPress** (exportador y borrador de datos personales RGPD y texto sugerido para la política de privacidad).
   - **Conservación**: las propuestas no caducan. Se conservan como historial compartido hasta que una solicitud de borrado de datos personales anonimiza la parte de la persona solicitante, o hasta que se desinstala el plugin.
5. **Datos del centro en el editor de bloques**:
   - Logo, bandera, edades, podcast, web, disponibilidad, coordenadas y datos del coordinador se editan en la barra lateral del editor (pestaña **Centro de Radio**), en los paneles **Datos de la emisora**, **Ubicación en el mapa** y **Coordinación**, y se guardan con el botón *Guardar* del centro.
   - Con el Editor Clásico se siguen editando en la caja *Datos de la Emisora y Coordinación*.
6. **Emisoras de Demostración Incluidas**:
   - Incluye 6 emisoras de ejemplo completas (*IES Sierra de Gata Radio*, *Ràdio Institut Montjuïc*, *CEIP La Mar*, *CEIP San José*, *IES Valle de Tena* y *Colegio Internacional San Patricio*) con fotografías de cabecera, logotipos, taxonomías y coordenadas sincronizables en un clic desde **RadioEdu Centros → Ajustes**.

---

## Instalación

1. Sube el archivo `radioedu-conecta_1.1.zip` desde **Plugins → Añadir nuevo plugin → Subir plugin** en WordPress, o descomprime la carpeta `radioedu-conecta` en `/wp-content/plugins/`.
2. Activa el plugin desde el menú **Plugins**. La activación no publica contenido: si quieres las 6 emisoras de demostración y la página `/traspasando-fronteras/` con el shortcode `[radioedu_directorio]`, cárgalas desde **RadioEdu Centros → Ajustes**.
3. Para mostrar el directorio en cualquier otra página, inserta el bloque **Directorio Traspasando Fronteras** (categoría *Widgets*) o el shortcode `[radioedu_directorio]`. Solo puede haber un directorio por página.
4. Gestiona los centros desde **RadioEdu Centros** (los datos de cada emisora están en la barra lateral del editor, pestaña **Centro de Radio**), responde a propuestas desde **RadioEdu Centros → Colaboraciones (Matches)** y configura el mapa, las notificaciones o el número de radios por usuario en **RadioEdu Centros → Ajustes**.

---

## Desarrollo

El JavaScript fuente (`assets/js/admin.js`, `assets/js/centro-panel.js`, `assets/js/directorio.js`, `assets/js/network-sync.js`) usa paquetes `@wordpress/*` (JSX y componentes del editor en los paneles del centro), así que el navegador solo puede ejecutar la versión compilada en `build/`. Esos paquetes los aporta WordPress como scripts del core: no se instalan con npm y [eslint.config.cjs](eslint.config.cjs) los declara para el lint. La carpeta `build/` se versiona y la CI comprueba que está al día:

```bash
npm ci
npm run build    # regenera build/*.js y build/*.asset.php; haz commit del resultado
```

Si falta `build/`, el plugin no falla: el directorio se muestra sin la parte interactiva y el editor de centros, sin los paneles de datos.

El bloque del directorio no necesita JavaScript de editor: sus metadatos están en `blocks/directorio/block.json`, el renderizado es una función PHP y, con `supports.autoRegister`, WordPress lo previsualiza en el editor con ServerSideRender.

### Traducciones

Las cadenas fuente están en castellano. `languages/` incluye catalán (`ca`), valenciano (`ca_valencia`), gallego (`gl_ES`) y euskera (`eu`). De cada idioma hay un `.po` editable y tres ficheros compilados: `.mo` y `.l10n.php` para PHP, y un `.json` por script de `build/`. Tras cambiar cadenas o traducciones, regenera con [WP-CLI](https://wp-cli.org/) desde la raíz del repositorio:

```bash
wp i18n make-pot . languages/radioedu-conecta.pot --exclude=node_modules,vendor,tests,dist,assets/js,artifacts
wp i18n update-po languages/radioedu-conecta.pot languages/   # añade las cadenas nuevas a cada .po; tradúcelas (p. ej. con Poedit)
wp i18n make-json languages/ --no-purge --use-map=bin/i18n-js-map.json
wp i18n make-mo languages/
wp i18n make-php languages/
```

- **Por qué el mapa** [bin/i18n-js-map.json](bin/i18n-js-map.json): WP-CLI 2.12 quita el sufijo `.min` con una expresión regular sin escapar y convierte `build/admin.js` en `build/a.js`. El `.json` resultante tendría un nombre que WordPress nunca busca, y el mapa lo evita.
- **Scripts nuevos:** `make-json` ignora los que no están en el mapa, así que añade ahí cada script nuevo de `build/`.
- **Control automático:** `tests/TranslationsTest.php` falla si a algún script le falta su `.json`.

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

### ¿Se pueden crear las fichas a partir de los sitios de la red?
Sí. En **Administración de la red → Ajustes → RadioEdu Conecta**, el botón **Sincronizar fichas con los sitios de la red** crea en el directorio del sitio principal una ficha por cada sitio de la red (salvo el principal y los archivados, marcados como spam o borrados), o actualiza la que ya tiene.
- **Datos que toma:** el nombre, la URL y el icono del sitio se actualizan en cada sincronización. El lema, el email de administración y el administrador del sitio solo se usan al crear la ficha: pasan a ser la descripción, el email del coordinador y el autor de la ficha.
- **Rol Autor en el portal principal:** con la casilla **Dar el rol Autor…** (marcada por defecto), el autor de cada ficha recibe el rol Autor en el sitio principal si no lo tiene ni tiene otro superior, para que pueda editar su ficha. Desmarcada, no se añade ningún rol. La pantalla recuerda la última elección y el resumen indica a cuántos coordinadores se ha dado el rol.
- **Lo que no toca:** lo que el coordinador complete después (coordenadas, bandera, etapas, idiomas, temas, podcast) no se modifica nunca, y las fichas en la papelera no se tocan.
- **Fichas existentes:** si ya existe una ficha creada a mano con la misma web, se reutiliza en lugar de duplicarla.
- **Sitios desaparecidos:** sus fichas no se borran; aparecen en el resumen final con un enlace para editarlas.
- **Listado final:** al terminar, una tabla muestra cada emisora sincronizada con enlace a su ficha, su sitio de origen, el resultado (creada, actualizada, reutilizada u omitida) y si su coordinador ha recibido el rol Autor.

La sincronización es manual, por lotes con barra de progreso, y solo la puede lanzar un superadministrador. El plugin debe estar activo en el sitio principal; lo recomendable es activarlo en red.

### ¿En qué idiomas está disponible?
En castellano, con traducciones incluidas al catalán, valenciano, gallego y euskera, tanto de las pantallas como de los scripts (directorio, paneles del editor y sincronización de red). WordPress las usa solo cuando el idioma del sitio o del usuario es uno de esos (**Ajustes → Generales → Idioma del sitio**, o el perfil de cada usuario). Si algún día hay paquetes de idioma oficiales en translate.wordpress.org, tienen prioridad sobre los incluidos.

### ¿Se publican los correos electrónicos de los docentes en la web?
No. Por privacidad y cumplimiento RGPD, las direcciones de email nunca se exponen en el DOM ni en el endpoint público `/wp-json/radioedu-conecta/v1/centros`. Las notificaciones se gestionan internamente en el servidor mediante `wp_mail()` y solo se comparten los datos de contacto cuando un centro acepta formalmente un match.

---

## Changelog

### 1.1
* Requiere WordPress 7.1.
* Nuevo bloque **Directorio Traspasando Fronteras**, registrado desde `block.json` y sin JavaScript de editor (`supports.autoRegister`), equivalente al shortcode `[radioedu_directorio]`.
* Iconos SVG de la API de iconos de WordPress 7.1 en lugar de emojis en el directorio, la ficha, el panel lateral y la pantalla de Colaboraciones.
* Multisite: botón de red **Sincronizar fichas con los sitios de la red** (**Administración de la red → Ajustes → RadioEdu Conecta**), que crea o actualiza en el sitio principal una ficha por cada sitio de la red, reutiliza las fichas hechas a mano con la misma web y lista en un resumen las fichas de sitios que ya no existen. Una casilla, marcada por defecto, da a los coordinadores el rol Autor en el portal principal.
* Nuevo ajuste **Radios por usuario** (**Ajustes → Coordinadores**): limita cuántas radios puede crear cada coordinador (rol Autor). Vacío, el valor por defecto, significa sin límite; con 0 solo editan las que les asigne un administrador. Administradores y editores no tienen límite. El límite se aplica en el menú, en la pantalla de creación y en la REST.
* La página pública de cada centro ya no repite encima de la ficha el título, el autor y la fecha ni la imagen destacada del tema: el plugin aporta su plantilla (una plantilla de bloques editable en el Editor del sitio, o una plantilla PHP con la cabecera y el pie del tema en los temas clásicos), que el tema puede sustituir.
* Los datos del centro pasan de la caja meta, plegada al pie del editor, a tres paneles de la barra lateral (**Datos de la emisora**, **Ubicación en el mapa** y **Coordinación**). La caja meta solo aparece con el Editor Clásico.
* Metadatos del centro registrados con `register_post_meta()`: se sanean en cualquier escritura. La REST del core solo los muestra en modo edición a quien puede editar el centro (nunca en las respuestas públicas) y XML-RPC no puede modificarlos.
* La activación ya no crea contenido de demostración: las emisoras de ejemplo y la página del directorio se importan solo desde **RadioEdu Centros → Ajustes** y nunca sobrescriben un centro real. Sus descripciones se crean como bloques de párrafo, no como un bloque clásico.
* Seguridad: las propuestas exigen una capacidad de RadioEdu, solo el centro receptor puede responderlas y los centros protegidos con contraseña quedan fuera del directorio y de las propuestas.
* El directorio ordena por afinidad antes de paginar, cancela las búsquedas obsoletas, anuncia los errores de carga con opción de reintentar y funciona con enlaces permanentes simples.
* La exportación y el borrado de datos personales se hacen por lotes, y las tareas de red recorren los sitios en lotes de 100.
* Scripts compilados con `@wordpress/scripts` y traducidos con `wp_set_script_translations()`. Sin `build/` o sin JavaScript, las páginas cargan igualmente: el directorio muestra el número de emisoras y un enlace a su listado (el archivo `/centros-radioedu/`).
* Traducciones incluidas al catalán, valenciano, gallego y euskera, para PHP y para los scripts. Los paquetes de idioma oficiales, si existen, tienen prioridad.
* Accesibilidad: foco visible, contraste AA en el color de acento y en los estados, y el botón *Subir / Elegir Logo* conserva su texto como nombre accesible (WCAG 2.5.3).
* Accesibilidad de la estructura: cada página tiene un único `main`. El directorio ya no añade un segundo `<main>` ni un `<header>` dentro del contenido; sus resultados forman una región con nombre. En temas clásicos, la ficha aporta el `<main>` y el destino del enlace «Saltar al contenido» solo cuando la cabecera del tema no los tiene (probado con Twenty Twenty, Twenty Twenty-One y YOOtheme). «Ordenar por» y «Volver al directorio» alcanzan el contraste AA sobre cualquier fondo claro.
* Avisos de administración con `wp_admin_notice()`.
* El texto completo de la licencia de Leaflet se incluye en `assets/leaflet-LICENSE.txt`.
* Las imágenes de ejemplo solo aparecen en las emisoras de demostración, no en centros reales cuyo slug contiene `san-jose` o `la-mar`.
* El importador de demostración solo actualiza las emisoras que creó. Ya no se apropia de una emisora que comparta slug o URL de podcast con una de ejemplo, ni marca para borrar al desinstalar una página del directorio que ya existía.
* Una actualización de la base de datos que falla se reintenta más tarde en lugar de darse por hecha.
* Privacidad: la respuesta a una propuesta registra quién la escribe (esquema 1.0.3), así que se exporta y se borra para esa persona. Las respuestas anteriores a la 1.1 no tienen autor: el borrado las conserva y lo indica.
* Un error de base de datos al responder una propuesta devuelve un error reintentable en lugar de «ya respondida».
* En el directorio, la respuesta tardía a una propuesta enviada antes de cerrar el panel ya no cambia el panel abierto para otra emisora.
* El límite de *Radios por usuario* también se aplica cuando un coordinador restaura una emisora desde la papelera o guarda una segunda pestaña de *Añadir nuevo* abierta antes de llegar al límite, en el editor de bloques o en el clásico.
* Cada coordinador puede enviar hasta 20 propuestas por hora en lugar de 10. Es un límite contra el spam: varias peticiones simultáneas pueden superarlo en alguna.
* El listado de propuestas tiene paginación: el endpoint REST acepta `page` y `per_page` y devuelve `X-WP-Total`, y la pantalla de administración muestra 20 propuestas por página. Antes, las propuestas posteriores a las 100 más recientes no se podían ver ni responder.
* Los coordinadores ven las propuestas de todas las emisoras que gestionan, sean cuantas sean, y los editores ven todas las propuestas. El selector de centro emisor muestra hasta 500 en lugar de 50 o 100.
* Los mensajes al enviar o responder una propuesta dicen qué ha pasado con el correo: enviado, desactivado en los ajustes o no enviado. Antes siempre prometían una notificación.
* Con el orden *Más afines*, administradores y editores, que no tienen emisora propia, ya no ven los resultados ordenados según los términos de la primera emisora por orden alfabético.
* La vista de mapa muestra todos los resultados de la búsqueda, hasta 500 marcadores, en lugar de solo las 24 tarjetas cargadas, y avisa cuando el límite deja alguno fuera.
* Accesibilidad: a 320 px de ancho (o con zoom del 400 %) el directorio ya no necesita desplazamiento horizontal; las tarjetas se ajustan al ancho disponible y los controles bajan de línea (WCAG 1.4.10).
* El enlace *Volver al directorio* de las fichas lleva a la página donde está realmente el bloque o el shortcode del directorio, no solo a una página con el slug `traspasando-fronteras`. El nuevo filtro `radioedu_conecta_directory_url` permite cambiarlo.
* La ficha ya no elimina los reproductores embebidos (YouTube, pódcast…) de la descripción: muestra el contenido tal como lo filtra WordPress, igual que cualquier tema.
* Multisite: el recuento de *Radios por usuario* se guarda en caché por sitio, así que el código que comprueba varios sitios en una misma petición ya no reutiliza el recuento de otro sitio.
* Multisite: dos sincronizaciones de red simultáneas ya no crean dos fichas del mismo sitio; la segunda indica que el sitio está ocupado.
* Multisite: el resumen final de la sincronización lista todas las fichas cuyo sitio ya no existe, no solo las de las 1.000 primeras.
* Sin JavaScript, el directorio muestra el número real de emisoras y un enlace a su listado en lugar de «0 centros».
* La desinstalación también quita a cada usuario la preferencia del editor propia del plugin (si se abrió el panel de la emisora) y conserva las de WordPress y otros plugins.
* *Cargar más centros* y las páginas del mapa conservan los filtros de la búsqueda que se está mostrando, aunque después se haya editado el formulario sin buscar.
* El enlace *Volver al directorio* también encuentra el directorio cuando está dentro de un patrón sincronizado.
* Pruebas E2E con Playwright y `wp-env` (datos del centro en los paneles del editor de WordPress 7.1, incluido el logo, y bloque del directorio).

### 1.0.0
* Lanzamiento completo del directorio interactivo `[radioedu_directorio]`, CPT `radioedu_centro` y 5 taxonomías facetadas con mapeo completo de capacidades.
* Nueva plantilla dedicada para el perfil individual de emisoras escolares (`templates/single-centro.php`) con envío directo de propuestas de match.
* Nuevo panel de administración **Colaboraciones (Matches)** (`ColaboracionesAdminPage`) para consultar, aceptar y declinar propuestas.
* Refuerzo integral de seguridad: mitigación anti-IDOR en `POST /colaboraciones`, validación estricta de URLs y nodos DOM en `directorio.js`, consultas SQL preparadas con `%i` y consentimiento previo RGPD para teselas de OpenStreetMap.
* Integración con los exportadores y borradores de datos personales de WordPress (`PrivacyPolicy`) y limpieza integral en `uninstall.php`.
