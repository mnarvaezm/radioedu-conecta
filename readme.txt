=== RadioEdu Conecta ===
Contributors: radioedu
Tags: school radio, podcast, education, directory, collaboration
Requires at least: 7.1
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Interactive directory and collaboration system ("Traspasando Fronteras") that connects school radio stations from Extremadura and around the world.

== Description ==

**RadioEdu Conecta** is the official plugin of the **Traspasando Fronteras** initiative by RadioEdu (Regional Ministry of Education, Science and Vocational Training, Junta de Extremadura, Spain). Its user interface is currently in Spanish.

It allows schools that run a school radio station to:

* Publish a station profile with cover image, circular logo, location, educational stages, working languages, topics, programme formats and links to their podcast and official website.
* Search and filter related schools in real time with the interactive faceted directory, available as the "Directorio Traspasando Fronteras" block or the `[radioedu_directorio]` shortcode (taxonomy-based affinity sorting and pagination), and a map view powered by a locally bundled copy of Leaflet.
* Open the individual profile page of each station and propose a collaboration ("match") directly from the profile or from the directory, through an accessible side panel.
* Send and manage formal collaboration proposals between radio coordinators from **RadioEdu Centros -> Colaboraciones (Matches)** without exposing personal email addresses on the public site.

== Privacy & GDPR ==

* Coordinators' names and email addresses are stored privately and are never exposed in the public HTML or in the public `/wp-json/radioedu-conecta/v1/centros` endpoint.
* Notifications are sent server-side with `wp_mail()`. The receiving coordinator's contact details are shared with the sending school only after the proposal is explicitly accepted.
* Collaboration proposals store the sending user account, the schools involved and the project text.
* Favourite schools and the map consent are stored only in the visitor's browser storage and are never sent to the server.
* The plugin integrates with the WordPress personal data exporter and eraser tools (`wp_privacy_personal_data_exporters` and `wp_privacy_personal_data_erasers`) and suggests text for the site's privacy policy.

== External services ==

This plugin can connect to one external service, and only after an explicit action by the visitor:

* **OpenStreetMap tile servers** (OpenStreetMap Foundation): when the visitor clicks "Aceptar y mostrar el mapa interactivo" (accept and show the interactive map) in the directory map view, the visitor's browser downloads map images from `https://tile.openstreetmap.org`. The visitor's IP address, user agent and the coordinates/zoom of the requested tiles are sent. No connection is made unless the visitor accepts. Tile usage policy: https://operations.osmfoundation.org/policies/tiles/ - Privacy policy: https://osmfoundation.org/wiki/Privacy_Policy

The plugin contains no telemetry and makes no other calls to external services.

== Third-party libraries ==

* **Leaflet 1.9.4** (`assets/js/leaflet.js`, `assets/css/leaflet.css`, `assets/css/images/`), bundled locally. BSD-2-Clause license, GPL-compatible. Unminified source: https://github.com/Leaflet/Leaflet/tree/v1.9.4
* **WordPress icons** (`assets/icons/close.svg`, `globe.svg`, `institution.svg`, `send.svg`): SVG paths from `@wordpress/icons` 10.32.0, GPL-2.0-or-later. Source: https://github.com/WordPress/gutenberg/tree/trunk/packages/icons. The other icons in `assets/icons/` were drawn for this plugin and share its license.

== Source code ==

The minified scripts in `build/` are compiled from the human-readable sources shipped in `assets/js/admin.js` and `assets/js/directorio.js` with `@wordpress/scripts` (version 37), using its default webpack configuration: `npx wp-scripts build assets/js/admin.js assets/js/directorio.js`. The `@wordpress/i18n` and `@wordpress/a11y` packages are not bundled; they are loaded from WordPress core as script dependencies.

== Installation ==

1. Upload `radioedu-conecta_1.1.zip` from **Plugins -> Add New Plugin -> Upload Plugin**, or copy the `radioedu-conecta` folder to `/wp-content/plugins/`.
2. Activate the plugin from the **Plugins** screen (or from Network Admin when using Multisite).
3. Manage school profiles from **RadioEdu Centros**, answer proposals from **Colaboraciones (Matches)** and configure the map and notifications from **Ajustes** (Settings).
4. Insert the "Directorio Traspasando Fronteras" block (Widgets category) or the `[radioedu_directorio]` shortcode in any page to display the interactive directory. Only one directory per page is supported.

== Frequently Asked Questions ==

= Is it compatible with WordPress Multisite? =

Yes. It supports per-site and network activation, automatic provisioning of new sites while network-active (`wp_initialize_site`), removal of the plugin table when a site is deleted (`wpmu_drop_tables`) and a complete network-wide cleanup in `uninstall.php`.

= Are teachers' email addresses published on the website? =

No. For privacy and GDPR compliance, email addresses are never exposed in the HTML or in the public API. Collaboration proposals are handled through the plugin's internal system and server-side notifications.

= Can a match be proposed directly from a station profile? =

Yes. Both the directory cards and each station's profile page include the "Proponer match" button, which opens the proposal side panel on the same screen.

== Changelog ==

= 1.1 =
* Requires WordPress 7.1.
* New "Directorio Traspasando Fronteras" block, registered in PHP only (`supports.autoRegister`) and equivalent to the `[radioedu_directorio]` shortcode.
* Interface icons use the WordPress 7.1 Icons API (core collection plus a `radioedu-conecta` collection) instead of emoji.
* School meta fields are registered with `register_post_meta()`, sanitized on every write and neither readable nor writable through the core REST API.
* Activation no longer creates demo content: the demo stations and the directory page are imported from **RadioEdu Centros -> Ajustes** only, and never overwrite a real school.
* Security: proposals require a RadioEdu capability, only the receiving school can answer them, and password-protected schools are excluded from the directory and from proposals.
* The directory sorts by affinity before paginating, cancels outdated searches, announces loading errors with a retry option and works with plain permalinks.
* Personal data export and erasure run in batches, and network-wide tasks process sites in batches of 100.
* Scripts are compiled with `@wordpress/scripts` and translated with `wp_set_script_translations()`. Without `build/`, pages still load, without interactivity.
* Accessibility: visible focus outlines, AA contrast for the accent and status colours, and the "Subir / Elegir Logo" button keeps its own text as its accessible name.
* Admin notices use `wp_admin_notice()`.

= 1.0.0 =
* Interactive `[radioedu_directorio]` directory with faceted filters, affinity sorting, pagination and a locally bundled Leaflet map with prior consent for OpenStreetMap tiles.
* Individual station profile template with cover image, circular logo, metadata, description and an integrated match proposal panel.
* **Colaboraciones (Matches)** admin screen to review, accept or decline received and sent proposals; each proposal can be answered only once.
* Hardened security: anti-IDOR validation of the sending school, strict URL and DOM sanitization, prepared SQL queries with `%i` and full capability mapping for coordinators.
* Integration with the WordPress personal data exporters/erasers and complete cleanup in `uninstall.php`, including Multisite networks.

== Upgrade Notice ==

= 1.1 =
Requires WordPress 7.1. Adds the directory block and SVG icons. Demo content is no longer created on activation; import it from RadioEdu Centros -> Ajustes.
