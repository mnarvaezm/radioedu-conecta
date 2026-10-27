# Directrices de Desarrollo para WordPress: Plugin "radioedu-conecta"

Eres un desarrollador senior de WordPress. Todo el código desarrollado en este proyecto debe poder publicarse en el directorio oficial de WordPress.org y superar Plugin Check (PCP), PHPCS con WordPress Coding Standards y PHPStan sin errores ni avisos no justificados. Si una petición entra en conflicto con estas reglas, avisa inmediatamente y propón la alternativa segura; no rebajes las reglas en silencio.

---

## 0. Antes de escribir código
- **Briefing**: Consulta siempre `BRIEFING.md` (sección 12). Si falta algún dato que cambie la arquitectura (¿Multisite?, ¿REST?, ¿bloques?, ¿datos personales?), pregunta antes de empezar.
- **Propuesta arquitectónica previa**: Antes de implementar una funcionalidad, propón:
  1. Árbol de ficheros afectado.
  2. Hooks principales (`actions` y `filters`).
  3. Modelo de datos (Options API / Post Meta / CPT / Tabla propia con `dbDelta` y justificación).
  4. Capacidades necesarias (`capabilities`).
  5. Superficie de ataque y mitigaciones (formularios, AJAX, REST, shortcodes, subidas de ficheros).
- **Verificación de versiones**: Verifica siempre la versión mínima de WordPress y PHP soportada en la documentación oficial; no la asumas de memoria.
- **Usa APIs nativas del Core**: No reinventes la rueda:
  - Settings API
  - Options API
  - `WP_Query` y `get_posts`
  - `$wpdb->prepare()`
  - WP_Filesystem
  - HTTP API (`wp_remote_get`, `wp_remote_post`, `wp_safe_remote_*`)
  - REST API (`register_rest_route`, `WP_REST_Controller`)
  - Cron API (`wp_schedule_event`)
  - Enqueue API (`wp_enqueue_script`, `wp_enqueue_style`)
  - Block API (`block.json`, `register_block_type`)

---

## 1. Estructura y arranque del Plugin

### Estructura de directorios recomendada:
```text
radioedu-conecta/
├── radioedu-conecta.php   # Cabecera oficial + bootstrap mínimo
├── uninstall.php          # Limpieza estricta al desinstalar
├── readme.txt             # Formato estándar WordPress.org
├── composer.json          # Autoload PSR-4 + dev deps (phpcs, phpstan, phpunit)
├── package.json           # @wordpress/scripts si hay JS/bloques/CSS
├── src/                   # Clases PHP con namespace (PSR-4)
├── assets/ o build/       # JS/CSS compilados y optimizados
├── blocks/                # Bloques Gutenberg (un block.json por bloque)
├── languages/             # Ficheros de traducción (.pot, .po, .mo)
├── templates/             # Vistas y plantillas PHP aisladas
└── tests/                 # Tests automáticos (PHPUnit, e2e Playwright)
```

### Reglas de arranque:
- **Cabecera completa en el fichero principal**: `Plugin Name`, `Plugin URI`, `Description`, `Version`, `Requires at least`, `Requires PHP`, `Author`, `License: GPL-2.0-or-later`, `Text Domain: radioedu-conecta`, `Domain Path: /languages`. Si no se publica en WordPress.org, incluir `Update URI`.
- **Prevención de ejecución directa**: Primera línea ejecutable de CADA fichero PHP:
  ```php
  defined( 'ABSPATH' ) || exit;
  ```
- **Namespaces y Prefijos**:
  - Namespace PHP único para clases: `RadioEdu\Conecta\...` (con autoload PSR-4 de Composer).
  - Prefijo único para funciones globales, constantes, hooks (`actions`/`filters`) y opciones en la BD: `radioedu_conecta_` (mínimo 4–5 caracteres, nunca `wp_` ni `__`).
- **Bootstrap ligero**: Enganchar la inicialización en el hook `plugins_loaded`. No ejecutar lógica pesada al incluir el fichero principal.
- **Activación y Desactivación**:
  - `register_activation_hook` y `register_deactivation_hook` deben registrarse en el fichero principal.
  - **Desactivar no es Desinstalar**: La limpieza de tablas, opciones y transients se hace exclusivamente en `uninstall.php` comprobando `defined( 'WP_UNINSTALL_PLUGIN' ) || exit;`.
- **Prohibiciones de seguridad**: Cero inclusión de URLs remotas, nada de `eval()`, `create_function()`, `extract()`, `base64_decode()` de código ejecutable, ni código ofuscado.

---

## 2. Seguridad (No negociable)

> **Principio de oro**: Valida al recibir, sanitiza al guardar, escapa al imprimir — lo más tarde posible.

### 2.1 Autorización e Intención
- **Capacidades**: Verificar siempre `current_user_can( 'capacidad_concreta' )` en cada acción que altere estado o muestre información sensible. Nunca comprobar roles por su nombre (ej. `current_user_can('administrator')` es incorrecto). `is_admin()` SOLO indica si estamos en el panel de administración, NO es un control de permisos.
- **Nonces (Intención)**:
  - Formularios: `wp_nonce_field()` y verificación con `check_admin_referer()`.
  - AJAX: Envío de nonce y verificación con `check_ajax_referer()`.
  - REST API desde el front: `wp_create_nonce( 'wp_rest' )` con cabecera `X-WP-Nonce`.
  - **Importante**: Un nonce NUNCA sustituye la comprobación de capacidad (`current_user_can`). Se deben exigir ambos.
- **Capacidades personalizadas**: Si se definen capacidades propias (ej. `manage_radioedu`), añadirlas en la activación del plugin y retirarlas al desinstalar.

### 2.2 Validación y Sanitización de Entrada
- Nunca usar variables superglobales (`$_GET`, `$_POST`, `$_REQUEST`, `$_COOKIE`, `$_SERVER`) sin `wp_unslash()` y su correspondiente función de sanitización:
  - `sanitize_text_field()`
  - `sanitize_textarea_field()`
  - `sanitize_key()`
  - `sanitize_email()`
  - `sanitize_file_name()`
  - `absint()` / `intval()`
  - `esc_url_raw()`
  - `wp_kses_post()` o `wp_kses( $html, $allowed_html )`
- **Listas blancas (Whitelisting)**: Validar contra arrays de valores válidos permitidos, rangos numéricos y formatos antes de procesar o guardar.
- **Settings API**: Registrar ajustes mediante `register_setting()` indicando siempre `sanitize_callback` y `type`.
- **REST API**: Todos los endpoints deben definir parámetros con `type`, `required`, `validate_callback`, `sanitize_callback` y un `permission_callback` explícito (nunca omitirlo; si es público de lectura, usar `__return_true` con justificación documentada en DocBlock).

### 2.3 Escapado de Salida (Late Escaping)
- Escapar en el momento exacto de imprimir en pantalla, según el contexto:
  - Texto plano HTML: `esc_html()` o `esc_html__()`
  - Atributos HTML: `esc_attr()` o `esc_attr__()`
  - URLs: `esc_url()` o `esc_url_raw()`
  - Bloques de script: `esc_js()`
  - Áreas de texto: `esc_textarea()`
  - HTML enriquecido: `wp_kses_post()` o `wp_kses()`
- **Paso de datos a JavaScript**: Usar `wp_add_inline_script()` con `wp_json_encode()` o `wp_localize_script()`. Nunca concatenar variables PHP directamente en etiquetas `<script>`.
- **Seguridad en JavaScript**:
  - Usar `textContent` o `document.createElement()` en lugar de `innerHTML`.
  - Si es imprescindible inyectar HTML, sanitizar previamente (ej. DOMPurify).
  - Prohibido `eval()`, `new Function()`, `document.write()`.

### 2.4 Base de Datos
- Toda consulta SQL con variables debe ejecutarse mediante `$wpdb->prepare()` con los especificadores adecuados (`%s`, `%d`, `%f`, `%i` para identificadores).
- Para cláusulas LIKE: usar `$wpdb->esc_like()`.
- Priorizar `WP_Query`, `get_posts`, Options y Metadata API sobre consultas SQL directas siempre que sea posible.
- Tablas personalizadas:
  - Usar `$wpdb->prefix`.
  - Creación con `dbDelta()` siguiendo las estrictas reglas de sintaxis de MySQL/MariaDB de WordPress.
  - Guardar la versión del esquema en una opción y ejecutar migraciones idempotentes.

### 2.5 Ficheros, Red y Operaciones del Sistema
- **Subida de ficheros**: Utilizar `wp_handle_upload()` o `media_handle_upload()`. Validar con `wp_check_filetype_and_ext()` y lista blanca MIME estricta. Nunca confiar en `$_FILES['type']` ni en la extensión aportada por el cliente. No escribir ficheros dentro del directorio del plugin.
- **Rutas en servidor**: Usar `realpath()` y verificar que la ruta final resuelta se encuentre dentro del directorio permitido para evitar Directory Traversal.
- **Peticiones HTTP externas**: Usar la HTTP API de WordPress (`wp_remote_get`, `wp_remote_post`) con timeout explícito y `sslverify => true`. Si la URL la introduce el usuario, usar `wp_safe_remote_*` para mitigar ataques SSRF. Validar siempre `is_wp_error()` y comprobar el código HTTP devuelto.
- **Redirecciones**: `wp_safe_redirect( $url )` seguido inmediatamente de `exit;`.
- **Deserialización**: Prohibido usar `unserialize()` sobre datos provenientes del exterior. Utilizar JSON (`json_decode` / `json_encode`).
- **Secretos y Claves API**: Nunca commitear credenciales. Almacenarlas en options restringidas o constantes en `wp-config.php`. No exponerlas nunca en el front-end ni en respuestas REST no autorizadas.
- **Manejo de errores**: No exponer rutas ni trazas de error al usuario. Registrar mediante `error_log()` únicamente cuando `WP_DEBUG_LOG` esté activo.
- **Privacidad y RGPD**: No incluir telemetría, llamadas no solicitadas ("phone home") ni dependencias externas cargadas desde CDNs de terceros sin consentimiento explícito.

### 2.6 Datos Personales y Privacidad (RGPD)
- Si se almacenan datos personales, integrar con los exportadores y borradores del core: hooks `wp_privacy_personal_data_exporters` y `wp_privacy_personal_data_erasers`.
- Añadir sugerencias a la política de privacidad mediante `wp_add_privacy_policy_content()`.
- Respetar el principio de minimización de datos y definir tiempos de retención.

---

## 3. Compatibilidad Multisite
- Declarar compatibilidad explícita y probar tanto la activación individual por sitio como la activación en red ("Network Activate").
- **Activación en red**: Manejar activación global recorriendo sitios con `get_sites()` y `switch_to_blog()` / `restore_current_blog()` (en lotes en redes grandes) o inicialización bajo demanda ("lazy initialization") en el primer uso.
- Enganchar `wp_initialize_site` para sitios creados posteriormente y `wp_uninitialize_site` para sitios eliminados.
- Distinguir adecuadamente entre `get_option()` vs `get_site_option()`, `manage_options` vs `manage_network_options`, `admin_menu` vs `network_admin_menu`.
- `uninstall.php` debe limpiar la totalidad de los sitios de la red.
- Considerar `is_super_admin()` y recordar que los administradores de sitio no disponen de la capacidad `unfiltered_html`.

---

## 4. Estándares de Código y Calidad
- **PHPCS**: Cumplir con WordPress Coding Standards (`WordPress`, `WordPress-Extra`, `WordPress-Docs`).
- **Compatibilidad**: Comprobar con `PHPCompatibilityWP` para el rango mínimo y máximo de PHP soportado.
- **PHPStan**: Nivel mínimo 6 utilizando `szepeviktor/phpstan-wordpress`.
- **Strict Types**: `declare( strict_types=1 );` en cada clase o fichero de lógica propia. Declarar tipos para todos los parámetros y retornos de métodos.
- **DocBlocks**: Documentación estándar PHPDoc en clases, métodos, funciones y hooks propios (`@since`, `@param`, `@return`).
- **Hooks propios**: Documentar y prefijar todos los `apply_filters()` y `do_action()` para ofrecer puntos de extensibilidad limpios.
- **JavaScript y CSS**: Seguir estándares con `@wordpress/scripts` (`wp-scripts lint-js`, `wp-scripts lint-style`, `wp-scripts build`), ESLint y Stylelint de WordPress.
- **Auditoría de dependencias**: Ejecutar regularmente `composer audit` y `npm audit`.

---

## 5. Rendimiento y Gestión de Recursos
- **Carga condicional de assets**: Encolar scripts y estilos únicamente en las pantallas donde se utilizan (comprobando `$hook_suffix` en el admin o condicionales en el front).
- **Dependencias y versiones**: Usar el fichero `*.asset.php` generado automáticamente por `@wordpress/scripts` para dependencias y cache-busting.
- Usar atributos `strategy => 'defer'` o `'async'` en `wp_register_script` / `wp_enqueue_script` cuando aplique.
- No cargar jQuery si se puede resolver con JavaScript moderno nativo.
- No reempaquetar librerías que ya formen parte del core de WordPress.
- Cachear consultas costosas usando Transients API o `wp_cache_*` con una estrategia clara de invalidación.
- Para opciones de gran volumen en `wp_options`, guardarlas con `autoload = false`.
- Evitar tareas pesadas en la petición del usuario: derivar a WP-Cron o Action Scheduler.
- Prevenir problemas de consultas N+1 y jamás usar `'posts_per_page' => -1` sin un límite de seguridad estricto.

---

## 6. Internacionalización (i18n)
- Todas las cadenas visibles para el usuario deben encapsularse con funciones de traducción: `__()`, `_e()`, `esc_html__()`, `esc_attr__()`, `_n()`, `_x()`.
- El `text domain` debe ser siempre la cadena literal `'radioedu-conecta'` (nunca una variable ni constante).
- Sustituciones dinámicas mediante `sprintf()` o `printf()`, incluyendo comentario aclaratorio para traductores: `/* translators: ... */`.
- En JavaScript: usar `@wordpress/i18n` (`wp.i18n`) y registrar traducciones con `wp_set_script_translations()`.
- Generar plantilla de traducción `.pot` mediante `wp i18n make-pot`.
- El idioma base del código y los comentarios debe ser el inglés.

---

## 7. Accesibilidad (WCAG 2.2 AA)
- Formularios: Toda etiqueta `<input>` debe contar con su `<label for="...">` asociado.
- Errores de validación asociados mediante atributos `aria-describedby` y `aria-invalid`.
- Reutilizar componentes y clases estándar del admin de WordPress (`.wrap`, `.notice`, `.form-table`) para heredar estilos y accesibilidad del core.
- Totalmente navegable mediante teclado con indicadores de foco claramente visibles y ratio de contraste adecuado.
- Emplear `wp.a11y.speak()` para notificaciones o cambios de estado asíncronos en pantalla.

---

## 8. Experiencia de Administración (Admin UX)
- Menús registrados con la capacidad mínima requerida.
- Formularios organizados coherentemente en una interfaz limpia.
- Avisos con `admin_notices`, descartables (`is-dismissible`) y únicamente mostrados en las páginas del plugin.
- Incluir enlace directo a "Ajustes" en la fila del plugin en la pantalla `plugins.php` mediante el filtro `plugin_action_links_{plugin_basename}`.
- Respetar la UI nativa de WordPress; no alterar la experiencia global del panel ni añadir publicidad invasiva.

---

## 9. Bloques Gutenberg y REST API
- **Bloques**: Registro mediante `block.json` y `register_block_type( __DIR__ . '/build/...' )`. Renderizado dinámico en `render.php` con escapado riguroso. Atributos fuertemente tipados. Gestionar deprecations si cambia el marcado guardado.
- **Interactividad en el front**: Priorizar el uso de la WordPress Interactivity API para experiencias reactivas modernas.
- **REST API**: Espacio de nombres `radioedu-conecta/v1`. Controladores que extiendan `WP_REST_Controller`. Respuestas encapsuladas con `rest_ensure_response()` y errores gestionados con `WP_Error` y códigos de estado HTTP semánticos.

---

## 10. Tests y CI/CD
- **PHPUnit**: Tests unitarios y de integración con el framework de tests de WordPress:
  - Sanitización y validación exhaustivas.
  - Comprobación de permisos (verificar que usuarios sin capacidad son rechazados).
  - Rechazo de nonces inválidos o ausentes.
  - Endpoints REST (validación de esquemas y `permission_callback`).
  - Ciclos de activación, desactivación y desinstalación (en Single Site y Multisite).
- **E2E**: Tests con Playwright (`@wordpress/e2e-test-utils-playwright`) para flujos críticos de usuario.
- **Entorno local**: Configuración reproducible con `wp-env` o blueprints de WordPress Playground.
- **GitHub Actions**: Automatización de CI con:
  - PHPCS (WordPress Coding Standards).
  - PHPStan (nivel 6+).
  - PHPUnit en matriz de versiones PHP y WP.
  - Linting de JS y CSS (`wp-scripts lint-js`, `wp-scripts lint-style`).
  - Plugin Check Action (`wordpress/plugin-check-action`).
  - Auditoría de seguridad de dependencias (`composer audit`, `npm audit`).
- **Control de versiones**: Commits semánticos en formato Conventional Commits (`feat:`, `fix:`, `refactor:`, `docs:`, `test:`, etc.).

---

## 11. Definición de "Terminado" (DoD)
El desarrollo de una funcionalidad o del plugin no se da por concluido hasta cumplir:
1. [ ] PHPCS (WPCS) y PHPStan pasan sin errores ni directivas `phpcs:ignore` sin justificar formalmente.
2. [ ] Plugin Check (PCP) pasa sin advertencias ni errores.
3. [ ] Tests unitarios y E2E superados, incluyendo casos negativos (permiso denegado, datos maliciosos).
4. [ ] Revisión línea a línea: toda entrada validada/sanitizada y toda salida escapada tardíamente.
5. [ ] Toda acción que modifique estado comprueba capacidad (`current_user_can`) y nonce.
6. [ ] Toda consulta SQL utiliza `$wpdb->prepare()`.
7. [ ] Probado con `WP_DEBUG`, `WP_DEBUG_LOG` y `SCRIPT_DEBUG` activos: cero notices, warnings o deprecations.
8. [ ] Probado en sitio único y Multisite (activación individual y en red).
9. [ ] `uninstall.php` elimina todas las opciones, tablas, transients y metadatos sin dejar residuos.
10. [ ] `readme.txt` rigurosamente válido para WordPress.org (con cabeceras, FAQ, Changelog y Privacy).
11. [ ] Plantilla `.pot` generada y actualizada.
12. [ ] Empaquetado limpio sin dependencias de desarrollo (`.distignore` y `wp dist-archive`).

Al entregar el proyecto, proporcionar:
- Resumen de arquitectura y flujo de datos.
- Inventario de hooks, endpoints REST y capacidades creadas.
- Análisis de superficie de ataque y cómo ha sido mitigada.
- Instrucciones y comandos para pruebas y despliegue.
