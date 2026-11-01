# Plan exhaustivo de revisión — RadioEdu Conecta

Fecha: 31 de octubre de 2026 (Europe/Madrid). Documento de referencia creado antes de la revisión del código.

## Objetivo y reglas de trabajo

Auditar documentación, arquitectura, seguridad, lógica funcional, privacidad, Multisite, frontend, accesibilidad, pruebas y distribución. No modificar el código funcional durante esta auditoría. Conservar los cambios locales preexistentes. Actualizar `INFORME.md` al descubrir y validar problemas, sin esperar al final de la sesión.

Revisión basada en AGENTS.md, BRIEFING.md y los skills de WordPress aplicables. La metodología de seguridad distingue `confirmed`, `needs_validation` (sin severidad) y `rejected`. Los defectos funcionales y de cumplimiento se registran por separado de las vulnerabilidades. Cada conclusión debe incluir archivo/línea, condiciones, flujo, efecto, corrección concreta y verificación realizada o pendiente. No equiparar un escáner limpio a ausencia de vulnerabilidades.

Pruebas activas: exclusivamente en un entorno local desechable con datos ficticios. No realizar pruebas contra servicios reales ni enviar correos reales. Usar dependencias disponibles; no instalar otras para validar la auditoría. Registrar los bloqueos en vez de atribuir resultados a pruebas no ejecutadas.

## Evidencias y continuidad

- `INFORME.md`: contexto, resultados incrementales, hallazgos, descartes, limitaciones y siguiente paso.
- `COBERTURA.md`: inventario y estados por superficie/frontera/clase de fallo.
- `evidencias/`: inventario con SHA-256, salidas de verificaciones y reproducciones locales mínimas, sin datos personales ni secretos.
- Estados: `planned`, `covered` (lectura y controles identificados), `candidate`, `blocked`, `deferred`, `out_of_scope`. `covered` no significa certificado seguro.
- Al retomar: leer informe y cobertura, comprobar hashes/cambios, continuar por pendientes, revalidar los archivos que hayan cambiado.

## Criterio de cierre de la lista

Actualizado al cerrar la auditoría. `[x]` significa que el punto fue examinado y su resultado/limitación quedó registrado; no significa que el requisito se cumpla ni que todas sus variantes se ejecutaran. `[ ]` conserva validaciones parciales o bloqueadas. Los métodos concretos y los hallazgos están en COBERTURA.md e INFORME.md.

## 01. Identificación, documentación y requisitos

- [x] Registrar commit, cambios sin commit, fecha, versiones y huellas de archivos propios.
- [x] Inventariar todos los archivos, incluidos configuración oculta, CI, licencias y artefactos.
- [x] Leer AGENTS.md, BRIEFING.md, ARCHITECTURE.md, README.md, readme.txt y TESTING-PROTOCOL.md completos.
- [x] Contrastar cada afirmación documental con implementación y pruebas.
- [x] Resolver/documentar divergencias entre briefing, arquitectura y reglas del proyecto.
- [x] Verificar mínimos WP/PHP y APIs utilizadas en fuentes oficiales; separar requisitos de versiones efectivamente probadas.
- [x] Revisar contrato de compatibilidad con temas clásicos, bloques, editor clásico y Gutenberg.
- [x] Inventariar requisitos pendientes, funcionalidades prometidas y criterios de aceptación.
- [x] Revisar documentación operativa: instalación, actualización, reversión, privacidad y borrado.
- [x] Leer skills de auditoría, desarrollo, REST, Multisite, privacidad, directorio y PHPStan según superficies reales.

## 02. Arquitectura y arranque

- [x] Cabecera del plugin, licencia, text domain, constantes y sincronización de versión.
- [x] Protección de acceso directo, strict_types, namespaces y autoload de producción.
- [x] Ausencia de efectos pesados al incluir archivos; orden plugins_loaded/init/rest_api_init.
- [x] Hooks registrados una sola vez, prioridades y número de argumentos correctos.
- [x] Activación, desactivación, actualización e inicialización diferida idempotentes.
- [x] Manejo de fallos parciales de migración, versión persistida solo tras éxito.
- [x] Carga condicional de componentes y assets; ausencia de efectos globales innecesarios.
- [x] Separación de responsabilidades y acoplamientos/ciclos inesperados.

## 03. Modelo de datos y consistencia

- [x] CPT, taxonomías, metadatos, opciones, transients, user meta y tabla propia inventariados.
- [x] Tipos, defaults, longitudes, enums, nulabilidad, índices y codificación compatibles.
- [x] Correspondencia entre esquemas REST/PHP/SQL/JS y normalización de booleanos/números.
- [x] Claves estables y prefijadas; opciones grandes sin autoload.
- [x] dbDelta correcto, prefijo del sitio y migraciones idempotentes.
- [x] SQL preparado, identificadores seguros, LIKE escapado y ordenación con lista permitida.
- [x] Errores de escritura, retornos false/0 y rollback lógico correctamente tratados.
- [x] Referencias a centros borrados, papelera, borradores o usuarios eliminados.
- [x] Integridad de propuestas, estados permitidos, duplicados y unicidad efectiva.
- [x] Concurrencia: límites de centros, solicitudes repetidas, aceptación/declinación y sincronización.
- [x] Fechas UTC/local, creación/actualización, orden estable y paginación coherente.

## 04. Identidades, capacidades y fronteras

- [x] Matriz anónimo/suscriptor/colaborador/autor/editor/admin de sitio/superadmin.
- [x] Capacidades creadas, asignación, migraciones y retirada al desinstalar.
- [x] Comprobar capacidades concretas, no nombres de roles ni is_admin como autorización.
- [x] Autorización por objeto en editar, listar, responder, importar y exportar.
- [x] Autoría, email del coordinador y edit_post no amplían permisos indebidamente.
- [x] No autoatribución de centros ajenos mediante datos editables por el solicitante.
- [x] Comprobar permisos en rutas alternativas: REST core, REST propio, metabox y bloque.
- [ ] Límite de radios por usuario: creación, edición, copia, restauración y concurrencia. **Validación parcial/pendiente; véase COBERTURA.md e INFORME.md.**
- [x] Revocación de permisos y cambios de propietario durante operaciones.
- [x] Nonces independientes de capacidades; cookie REST y contraseñas de aplicación.
- [x] Confidencialidad de listados, contadores, errores, búsquedas y filtros.

## 05. REST, formularios y entradas

- [x] Inventario de endpoints, métodos, permission_callback, esquemas y contexto.
- [x] namespace, WP_REST_Controller, WP_Error y códigos HTTP adecuados.
- [x] type/required/validate_callback/sanitize_callback y límites explícitos.
- [x] Tipos incorrectos, arrays/objetos, null, campos ausentes y parámetros desconocidos.
- [x] Enteros negativos/cero/enormes, floats no finitos, longitudes excesivas y Unicode.
- [x] Límites de paginación, ordenación y búsquedas; post_status no controlable indebidamente.
- [x] POST/PATCH: identidad del centro, disponibilidad, mismo centro, destino inexistente.
- [x] GET: lectura limitada a participantes y exclusión de datos personales públicos.
- [x] Respuestas a propuestas: solo receptor autorizado, solo pendiente, una transición efectiva.
- [ ] Nonces ausentes, caducados, incorrectos y de otro usuario en formularios y REST cookie. **Validación parcial/pendiente; véase COBERTURA.md e INFORME.md.**
- [x] Superglobales: forma, wp_unslash, sanitización y validación antes de uso.
- [x] Protección contra CSRF, IDOR, escalada, asignación masiva y enumeración sensible.
- [x] Errores sin SQL, rutas, trazas ni contactos personales.
- [x] Reintentos/idempotencia, abuso repetido y coste por petición.

## 06. Sinks y seguridad transversal

- [x] XSS almacenado/reflejado/DOM: texto, atributos, URLs, HTML enriquecido y scripts.
- [x] Escapado tardío contextual; wp_kses con lista adecuada y JSON en inline scripts.
- [x] DOM: textContent/createElement, URLs allowlist, ausencia de ejecución dinámica.
- [x] SQL injection y segundo uso de datos persistidos en consultas.
- [x] Archivos: includes, rutas, realpath, traversal y confinamiento.
- [x] Subidas/importación: MIME, extensión, permisos, tamaño, temporales y limpieza.
- [x] RCE: eval, deserialización externa, código remoto y comandos de sistema.
- [x] Red: HTTP API, SSRF, esquemas/hosts, TLS, timeout, códigos y errores.
- [x] Redirecciones internas seguras y exit inmediato.
- [x] Secretos, credenciales, dumps, logs, backups y archivos innecesarios en distribución.
- [x] CORS y cachés que mezclen usuarios/sitios o expongan respuestas privadas.
- [x] Fallos de segundo orden y combinaciones importación/REST/Multisite/caché.

## 07. Colaboraciones y notificaciones

- [x] Flujo completo proponer → listar → aceptar/declinar → notificar.
- [x] Estado y destinatarios correctos, mensajes completos sin truncado silencioso.
- [x] Permisos del emisor y receptor comprobados en todos los caminos.
- [x] Disponibilidad y publicación de ambos centros coherentes con requisitos.
- [x] Repetición, carrera, respuesta contradictoria y recuperación de errores SQL.
- [x] Opciones de notificación respetadas; wp_mail y formatos seguros.
- [x] Revelación de contactos solo en la transición/documentación autorizada.
- [x] Fallo de email separado del éxito de persistencia; feedback honesto al usuario.
- [x] No spam arbitrario, destinatarios externos manipulables o envío sin límites justificados.
- [x] Traducciones, enlaces admin, zona horaria y nombres correctos en emails.

## 08. Privacidad

- [x] Inventario de nombres, emails, mensajes, identificadores, ubicaciones y retención.
- [x] Política sugerida describe datos, destinatarios, conservación y terceros realmente usados.
- [x] Metadatos privados ocultos en REST propio/core, context=view/edit/embed, búsqueda y perfil.
- [x] Exportadores completos, datos asociados por email/autor, paginación y orden estable.
- [x] Borradores completos, paginación cuando se mutan resultados y anonimización coherente.
- [x] Datos personales en texto libre, notificaciones, logs y medios considerados.
- [x] Borrado/desinstalación sin datos residuales ni borrado de datos ajenos.
- [x] Mapa con consentimiento antes de conexiones externas y comportamiento al revocarlo.
- [x] Favoritos/localStorage: contenido mínimo, fallos de almacenamiento y ámbito correcto.
- [x] No telemetría, CDNs o solicitudes no documentadas.

## 09. Multisite y sincronización

- [x] Activación individual, en red y plugins activados solo en algunos sitios.
- [x] Creación/eliminación de sitios y tablas/capacidades correspondientes.
- [x] switch_to_blog/restore_current_blog balanceados incluso en errores.
- [x] Contexto de autorización antes/después de cambiar de sitio.
- [x] manage_network_options/manage_options y opciones de red/sitio correctamente separados.
- [x] Ningún blog_id del cliente autoriza por sí solo.
- [ ] Sitios privados, archivados, spam, eliminados y multisite multinetwork. **Validación parcial/pendiente; véase COBERTURA.md e INFORME.md.**
- [x] Sincronización por lotes: cursor, idempotencia, duplicados, interrupción y reintento.
- [x] Vinculación/reutilización de fichas sin sobrescribir datos de otro centro.
- [x] Coordenadas, URLs, identidad y datos privados del sitio origen tratados correctamente.
- [x] Complejidad en redes grandes y limpieza total paginada.

## 10. Administración, importación y editores

- [x] Menús, enlaces de ajustes, avisos acotados y capacidades mínimas.
- [x] Settings API: tipos, saneamiento, defaults, valores inválidos y CSRF.
- [x] Metabox clásico: autosave, revisiones, nonce, permisos y campos omitidos.
- [x] Gutenberg: store de edición, metadatos, guardado, permisos y estado asíncrono.
- [x] Selector de medios: adjuntos existentes, propiedad/permisos, tipo imagen y borrado.
- [x] Importador demo: autorización, nonce, fuentes locales, imágenes y limpieza de temporales.
- [x] Importar/sincronizar repetidamente no destruye ediciones manuales sin intención explícita.
- [x] Listado admin: privacidad por participante, paginación, estados y mensajes seguros.
- [x] UI informa errores de persistencia, red y operaciones parciales.

## 11. Directorio, perfil, bloques y frontend

- [x] Shortcode/bloque, atributos, renderizado y múltiples instancias en una página.
- [ ] Temas clásicos/bloques: wrappers, cabecera/pie, the_content, consultas y previews. **Validación parcial/pendiente; véase COBERTURA.md e INFORME.md.**
- [x] Filtros combinados, limpieza, ordenar por afinidad/fecha/nombre y paginar.
- [x] Peticiones en carrera, cancelación, errores HTTP/JSON y estados vacíos.
- [x] Resultados/contador/mapa coherentes; deduplicación y cargar más.
- [x] Favoritos persistentes, errores localStorage y contenido no confiable.
- [x] Drawer: centro origen autorizado, destino, campos, envío, éxito, error y cierre.
- [x] URLs y botones de web/podcast/login/retorno; protocolos inseguros rechazados.
- [x] Imágenes/logo/caratula/alt/fallback y dimensiones para evitar saltos.
- [x] Consentimiento OSM, atribución, coordenadas válidas, marcadores y enlaces.
- [x] Assets solo donde se necesitan; dependencias, defer, versionado y traducciones.
- [x] build sincronizado con fuentes y comportamiento igual al distribuido.

## 12. Accesibilidad y diseño adaptable

- [x] labels, nombres accesibles, títulos, landmarks y orden de encabezados.
- [x] Navegación por teclado, foco visible y orden lógico.
- [x] Dialog modal: foco inicial, trap, Escape, devolución del foco e inert/aria-modal.
- [x] aria-expanded/pressed/disabled y anuncios wp.a11y.speak de cambios.
- [x] Errores asociados a campos con aria-describedby/invalid.
- [ ] Contraste, zoom/reflow, móvil, texto largo y reducción de movimiento. **Validación parcial/pendiente; véase COBERTURA.md e INFORME.md.**
- [x] Contenido esencial utilizable con errores JS, mapa no aceptado y red lenta.
- [x] Diferenciar inspección estática de pruebas visuales/teclado realmente ejecutadas.

## 13. Rendimiento y disponibilidad

- [x] Consultas sin límites, N+1, precarga de meta/términos y costes de afinidad.
- [x] Límites efectivos antes de cargar grandes conjuntos en memoria.
- [x] Índices SQL para listados, participantes, estado y duplicados.
- [x] Caché e invalidación en cambios de centros/términos/disponibilidad.
- [x] Coste de filtros y generador de opciones por cada petición pública.
- [x] Trabajos largos por lotes/cron; errores y reanudación.
- [x] Activos pesados, biblioteca local, carga diferida de imágenes y mapa.
- [x] Candidatos de agotamiento distinguidos de mediciones reales y de endurecimiento.

## 14. Calidad, i18n y WordPress.org

- [ ] PHPCS WordPress/Extra/Docs, PHPCompatibilityWP y justificación de ignores. **Validación parcial/pendiente; véase COBERTURA.md e INFORME.md.**
- [ ] PHPStan nivel >=6, stubs y ausencia de exclusiones que oculten lógica relevante. **Validación parcial/pendiente; véase COBERTURA.md e INFORME.md.**
- [x] PHPDoc, strict types, retornos y compatibilidad de firmas con Core.
- [x] JS/CSS lint y dependencias con APIs Core sin duplicación innecesaria.
- [x] Cadenas traducibles, dominio literal, placeholders y comentarios translators.
- [x] POT actualizado y PO/MO/l10n.php/JSON coherentes con build.
- [x] Idioma base frente a regla del proyecto y decisiones documentadas.
- [x] readme WordPress.org, GPL, licencias/atribución de Leaflet, iconos e imágenes.
- [x] Plugin Check estático/runtime sin avisos injustificados.
- [x] Auditorías Composer/npm, versiones fijadas y alcance producción/desarrollo.
- [x] .distignore, ZIP real, autoload sin vendor dev, rutas POSIX y archivos sensibles.

## 15. Pruebas y CI

- [x] Leer todos los tests y comprobar que ejercitan entradas reales, no solo helpers.
- [ ] PHPUnit: positivos, permisos negativos, nonce, validación, datos maliciosos y regresiones. **Validación parcial/pendiente; véase COBERTURA.md e INFORME.md.**
- [ ] Matriz Single Site/Multisite, PHP mínimo/máximo y WP mínimo/actual. **Validación parcial/pendiente; véase COBERTURA.md e INFORME.md.**
- [x] Ciclos de activación, actualización, desactivación y desinstalación.
- [ ] E2E de directorio, filtros, perfil, propuesta/respuesta, editores y sincronización. **Validación parcial/pendiente; véase COBERTURA.md e INFORME.md.**
- [x] WP_DEBUG/WP_DEBUG_LOG/SCRIPT_DEBUG y revisión de errores.
- [x] CI ejecuta todos los controles exigidos y falla correctamente.
- [x] Entorno reproducible, imágenes fijadas, puertos locales y credenciales ficticias.
- [x] Registrar comandos, versiones, salidas, exit code y verificaciones bloqueadas.
- [x] Reproducciones mínimas de hallazgos sin modificar el código de producción.

## 16. Validación y entrega

- [x] Releer cada candidato desde la entrada intentando refutarlo con controles efectivos.
- [x] Fusionar duplicados por causa raíz y separar síntomas.
- [x] Hallazgos confirmados con evidencia estática o reproducción local explícita.
- [x] Hipótesis con hecho bloqueante exacto y plan seguro, sin severidad.
- [x] Registrar descartes para evitar repetir falsos positivos.
- [x] Revisión final de cobertura: superficies, roles, caminos alternativos y archivos omitidos.
- [x] Informe con prioridades de corrección, mitigaciones y pruebas de regresión propuestas.
- [x] Resumen de arquitectura, flujos, hooks, endpoints y capacidades.
- [x] Pendientes y limitaciones explícitos; no otorgar aprobación de publicación si faltan controles.
- [x] Comprobar que solo se añadieron documentos/evidencias de auditoría.
