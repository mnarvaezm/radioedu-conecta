---
name: wordpress-plugin-development
description: Guía y estándares de desarrollo para crear plugins de WordPress seguros, modulares y listos para WordPress.org (WPCS, PHPStan, Plugin Check, APIs nativas, RGPD y Multisite).
---

# Desarrollo de Plugins de WordPress: Estándares Oficiales

Esta skill proporciona los procedimientos, estándares y comprobaciones obligatorias para el desarrollo del plugin **RadioEdu Conecta**.

## Checklist de Desarrollo y Calidad

### 1. Inicialización y Arquitectura
- Consultar `BRIEFING.md` para validar requisitos.
- Proponer siempre arquitectura (árbol, hooks, modelo de datos, capacidades, superficie de ataque).
- Utilizar APIs nativas (`Settings API`, `WP_Query`, `$wpdb->prepare()`, `WP_REST_Controller`, etc.).

### 2. Seguridad Obligatoria
- **Autorización**: `current_user_can()` en cada acción que modifique datos.
- **Intención**: Nonces (`check_admin_referer`, `check_ajax_referer`, `X-WP-Nonce`).
- **Entrada**: `wp_unslash()` + sanitización contextual (`sanitize_text_field`, `absint`, etc.).
- **Salida**: Escapado tardío contextual (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`).
- **Base de Datos**: `$wpdb->prepare()` con `%s`, `%d`, `%f`, `%i`.
- **Peticiones HTTP**: `wp_remote_*` o `wp_safe_remote_*` con `sslverify => true` y comprobación de errores.

### 3. Calidad y Herramientas
- **PHPCS**: Reglas WordPress, WordPress-Extra y WordPress-Docs.
- **PHPStan**: Nivel 6+ con `szepeviktor/phpstan-wordpress`.
- **Plugin Check (PCP)**: Validación previa a release.
- **Tipado**: `declare(strict_types=1);` y tipos explícitos en clases y métodos.
- **Assets**: `@wordpress/scripts` para compilar y generar `.asset.php`.

### 4. Definición de Terminado (DoD)
Verificar punto 11 de `AGENTS.md` antes de considerar cualquier tarea o funcionalidad finalizada.
