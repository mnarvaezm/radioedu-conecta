# Protocolo de Pruebas y Validación: RadioEdu Conecta

Este protocolo describe paso a paso las pruebas funcionales, de seguridad, de base de datos y de compatibilidad necesarias para validar el plugin **RadioEdu Conecta** en un entorno Dockerizado, garantizando el cumplimiento de los estándares de WordPress.org y las directrices de `AGENTS.md`.

---

## 1. Entorno de Pruebas (Docker)

El archivo [docker-compose.test.yml](file:///c:/radioedu-conecta/docker-compose.test.yml) está preconfigurado con:
* **WordPress**: 6.7 sobre PHP 8.2 (Apache).
* **Base de Datos**: MariaDB 10.11.
* **Puerto asignado**: `8090` (para evitar colisiones con otros servicios activos en los puertos 80 y 8080).
* **Modo Depuración Activo**: `WP_DEBUG=true`, `WP_DEBUG_LOG=true`, `SCRIPT_DEBUG=true`.
* **Montaje en Vivo**: La carpeta local del plugin se monta directamente en `/var/www/html/wp-content/plugins/radioedu-conecta`.

---

## 2. Fase 1: Despliegue del Contenedor

### Paso 1.1: Iniciar los contenedores
Ejecuta en la terminal PowerShell dentro del directorio del proyecto:
```powershell
docker compose -f docker-compose.test.yml up -d
```

### Paso 1.2: Verificar el estado
Comprueba que los contenedores `radioedu-wordpress` y `radioedu-db` están corriendo:
```powershell
docker ps --filter "name=radioedu"
```

---

## 3. Fase 2: Configuración Inicial de WordPress

1. Abre el navegador en: **`http://localhost:8090`**.
2. Completa la instalación guiada de WordPress:
   * **Idioma**: Español.
   * **Título del sitio**: *RadioEdu - Traspasando Fronteras (Test)*.
   * **Usuario**: `admin`.
   * **Contraseña**: `admin1234` *(entorno de pruebas local)*.
   * **Correo electrónico**: `admin@educarex.local`.
3. Inicia sesión en el panel de control: `http://localhost:8090/wp-admin`.

---

## 4. Fase 3: Activación del Plugin y Verificación de BD

### Paso 3.1: Activar el plugin
1. Dirígete a **Plugins → Plugins instalados**.
2. Localiza **RadioEdu Conecta** y pulsa **Activar**.
3. Comprueba que aparece el nuevo elemento en el menú lateral: **RadioEdu Centros** con el icono de audio.

### Paso 3.2: Verificar Base de Datos y Schemas
Comprueba que la tabla personalizada `wp_radioedu_colaboraciones` ha sido creada correctamente:
```powershell
docker exec -it radioedu-wordpress bash -c "mysql -uwordpress -pwordpresspassword -hdb wordpress -e 'DESCRIBE wp_radioedu_colaboraciones;'"
```
*Criterio de éxito*: Deben figurar los campos `id`, `centro_emisor_id`, `centro_receptor_id`, `usuario_emisor_id`, `idea_proyecto`, `curso_edades`, `idiomas`, `fechas_aprox`, `estado`, `created_at`, `updated_at`.

### Paso 3.3: Auditoría de Logs de Depuración
Verifica que no se ha registrado ningún *Notice*, *Warning* o *Deprecated*:
```powershell
docker exec -it radioedu-wordpress cat /var/www/html/wp-content/debug.log
```
*Criterio de éxito*: El archivo debe estar vacío o no existir.

---

## 5. Fase 4: Pruebas Funcionales y Población de Datos

### Paso 4.1: Crear Centros de Prueba
Crea al menos 3 centros desde **RadioEdu Centros → Añadir nuevo centro**:

1. **Centro 1: IES Sierra de Gata Radio**
   * *Título*: IES Sierra de Gata Radio
   * *Contenido*: Proyecto de radio escolar en el entorno de la Sierra de Gata.
   * *Foto de cabecera*: Sube una foto panorámica.
   * *Logo de la radio*: Sube un logo circular en el metabox.
   * *Bandera*: `Extremadura (España)`.
   * *Coordenadas*: Latitud `40.2398`, Longitud `-6.7214`.
   * *Taxonomías*:
     * Etapas: *Secundaria*.
     * Idiomas: *Español*.
     * Temáticas: *Medioambiente*, *Rural*.
     * Formatos: *Podcast*, *Entrevistas*.
   * *Publicar*.

2. **Centro 2: Ràdio Institut Montjuïc**
   * *Título*: Ràdio Institut Montjuïc
   * *Bandera*: `Cataluña (España)`.
   * *Coordenadas*: Latitud `41.3667`, Longitud `2.1583`.
   * *Taxonomías*:
     * Etapas: *Secundaria*, *Bachillerato*.
     * Idiomas: *Español*, *Catalán*.
     * Temáticas: *Música*, *Cultura*.
     * Formatos: *Entrevistas*, *Magacín*.
   * *Publicar*.

3. **Centro 3: Coláiste Dublin Radio**
   * *Título*: Coláiste Dublin Radio
   * *Bandera*: `Irlanda`.
   * *Coordenadas*: Latitud `53.3498`, Longitud `-6.2603`.
   * *Taxonomías*:
     * Etapas: *Secundaria*.
     * Idiomas: *Inglés*.
     * Temáticas: *Proyectos europeos*, *Idiomas*.
     * Formatos: *Podcast*.
   * *Publicar*.

### Paso 4.2: Publicar la Página del Directorio
1. Dirígete a **Páginas → Añadir nueva página**.
2. Título: *Traspasando Fronteras*.
3. Contenido: Inserta un bloque de shortcode con:
   ```text
   [radioedu_directorio]
   ```
4. Pulsa **Publicar** y abre la página en el navegador.

---

## 6. Fase 5: Validación de Interfaz y Filtros (Mockup Parity)

| ID | Prueba | Acción | Resultado Esperado |
| :--- | :--- | :--- | :--- |
| **TC-01** | **Carga inicial** | Cargar la página pública. | Se muestran el banner Hero, la barra de filtros, el contador de centros y la cuadrícula de tarjetas con fotos, logos circulares, banderas e insignias de colores. |
| **TC-02** | **Filtro por Ubicación** | Seleccionar `Extremadura` en el desplegable de ubicación. | La lista se actualiza asíncronamente mostrando únicamente los centros extremeños sin recargar la página. |
| **TC-03** | **Filtro por Idioma** | Seleccionar `Inglés`. | Se muestran sólo emisoras que emiten en inglés (ej. Coláiste Dublin Radio). |
| **TC-04** | **Búsqueda por texto** | Escribir *Sierra* en "Busca colaboración con...". | Filtra al instante el IES Sierra de Gata Radio. |
| **TC-05** | **Vista de mapa** | Pulsar el botón **"Vista de mapa"**. | El grid se oculta y aparece el mapa interactivo Leaflet con los marcadores geolocalizados. Al hacer clic en un pin, se abre el popup con los datos del centro. |
| **TC-06** | **Sistema de Favoritos** | Pulsar el corazón (`♡`) en una tarjeta. | El corazón cambia a rojo (`❤️`) y persiste tras refrescar la página (`localStorage`). |

---

## 7. Fase 6: Flujo de Match (Colaboraciones)

| ID | Prueba | Acción | Resultado Esperado |
| :--- | :--- | :--- | :--- |
| **TC-07** | **Apertura de Drawer** | Pulsar el botón **"Proponer match"** en una tarjeta. | Se despliega suavemente el panel lateral derecho con el centro receptor precargado y el formulario completo. |
| **TC-08** | **Envío de Propuesta** | Rellenar la idea del proyecto y pulsar **Enviar propuesta**. | Aparece mensaje de confirmación en verde y se registra la colaboración con estado `pendiente`. |
| **TC-09** | **Rate Limiting (Anti-spam)** | Intentar enviar más de 10 solicitudes seguidas. | El servidor responde con error HTTP `429 (Too Many Requests)` indicando que se ha alcanzado el límite por hora. |
| **TC-10** | **Privacidad RGPD** | Inspeccionar el código fuente del navegador (`F12`). | **Ningún correo electrónico** de los coordinadores aparece expuesto en el HTML. |

---

## 8. Fase 7: Pruebas Multisite (Educarex)

1. En el archivo `wp-config.php` del contenedor, activar multisite:
   ```php
   define( 'WP_ALLOW_MULTISITE', true );
   ```
2. En el panel de control, ir a **Herramientas → Configuración de la red** e instalar la red.
3. Desactivar el plugin a nivel de sitio y pulsar **Activar en la red** (*Network Activate*).
4. Crear un nuevo sitio escolar (ej. `/centro2/`) y verificar que el directorio y los CPT funcionan de manera aislada y limpia.

---

## 9. Fase 8: Validación de Desinstalación Limpia (`uninstall.php`)

1. Dirígete a **Plugins → Plugins instalados**.
2. Desactiva **RadioEdu Conecta** y pulsa **Borrar**.
3. Comprueba en la base de datos que no quedan tablas ni opciones huérfanas:
   ```powershell
   docker exec -it radioedu-wordpress bash -c "mysql -uwordpress -pwordpresspassword -hdb wordpress -e 'SHOW TABLES LIKE \"%radioedu%\"; SELECT option_name FROM wp_options WHERE option_name LIKE \"%radioedu%\";'"
   ```
   *Criterio de éxito*: No debe devolverse ningún registro (0 tablas, 0 opciones huérfanas).

---

## 10. Comandos Útiles de Gestión Docker

* **Iniciar el entorno**: `docker compose -f docker-compose.test.yml up -d`
* **Detener el entorno**: `docker compose -f docker-compose.test.yml stop`
* **Reiniciar servicios**: `docker compose -f docker-compose.test.yml restart`
* **Ver logs en tiempo real**: `docker compose -f docker-compose.test.yml logs -f wordpress`
* **Destruir y reiniciar desde cero**: `docker compose -f docker-compose.test.yml down -v`
