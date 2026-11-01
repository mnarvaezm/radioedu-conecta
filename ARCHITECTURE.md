# ARCHITECTURE & TECHNICAL SPECIFICATION: RadioEdu Conecta

> **AI System Note:** This document provides a comprehensive, deterministic, machine-readable architectural blueprint of the `radioedu-conecta` WordPress plugin. It details runtime lifecycles, data contracts, security mitigations, frontend pipelines, and Architecture Decision Records (ADRs) for any artificial intelligence or human engineer contributing to this repository.

---

## 1. System Metadata & Project Overview

```yaml
system_id: radioedu-conecta
display_name: RadioEdu Conecta (Traspasando Fronteras)
version: "1.1"
db_schema_version: 1.0.3
php_version_requirement: ">=8.1"
wp_version_requirement: ">=7.1"
tested_up_to: "7.1"
license: GPL-2.0-or-later
text_domain: radioedu-conecta
psr4_namespace: RadioEdu\Conecta\
target_environment:
  - WordPress Single Site
  - WordPress Multisite (Educarex Network)
governing_standard:
  - WordPress Coding Standards (WPCS)
  - PHPStan Level 6+
  - Plugin Check (PCP - WordPress.org)
  - WCAG 2.2 AA Accessibility
  - RGPD / GDPR EU 2016/679
institutional_context:
  entity: RadioEdu - Consejería de Educación, Ciencia y Formación Profesional
  government: Junta de Extremadura (Spain)
  portal: https://radioedu.educarex.es
```

### Purpose & Value Proposition
**RadioEdu Conecta** is the official collaborative platform for the **Traspasando Fronteras** initiative. It enables primary, secondary, and vocational schools across Extremadura (Spain) and worldwide to:
1. Publicly register and showcase their school radio station profile, cover image, circular logo, equipment, genres, and educational stages.
2. Discover partner stations via a faceted real-time filter directory (with taxonomy-based affinity sorting and pagination) and an interactive geographical map.
3. Propose structured institutional twinning/collaboration ("Matches") directly from the directory or from the individual station profile page without exposing personal coordinator contact information to the public web.
4. Manage, accept, or decline collaboration proposals from the WordPress Administration Panel (`RadioEdu Centros -> Colaboraciones (Matches)`).

---

## 2. Directory Tree & File Inventory

```text
c:\radioedu-conecta\
├── radioedu-conecta.php                 # Main plugin entrypoint, WP.org headers, bootstrap & fallback autoloader
├── uninstall.php                        # Idempotent cleanup script for Single Site & Multisite uninstall
├── readme.txt                           # WordPress.org standard metadata & repository documentation
├── README.md                            # GitHub repository documentation
├── ARCHITECTURE.md                      # [THIS FILE] Machine-readable technical & architectural specification
├── BRIEFING.md                          # Functional & UX requirements derived from initial institutional mockup
├── AGENTS.md                            # Development rules, security directives, and DoD
├── TESTING-PROTOCOL.md                  # Test suites, Docker topology, and verification scripts
├── composer.json                        # PSR-4 autoload definition & dev dependencies
├── package.json                         # npm script configurations (@wordpress/scripts, wp-env, Playwright)
├── playwright.config.js                 # E2E config: extends @wordpress/scripts defaults, specs in tests/e2e
├── eslint.config.cjs                    # wp-scripts ESLint defaults + @wordpress/* packages loaded from core (not installed)
├── .wp-env.json                         # Reproducible E2E site (latest WordPress, PHP 8.1, plugin mounted)
├── .distignore                          # Build exclusion rules for release distribution
├── .gitignore                           # Git repository exclusions
├── docker-compose.test.yml              # Local testing environment on port 8090 (WP 7.1 + MariaDB 10.11)
├── radioedu-conecta_1.1.zip             # Release archive, always named radioedu-conecta_{version}.zip (POSIX paths, top folder radioedu-conecta/, ~9.4 MB with demo media; not in git)
├── src/
│   ├── Plugin.php                       # Core singleton orchestrator (lifecycle, hooks, Multisite, DB schema check)
│   ├── Icons.php                        # WordPress 7.1 Icons API: plugin icon collection + escaped wp_get_icon() output
│   ├── Admin/
│   │   ├── CentroEditorPanel.php        # Loads the centro sidebar panels of the block editor (build/centro-panel.js)
│   │   ├── CentroMetaBoxes.php          # Classic-editor meta box for the same fields (__back_compat_meta_box)
│   │   ├── NetworkSyncPage.php          # Network Admin → Settings → RadioEdu Conecta (sync button, build/network-sync.js)
│   │   ├── ColaboracionesAdminPage.php  # Admin panel to view, accept, or decline match proposals
│   │   ├── DemoDataImporter.php         # Demo stations importer & synchronizer (6 sample stations + images)
│   │   └── Settings.php                 # WordPress Settings API options (map defaults, notification flags, demo sync)
│   ├── Capabilities/
│   │   ├── CentroLimit.php              # "Radios por usuario" limit: grants create_radioedu_centros on the fly
│   │   └── RoleCapabilities.php         # Custom capabilities management for admin, editor, and author roles
│   ├── Database/
│   │   └── ColaboracionesTable.php      # Custom table DDL via dbDelta() and DB lifecycle management
│   ├── Emails/
│   │   └── NotificationService.php      # wp_mail() notification pipeline for proposals and match responses
│   ├── Frontend/
│   │   └── DirectorioShortcode.php      # Shortcode [radioedu_directorio], block radioedu-conecta/directorio (render callback), single profile filter & assets
│   ├── PostTypes/
│   │   ├── CentroPostType.php           # Custom Post Type registration (`radioedu_centro`)
│   │   └── CentroMeta.php               # register_post_meta() for every `_radioedu_*` key (type, sanitizer, edit-context REST, auth)
│   ├── Privacy/
│   │   └── PrivacyPolicy.php            # RGPD/GDPR personal data exporter, eraser, and policy notice
│   ├── Network/
│   │   └── NetworkSync.php              # Multisite: one ficha on the main site per network site (create/update/reuse)
│   ├── Rest/
│   │   ├── CentrosController.php        # REST Controller (`GET /radioedu-conecta/v1/centros`)
│   │   ├── ColaboracionesController.php # REST Controller (`POST|PATCH|GET /radioedu-conecta/v1/colaboraciones`)
│   │   └── NetworkSyncController.php    # REST Controller (`POST /radioedu-conecta/v1/network-sync`, Multisite only)
│   ├── Support/
│   │   └── BlockMarkup.php              # Serialized Paragraph blocks for content the plugin creates
│   └── Taxonomies/
│       └── TaxonomiesRegistrar.php      # 5 faceted taxonomies registration & capability bindings
├── templates/
│   ├── directorio.php                   # Frontend directory view: Banner, Facet Bar, Grid, Map
│   ├── single-centro.php                # Single station profile view: Cover, Identity, Tags, Metadata, Description
│   ├── theme-single-centro.php          # Classic-theme page template: theme header/footer around the profile only
│   └── match-drawer.php                 # Shared "Solicitud de match" drawer (accessible dialog) used by both views
├── assets/
│   ├── css/
│   │   ├── admin.css                    # Centro editor panels, classic meta box, logo preview and Colaboraciones statuses
│   │   ├── directorio.css               # Styling for directory UI, facet filters, card grid, single profile, and drawer
│   │   ├── images/                      # Leaflet marker and layer control images
│   │   └── leaflet.css                  # Self-contained Leaflet 1.9.4 CSS (zero CDN)
│   ├── icons/                           # Fill-based SVGs of the `radioedu-conecta` icon collection (core lacks them)
│   ├── images/
│   │   └── demo/                        # Local cover photos and circular logos for the 6 demo radio stations
│   ├── js/
│   │   ├── admin.js                     # Source: WP Media Uploader connector for the classic meta box logo
│   │   ├── centro-panel.js              # Source: block editor sidebar panels of the centro fields (registerPlugin)
│   │   ├── directorio.js                # Source: frontend reactive controller (DOM builder, Leaflet manager, Drawer)
│   │   ├── network-sync.js              # Source: network admin sync button (batched apiFetch loop, progress, summary)
│   │   └── leaflet.js                   # Self-contained Leaflet 1.9.4 JavaScript runtime (zero CDN)
│   └── leaflet-LICENSE.txt              # Leaflet 1.9.4 BSD-2-Clause license, covering leaflet.js, leaflet.css and css/images/
├── blocks/
│   └── directorio/
│       └── block.json                   # Metadata of the radioedu-conecta/directorio block (title, keywords, supports, style)
├── build/                               # Committed `npm run build` output (@wordpress/scripts); the only JS the plugin loads
│   ├── admin.js / admin.asset.php       # Compiled admin.js + generated dependencies (wp-i18n) and version hash
│   ├── centro-panel.js / centro-panel.asset.php # Compiled panels + dependencies (wp-editor, wp-plugins, wp-components…)
│   ├── directorio.js / directorio.asset.php # Compiled directorio.js + dependencies (wp-a11y, wp-i18n)
│   └── network-sync.js / network-sync.asset.php # Compiled network sync + dependencies (wp-api-fetch, wp-a11y, wp-dom-ready, wp-i18n)
├── languages/
│   ├── radioedu-conecta.pot             # GNU gettext internationalization template (Spanish source strings)
│   ├── radioedu-conecta-{locale}.po     # Editable translations: ca, ca_valencia, gl_ES, eu
│   ├── radioedu-conecta-{locale}.mo / .l10n.php # Compiled PHP translations (WordPress prefers .l10n.php)
│   └── radioedu-conecta-{locale}-{md5}.json # Script translations, one per build/*.js (md5 of its relative path)
├── tests/                               # PHPUnit (single site + Multisite) and tests/e2e Playwright specs (not shipped)
└── vendor/
    └── autoload.php                     # Composer generated PSR-4 class loader
```

---

## 3. Core Architectural Principles & Institutional Constraints

### 3.1 Zero Third-Party CDN Dependency & Explicit Map Tile Consent
* **Context:** Extremadura's regional educational network (*Educarex*) operates behind strict institutional proxies and firewall rules that block external unverified CDNs. Furthermore, WordPress.org Official Guidelines and GDPR forbid loading external resources without explicit user consent.
* **Implementation:**
  - The Leaflet map engine (`leaflet.js` v1.9.4, `leaflet.css` v1.9.4) and all demo station images are vendored locally in `/assets/`.
  - External OpenStreetMap cartography tiles (`tile.openstreetmap.org`) are **never** loaded automatically on page load; the user must explicitly click **"Aceptar y mostrar el mapa interactivo"** (`#radioedu-btn-accept-map`) before any connection to OpenStreetMap servers is established.

### 3.2 Asymmetric RGPD / GDPR Privacy Model & Core Privacy Tools
* **Constraint:** School radio station coordinators are public school teachers; their email addresses must never be indexed, scraped, or visible in public DOM/JSON responses.
* **Implementation:**
  - Coordinators' emails and direct names are saved in `_radioedu_coordinador_email` and `_radioedu_coordinador_nombre` post meta.
  - `CentrosController::prepare_item_for_response()` **explicitly omits** these fields from public REST API payloads.
  - In the core `/wp/v2/centros` endpoint, the centro meta exists only in the `edit` context (which requires editing rights), and `CentroMeta::hide_private_meta()` strips it from `edit` responses of centros the user cannot edit, so a coordinator listing every centro with `context=edit` never sees other coordinators' data.
  - External coordinators interact with other schools exclusively through the platform's proposal system. The plugin dispatches transactional emails on the server side (`NotificationService`), revealing the receiving coordinator's contact details (`Nombre <email>`) only once a proposal is explicitly **accepted** (`estado === 'aceptada'`). When a centro has no coordinator email, `NotificationService::get_center_contact_email()` uses the email of the centro author's account. This also applies after an erasure request removed the coordinator email: the account is core data the eraser does not remove, and keeping it in use is a deliberate decision (audit finding F-02).
  - `PrivacyPolicy.php` integrates with WordPress Core's GDPR subsystem (`wp_add_privacy_policy_content`, `wp_privacy_personal_data_exporters`, `wp_privacy_personal_data_erasers`) to export or erase/anonymize coordinator data upon request. Proposals are matched by the account that sent them (`usuario_emisor_id`) and by the account that answered them (`usuario_respuesta_id`). Replies written before 1.1 have no author: the eraser leaves them, reports them as retained and asks the administrator to review them, instead of attributing them to whoever coordinates the centro now. Proposals have no automatic expiry: they are kept as shared history until an erasure request or uninstall, a decision recorded for audit finding F-22, and the suggested privacy policy text says so.

### 3.3 Strict Output Escaping, Zero `innerHTML`, and URL Validation
* **Server-side (PHP):** Strict "Late Escaping" rule. Variables are escaped at the exact moment of output using `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`, or `wp_json_encode()`. All SQL queries use `$wpdb->prepare()` with `%i` identifier placeholders for table names.
* **Profile description:** the one exception. The description is the output of the core `the_content` filters, which the profile prints as themes print `the_content()`. kses already cleaned it on save for users without `unfiltered_html`, and the embed iframes come from core's own handlers. Running `wp_kses_post()` on it again stripped those players (audit finding F-15). No iframe allowlist is added, and the station data around it is still escaped.
* **Client-side (JavaScript):** `assets/js/directorio.js` (compiled to `build/directorio.js`) prohibits `innerHTML` and HTML string concatenation. All dynamic card nodes, badges, and Leaflet map popups (`marker.bindPopup(popupNode)`) are assembled using `document.createElement()` and `textContent`. All external URLs are validated via `sanitizeHttpUrl()` (`http:` / `https:` whitelist) and escaped before CSS `url(...)` injection. Icons the script needs (favourite stars, send) are server-rendered SVGs inside `<template id="radioedu-icon-templates">` that the script clones with `cloneNode()`. Server-side, the profile cover URL is percent-encoded for CSS context (`DirectorioShortcode::css_safe_url()`) because `esc_url()` keeps quotes and parentheses that the browser decodes before parsing CSS.
* **Without JavaScript:** the directory template prints the real number of published stations and a notice linking to the post type archive (`/centros-radioedu/`), which the theme lists without JavaScript. `directorio.js` hides the notice when it starts, so it stays visible when the script is missing (no `build/`) or fails to load.
* **Map markers:** the cards load 24 at a time, so the map view fetches the results of the current search itself, 100 per request, and draws up to 1,000 markers, the size of the largest expected network (`MAP_MAX_CENTROS` in `directorio.js`). A `role="status"` note says how many are shown when the ceiling leaves some out. A new search cancels the pending map requests. Every page of a search, more cards or map pages, uses the filters and order captured when that search started (`appliedSearch`), not what the form holds later: the user may edit it without searching (second review R-04).


### 3.4 Landmarks
The directory and the profile render inside the theme's page, so they never add page-level landmarks of their own:
* **Directory:** no `<main>` and no `<header>`. Its two named regions are `<section aria-label="Filtros del directorio">` and `<section aria-labelledby="radioedu-results-heading">` ("Resultados (N centros)").
* **Profile:** the profile is an `<article>`. The page's single `<main>` comes from the block template, the theme, or the classic template when the theme's header left none open (section 4.1).
* **Text colours** that sit on the theme background rather than on a white card ("Ordenar por") use `#475569`, which keeps 4.5:1 on tinted backgrounds such as Twenty Twenty's beige.

---

## 4. Data Models & Database Schemas

### 4.1 Custom Post Type: `radioedu_centro`
* **Registered in:** [src/PostTypes/CentroPostType.php](file:///c:/radioedu-conecta/src/PostTypes/CentroPostType.php)
* **Features:** `title`, `editor` (station description), `thumbnail` (station header/cover image), `excerpt` (brief intro), `author`, `custom-fields` (required by the REST API to expose the registered meta to the block editor panels; the plugin keys are protected, so the Custom Fields panel never lists them).
* **Single Profile Template:** Rendered dynamically via `DirectorioShortcode::render_single_centro_profile()` using [templates/single-centro.php](file:///c:/radioedu-conecta/templates/single-centro.php), featuring the full-width cover header, left-aligned station logo, title, flag, location, colored tags, metadata cards, action buttons (including direct Match Drawer trigger), and descriptive text below.
* **Back link:** "Volver al directorio" uses `DirectorioShortcode::get_directory_url()`. The page recorded in `radioedu_conecta_directory_page_id` is used while it is published, without password, and still holds the shortcode or the block, directly or inside a synced pattern (`core/block` referencing a `wp_block`, opened up to five levels and each one once; second review R-03). When the directory renders on a page and no valid page is recorded, that page is recorded. Otherwise the page with the `traspasando-fronteras` slug is used, then the home page. The `radioedu_conecta_directory_url` filter (`$url`, `$page_id`) can change the result.
* **Profile Page Template (no theme duplicates):** the profile already shows the title, the featured image (as cover) and the station data, so the page around it only keeps the theme header and footer.
  - **Block themes:** `DirectorioShortcode::register_profile_block_template()` registers `radioedu-conecta//single-radioedu_centro` with `register_block_template()`. It contains the header part, `core/post-content` in a `<main>` group (980px) and the footer part, with no post title, date, author or featured image. It is the most specific template of the hierarchy; a theme template with the same slug or a Site Editor customization takes precedence.
  - **Classic themes:** `DirectorioShortcode::use_profile_page_template()` (`template_include`) swaps only the generic `single.php` / `singular.php` / `index.php` for [templates/theme-single-centro.php](file:///c:/radioedu-conecta/templates/theme-single-centro.php) (`get_header()`, the profile, `get_footer()`). A theme or page-builder template made for centros is kept, and themes can override the file with `radioedu-conecta/single-radioedu_centro.php`.
    - **Main landmark:** most classic themes open `<main>` in the single template that this file replaces (Twenty Twenty), and a few open it in `header.php` (Twenty Twenty-One, YOOtheme). The template buffers `get_header()` and passes it to `DirectorioShortcode::profile_main_landmark()`, which reads it with `WP_HTML_Tag_Processor`. The profile goes in `<main>` only when the header left none open, so the page always has exactly one.
    - **Skip link:** when the theme's skip link (`a.skip-link`) points to an id the header does not contain, that `<main>` takes the id (`#site-content` in Twenty Twenty).
  - **Fallback:** on the profile page, the `has_post_thumbnail` and `post_thumbnail_html` filters hide the featured image from the theme for the queried centro only. They do not run inside `wp_head`, so Open Graph and SEO tags keep the image. The profile reads its cover with `get_the_post_thumbnail_url()`, which the filters do not affect.

#### Meta Keys Schema (`wp_postmeta`)
Every key is declared with `register_post_meta()` in [src/PostTypes/CentroMeta.php](file:///c:/radioedu-conecta/src/PostTypes/CentroMeta.php), so its sanitizer runs on **every** write (editor panels, classic meta box, demo importer, WP-CLI, imports, other plugins).

* **Editable keys** (every key except `_radioedu_logo_url`, `_radioedu_cover_url` and `_radioedu_conecta_demo`) are shown in the core `/wp/v2/centros` endpoint **only in the `edit` context**, which the block editor uses. Their `auth_callback` (`CentroMeta::can_edit_meta()`) allows writes only to users who can `edit_post` the centro, and denies XML-RPC. The REST schema only fixes the type: the editor re-sends every key on save, so values are cleaned by the sanitizers rather than rejected.
* **A new logo** sent through REST must be an image the user can read (`read_post`). `CentroMeta::validate_rest_logo()` (`rest_pre_insert_radioedu_centro`) returns `400 radioedu_conecta_invalid_logo` otherwise. An unchanged logo chosen by another user is not re-checked. The classic meta box applies the same check.
* **Locked keys** (written only by the demo importer) keep `show_in_rest => false` and `auth_callback => '__return_false'`.
* `_radioedu_disponible_colaborar` defaults to `'1'` (available) when unset.

The last column refers to the plugin's own public `/radioedu-conecta/v1/centros` endpoint.

| Meta Key | Data Type | Sanitization / Validation | Description | Public in REST? |
| :--- | :--- | :--- | :--- | :---: |
| `_radioedu_logo_id` | `int` | `absint()` + must be an image attachment (a new one must also be readable by the user) | Attachment ID for the circular radio station logo | Yes (URL) |
| `_radioedu_logo_url` | `string` (URL) | `esc_url_raw()` (http/https) | Fallback URL for bundled demo station logos | Yes (URL) |
| `_radioedu_cover_url` | `string` (URL) | `esc_url_raw()` (http/https) | Fallback URL for bundled demo station cover images | Yes (URL) |
| `_radioedu_bandera` | `string` | `sanitize_key()` + whitelist | Region/Country flag code (`es-ex`, `es-ct`, `ie`, etc.) | Yes |
| `_radioedu_lat` | `float` (stored as `string`) | Clamped `[-90.0, 90.0]`, non-numeric becomes `''` | Latitude coordinates for Leaflet map pin | Yes |
| `_radioedu_lng` | `float` (stored as `string`) | Clamped `[-180.0, 180.0]`, non-numeric becomes `''` | Longitude coordinates for Leaflet map pin | Yes |
| `_radioedu_podcast_url` | `string` (URL) | `esc_url_raw()` (http/https) | External RSS / iVoox / Educarex podcast feed URL | Yes |
| `_radioedu_web_centro` | `string` (URL) | `esc_url_raw()` (http/https) | Official school website URL | Yes |
| `_radioedu_edades` | `string` | `sanitize_text_field()` | Student age range (e.g. `10-14 años`) | Yes |
| `_radioedu_coordinador_nombre` | `string` | `sanitize_text_field()` | Full name of teacher coordinator | **NO (Admin only)** |
| `_radioedu_coordinador_email` | `string` | `sanitize_email()` | Notification email address of coordinator | **NO (Admin only)** |
| `_radioedu_disponible_colaborar`| `string` (`'1'` or `'0'`, default `'1'`) | `'1'` or `''` → `'1'`, anything else → `'0'` | Availability flag for receiving new match proposals | Yes (Boolean) |
| `_radioedu_conecta_demo` | `string` (`'1'` or `''`) | `'1' === $val ? '1' : ''` | Marks content created by the demo importer | No |

### 4.2 Faceted Taxonomies
* **Registered in:** [src/Taxonomies/TaxonomiesRegistrar.php](file:///c:/radioedu-conecta/src/Taxonomies/TaxonomiesRegistrar.php)
* **Capabilities:** `manage_terms`, `edit_terms`, `delete_terms` mapped to `manage_radioedu`; `assign_terms` mapped to `edit_radioedu_centros`.
* Bound to post type `radioedu_centro`:
  1. `radioedu_etapa` (`hierarchical => true`): Educational stage (Infantil, Primaria, Secundaria, Bachillerato, FP).
  2. `radioedu_idioma` (`hierarchical => false`): Broadcast language (Español, Inglés, Francés, Portugués, Catalán, Valenciano).
  3. `radioedu_tematica` (`hierarchical => false`): Content themes (Medioambiente, Convivencia, Igualdad, Música, Cultura, Rural, Proyectos europeos).
  4. `radioedu_formato` (`hierarchical => false`): Audio formats (Podcast, Entrevistas, Magacín, Noticias, Ficción sonora).
  5. `radioedu_localizacion` (`hierarchical => true`): Geographical taxonomy.

### 4.3 Custom Database Table: `{$wpdb->prefix}radioedu_colaboraciones`
* **Managed in:** [src/Database/ColaboracionesTable.php](file:///c:/radioedu-conecta/src/Database/ColaboracionesTable.php)
* **Schema Definition (MySQL / MariaDB via `dbDelta`):**

```sql
CREATE TABLE {$wpdb->prefix}radioedu_colaboraciones (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    centro_emisor_id bigint(20) unsigned NOT NULL,
    centro_receptor_id bigint(20) unsigned NOT NULL,
    usuario_emisor_id bigint(20) unsigned NOT NULL,
    idea_proyecto text NOT NULL,
    curso_edades varchar(100) NOT NULL DEFAULT '',
    idiomas varchar(100) NOT NULL DEFAULT '',
    fechas_aprox varchar(150) NOT NULL DEFAULT '',
    estado varchar(20) NOT NULL DEFAULT 'pendiente',
    respuesta_mensaje text DEFAULT NULL,
    usuario_respuesta_id bigint(20) unsigned NOT NULL DEFAULT 0,
    created_at datetime NOT NULL,
    updated_at datetime NOT NULL,
    PRIMARY KEY  (id),
    KEY centro_emisor_id (centro_emisor_id),
    KEY centro_receptor_id (centro_receptor_id),
    KEY usuario_emisor_id (usuario_emisor_id),
    KEY usuario_respuesta_id (usuario_respuesta_id),
    KEY estado (estado)
) {$charset_collate};
```
* **Schema updates:** `create_table()` saves `radioedu_conecta_db_version` only after `SHOW COLUMNS` confirms every column the plugin uses and `SHOW INDEX` confirms every index (dbDelta() adds a column and its index in separate statements, and the second can fail on its own). If the database refuses the DDL, the version stays behind and `Plugin::maybe_update_db_schema()` retries on a later request, at most every 5 minutes (`radioedu_conecta_schema_retry` transient).
* **Who answered:** `usuario_respuesta_id` (since schema 1.0.3) records the account that accepted or declined the proposal, so the reply can be exported and erased for that person. Older replies keep 0.

---

## 5. Security & Authorization Matrix

### 5.1 Capabilities Matrix
* **Configured in:** [src/Capabilities/RoleCapabilities.php](file:///c:/radioedu-conecta/src/Capabilities/RoleCapabilities.php)

| Capability | Administrator | Editor | Author (Coordinator) | Subscriber / Visitor |
| :--- | :---: | :---: | :---: | :---: |
| `manage_radioedu` | ✅ | ❌ | ❌ | ❌ |
| `edit_radioedu_centros` | ✅ | ✅ | ✅ | ❌ |
| `edit_published_radioedu_centros` | ✅ | ✅ | ✅ | ❌ |
| `edit_private_radioedu_centros` | ✅ | ✅ | ❌ | ❌ |
| `publish_radioedu_centros`| ✅ | ✅ | ✅ | ❌ |
| `read_private_radioedu_centros` | ✅ | ✅ | ❌ | ❌ |
| `delete_radioedu_centros` | ✅ | ✅ | ✅ | ❌ |
| `delete_published_radioedu_centros` | ✅ | ✅ | ✅ | ❌ |
| `delete_private_radioedu_centros` | ✅ | ✅ | ❌ | ❌ |
| `edit_others_radioedu_centros` | ✅ | ✅ | ❌ | ❌ |
| `delete_others_radioedu_centros` | ✅ | ❌ | ❌ | ❌ |
| `propose_radioedu_colaboracion` | ✅ | ✅ | ✅ | ❌ |
| `create_radioedu_centros` (dynamic, see 5.2) | ✅ | ✅ | ✅ up to the limit | ❌ |

### 5.2 Radios per User Limit (`CentroLimit`)
* **Setting:** `max_centros_per_user` in `radioedu_conecta_settings` (**Ajustes → Coordinadores → Radios por usuario**).
  - Empty (the default) means no limit. A whole number of 0 or more is the maximum, and 0 means coordinators create none.
  - `Settings::sanitize_settings()` keeps the previous value and shows a settings error on invalid input, so a typo never removes the limit.
  - It is a per-site setting in Multisite.
* **Enforcement:** the post type maps `create_posts` to `create_radioedu_centros`, which no role stores.
  - `CentroLimit::grant_create_cap()` (`user_has_cap`) grants it to users with `edit_radioedu_centros` who either manage every centro (`edit_others_radioedu_centros` / `manage_radioedu`) or are under the limit. It reads the other capabilities from `$allcaps` to avoid recursion.
  - Being a capability, it hides **Añadir nuevo** (menu, list, admin bar) and blocks `post-new.php`, REST `POST /wp/v2/centros` (403) and XML-RPC.
  - The capability only guards creation, so status changes are checked too. A change from a status that does not count to one that counts is refused for limited users at the limit: `CentroLimit::check_rest_status_change()` (`rest_pre_insert_radioedu_centro`, 403 `radioedu_centro_limit`) covers the block editor, including an auto-draft opened in a second tab before the limit was reached, and `CentroLimit::check_untrash()` (`pre_untrash_post`) covers the **Restaurar** link. Without them a coordinator could trash a centro, create another and restore the first (audit finding S-01). Every other save (classic editor, Quick Edit, XML-RPC, `wp_update_post()`) goes through `CentroLimit::keep_uncounted_status()` (`wp_insert_post_data`): the centro keeps its auto-draft or trash status, what was typed is saved, and its edit screen shows the limit notice (second review R-01). Concurrent saves are serialized per author and site with a MySQL/MariaDB named lock (`GET_LOCK`, `CentroLimit::quota_lock_name()`): the check counts the stored centros plus the places reserved in this request, and the lock is released once the centro is saved (`wp_insert_post`) or at `shutdown`. A request waits up to 10 seconds for another one and refuses if it does not finish, so two creations at once cannot both take the last place.
* **Counting:**
  - Counted: centros authored by the user in `publish`, `future`, `draft`, `pending` or `private`. Trash and `auto-draft` (created when opening the editor) do not count.
  - The query stops at the limit (`posts_per_page`). Results are cached per request, keyed by site, user and limit so `switch_to_blog()` never reuses another site's count, and flushed on `clean_post_cache` for centros.
* **Exception:** administrators and editors may still assign more centros to a coordinator by setting them as author. The limit only stops self-service creation.
* **Notice:** coordinators at the limit see an info notice on the centros list (`admin_notices`).

---

## 6. REST API Contracts & Endpoints

### 6.1 `GET /wp-json/radioedu-conecta/v1/centros`
* **Permission:** Public (`__return_true`), strictly validated and sanitized.
* **Query Parameters:**
  - `page` (`int`, default: 1): Current page.
  - `per_page` (`int`, default: 24, max: 100): Centros per page.
  - `search` (`string`): Free-text search matching title/description.
  - `orden` (`enum`: `afines`, `recientes`, `alfa`): Ordering criteria (`afines` prioritizes available centers and ranks by shared taxonomy tags with the reference center, which is sorted last; the ordering runs in SQL, before pagination).
  - `afines_a` (`int`, default: 0): Optional reference center ID. When omitted, the endpoint uses the logged-in coordinator's own center; global managers (`manage_radioedu` / `edit_others_radioedu_centros`) get no reference at all: no arbitrary center is pushed to the end, and no center's terms drive the affinity score, so the results keep the title order. The default is resolved once, in `get_items()`. The bundled directory JavaScript relies on this default and does not send the parameter.
  - `etapa`, `idioma`, `tematica`, `formato`, `ubicacion` (`string`): Taxonomy slugs.

### 6.2 `GET /wp-json/radioedu-conecta/v1/colaboraciones`
* **Permission:** Authenticated user with `propose_radioedu_colaboracion` or `manage_radioedu`.
* **Behavior:** Returns collaboration proposals where the current user is the sender (`usuario_emisor_id`) or manages either the sending (`centro_emisor_id`) or receiving (`centro_receptor_id`) center: authors it, or is its coordinator by email, whatever its status. Users who manage every centro (`manage_radioedu` or `edit_others_radioedu_centros`) get all records. The rule is applied in SQL, with no cap on the number of centros. The match panel's sender selector lists up to 2,000 managed published centros (`ColaboracionesController::MAX_MANAGED_CENTERS`), sorted by title: twice the largest expected network of about 1,000 school radios, so administrators and editors find every station.
* **Pagination:** `page` (default 1) and `per_page` (1–100, default 100), newest first with `created_at DESC, id DESC`, a deterministic order. Pages use `OFFSET`, not a cursor: they do not overlap while the set stays the same, but a proposal created or erased between two requests shifts the following pages by one. A page past the last one returns an empty list; it is never turned into an offset, so a huge page number cannot overflow (second review R-05). The `X-WP-Total` and `X-WP-TotalPages` headers give the count with the same visibility rule. The **Colaboraciones (Matches)** admin screen shows 20 proposals per page.

### 6.3 `POST /wp-json/radioedu-conecta/v1/colaboraciones`
* **Permission:** Authenticated user with `current_user_can('propose_radioedu_colaboracion')`.
* **IDOR & Integrity Validation:**
  - `centro_emisor_id` !== `centro_receptor_id` (cannot propose to self).
  - Both centers must have `post_type === 'radioedu_centro'` and `post_status === 'publish'`.
  - `ColaboracionesController::user_can_manage_center( $centro_emisor_id, $user_id )` must be `true` (prevents spoofing another school's station).
  - Receiving center must be available (`_radioedu_disponible_colaborar !== '0'`).
* **Rate Limiting:** Fixed 1-hour window allowing a maximum of 20 proposals per user per hour (`ColaboracionesController::RATE_LIMIT_PER_HOUR`, transient `radioedu_rate_{user_id}`). Returns `HTTP 429` if exceeded. The limit is strict: the counter is read, the proposal stored and the counter written under the user's MySQL/MariaDB named lock (`GET_LOCK`, `ColaboracionesController::rate_lock_name()`), so proposals sent at the same moment cannot exceed it (audit finding S-02). A proposal waits up to 10 seconds for another one of the same user and is refused with the 429 if it does not finish. The notification email is sent after the lock is released.
* **Notification:** the `201` response includes `notificacion`: `sent` when `wp_mail()` accepted the email (which does not prove delivery), `failed` when it could not be sent (no contact email or a mail error) and `disabled` when the setting turns proposal emails off. The `message` matches it, so the sender is never promised an email that was not sent.

### 6.4 `PATCH /wp-json/radioedu-conecta/v1/colaboraciones/{id}`
* **Permission:** Target station coordinator (`ColaboracionesController::user_can_manage_center( $centro_receptor_id )`) or Admin (`manage_radioedu`).
* **Result:** Updates table record (`aceptada` or `rechazada`), sets `updated_at`, and triggers `NotificationService::send_status_update_email()`. The response includes `notificacion` with the same values as for proposals, and the **Colaboraciones (Matches)** screen shows a success or warning notice to match.
* **Single response:** Only `pendiente` proposals can be answered (`ColaboracionesTable::resolve_pending()` conditions the `UPDATE` on `estado = 'pendiente'`). Unknown IDs return `404 radioedu_not_found`; already answered proposals return `409 radioedu_already_resolved` and no email is re-sent. The admin screen (`admin-post.php?action=radioedu_respond_match`) shares the same logic through `ColaboracionesController::respond_to_proposal()`.

---

### 6.5 `POST /wp-json/radioedu-conecta/v1/network-sync` (Multisite only)
* **Permission:** main site of a network only, otherwise `404 radioedu_conecta_not_main_site`. Requires `current_user_can( 'manage_network_options' )`, otherwise 401 when logged out or 403. The REST nonce is sent by `wp.apiFetch`.
* **Arguments:**
  - `offset` (`integer`, minimum 0, default 0, `absint`).
  - `grant_author_role` (`boolean`, `rest_sanitize_boolean`). When omitted, the stored choice is used. The first batch (offset 0) stores it in the network option `radioedu_conecta_sync_grant_author` (default `'1'`), which `uninstall.php` deletes.
* **Response:** `processed`, `total`, `next_offset`, `done`, `created`, `updated`, `reused`, `skipped`, `authors_added`, `synced[] {id, title, blog_id, site_url, outcome, author_added, edit_url}` (this batch), `errors[] {blog_id, message}`, `missing[] {id, title, blog_id, edit_url}` (last batch only). See section 8.

## 7. Frontend & Admin Workflows

### 7.1 Drawer Offcanvas Workflow (Directory & Single Station Profile)
1. User clicks **"Proponer match"** on any station card in `[radioedu_directorio]` **or** directly on the station's individual profile page (`single-radioedu_centro`).
2. If visitor is **unauthenticated**, a notice prompts login and redirects to `/wp-login.php`.
3. If user is authenticated without `canPropose`, an authorization alert is displayed.
4. If user is an authorized coordinator/editor/admin:
   - The drawer opens smoothly (`.is-open` applied to `#radioedu-match-drawer` and `#radioedu-drawer-overlay`).
   - Target station ID and name/location are pre-populated into `#radioedu-match-receptor` and `#radioedu-match-receptor-txt`.
   - Emisor center dropdown (`#radioedu-match-emisor`) lists the user's managed centers.
5. On submission, client issues `fetch(restUrl + 'colaboraciones', { method: 'POST' })` with `X-WP-Nonce`.

### 7.2 Admin Match Management (`ColaboracionesAdminPage.php`)
1. Accessible under **RadioEdu Centros → Colaboraciones (Matches)**.
2. Displays a table of received and sent proposals for the current coordinator's centers (or all proposals for `manage_radioedu`).
3. For pending proposals where the user manages the receiving center, provides an inline response textarea and **Aceptar** / **Declinar** buttons protected by `check_admin_referer`.
4. Once accepted, displays direct `mailto:` links for both coordinators and sends an acceptance email containing the coordinator's contact information.
5. Result notices (here and in **Ajustes**) are printed with core `wp_admin_notice()`. The requirement notices of the main file use it too, with a classic-markup fallback for WordPress < 6.4, where that check is the only code that runs.

### 7.3 Directory Block (`radioedu-conecta/directorio`)
1. Registered from [blocks/directorio/block.json](file:///c:/radioedu-conecta/blocks/directorio/block.json) by `DirectorioShortcode::register_block()`, which only adds the PHP `render_callback`. block.json holds the title, description, keywords (translated with the `radioedu-conecta` text domain and the "block title", "block description" and "block keyword" contexts), the `widgets` category, the `microphone` icon, the supports and the style. With `supports.autoRegister` (WordPress 7.0+) core registers the block in the editor and previews it with ServerSideRender, so the plugin ships no editor JavaScript.
2. `render_block()` wraps the shortcode output in `get_block_wrapper_attributes()` (align `wide`/`full`). `"multiple": false` because the directory markup uses fixed IDs.
3. The block.json `style` (handle `radioedu-directorio-css`) loads `directorio.css` in the editor iframe; front-end assets are registered on `init` and enqueued in the `<head>` when the post contains the block or the shortcode. The shortcode `[radioedu_directorio]` remains supported.

### 7.4 SVG Icons (WordPress 7.1 Icons API)
1. UI icons come from `wp_get_icon()`: the core `core/*` collection when WordPress ships a suitable icon, and the plugin collection `radioedu-conecta` (registered in `Icons::register()` from `assets/icons/*.svg`) for the rest: close, globe, graduation, headphones, institution, map, microphone, send.
2. The core sanitizer only keeps `<svg>`, `<path>` and `<polygon>` with fill attributes, so plugin icons are fill-based (stroke icons would render empty). `Icons::render()` escapes the markup again with an SVG `wp_kses()` allowlist.
3. Icons are decorative (`aria-hidden="true"`, `focusable="false"`) unless a `label` is given. `wp_get_icon()` does not set a fill colour, so `.radioedu-svg-icon { fill: currentcolor; }` makes them follow the text colour.
4. Region flags remain emoji: they are data, not interface icons.

### 7.5 Centro Editor Panels (block editor sidebar)
1. `CentroEditorPanel::enqueue_assets()` (`enqueue_block_editor_assets`) loads `build/centro-panel.js` only in the block editor of `radioedu_centro`. It passes the flag whitelist (translated in PHP) and the demo logo URL through `wp_add_inline_script()` + `wp_json_encode()`.
2. The script registers a plugin with three `PluginDocumentSettingPanel` panels in the **Centro de Radio** tab:
   - **Datos de la emisora:** logo through `MediaUpload`, flag, ages, podcast, website, availability.
   - **Ubicación en el mapa:** latitude and longitude.
   - **Coordinación:** name and email, with the GDPR notice.
3. The fields edit the post's `meta` (`editPost`), so the editor saves them with the centro in the same REST request, under the rules of section 4.1.
4. The main panel opens automatically the first time each user edits a centro, tracked with the `radioedu-conecta/centroPanelOpened` preference. Afterwards the editor remembers whether the user closed it.
5. The classic meta box (`CentroMetaBoxes`) is registered with `__back_compat_meta_box`, so only the classic editor shows it, and `admin.js` loads only there.
6. The logo controls have no `<label for>`, so the buttons keep their own text as accessible name (WCAG 2.5.3). After **Eliminar logo**, focus returns to **Subir / Elegir Logo**.

### 7.6 Internationalization (bundled translations)
1. The source strings are Spanish. `languages/` ships Catalan (`ca`), Valencian (`ca_valencia`), Galician (`gl_ES`) and Basque (`eu`), all with `nplurals=2`.
2. **PHP:** `radioedu_conecta_load_textdomain()` (`init`, priority 0, in the main file, outside the requirements check so the requirement notices are translated too) calls `load_plugin_textdomain()` to register the folder.
   - WordPress 7.1 loads translations just in time. It looks in `wp-content/languages/plugins` first and in the registered folder last, so official language packs win.
   - Without the call, WordPress never reads `languages/`. Plugin Check flags the call for plugins hosted on WordPress.org; the `phpcs:ignore` next to it records why it is needed here.
3. **Scripts:** each `wp_set_script_translations()` call passes `languages/`. WordPress looks for `radioedu-conecta-{locale}-{md5}.json`, where md5 is the hash of the script path relative to the plugin (`build/directorio.js`), and prints it inline before the script.
4. **Build:** `wp i18n make-json --no-purge --use-map=bin/i18n-js-map.json`, then `make-mo` and `make-php`.
   - The map works around WP-CLI 2.12, which strips `.min` with an unescaped regex and turns `build/admin.js` into `build/a.js`. It also limits the JSON files to the four scripts, so new scripts must be added to it.
   - `--no-purge` keeps script strings in the `.po`/`.mo`, because some are shared with PHP.
5. **Tests:** `TranslationsTest` checks the `init` registration, a PHP string in each locale, the JSON of every script in each locale and the plural forms.

---

## 8. Multisite (Educarex) Lifecycle Specifications

* **Single Site Activation:** Creates `{$wpdb->prefix}radioedu_colaboraciones`, assigns role capabilities, registers CPT/taxonomies and flushes rewrite rules. Activation never publishes content: the 6 demo stations and the `/traspasando-fronteras/` page, which holds the directory block, are created only when an administrator clicks the demo sync button in **Ajustes**. The importer marks what it creates with `_radioedu_conecta_demo` and only overwrites centros that carry that marker or, for demos imported before the marker existed, the internal `_radioedu_cover_url` pointing to a bundled demo image (no user can edit it). A real school with a colliding slug or the same podcast URL is never touched. An existing page with the directory slug and that same block is recorded as the directory page but not marked, so uninstall leaves it. The bundled demo images fill in only for centros with the marker.
* **Network Activation (`$network_wide = true`):** Iterates through sites via `get_sites()` + `switch_to_blog()`, provisioning tables and capabilities without seeding demo posts.
* **Dynamic Site Provisioning & Teardown:** `wp_initialize_site` (`Plugin::on_initialize_site()`) provisions new sites only when the plugin is network-active. On site deletion the custom table is added to core's drop list through the `wpmu_drop_tables` filter (`ColaboracionesTable::add_to_dropped_tables()`); options and roles disappear with the site's own tables, so nothing is written to tables that core has already dropped.
* **Rewrite rules in switched context:** inside `switch_to_blog()` the plugin deletes the `rewrite_rules` option instead of flushing, so each site regenerates its own rules with its own permalink structure.
* **Network Sync of Fichas (`NetworkSync`, `NetworkSyncPage`, `NetworkSyncController`):**
  - **Where:** a manual button in **Network Admin → Settings → RadioEdu Conecta** (`manage_network_options`) calls `POST /radioedu-conecta/v1/network-sync`. The endpoint is only available on the main site of a network (404 elsewhere, 401/403 without `manage_network_options`).
  - **Batches:** each request handles `radioedu_conecta_network_sync_batch_size` sites (filter, default 20, clamped 1–100) and returns `next_offset`/`done`. `build/network-sync.js` loops, updates a `<progress>` and a `role="status"` line, and prints the summary with DOM nodes and `wp.a11y.speak()`, followed by a table of every synced station (ficha link, source site, result, Author role added).
  - **Concurrency:** `sync_site()` takes a per-site MySQL/MariaDB named lock (`GET_LOCK`, name from `NetworkSync::lock_name()`: prefix, a hash of the database and table prefix, and the site ID). It looks the ficha up again inside the lock, so two syncs at once (two tabs, two administrators) cannot both create a ficha; the second one reports `radioedu_sync_busy` for that site. The lock belongs to the database connection: only it can release it (`RELEASE_LOCK`), and the server releases it when the connection ends, so a request that dies frees it at once and a slow one never loses it. There is no expiry time (second review R-02). An in-memory list also refuses a second sync of the same site in one request. Database proxies that share connections between requests (for example ProxySQL with multiplexing) must keep a request on one connection for named locks to work. Duplicates created before 1.1 are not merged automatically.
  - **Sources and target:** every site except the main one, skipping archived, spam and deleted sites. Fichas are `radioedu_centro` posts on the main site (the directory), linked to their site through the locked meta `_radioedu_source_blog_id`.
  - **Mapping:** `switch_to_blog()` reads each site, with `restore_current_blog()` in `finally`.
    - Refreshed on every sync: `blogname` → title, `home_url('/')` → `_radioedu_web_centro`, `get_site_icon_url(512)` → `_radioedu_logo_url`.
    - On creation only: tagline → excerpt and a Paragraph block, `admin_email` → `_radioedu_coordinador_email`, and the site administrator (matching the admin email, else the first administrator) → post author.
  - **Author role (checkbox, checked by default):** on every sync, the current author of each synced ficha is added to the main site as Author when not a member, or receives the Author role when they are a member without `edit_radioedu_centros`. Author, Editor and Administrator are left as they are, and existing roles are kept. Unchecked, no role or membership is added. `authors_added` counts the users who got the role.
  - **Never touched:** coordinator-edited data (coordinates, flag, taxonomies, podcast). Trashed fichas are reported as skipped and never recreated.
  - **Reuse:** a ficha without the source meta whose website matches the site URL (http/https, with or without trailing slash) is adopted.
  - **Orphans:** in the last batch, fichas whose source site is gone, archived, spam or deleted are listed (title, site ID, edit link) and kept. Every synced ficha is checked, in batches of 500 in ID order (`paged`, not a cursor; the scan runs within one request), against the set of live sites, which is also read in batches. Before, only one page of 1,000 fichas was checked, without saying so.
  - The "Radios por usuario" limit does not apply: the sync creates fichas directly as a network administrator.
* **Complete Uninstallation ([uninstall.php](file:///c:/radioedu-conecta/uninstall.php)):** Drops `{$wpdb->prefix}radioedu_colaboraciones`, deletes all `radioedu_centro` posts and postmeta, deletes terms across all 5 custom taxonomies, removes options and transients, revokes all 12 custom capabilities, and removes the `radioedu-conecta` scope (`centroPanelOpened`) from each user's `{prefix}persisted_preferences`, keeping the preferences of core and other plugins. It does this on a single site or on every site of a Multisite network. The network option `radioedu_conecta_sync_grant_author` is removed from every network of the installation (`get_networks()` in batches of 100, `delete_network_option()`), since the site cleanup covers all of them. Users are processed in batches of 500 by user ID.
