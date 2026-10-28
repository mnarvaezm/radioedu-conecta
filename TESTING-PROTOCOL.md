# Protocolo de Pruebas y Validación: RadioEdu Conecta

Este protocolo describe paso a paso las pruebas funcionales, de seguridad, de base de datos y de compatibilidad necesarias para validar el plugin **RadioEdu Conecta** en el entorno Dockerizado, garantizando el cumplimiento de los estándares de WordPress.org y las directrices de `AGENTS.md`.

---

## 1. Entorno de Pruebas (Docker)

El archivo [docker-compose.test.yml](file:///c:/radioedu-conecta/docker-compose.test.yml) está preconfigurado con:
* **WordPress**: 6.7 sobre PHP 8.2 (Apache).
* **Base de Datos**: MariaDB 10.11.
* **Puerto asignado**: `8090` (`http://localhost:8090`).
* **Modo Depuración Activo**: `WP_DEBUG=true`, `WP_DEBUG_LOG=true`, `SCRIPT_DEBUG=true`.
* **Montaje en Vivo**: La carpeta local del plugin se monta directamente en `/var/www/html/wp-content/plugins/radioedu-conecta`.

---

## 2. Fase 1: Despliegue y Activación

### Paso 1.1: Iniciar los contenedores
```powershell
docker compose -f docker-compose.test.yml up -d
```

### Paso 1.2: Activar el plugin y sincronizar las 6 emisoras de demostración
1. Accede a `http://localhost:8090/wp-admin` e inicia sesión.
2. En **Plugins → Plugins instalados**, activa **RadioEdu Conecta**.
3. Al activarlo en un sitio individual sin centros previos, se crean automáticamente las **6 emisoras de demostración** con sus imágenes de cabecera y logotipos, además de la página `/traspasando-fronteras/` con el shortcode `[radioedu_directorio]`.
4. También puedes volver a sincronizarlas en cualquier momento desde **RadioEdu Centros → Ajustes → "Sincronizar / Cargar las 6 emisoras de ejemplo"**.

### Paso 1.3: Verificar Tabla Personalizada y Logs
```powershell
docker exec radioedu-wordpress wp db query "DESCRIBE wp_radioedu_colaboraciones;" --allow-root
```
*Criterio de éxito*: Deben figurar `id`, `centro_emisor_id`, `centro_receptor_id`, `usuario_emisor_id`, `idea_proyecto`, `curso_edades`, `idiomas`, `fechas_aprox`, `estado`, `respuesta_mensaje`, `created_at`, `updated_at`.

---

## 3. Fase 2: Validación del Directorio, Mapa y Ficha de Perfil

| ID | Prueba | Acción | Resultado Esperado |
| :--- | :--- | :--- | :--- |
| **TC-01** | **Carga inicial del directorio** | Abrir `http://localhost:8090/traspasando-fronteras/`. | Se muestran el banner Hero, la barra de filtros, el contador de centros y la cuadrícula de tarjetas con cabeceras, logos circulares, banderas e insignias de colores. |
| **TC-02** | **Filtros facetados y búsqueda** | Filtrar por ubicación, idioma, etapa, temática, formato o texto libre. | La cuadrícula se actualiza asíncronamente vía `GET /wp-json/radioedu-conecta/v1/centros` sin recargar la página. |
| **TC-03** | **Ordenación por Afinidad** | Seleccionar *"Más afines"* en "Ordenar por". | Prioriza los centros disponibles y ordena según coincidencias de taxonomías con el centro del usuario conectado. |
| **TC-04** | **Vista de Mapa y Consentimiento RGPD** | Pulsar **"Vista de mapa"**. | Antes de conectar con `tile.openstreetmap.org`, se muestra el aviso RGPD. Al pulsar *"Aceptar y mostrar el mapa interactivo"*, se carga el mapa Leaflet con los marcadores y popups construidos con nodos DOM seguros. |
| **TC-05** | **Ficha de Perfil Individual** | Pulsar **"Ver perfil"** en cualquier tarjeta (ej. `/centro-radioedu/ceip-san-jose/`). | Se muestra la ficha con imagen de cabecera arriba, insignia de disponibilidad, logo circular, nombre, bandera, ubicación, etiquetas de colores, edades, idiomas, botones de acción a la izquierda y descripción debajo. |
| **TC-06** | **Solicitud de Match desde el Perfil** | Pulsar **"✈️ Proponer match"** dentro de la página de perfil individual de la emisora. | Se despliega directamente el panel lateral *"Solicitud de match"* en esa misma pantalla sin necesidad de volver al directorio. |

---

## 4. Fase 3: Flujo Completo de Match y Seguridad (Anti-IDOR / Rate Limit)

| ID | Prueba | Acción | Resultado Esperado |
| :--- | :--- | :--- | :--- |
| **TC-07** | **Envío de Propuesta** | Completar el formulario en el panel lateral y pulsar **Enviar propuesta**. | Devuelve HTTP `201`, muestra confirmación en verde y registra la propuesta en estado `pendiente`. |
| **TC-08** | **Protección Anti-IDOR** | Enviar por REST API un `centro_emisor_id` que no pertenece al coordinador autenticado. | El servidor rechaza la petición con HTTP `403 (radioedu_forbidden_center)`. |
| **TC-09** | **Gestión en WP Admin** | Ir a **RadioEdu Centros → Colaboraciones (Matches)** y pulsar **Aceptar** o **Declinar** con un mensaje. | El estado pasa a `aceptada` o `rechazada`, se envía el correo de notificación al centro emisor (incluyendo el email de contacto si fue aceptada) y se muestra el enlace `mailto:`. |
| **TC-10** | **Rate Limiting de Ventana Fija** | Intentar enviar más de 10 propuestas en menos de 1 hora. | El servidor responde con HTTP `429 (Too Many Requests)` sin reiniciar la ventana de expiración de 1 hora. |
| **TC-11** | **Privacidad RGPD** | Inspeccionar el HTML público y la respuesta JSON de `/wp-json/radioedu-conecta/v1/centros`. | Ningún correo electrónico ni nombre personal de coordinador aparece expuesto públicamente. |

---

## 5. Fase 4: Desinstalación Limpia (`uninstall.php`)

1. En **Plugins → Plugins instalados**, desactiva **RadioEdu Conecta** y pulsa **Borrar**.
2. Verifica en la base de datos que se han eliminado la tabla `wp_radioedu_colaboraciones`, los posts `radioedu_centro`, los términos de las 5 taxonomías, las opciones, los transients y las capacidades personalizadas.
