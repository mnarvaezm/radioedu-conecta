# Auditoría de RadioEdu Conecta — 31/10/2026

## Documentos

1. [Plan de revisión](PLAN-DE-REVISION.md): 165 puntos, creado antes del análisis. Las marcas indican revisión, no conformidad; 10 puntos conservan validaciones parciales/pendientes.
2. [Informe](INFORME.md): 2 hallazgos de seguridad de severidad baja y 22 defectos funcionales/de cumplimiento, con prioridades, causas, reproducción y propuestas de corrección. Su última sección, [Seguimiento de correcciones](INFORME.md#seguimiento-de-correcciones-2026-11-01) (2026-11-01), recoge el estado de cada hallazgo, los commits, la verificación, los matices y lo pendiente.
3. [Cobertura](COBERTURA.md): 23 superficies, métodos, límites y verificaciones pendientes.
4. [Arquitectura y pruebas](ANEXO-ARQUITECTURA-Y-PRUEBAS.md): modelo de datos, hooks, endpoints, capacidades, herramientas y preparación de una entrega posterior.

## Evidencias

La carpeta `evidencias/` conserva inventarios/hash, salidas de comandos, sondas reproducibles y dos capturas de interfaz. Los scripts usan únicamente datos ficticios. Algunos alteran o eliminan datos del fixture: no ejecutarlos fuera de un entorno desechable preparado para ellos.

- Hallazgos de privacidad, cuota, paginación y migración: `runtime-checks.php/.txt`.
- Intercalado de cuota, error SQL, afinidad y fallback demo: `extended-checks.php/.txt`.
- Interfaz, mapa, reflow, nonces: `browser-checks.cjs/.json/.txt`, `drawer-race.png`, `mobile-320.png`.
- Red: `multisite-checks.php/.txt` y `multisite-extra.php/.txt`. La segunda prueba corrige la clasificación del administrador del primer fixture; no hay bypass de `manage_network_options` demostrado.
- Limpieza: `uninstall-checks.php`, resultados Single Site/Multisite y `cleanup.txt`.
- Calidad: salidas WPCS, PCP, lint, build, E2E, Composer/npm audit. Los intentos fallidos iniciales se conservan y están explicados.
- Traducciones: comparación POT, PO/MO/JSON y `.l10n.php`; 444 mensajes más cabecera en cada catálogo.
- Integridad: inventario inicial, comparación final de fuentes y comparación de los 100 archivos del ZIP.
- Seguimiento (2026-11-01): `seguimiento-2026-11-01/`, con los scripts de navegador que verificaron F-11, F-12, F-13 y F-16.

Las trazas completas de Playwright y el build aislado están también en `artifacts/audit-e2e*` y `artifacts/audit-build`. Son artefactos locales ignorados por Git. Las capturas y resultados principales se copiaron a esta carpeta.

No se corrigió código funcional ni se publicó nada. Todos los archivos fuente conservan su hash inicial. Se retiraron los recursos Docker creados para la auditoría. Antes de empaquetar una versión futura, excluir `docs/auditoria/` mediante `.distignore`: el ZIP existente no la contiene, pero la regla actual no la excluye.

**Actualización 2026-11-01.** Las correcciones se hicieron después, fuera de la auditoría, con un commit por hallazgo. `.distignore` ya excluye `docs`, `.env` y `.env.*` (commit `fbb1942`). Los hashes del inventario corresponden al código auditado, no al corregido.
