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
* Registrar su ficha de emisora con datos de localización, etapas educativas, idiomas de trabajo, temáticas y formatos de programa.
* Buscar y filtrar centros afines en tiempo real mediante un directorio facetado interactivo y vista en mapa geográfico.
* Enviar propuestas formales de colaboración entre coordinadores de radio sin exponer correos electrónicos personales.
* Gestionar acuerdos de hermanamiento y proyectos conjuntos de podcast escolar.

== Installation ==

1. Sube la carpeta `radioedu-conecta` al directorio `/wp-content/plugins/` de tu instalación de WordPress.
2. Activa el plugin a través del menú 'Plugins' en WordPress (o en la Administración de la Red si usas Multisite).
3. Añade las fichas de los centros desde el menú **RadioEdu Centros**.
4. Inserta el shortcode `[radioedu_directorio]` en cualquier página para mostrar el buscador interactivo.

== Frequently Asked Questions ==

= ¿Es compatible con WordPress Multisite? =
Sí. RadioEdu Conecta está diseñado para funcionar en instalaciones individuales y en redes educativas multisite (Educarex), soportando activación en red y desinstalación limpia.

= ¿Se publican los correos electrónicos de los docentes en la web? =
No. Por privacidad y seguridad (cumplimiento RGPD), las direcciones de email nunca se exponen en el código HTML. Las propuestas de colaboración se gestionan a través del sistema interno del plugin y notificaciones automáticas por servidor.

== Changelog ==

= 1.0.0 =
* Versión inicial: Registro de centros de radio escolar, taxonomías facetadas, tabla de colaboraciones y arquitectura base.
