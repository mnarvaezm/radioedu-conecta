# Comprobaciones de navegador del seguimiento (2026-11-01)

Scripts de Playwright con Edge usados para verificar las correcciones que no cubren los tests PHP. Se ejecutan con `node <script>` desde un equipo con el repositorio y sus `node_modules`. Todos los scripts trabajan contra el entorno desechable de `http://localhost:8091`: no deben ejecutarse contra otros sitios.

| Script | Hallazgo | Qué comprueba | Resultado |
| --- | --- | --- | --- |
| `e2e-drawer-race.js` | F-11 | Envía una propuesta a A con la respuesta retenida, cierra el panel, lo abre para B y libera la respuesta de A. La petición se intercepta con un 201 simulado, así que no se guarda nada ni se envían correos. Necesita la cuenta de pruebas `admin`/`admin` del entorno. | Con el build anterior, ID de A, etiqueta de B y borrador perdido. Con el nuevo, se conservan el receptor B y su borrador. |
| `e2e-map-all.js` | F-12 | Con 66 centros con coordenadas, cuenta las tarjetas y los marcadores del mapa. Las teselas de OpenStreetMap se bloquean. | 24 tarjetas y 66 marcadores, con una petición extra de 100 resultados. |
| `e2e-reflow.js` | F-13 | A 320 px de ancho, lista los elementos del directorio que salen del viewport. | Ninguno, con Twenty Twenty-Five y con YOOtheme. |
| `e2e-nojs.js` | F-16 | Con JavaScript desactivado, comprueba el contador, el aviso y el enlace al listado. Con JavaScript, que el aviso se oculte. | Contador real (66), aviso visible con enlace a `/centros-radioedu/`, y aviso oculto con JavaScript. |

Los 60 centros extra de F-12 y F-16 eran datos ficticios del entorno de QA. Se borraron en la prueba de desinstalación de F-20.
