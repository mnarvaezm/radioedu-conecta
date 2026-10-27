# Briefing del Proyecto: RadioEdu Conecta

Este documento recopila los requisitos funcionales, técnicos, de diseño y de arquitectura del plugin **RadioEdu Conecta**, conforme al punto 0 y 12 de las directrices del proyecto y al diseño conceptual analizado de *Traspasando Fronteras*.

---

### 1. Datos Generales
- **Nombre del Plugin**: RadioEdu Conecta
- **Slug**: `radioedu-conecta`
- **Namespace PHP**: `RadioEdu\Conecta\`
- **Prefijo de funciones / constantes / BD**: `radioedu_conecta_`
- **Text Domain**: `radioedu-conecta`
- **Iniciativa**: *Traspasando Fronteras* (RadioEdu - Educarex)

---

### 2. Definición del Producto e Interfaz (Basado en Mockup Oficial)

#### A. Banner / Hero de Cabecera
- Título: **Traspasando Fronteras**
- Subtítulo: *Conectamos radios escolares de Extremadura con otras emisoras de España y del mundo.*
- Gráfico temático con mapa de conexiones y mensaje institucional (*"Diferentes voces, un mismo mundo - Radios escolares que hacen un mundo más grande"*).

#### B. Barra Superior de Filtros Facetados (Búsqueda Instantánea)
Filtros desplegables combinables en una barra horizontal:
1. 📍 **Ubicación**: País / Comunidad / Provincia / Localidad (Extremadura, resto de España e Internacional).
2. 💬 **Idioma**: Español, Inglés, Portugués, Francés, etc.
3. 🎓 **Etapa educativa**: Infantil, Primaria, Secundaria, Bachillerato, FP, Educación Especial, Adultos...
4. 🏷️ **Temas**: Medioambiente, Convivencia, Igualdad, Ciencia/STEAM, Cultura, Rural, Proyectos Europeos, etc.
5. 🎙️ **Formato**: Podcast, Entrevistas, Magacín, Noticias, Música, Ficción Sonora...
6. 👥 **Busca colaboración con...**: Input de texto / selector para filtrar por nombre o palabras clave de proyecto.
7. Botón de acción **Buscar centros**.

#### C. Barra de Estado y Visualización
- Contador dinámico de resultados (ej. *"Resultados (24 centros)"*).
- Selector **Ordenar por**:
  - *Más afines* (algoritmo de coincidencia de tags/intereses con el centro del usuario).
  - *Más recientes*.
  - *Alfabético (A-Z)*.
- Botón alternador **Vista de mapa** (toggle entre grid de tarjetas y mapa interactivo Leaflet).

#### D. Tarjeta de Centro (Card Grid)
- **Cabecera**: Foto/fachada del centro o ilustración de cabecera.
- **Acción rápida**: Botón de favoritos/guardado en forma de corazón (`♡` / `❤️`).
- **Avatar circular**: Logo distintivo de la radio escolar superpuesto sobre la foto.
- **Datos principales**:
  - Nombre de la radio escolar y centro (ej. *IES Sierra de Gata Radio*).
  - Bandera de la región/país + Localidad y Provincia (ej. Bandera de Extremadura + *Hoyos, Cáceres (Extremadura)*, Bandera de Irlanda + *Dublín, Irlanda*).
- **Insignias / Tags (Píldoras de colores según categoría)**:
  - Formatos (Verde menta): *Podcast*, *Entrevistas*.
  - Temáticas (Coral/Lavanda): *Medioambiente*, *Convivencia*, *Música*.
  - Contexto (Azul suave): *Rural*, *Intercambio cultural*, *Proyectos europeos*.
- **Acciones inferiores**:
  - Botón secundario: **Ver perfil** (abre ficha detallada o modal del centro).
  - Botón principal: **Proponer match** (botón destacado con icono de enviar).

#### E. Panel Lateral Desplegable: "Solicitud de match" (Drawer / Offcanvas)
Se abre al hacer clic en *Proponer match* en cualquier tarjeta:
- **Cabecera**:
  - Título: *Solicitud de match*
  - Subtítulo: *¡Cuéntanos tu idea y empezad a crear juntos!*
  - Botón de cierre `✕`.
- **Campos del formulario**:
  - 🏫 **Tu centro**: Selector preasignado con el centro del coordinador autenticado.
  - 🏛️ **Centro con el que quieres colaborar**: Preseleccionado según la tarjeta pulsada.
  - 💡 **Idea de proyecto**: Textarea para describir la propuesta conjunta (ej. *"Queremos crear un podcast conjunto sobre..."*).
  - 👥 **Curso o edades**: Selector de rango de edad / nivel educativo.
  - 💬 **Idioma(s)**: Selector de idioma de la colaboración.
  - 📅 **Fechas aproximadas**: Rango de fechas estimado para el proyecto.
  - 🚀 Botón **Enviar propuesta**.
- **Pie del panel (Guía en 3 pasos)**:
  1. *Encuentra*: Busca centros afines a tus intereses.
  2. *Conecta*: Envía tu propuesta de match.
  3. *Crea*: ¡Desarrollad juntos vuestro proyecto!

---

### 3. Modelo de Datos y Arquitectura en WordPress

#### A. Custom Post Type: `radioedu_centro`
- `post_title`: Nombre de la radio y centro escolar.
- `post_content`: Descripción detallada del proyecto de radio, historia y líneas de trabajo.
- `post_excerpt`: Resumen corto mostrado en la tarjeta.
- `thumbnail` (Imagen destacada): Foto de cabecera del centro.

#### B. Taxonomías (Filtros Facetados)
1. `radioedu_etapa` (Etapas educativas).
2. `radioedu_idioma` (Idiomas del proyecto).
3. `radioedu_tematica` (Temas de interés).
4. `radioedu_formato` (Formatos de radio y podcast).
5. `radioedu_localizacion` (País, Comunidad Autónoma, Provincia, Localidad).

#### C. Post Meta (`wp_postmeta`)
- `_radioedu_logo_id`: ID del archivo adjunto para el logo circular de la radio.
- `_radioedu_bandera`: Código de país/región para mostrar la bandera oficial.
- `_radioedu_coordinador_id`: ID del usuario `WP_User` responsable de la emisora.
- `_radioedu_lat` y `_radioedu_lng`: Coordenadas geográficas para la vista en mapa.
- `_radioedu_podcast_url`: URL de streaming o canal de podcasts (iVoox, Spotify, web propia).
- `_radioedu_edades`: Rango de edades de los alumnos.

#### D. Tabla Personalizada: `{$wpdb->prefix}radioedu_colaboraciones`
Almacena las solicitudes de colaboración sin saturar la tabla `wp_postmeta`:
- `id` (bigint auto_increment, PK)
- `centro_emisor_id` (bigint, FK a posts)
- `centro_receptor_id` (bigint, FK a posts)
- `usuario_emisor_id` (bigint, FK a users)
- `mensaje` (text)
- `curso_edades` (varchar 100)
- `idiomas` (varchar 100)
- `fechas_aprox` (varchar 150)
- `estado` (enum: 'pendiente', 'aceptada', 'rechazada', 'cancelada')
- `created_at` (datetime)
- `updated_at` (datetime)

---

### 4. Seguridad, Privacidad y Rendimiento
- **Nonces y Permisos**: Todo envío de propuesta de match exige `wp_verify_nonce` y comprobación de rol/capacidad (`propose_radioedu_colaboracion`).
- **Privacidad Escolar**: Cero exposición pública de direcciones de correo electrónico personales o de contacto. La comunicación inicial se realiza 100% dentro de la plataforma y vía notificaciones del sistema (`wp_mail`).
- **REST API Segura**: Endpoint `/wp-json/radioedu-conecta/v1/centros` con paginación, sanitización de argumentos de filtro y rate-limiting en `/v1/colaboraciones`.
- **Assets Limpios**: JavaScript Vanilla (ESNext) sin jQuery, con estilos CSS modernos encapsulados y mapa con Leaflet embebido localmente sin CDN externa (cumplimiento RGPD y WordPress.org).
