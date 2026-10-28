# Briefing del Proyecto: RadioEdu Conecta

Este documento recopila los requisitos funcionales, técnicos, de diseño y de arquitectura del plugin **RadioEdu Conecta**, conforme al punto 0 y 12 de las directrices del proyecto y al diseño oficial de *Traspasando Fronteras*.

---

### 1. Datos Generales
- **Nombre del Plugin**: RadioEdu Conecta
- **Slug**: `radioedu-conecta`
- **Namespace PHP**: `RadioEdu\Conecta\`
- **Prefijo de funciones / constantes / BD**: `radioedu_conecta_`
- **Text Domain**: `radioedu-conecta`
- **Iniciativa**: *Traspasando Fronteras* (RadioEdu - Educarex)

---

### 2. Definición del Producto e Interfaz

#### A. Banner / Hero de Cabecera
- Título: **Traspasando Fronteras**
- Subtítulo: *Conectamos radios escolares de Extremadura con otras emisoras de España y del mundo.*
- Nota adhesiva institucional (*"Diferentes voces, un mismo mundo - Radios escolares que hacen un mundo más grande 🌍"*).

#### B. Barra Superior de Filtros Facetados (Búsqueda Instantánea)
Filtros desplegables combinables en una barra horizontal:
1. 📍 **Ubicación**: País / Comunidad / Provincia / Localidad (Extremadura, resto de España e Internacional).
2. 💬 **Idioma**: Español, Inglés, Portugués, Francés, Catalán, Valenciano, etc.
3. 🎓 **Etapa educativa**: Infantil, Primaria, Secundaria, Bachillerato, FP...
4. 🏷️ **Temas**: Medioambiente, Convivencia, Igualdad, Música, Cultura, Rural, Proyectos Europeos, etc.
5. 🎙️ **Formato**: Podcast, Entrevistas, Magacín, Noticias, Ficción Sonora...
6. 👥 **Busca colaboración con...**: Input de texto para filtrar por nombre o palabras clave de proyecto.
7. Botón de acción **Buscar centros**.

#### C. Barra de Estado, Paginación y Visualización
- Contador dinámico de resultados (ej. *"Resultados (6 centros)"*).
- Selector **Ordenar por**:
  - *Más afines* (prioriza centros disponibles y ordena según el número de etiquetas/taxonomías compartidas con el centro del usuario conectado).
  - *Más recientes*.
  - *Alfabético (A-Z)*.
- Botón alternador **Vista de mapa** (toggle entre cuadrícula de tarjetas y mapa interactivo Leaflet con aviso de consentimiento RGPD previo a cargar cartografía de OpenStreetMap).
- Botón **Cargar más centros** cuando existen múltiples páginas de resultados.

#### D. Tarjeta de Centro (Card Grid)
- **Cabecera**: Foto/fachada del centro o ilustración de cabecera.
- **Acción rápida**: Botón de favoritos en forma de corazón (`♡` / `❤️`) persistente en `localStorage`.
- **Avatar circular**: Logo distintivo de la radio escolar superpuesto sobre la foto.
- **Datos principales**:
  - Nombre de la radio escolar y centro (ej. *IES Sierra de Gata Radio*).
  - Bandera de la región/país + Localidad y Provincia.
- **Insignias / Tags (Píldoras de colores según categoría)**:
  - Formatos (Verde menta): *Podcast*, *Entrevistas*, *Magacín*, *Noticias*.
  - Temáticas (Coral): *Medioambiente*, *Convivencia*, *Música*, *Igualdad*.
  - Etapas (Azul suave): *Primaria*, *Secundaria*, *Bachillerato*, *FP*.
- **Acciones inferiores**:
  - Botón secundario: **Ver perfil** (abre la ficha detallada de la emisora).
  - Botón principal: **Proponer match** (o *No disponible* si el centro ha pausado la recepción de propuestas).

#### E. Ficha de Perfil Individual de la Emisora (`templates/single-centro.php`)
- **Enlace superior**: Retorno directo al directorio (*"← Volver al directorio Traspasando Fronteras"*).
- **Cabecera superior**: Imagen de portada a ancho completo con insignia de estado (*"🟢 Disponible para colaborar"* / *"⚪ No disponible actualmente"*).
- **Bloque de identidad e información (debajo de la cabecera, alineado a la izquierda)**:
  - Logotipo circular de la emisora superpuesto.
  - Nombre de la emisora, bandera y ubicación geográfica.
  - Píldoras de colores (Formatos, Temáticas y Etapas).
  - Bloques informativos de **Cursos / Edades** e **Idiomas de trabajo**.
  - Botones de acción: **✈️ Proponer match** (abre el panel lateral de solicitud directamente en la misma ficha sin volver al directorio), **🎧 Escuchar Podcast / Emisora** y **🌐 Web del centro**.
- **Sección inferior**: Texto descriptivo completo de la emisora escolar (*"Sobre nuestra emisora escolar"*).

#### F. Panel Lateral Desplegable: "Solicitud de match" (Drawer / Offcanvas) y Gestión en WP Admin
- Se abre al hacer clic en *Proponer match* tanto desde las tarjetas del directorio como desde la ficha individual del centro.
- **Campos del formulario**:
  - 🏫 **Tu centro**: Selector con los centros gestionados por el usuario autenticado (por autoría, email de coordinación o permisos de edición).
  - 🏛️ **Centro con el que quieres colaborar**: Preseleccionado según el centro elegido.
  - 💡 **Idea de proyecto**, 👥 **Curso o edades**, 💬 **Idioma(s)** y 📅 **Fechas aproximadas**.
- **Gestión en el Panel de Administración (`RadioEdu Centros → Colaboraciones (Matches)`)**:
  - Permite a los coordinadores y administradores ver todas las solicitudes recibidas y enviadas, escribir un mensaje de respuesta y **Aceptar** o **Declinar** propuestas pendientes.

---

### 3. Seguridad, Privacidad RGPD y Multisite
- **Anti-IDOR y Permisos**: Todo envío de propuesta verifica `X-WP-Nonce`, capacidad `propose_radioedu_colaboracion`, disponibilidad del centro receptor y autorización real del usuario sobre el centro emisor (`ColaboracionesController::user_can_manage_center()`).
- **Privacidad Escolar Asimétrica y RGPD**: Cero exposición pública de correos electrónicos de docentes. Integración con los exportadores y borradores de datos personales del Core de WordPress (`PrivacyPolicy`).
- **Multisite y Desinstalación Limpia**: Soporte para activación individual o en red, hooks `wp_initialize_site` / `wp_uninitialize_site` y borrado completo de tablas, CPTs, términos, opciones, transients y capacidades en `uninstall.php`.
