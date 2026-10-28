=== RadioEdu Conecta ===
Contributors: radioedu
Tags: radio-escolar, podcast, educacion, directorio, colaboracion
Requires at least: 6.5
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Directorio interactivo y sistema de colaboraciones ("Traspasando Fronteras") para conectar radios escolares de Extremadura y de todo el mundo.

== Description ==

**RadioEdu Conecta** es el plugin oficial de la iniciativa **Traspasando Fronteras** de RadioEdu (Consejería de Educación, Ciencia y Formación Profesional de la Junta de Extremadura).

Permite a los centros educativos con emisoras de radio escolar:
* Registrar su ficha de emisora con imagen de cabecera, logotipo circular, localización, etapas educativas, idiomas de trabajo, temáticas, formatos de programa y enlaces a su podcast y web oficial.
* Buscar y filtrar centros afines en tiempo real mediante el directorio facetado interactivo `[radioedu_directorio]` (con algoritmo de afinidad por taxonomías y paginación) y vista en mapa geográfico con Leaflet local.
* Consultar la página de perfil individual de cada emisora escolar y abrir el panel lateral de propuesta de match directamente desde la ficha del centro o desde el directorio.
* Enviar y gestionar propuestas formales de colaboración ("Matches") entre coordinadores de radio desde **RadioEdu Centros -> Colaboraciones (Matches)** sin exponer correos electrónicos personales en la web pública.

== Privacy & GDPR ==

* Los nombres y direcciones de correo electrónico de los docentes coordinadores se almacenan de forma privada y nunca se exponen en el HTML público ni en el endpoint público `/wp-json/radioedu-conecta/v1/centros`.
* Las notificaciones se envían desde el servidor mediante `wp_mail()` y los datos de contacto solo se comparten entre centros cuando una propuesta es aceptada explícitamente.
* La librería Leaflet está empaquetada localmente sin depender de CDNs externas. La descarga de cartografía desde OpenStreetMap requiere el consentimiento explícito del usuario antes de conectar con `tile.openstreetmap.org`.
* El plugin se integra con las herramientas de exportación y borrado de datos personales de WordPress Core (`wp_privacy_personal_data_exporters` y `wp_privacy_personal_data_erasers`).

== Installation ==

1. Sube el archivo `radioedu-conecta.zip` desde **Plugins -> Añadir nuevo plugin -> Subir plugin** o copia la carpeta `radioedu-conecta` en `/wp-content/plugins/`.
2. Activa el plugin a través del menú 'Plugins' en WordPress (o en la Administración de la Red si usas Multisite).
3. Gestiona las fichas de los centros desde el menú **RadioEdu Centros**, responde a las solicitudes en **Colaboraciones (Matches)** y sincroniza los datos de ejemplo desde **Ajustes**.
4. Inserta el shortcode `[radioedu_directorio]` en cualquier página para mostrar el buscador interactivo.

== Frequently Asked Questions ==

= ¿Es compatible con WordPress Multisite? =
Sí. RadioEdu Conecta está diseñado para funcionar en instalaciones individuales y en redes educativas multisite (Educarex), soportando activación individual o en red, aprovisionamiento automático de nuevos sitios (`wp_initialize_site`) y desinstalación limpia (`uninstall.php`).

= ¿Se publican los correos electrónicos de los docentes en la web? =
No. Por privacidad y seguridad (cumplimiento RGPD), las direcciones de email nunca se exponen en el código HTML ni en la API pública. Las propuestas de colaboración se gestionan a través del sistema interno del plugin y notificaciones automáticas por servidor.

= ¿Se puede proponer un match directamente desde el perfil de una emisora? =
Sí. Tanto las tarjetas del directorio como la página de perfil individual de cada emisora cuentan con el botón "Proponer match", que despliega el panel lateral de solicitud en esa misma pantalla.

== Changelog ==

= 1.0.0 =
* Directorio interactivo `[radioedu_directorio]` con filtros facetados, ordenación por afinidad real, paginación y mapa Leaflet local con consentimiento RGPD.
* Plantilla de perfil individual de emisora (`templates/single-centro.php`) con imagen de cabecera, logotipo circular, metadatos, descripción y panel de solicitud de match integrado.
* Panel de administración **Colaboraciones (Matches)** para consultar, aceptar o declinar propuestas recibidas y enviadas.
* Controles de seguridad reforzados: validación anti-IDOR del centro emisor, sanitización estricta de URLs y nodos DOM, consultas preparadas con `%i` y mapeo completo de capacidades para coordinadores.
* Integración completa con los exportadores/borradores RGPD de WordPress y limpieza integral en `uninstall.php`.
