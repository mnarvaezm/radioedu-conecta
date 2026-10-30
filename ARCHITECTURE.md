# ARCHITECTURE & TECHNICAL SPECIFICATION: RadioEdu Conecta

> **AI System Note:** This document provides a comprehensive, deterministic, machine-readable architectural blueprint of the `radioedu-conecta` WordPress plugin. It details runtime lifecycles, data contracts, security mitigations, frontend pipelines, and Architecture Decision Records (ADRs) for any artificial intelligence or human engineer contributing to this repository.

---

## 1. System Metadata & Project Overview

```yaml
system_id: radioedu-conecta
display_name: RadioEdu Conecta (Traspasando Fronteras)
version: 1.0.0
db_schema_version: 1.0.1
php_version_requirement: ">=8.1"
wp_version_requirement: ">=7.0"
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
├── package.json                         # npm script configurations (@wordpress/scripts)
├── .distignore                          # Build exclusion rules for release distribution
├── .gitignore                           # Git repository exclusions
├── docker-compose.test.yml              # Local testing environment on port 8090 (WP 7.1 + MariaDB 10.11)
├── radioedu-conecta.zip                 # Production release archive (POSIX paths, ~9.35 MB with demo media)
├── src/
│   ├── Plugin.php                       # Core singleton orchestrator (lifecycle, hooks, Multisite, DB schema check)
│   ├── Admin/
│   │   ├── CentroMetaBoxes.php          # Metaboxes for CPT (coordinates, logo upload, RGPD emails, podcast)
│   │   ├── ColaboracionesAdminPage.php  # Admin panel to view, accept, or decline match proposals
│   │   ├── DemoDataImporter.php         # Demo stations importer & synchronizer (6 sample stations + images)
│   │   └── Settings.php                 # WordPress Settings API options (map defaults, notification flags, demo sync)
│   ├── Capabilities/
│   │   └── RoleCapabilities.php         # Custom capabilities management for admin, editor, and author roles
│   ├── Database/
│   │   └── ColaboracionesTable.php      # Custom table DDL via dbDelta() and DB lifecycle management
│   ├── Emails/
│   │   └── NotificationService.php      # wp_mail() notification pipeline for proposals and match responses
│   ├── Frontend/
│   │   └── DirectorioShortcode.php      # Shortcode [radioedu_directorio], single station profile filter & assets
│   ├── PostTypes/
│   │   └── CentroPostType.php           # Custom Post Type registration (`radioedu_centro`)
│   ├── Privacy/
│   │   └── PrivacyPolicy.php            # RGPD/GDPR personal data exporter, eraser, and policy notice
│   ├── Rest/
│   │   ├── CentrosController.php        # REST Controller (`GET /radioedu-conecta/v1/centros`)
│   │   └── ColaboracionesController.php # REST Controller (`POST|PATCH|GET /radioedu-conecta/v1/colaboraciones`)
│   └── Taxonomies/
│       └── TaxonomiesRegistrar.php      # 5 faceted taxonomies registration & capability bindings
├── templates/
│   ├── directorio.php                   # Frontend directory view: Banner, Facet Bar, Grid, Map
│   ├── single-centro.php                # Single station profile view: Cover, Identity, Tags, Metadata, Description
│   └── match-drawer.php                 # Shared "Solicitud de match" drawer (accessible dialog) used by both views
├── assets/
│   ├── css/
│   │   ├── admin.css                    # Styling for admin metaboxes and media uploader preview
│   │   ├── directorio.css               # Styling for directory UI, facet filters, card grid, single profile, and drawer
│   │   └── leaflet.css                  # Self-contained Leaflet 1.9.4 CSS (zero CDN)
│   ├── images/
│   │   └── demo/                        # Local cover photos and circular logos for the 6 demo radio stations
│   └── js/
│       ├── admin.js                     # Source: WP Media Uploader connector for radio station logo
│       ├── directorio.js                # Source: frontend reactive controller (DOM builder, Leaflet manager, Drawer)
│       └── leaflet.js                   # Self-contained Leaflet 1.9.4 JavaScript runtime (zero CDN)
├── build/                               # Committed `npm run build` output (@wordpress/scripts); the only JS the plugin loads
│   ├── admin.js / admin.asset.php       # Compiled admin.js + generated dependencies (wp-i18n) and version hash
│   └── directorio.js / directorio.asset.php # Compiled directorio.js + dependencies (wp-a11y, wp-i18n)
├── languages/
│   └── radioedu-conecta.pot             # GNU gettext internationalization template
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
  - External coordinators interact with other schools exclusively through the platform's proposal system. The plugin dispatches transactional emails on the server side (`NotificationService`), revealing the receiving coordinator's contact details (`Nombre <email>`) only once a proposal is explicitly **accepted** (`estado === 'aceptada'`).
  - `PrivacyPolicy.php` integrates with WordPress Core's GDPR subsystem (`wp_add_privacy_policy_content`, `wp_privacy_personal_data_exporters`, `wp_privacy_personal_data_erasers`) to export or erase/anonymize coordinator data upon request.

### 3.3 Strict Output Escaping, Zero `innerHTML`, and URL Validation
* **Server-side (PHP):** Strict "Late Escaping" rule. Variables are escaped at the exact moment of output using `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`, or `wp_json_encode()`. All SQL queries use `$wpdb->prepare()` with `%i` identifier placeholders for table names.
* **Client-side (JavaScript):** `assets/js/directorio.js` (compiled to `build/directorio.js`) prohibits `innerHTML` and HTML string concatenation. All dynamic card nodes, badges, and Leaflet map popups (`marker.bindPopup(popupNode)`) are assembled using `document.createElement()` and `textContent`. All external URLs are validated via `sanitizeHttpUrl()` (`http:` / `https:` whitelist) and escaped before CSS `url(...)` injection. Server-side, the profile cover URL is percent-encoded for CSS context (`DirectorioShortcode::css_safe_url()`) because `esc_url()` keeps quotes and parentheses that the browser decodes before parsing CSS.

---

## 4. Data Models & Database Schemas

### 4.1 Custom Post Type: `radioedu_centro`
* **Registered in:** [src/PostTypes/CentroPostType.php](file:///c:/radioedu-conecta/src/PostTypes/CentroPostType.php)
* **Features:** `title`, `editor` (station description), `thumbnail` (station header/cover image), `excerpt` (brief intro), `author`.
* **Single Profile Template:** Rendered dynamically via `DirectorioShortcode::render_single_centro_profile()` using [templates/single-centro.php](file:///c:/radioedu-conecta/templates/single-centro.php), featuring the full-width cover header, left-aligned station logo, title, flag, location, colored tags, metadata cards, action buttons (including direct Match Drawer trigger), and descriptive text below.

#### Meta Keys Schema (`wp_postmeta`)
| Meta Key | Data Type | Sanitization / Validation | Description | Public in REST? |
| :--- | :--- | :--- | :--- | :---: |
| `_radioedu_logo_id` | `int` | `absint()` | Attachment ID for the circular radio station logo | Yes (URL) |
| `_radioedu_logo_url` | `string` (URL) | `esc_url_raw()` | Fallback URL for bundled demo station logos | Yes (URL) |
| `_radioedu_cover_url` | `string` (URL) | `esc_url_raw()` | Fallback URL for bundled demo station cover images | Yes (URL) |
| `_radioedu_bandera` | `string` | `sanitize_key()` + whitelist | Region/Country flag code (`es-ex`, `es-ct`, `ie`, etc.) | Yes |
| `_radioedu_lat` | `float` (stored as `string`) | Clamped `[-90.0, 90.0]` | Latitude coordinates for Leaflet map pin | Yes |
| `_radioedu_lng` | `float` (stored as `string`) | Clamped `[-180.0, 180.0]` | Longitude coordinates for Leaflet map pin | Yes |
| `_radioedu_podcast_url` | `string` (URL) | `esc_url_raw()` | External RSS / iVoox / Educarex podcast feed URL | Yes |
| `_radioedu_web_centro` | `string` (URL) | `esc_url_raw()` | Official school website URL | Yes |
| `_radioedu_edades` | `string` | `sanitize_text_field()` | Student age range (e.g. `10-14 años`) | Yes |
| `_radioedu_coordinador_nombre` | `string` | `sanitize_text_field()` | Full name of teacher coordinator | **NO (Admin only)** |
| `_radioedu_coordinador_email` | `string` | `sanitize_email()` | Notification email address of coordinator | **NO (Admin only)** |
| `_radioedu_disponible_colaborar`| `string` (`'1'` or `'0'`) | `'1' === $val ? '1' : '0'` | Availability flag for receiving new match proposals | Yes (Boolean) |

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
    created_at datetime NOT NULL,
    updated_at datetime NOT NULL,
    PRIMARY KEY  (id),
    KEY centro_emisor_id (centro_emisor_id),
    KEY centro_receptor_id (centro_receptor_id),
    KEY usuario_emisor_id (usuario_emisor_id),
    KEY estado (estado)
) {$charset_collate};
```

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

---

## 6. REST API Contracts & Endpoints

### 6.1 `GET /wp-json/radioedu-conecta/v1/centros`
* **Permission:** Public (`__return_true`), strictly validated and sanitized.
* **Query Parameters:**
  - `page` (`int`, default: 1): Current page.
  - `per_page` (`int`, default: 24, max: 100): Centros per page.
  - `search` (`string`): Free-text search matching title/description.
  - `orden` (`enum`: `afines`, `recientes`, `alfa`): Ordering criteria (`afines` prioritizes available centers and ranks by shared taxonomy tags with the reference center, which is sorted last; the ordering runs in SQL, before pagination).
  - `afines_a` (`int`, default: 0): Optional reference center ID. When omitted, the endpoint uses the logged-in coordinator's own center; global managers (`manage_radioedu` / `edit_others_radioedu_centros`) get no self center, so no arbitrary center is pushed to the end. The bundled directory JavaScript relies on this default and does not send the parameter.
  - `etapa`, `idioma`, `tematica`, `formato`, `ubicacion` (`string`): Taxonomy slugs.

### 6.2 `GET /wp-json/radioedu-conecta/v1/colaboraciones`
* **Permission:** Authenticated user with `propose_radioedu_colaboracion` or `manage_radioedu`.
* **Behavior:** Returns collaboration proposals where the current user is the sender (`usuario_emisor_id`) or manages either the sending (`centro_emisor_id`) or receiving (`centro_receptor_id`) center (or all records if `manage_radioedu`).

### 6.3 `POST /wp-json/radioedu-conecta/v1/colaboraciones`
* **Permission:** Authenticated user with `current_user_can('propose_radioedu_colaboracion')`.
* **IDOR & Integrity Validation:**
  - `centro_emisor_id` !== `centro_receptor_id` (cannot propose to self).
  - Both centers must have `post_type === 'radioedu_centro'` and `post_status === 'publish'`.
  - `ColaboracionesController::user_can_manage_center( $centro_emisor_id, $user_id )` must be `true` (prevents spoofing another school's station).
  - Receiving center must be available (`_radioedu_disponible_colaborar !== '0'`).
* **Rate Limiting:** Fixed 1-hour window allowing a maximum of 10 proposals per user per hour (`radioedu_rate_{user_id}`). Returns `HTTP 429` if exceeded.

### 6.4 `PATCH /wp-json/radioedu-conecta/v1/colaboraciones/{id}`
* **Permission:** Target station coordinator (`ColaboracionesController::user_can_manage_center( $centro_receptor_id )`) or Admin (`manage_radioedu`).
* **Result:** Updates table record (`aceptada` or `rechazada`), sets `updated_at`, and triggers `NotificationService::send_status_update_email()`.
* **Single response:** Only `pendiente` proposals can be answered (`ColaboracionesTable::resolve_pending()` conditions the `UPDATE` on `estado = 'pendiente'`). Unknown IDs return `404 radioedu_not_found`; already answered proposals return `409 radioedu_already_resolved` and no email is re-sent. The admin screen (`admin-post.php?action=radioedu_respond_match`) shares the same logic through `ColaboracionesController::respond_to_proposal()`.

---

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

---

## 8. Multisite (Educarex) Lifecycle Specifications

* **Single Site Activation:** Creates `{$wpdb->prefix}radioedu_colaboraciones`, assigns role capabilities, registers CPT/taxonomies and flushes rewrite rules. Activation never publishes content: the 6 demo stations and the `/traspasando-fronteras/` page are created only when an administrator clicks the demo sync button in **Ajustes**. The importer marks what it creates with `_radioedu_conecta_demo` and only overwrites centros that carry that marker or the demo podcast URL, so a real school with a colliding slug is never touched.
* **Network Activation (`$network_wide = true`):** Iterates through sites via `get_sites()` + `switch_to_blog()`, provisioning tables and capabilities without seeding demo posts.
* **Dynamic Site Provisioning & Teardown:** `wp_initialize_site` (`Plugin::on_initialize_site()`) provisions new sites only when the plugin is network-active. On site deletion the custom table is added to core's drop list through the `wpmu_drop_tables` filter (`ColaboracionesTable::add_to_dropped_tables()`); options and roles disappear with the site's own tables, so nothing is written to tables that core has already dropped.
* **Rewrite rules in switched context:** inside `switch_to_blog()` the plugin deletes the `rewrite_rules` option instead of flushing, so each site regenerates its own rules with its own permalink structure.
* **Complete Uninstallation ([uninstall.php](file:///c:/radioedu-conecta/uninstall.php)):** Drops `{$wpdb->prefix}radioedu_colaboraciones`, deletes all `radioedu_centro` posts and postmeta, deletes terms across all 5 custom taxonomies, removes options and transients, and revokes all 12 custom capabilities across single-site or entire Multisite networks.
