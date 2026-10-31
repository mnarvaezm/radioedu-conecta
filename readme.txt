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

**RadioEdu Conecta** is the official plugin of the **Traspasando Fronteras** initiative by RadioEdu (Regional Ministry of Education, Science and Vocational Training, Junta de Extremadura, Spain). Its user interface is in Spanish, with bundled Catalan, Valencian, Galician and Basque translations.

It allows schools that run a school radio station to:

* Publish a station profile with cover image, circular logo, location, educational stages, working languages, topics, programme formats and links to their podcast and official website, edited in sidebar panels of the block editor (or in a meta box with the classic editor).
* Search and filter related schools in real time with the interactive faceted directory, available as the "Directorio Traspasando Fronteras" block or the `[radioedu_directorio]` shortcode (taxonomy-based affinity sorting and pagination), and a map view powered by a locally bundled copy of Leaflet.
* Open the individual profile page of each station and propose a collaboration ("match") directly from the profile or from the directory, through an accessible side panel.
* Send and manage formal collaboration proposals between radio coordinators from **RadioEdu Centros -> Colaboraciones (Matches)** without exposing personal email addresses on the public site.

== Privacy & GDPR ==

* Coordinators' names and email addresses are stored privately and are never exposed in the public HTML or in the public `/wp-json/radioedu-conecta/v1/centros` endpoint. The core `/wp/v2/centros` endpoint returns them only in the edit context, and only to users who can edit that school, so the block editor can show them.
* Notifications are sent server-side with `wp_mail()`. The receiving coordinator's contact details are shared with the sending school only after the proposal is explicitly accepted. A station without a coordinator email, for example after a personal data erasure request, uses the email of its author's WordPress account instead: the eraser does not remove WordPress accounts.
* Collaboration proposals store the sending user account, the schools involved, the project text and the reply, with the account that wrote it.
* Proposals have no automatic expiry. They are kept as shared history until a personal data erasure request anonymizes the requester's part, or until the plugin is uninstalled.
* Favourite schools and the map consent are stored only in the visitor's browser storage and are never sent to the server.
* The plugin integrates with the WordPress personal data exporter and eraser tools (`wp_privacy_personal_data_exporters` and `wp_privacy_personal_data_erasers`) and suggests text for the site's privacy policy.

== External services ==

This plugin can connect to one external service, and only after an explicit action by the visitor:

* **OpenStreetMap tile servers** (OpenStreetMap Foundation): when the visitor clicks "Aceptar y mostrar el mapa interactivo" (accept and show the interactive map) in the directory map view, the visitor's browser downloads map images from `https://tile.openstreetmap.org`. The visitor's IP address, user agent and the coordinates/zoom of the requested tiles are sent. No connection is made unless the visitor accepts. Tile usage policy: https://operations.osmfoundation.org/policies/tiles/ - Privacy policy: https://osmfoundation.org/wiki/Privacy_Policy

The plugin contains no telemetry and makes no other calls to external services.

== Third-party libraries ==

* **Leaflet 1.9.4** (`assets/js/leaflet.js`, `assets/css/leaflet.css`, `assets/css/images/`), bundled locally. BSD-2-Clause license, GPL-compatible. The full license text ships in `assets/leaflet-LICENSE.txt`. Unminified source: https://github.com/Leaflet/Leaflet/tree/v1.9.4
* **WordPress icons** (`assets/icons/close.svg`, `globe.svg`, `institution.svg`, `send.svg`): SVG paths from `@wordpress/icons` 10.32.0, GPL-2.0-or-later. Source: https://github.com/WordPress/gutenberg/tree/trunk/packages/icons. The other icons in `assets/icons/` were drawn for this plugin and share its license.

== Source code ==

The minified scripts in `build/` are compiled from the human-readable sources shipped in `assets/js/admin.js`, `assets/js/centro-panel.js`, `assets/js/directorio.js` and `assets/js/network-sync.js` with `@wordpress/scripts` (version 37), using its default webpack configuration: `npx wp-scripts build assets/js/admin.js assets/js/centro-panel.js assets/js/directorio.js assets/js/network-sync.js`. The `@wordpress/*` packages they import (such as `i18n`, `a11y`, `api-fetch`, `dom-ready`, `editor`, `components` and `element`) are not bundled; they are loaded from WordPress core as script dependencies.

== Installation ==

1. Upload `radioedu-conecta_1.1.zip` from **Plugins -> Add New Plugin -> Upload Plugin**, or copy the `radioedu-conecta` folder to `/wp-content/plugins/`.
2. Activate the plugin from the **Plugins** screen (or from Network Admin when using Multisite).
3. Manage school profiles from **RadioEdu Centros** (each station's data is in the editor sidebar, "Centro de Radio" tab), answer proposals from **Colaboraciones (Matches)** and configure the map, notifications and the number of stations per user from **Ajustes** (Settings).
4. Insert the "Directorio Traspasando Fronteras" block (Widgets category) or the `[radioedu_directorio]` shortcode in any page to display the interactive directory. Only one directory per page is supported.

== Frequently Asked Questions ==

= Is it compatible with WordPress Multisite? =

Yes. It supports per-site and network activation, automatic provisioning of new sites while network-active (`wp_initialize_site`), removal of the plugin table when a site is deleted (`wpmu_drop_tables`) and a complete network-wide cleanup in `uninstall.php`.

= Can the station profiles be created from the sites of the network? =

Yes. In **Network Admin -> Settings -> RadioEdu Conecta**, the "Sincronizar fichas con los sitios de la red" button creates one profile per network site in the directory of the main site, or updates the existing one. The main site and archived, spam or deleted sites are skipped.

* The site name, URL and icon are refreshed on every sync.
* The tagline, admin email and site administrator are only used when the profile is created. They become the description, the coordinator email and the profile author.
* With the "Dar el rol Autor…" checkbox (checked by default), each profile author gets the Author role on the main site, unless they already have it or a higher role, so they can edit their profile. Unchecked, no role is added. The page remembers the last choice.
* Data the coordinator completes later is never overwritten, and trashed profiles are left alone.
* A hand-made profile with the same website is reused instead of duplicated.
* Profiles whose site no longer exists are kept and listed in the final summary.
* At the end, a table lists every synced station with a link to its profile, its source site, the result (created, updated, reused or skipped) and whether its coordinator got the Author role.

Only network administrators can run it, and the plugin must be active on the main site.

= Which languages are available? =

The interface is in Spanish. The plugin includes Catalan (`ca`), Valencian (`ca_valencia`), Galician (`gl_ES`) and Basque (`eu`) translations of its screens and scripts. WordPress uses them when the site or user language is one of these. Official language packs from translate.wordpress.org, when available, take priority over the bundled files.

= Are teachers' email addresses published on the website? =

No. For privacy and GDPR compliance, email addresses are never exposed in the HTML or in the public API. Collaboration proposals are handled through the plugin's internal system and server-side notifications.

= Can I limit how many stations each coordinator creates? =

Yes. In **RadioEdu Centros -> Ajustes -> Coordinadores**, set "Radios por usuario". Leave it empty (the default) for no limit, or set 0 so coordinators only edit the stations an administrator assigns to them. Administrators and editors have no limit, and trashed stations do not count.

= Can a match be proposed directly from a station profile? =

Yes. Both the directory cards and each station's profile page include the "Proponer match" button, which opens the proposal side panel on the same screen.

== Changelog ==

= 1.1 =
* Requires WordPress 7.1.
* New "Directorio Traspasando Fronteras" block, registered in PHP only (`supports.autoRegister`) and equivalent to the `[radioedu_directorio]` shortcode.
* Interface icons use the WordPress 7.1 Icons API (core collection plus a `radioedu-conecta` collection) instead of emoji.
* Multisite: new network admin button that creates or updates one station profile per network site in the main site's directory. It reuses hand-made profiles with the same website and lists the profiles of sites that no longer exist. A checkbox, checked by default, gives coordinators the Author role on the main site.
* New "Radios por usuario" setting (Ajustes -> Coordinadores) limits how many stations each coordinator (Author role) can create. Empty, the default, means no limit; 0 lets coordinators only edit stations assigned by an administrator. Administrators and editors have no limit.
* The public school page no longer repeats the theme's title, author/date and featured image above the profile. The plugin provides the page template: a block template (editable in the Site Editor) for block themes, or the theme header and footer around the profile for classic themes. Themes can override it.
* School data moves from the meta box, collapsed below the editor, to three sidebar panels ("Datos de la emisora", "Ubicación en el mapa" and "Coordinación"). The meta box remains for the classic editor.
* School meta fields are registered with `register_post_meta()` and sanitized on every write. The core REST API shows them only in the edit context to users who can edit the school, never in public responses, and XML-RPC cannot change them.
* Activation no longer creates demo content: the demo stations and the directory page are imported from **RadioEdu Centros -> Ajustes** only, and never overwrite a real school. Their descriptions are Paragraph blocks instead of Classic content.
* Security: proposals require a RadioEdu capability, only the receiving school can answer them, and password-protected schools are excluded from the directory and from proposals.
* The directory sorts by affinity before paginating, cancels outdated searches, announces loading errors with a retry option and works with plain permalinks.
* Personal data export and erasure run in batches, and network-wide tasks process sites in batches of 100.
* Scripts are compiled with `@wordpress/scripts` and translated with `wp_set_script_translations()`. Without `build/`, pages still load, without interactivity.
* Bundled Catalan, Valencian, Galician and Basque translations for PHP and scripts. Official language packs, when available, take priority.
* Accessibility: visible focus outlines, AA contrast for the accent and status colours, and the "Subir / Elegir Logo" button keeps its own text as its accessible name.
* Accessibility: every page has a single main landmark. The directory no longer adds a nested `<main>` or a `<header>` inside the content, and its results are a named region. With classic themes, the profile page provides `<main>` and the skip link target only when the theme's header does not.
* Admin notices use `wp_admin_notice()`.
* The full Leaflet license text ships in `assets/leaflet-LICENSE.txt`.
* The bundled demo images appear only on demo stations, not on real schools whose slug contains "san-jose" or "la-mar".
* The demo importer only updates the demo stations it created. It no longer takes over a station that shares a slug or podcast URL with a demo station, nor marks an existing directory page for deletion on uninstall.
* A database update that fails is retried later instead of being recorded as done.
* Privacy: the reply to a proposal now records who wrote it (database schema 1.0.3), so it is exported and erased for that person. Replies written before 1.1 have no author: erasure keeps them and says so.
* A database error while answering a proposal returns a retryable error instead of "already answered".
* In the directory, a late reply to a proposal sent before closing the panel no longer changes the panel opened for another station.
* The "Radios por usuario" limit also applies when a coordinator restores a station from the trash or saves a second "Add New" tab opened before reaching the limit.

= 1.0.0 =
* Interactive `[radioedu_directorio]` directory with faceted filters, affinity sorting, pagination and a locally bundled Leaflet map with prior consent for OpenStreetMap tiles.
* Individual station profile template with cover image, circular logo, metadata, description and an integrated match proposal panel.
* **Colaboraciones (Matches)** admin screen to review, accept or decline received and sent proposals; each proposal can be answered only once.
* Hardened security: anti-IDOR validation of the sending school, strict URL and DOM sanitization, prepared SQL queries with `%i` and full capability mapping for coordinators.
* Integration with the WordPress personal data exporters/erasers and complete cleanup in `uninstall.php`, including Multisite networks.

== Upgrade Notice ==

= 1.1 =
Requires WordPress 7.1. Adds the directory block, SVG icons and editor sidebar panels for school data. Demo content is no longer created on activation; import it from RadioEdu Centros -> Ajustes.
