# Segunda revisión de las correcciones — 01/11/2026

## Contexto y alcance

Revisión independiente solicitada por el responsable del proyecto. Base auditada: `dc245a9711f113fbb332c570056bc0ee609bd842`. Estado corregido que se revisa: `63d9fbc277a8f4ef2baadd1d3efb0f675e31196f`. El informe previo y su seguimiento del 01/11 se conservan sin modificar. Hay 27 commits y 66 archivos versionados cambiados; al iniciar solo `docs/` figura sin seguimiento.

Se contrasta cada afirmación de cierre con el flujo completo y con casos negativos. Las decisiones documentadas sobre F-02, F-22 y S-02 no equivalen a correcciones técnicas. No se implementan cambios funcionales. Las comprobaciones activas se harán solo con datos ficticios en un entorno desechable. No se certifica ausencia de vulnerabilidades ni preparación para publicar.

## Plan de referencia

- [ ] Leer íntegramente el seguimiento y contrastar sus afirmaciones, cifras y límites.
- [ ] S-01: creación/restauración REST, papelera, borrador automático, editor clásico, roles exentos, caché y contexto de sitio.
- [ ] S-02: contrato de 20/h, concurrencia, consumo de cuota, errores de guardado y comunicación del riesgo aceptado.
- [ ] F-01/F-02/F-22: migración de autor de respuesta, exportación/borrado por lotes, respuestas antiguas, errores SQL, conservación, contactos y consentimiento documentado.
- [ ] F-03/F-10: identidad de demos antiguas/nuevas, colisiones, preservación de páginas y fichas reales, imágenes.
- [ ] F-04: instalación nueva, actualización parcial, comprobación del esquema, reintento, capacidades y efecto de una tabla incompatible.
- [ ] F-05/F-06: alcance SQL de permisos, paginación y orden, totales, email, editores, centros no publicados, límites de selectores y acceso cruzado.
- [ ] F-07/F-08: errores de escritura, conflictos, autor de respuesta, avisos desactivados/fallidos/parciales y duplicación de correo.
- [ ] F-09: afinidad explícita/implícita y orden de gestores/coordinadores.
- [ ] F-11: respuesta y cierre tardíos, cambio de destinatario, reenvío, borrador y estados del formulario.
- [ ] F-12: todas las páginas del mapa, tope 500, filtros, cancelación, errores, selección de vistas y consentimiento.
- [ ] F-13/F-16: reflow, elementos ocultos, contador y alternativa sin JS, tema y navegación.
- [ ] F-14: resolución/caché de URL, página configurada, contenido anidado, páginas privadas y filtros.
- [ ] F-15: contenido renderizado y reproductores, KSES al guardar, usuarios con/sin unfiltered_html, acceso al contenido protegido.
- [ ] F-17/Q-06: licencia completa, archivos incluidos y excluidos, ZIP existente frente al código nuevo.
- [ ] F-18/F-19/F-21: aislamiento de caché, bloqueo, caducidad/liberación/propietario, simultaneidad, huérfanos y paginación de red.
- [ ] F-20: limpieza multisitio de preferencias, preservación de otros ámbitos, opciones de bloqueo/reintento y recorrido completo.
- [ ] Revisar los tests añadidos buscando casos ausentes y aserciones que no cubran el defecto.
- [ ] Repetir las comprobaciones de calidad disponibles sin instalar dependencias ni modificar entornos ajenos.
- [ ] Guardar evidencias, matriz de cierre y hallazgos conforme se obtengan; comprobar integridad de fuentes y retirar recursos propios.

## Registro persistente

- Inicio: leídos el seguimiento completo y cambios de cupo, esquema, privacidad, red y desinstalación. Las validaciones anteriores se consideran afirmaciones del informe hasta contrastarlas en esta revisión.
- Estado: EN CURSO. No hay todavía veredicto final por hallazgo.

## Candidatos en investigación

Sin severidad hasta completar el flujo y la reproducción: comprobación de esquema basada solo en nombres de columnas; liberación de bloqueo sin identificar a su propietario tras caducar; vías no REST del cupo; veracidad del estado de notificación cuando se envían varios correos.
