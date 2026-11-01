# Registro de cobertura

Cada unidad agrupa superficie, frontera de confianza y clases de fallo. El estado refleja revisión, no ausencia universal de errores. Los hallazgos tendrán identificadores en INFORME.md.

| ID | Superficie y frontera | Controles/clases | Estado | Evidencia/resultado |
| --- | --- | --- | --- | --- |
| DOC | Documentación → contrato de producto | Coherencia, versiones, requisitos | covered | Documentación completa; F-03, F-14, F-16, F-20, F-22 y desviaciones Q |
| BOOT | Bootstrap/hooks → runtime | Autoload, lifecycle, compatibilidad | covered | Lectura y arranque real WP 7.1.4/PHP 8.1.34; F-04 |
| CAP | Roles/usuario → centro propio/ajeno | Capacidades, límite, autoría | covered | REST negativo y cupo; S-01, F-18 |
| META | REST core/metabox/editor → meta | IDOR, privacidad, tipos, sanitización | covered | Lectura de todos los sanitizadores/tests; REST ajeno 403 y E2E de guardado |
| REST-C | Anónimo → directorio y filtros | Exposición, SQL, límites, afinidad | covered | Lectura, muestra pública y afinidad; F-09 |
| REST-P | Coordinador → crear propuesta | Autorización, CSRF, lógica, abuso | covered | Dispatcher y nonces HTTP 401/403; S-02, F-08 |
| REST-R | Receptor → responder propuesta | IDOR, estado, concurrencia | covered | Receptor 200, emisor 403, repetición 409; F-07 |
| REST-L | Participante → listar propuestas | Exfiltración, paginación | covered | 102 registros/53 centros; F-05 y F-06 |
| REST-N | Superadmin → sincronización | Red/sitio, lotes, validación | covered | Admin sitio 403, anónimo 401, subsite 404; Multisite real |
| ADMIN | Admin/coordinador → ajustes/listado | Nonces, capacidades, XSS, errores | covered | Lectura de formularios y handlers; guardado E2E, sin E2E de respuesta admin |
| DEMO | Admin → importación local | Ficheros, medios, sobrescritura | covered | Importación con colisión controlada; F-03 y F-10 |
| DB | Handlers → tabla colaboraciones | SQL, índices, integridad, migración | covered | SQL trazado; fallos DDL/UPDATE inyectados; F-04, F-07 |
| MAIL | Propuesta/respuesta → correo | Datos privados, destinatarios, fallos | covered | Lectura completa; correo real bloqueado; F-02, F-08 |
| PRIV | Solicitud verificada → exportar/borrar | Cobertura de PII, paginación, retención | covered | Reproducción emisor/receptor; lectura test 101 filas; F-01, F-02, F-22 |
| NET | Sitio/red → ficha principal | Aislamiento, publicación, identidad | covered | Secuencia y concurrencia controlada; F-18, F-19, F-21; identidad heredada pendiente |
| LIFE | Activación/borrado sitio/uninstall → datos | Contexto, residuos, escalabilidad | covered | Sitio único/red/por sitio, nuevo sitio, borrar sitio, uninstall de ambas modalidades; F-20 |
| VIEW | CPT/shortcode/bloque → HTML público | Escape, permisos, temas, assets | covered | PHP/templates leídos; E2E block theme; clásicos solo lectura de código/tests; F-14…F-16 |
| JS | API/usuario → DOM y acciones | XSS, carreras, formularios, storage | covered | Cuatro fuentes leídas; bundles idénticos; carrera de modal y mapa F-11/F-12 |
| A11Y | Usuario/teclado → interfaz | Foco, modal, etiquetas, errores | covered | Inspección estática y visual 320/1280; F-13; sin lector de pantalla/axe ni certificación AA |
| PERF | Peticiones → DB/memoria/red | N+1, caché, límites, coste | covered | Revisión estática y límites; sin benchmark volumétrico ni object cache persistente |
| I18N | Fuentes → traducciones/build | Dominios, cobertura, sincronización | covered | POT 444 mensajes + cabecera; 4 PO/MO y 16 JSON completos/coherentes; no revisión lingüística nativa |
| DIST | Fuentes/dependencias → ZIP | GPL, PCP, secretos, dev deps | covered | ZIP 100 archivos idénticos a fuentes; WPCS/PCP y auditorías; F-17; empaquetado futuro docs pendiente |
| TEST | Tests/CI → garantías | Cobertura negativa, entornos, falsos verdes | covered | Todos los tests leídos; 2 E2E pasan; PHPStan/PHPUnit/PHPCompatibilityWP no disponibles |

## Archivos

Inventario completo de archivos versionados y SHA-256 en `evidencias/inventario.csv`. `evidencias/final-source-comparison.json` confirma que ninguna fuente cambió durante la auditoría. El commit avanzó a dc245a9 al registrarse los cambios previos; sus bytes ya estaban incluidos en el inventario inicial.

Lectura íntegra: 22 clases de src, bootstrap, uninstall y cuatro templates (28 PHP de ejecución), cuatro JS propios, dos CSS propios, todos los 16 PHP de tests/bootstraps y dos E2E, bin, configuraciones y documentación de proyecto. SVG revisados como datos de iconos, traducciones y build cotejados estructuralmente; JPG/PNG y MO no se describen como código leído línea a línea.

Terceros: Leaflet revisado por integración, versión declarada, atribución y licencia; WordPress/Gutenberg y demás dependencias por contratos usados y advisories de los lockfiles. No es auditoría interna completa de esas dependencias. `npm audit` no cubre la copia manual de Leaflet.

## Validaciones fuera del alcance ejecutado

- PHPStan, PHPUnit completo y PHPCompatibilityWP: **blocked**, faltan dependencias instaladas; no se descargaron otras, conforme al plan de auditoría. *Actualización 2026-11-01: se ejecutaron después con las herramientas de QA locales, sobre el código auditado y sobre el corregido, sin errores; PHPUnit en sitio único y en multisitio. Ver «Seguimiento de correcciones» en INFORME.md.*
- Matriz PHP 8.3/8.4/8.5 y WordPress 7.1.0 exacto; temas clásicos reales, lector de pantalla, zoom 400 %, SMTP real, object cache persistente, red de >1.000 sitios, multi-network y carga concurrente HTTP: **deferred**. Se revisó estáticamente lo aplicable y se documentaron casos mínimos para continuar.
- Producción, servicios remotos de RadioEdu, cuentas reales y envío de correos: **out_of_scope**.
