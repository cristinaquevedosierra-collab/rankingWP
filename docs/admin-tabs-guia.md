# Guía detallada de pestañas de administración (Ranking Futbolín API)

Orden oficial de referencia:

1. Conexión
2. Configuración
3. Páginas
4. Acciones de Datos
5. Control de listados oficiales
6. Generador de listados (RankGen)
7. Avanzado
8. Log

---
 
## 1. Conexión

Propósito: Definir credenciales y endpoint de la API remota.

Elementos clave:

- Base URL de la API (sin barra final)
- Usuario y Contraseña
- Botón "Probar conexión" (login contra /Seguridad/login sin persistir)
- Origen actual de cada valor (prioridad visual): Formulario (sin guardar) → Ajustes (esta pestaña) → Ajustes legacy → Filtro → Constante → Fallback JSON
- Última conexión OK (minutos y host)

Flujo recomendado:

1. Introducir credenciales
2. Probar conexión (ver mensaje verde/rojo)
3. Guardar credenciales
4. Revisar que el origen cambia a "Ajustes (esta pestaña)"

Riesgos / Notas:

- Si la base URL lleva barra doble o barra final extra puede romper endpoints.
- Constantes en wp-config.php solo se usan si el campo equivalente está vacío.
- Ante fallo intermitente comprobar: caducidad del token, CORS, firewall, DNS.

Diagnóstico rápido:

- Revisar pestaña Log (filtrar por 401 / 403 / timeout)
- Hacer test conexión: si falla, comparar payload con debug.log

KPIs relevantes:

- Tiempo medio de respuesta en logs (si está instrumentado)
- Frecuencia de reconexiones / errores 401

---
 
## 2. Configuración

Propósito: Activar / desactivar bloques funcionales y visuales del ranking y del perfil.

Subgrupos principales:

- Menú ranking elo (modalidad por defecto, modalidades visibles, ID previsualización perfil)
- Menu Ranking Anual (modalidad anual default, activación dobles/individual)
- Menu Estadísticas (mostrar campeones, torneos, hall of fame, informes, stats globales, clubs, rivalidades)
- Visualización de Jugador (habilitar perfil + pestañas: glicko, resumen, estadísticas, hitos, partidos, torneos)
- Modo mantenimiento (bloquea front para usuarios no admin)

Buenas prácticas:

- Mantener solo pestañas realmente usadas para reducir ancho de banda del perfil.
- Elegir un ID de jugador real para la previsualización (permite tests rápidos desde el panel Páginas -> "Abrir seleccionada").
- Activar Hall of Fame solo si se calculan previamente listados oficiales.

Riesgos:

- Desactivar sin querer pestañas clave provoca vacíos visuales.
- Modo mantenimiento afecta también a buscadores si permanece largo tiempo.

Checklist después de cambios:

- Ver /?jugador_id=&lt;ID&gt; y comprobar pestañas esperadas.
- Revisar logs por avisos de caché generando pestañas desactivadas.

---
 
## 3. Páginas

Propósito: Creación y gestión asistida de las páginas públicas necesarias:

- Página de Perfil de jugador (shortcode [futbolin_jugador])
- Página de Ranking (shortcode [futbolin_ranking])

Funciones:

- Crear página (con título, visibilidad y contraseña opcional)
- Insertar shortcode en página existente
- Abrir seleccionada (autocompleta ?jugador_id=&lt;ID demo&gt;)
- Eliminar (mover a papelera)

Persistencia auxiliar:
- Guardado local (localStorage) de última selección para agilizar flujos.



Riesgos:
- Insertar shortcode duplicado -> interfaz redundante (no crítico, pero limpiar manualmente).
- Borrar página en uso: enlaces rotos externos / menús.




Checklist tras crear:
- Abrir página ranking: verificar tabla y filtros.
- Abrir página perfil: añade ?jugador_id=ID y renderiza pestañas base.





---
 
## 4. Acciones de Datos

Propósito: Operaciones sobre caché y datasets.
Bloques:

1. Caché: Vaciar todas las cachés propias + toggle "Habilitar caché".
2. Precarga específica:
   - Precargar jugador (cachea sus 6 pestañas si están activas)
   - Precargar Top (rango configurable + modalidad) con progreso y cancelación
3. Precarga ALL (proceso programado multi-lote) con estado (cursor, total, porcentaje, fecha fin, tabs).
4. Versión de dataset (bump manual para invalidar transients masivos)
5. Limpieza de transients antiguos (pestañas obsoletas)
6. Cron settings: activar precache incremental y cleanup, top_n y time_budget.

Métricas expuestas:

- Jugadores cacheados (y fragmentos si se instrumenta)
- Progreso lote precarga
- Tabs cacheadas vs esperadas

Riesgos:

- Desactivar caché en producción → incremento de latencia y carga API.
- Bump excesivo de dataset_version → tormenta de re-cómputos.
- Precarga ALL en hosting limitado → timeouts si time_budget agresivo.

Procedimientos recomendados:

- Precargar TOP antes de campañas de alto tráfico.
- Ejecutar limpieza tras cambios estructurales de pestañas.

Troubleshooting:

- Jugador con <6 pestañas cacheadas → revisar razones en log inline (reasons{tab}).
- Lote detenido: comprobar si usuario pulsó Cancelar o si hubo error 500 (mirar Log).

---
 
## 5. Control de listados oficiales

Propósito: Centralizar cálculos/acciones sobre listados oficiales (Hall of Fame, etc.).
Estado actual: bloque migrado para futuras ampliaciones (diagnóstico y cálculos). Incluye acción de recalcular Hall of Fame si está definida.

Buenas prácticas futuras (sugerido):

- Añadir fecha/hora última regeneración.
- Guardar diff de entradas añadidas/eliminadas.
- Mostrar conteo por categoría.

Riesgos:

- Recalcular en hora pico puede afectar tiempos de respuesta.

---
 
## 6. Generador de listados (RankGen)

Propósito: Generar listados estáticos / embebibles con filtros específicos.
Características (según partial `rankgen-tab.php`):

- Formularios propios (no options.php) para evitar colisiones.
- Parámetros slug + modalidad + tipo de ranking.
- Uso de vista ?view=rankgen&slug=... para acceso público.

Buenas prácticas:

- Reutilizar slugs semánticos (ej. top-dobles-2024-q1).
- Documentar origen de cada listado y fecha de congelación.

Riesgos:

- Slug duplicado sobrescribe sin aviso si no se controla.
- Abuso de listados puede aumentar espacio de almacenamiento.

Checklist al publicar:

- Abrir URL pública y validar filtros aplicados.
- Verificar paginación interna si corresponde.

---
 
## 7. Avanzado

Propósito: Ajustes técnicos y de entorno.

Elementos:

- Timeout API (5–120s)
- Reintentos (0–5)
- Ancho máximo panel admin (UI ergonomía)
- Modo aislado (Shadow DOM) para aislar estilos front
- (Opcional) futuras banderas experimentales




Impactos:

- Timeout mayor mejora resiliencia pero bloquea hilos PHP más tiempo.
- Reintentos altos pueden agravar picos si backend lento.
- Shadow DOM reduce interferencias pero dificulta overrides de tema.




Recomendaciones:

- Producción estable: timeout 30s, reintentos 2–3.
- Activar Shadow DOM solo si hay conflictos de CSS significativos.




---
 
## 8. Log

Propósito: Observabilidad.

Componentes:

- Ajustes de logging (activar / desactivar plugin log)
- Selector de fuente: Plugin (Alto/Medio/Bajo), WP debug.log, Combinado
- Búsqueda textual y por códigos (200, 401, 404, 500)
- Autorefresco configurable (2–60 s)
- Acciones: refrescar, vaciar, descargar, borrar todos, descargar ZIP (todos niveles)



Formato recomendado de eventos (sugerido):
`[nivel][fecha][contexto] mensaje {meta}`

Buenas prácticas:

- Desactivar autorefresco si el log crece muy rápido (optimiza admin).
- Descargar antes de borrar en incidentes.
- Usar filtro de búsqueda para aislar errores HTTP específicos.

Riesgos:

- Log infinito sin rotación → espacio disco.
- Borrar antes de exportar → pérdida forense.

KPIs:

- Ratio errores/total peticiones.
- Latencias medias (si se registran).

---
 
## Anexos

### Prioridad de orígenes de conexión (resumen)

1. Ajustes Conexión (ranking_api_config)
2. Legacy (mi_plugin_futbolin_options)
3. Filtro futbolin_api_config
4. Constantes PHP
5. Fallback BUENO_master.json

### Flujos rápidos

- Alta inicial: Conexión → Páginas → Configuración → Acciones de Datos (precargar TOP) → Ver front.
- Regenerar tras importación masiva: Acciones de Datos (bump dataset) → Precargar TOP → Ver perfil random.
- Diagnóstico latencia: Log (filtrar 500) → Ajustar Timeout/Reintentos (Avanzado) → Revisar caché.

### Tabla de riesgos y mitigaciones

| Riesgo | Consecuencia | Mitigación |
|--------|--------------|------------|
| Caché desactivada en prod | Carga lenta y más llamadas API | Revisar indicador en Acciones de Datos, reactivar |
| Timeout muy bajo | Errores falsos de fallo API | Aumentar a >=20s si backend de alto volumen |
| Reintentos altos | Sobrecarga backend bajo fallo crónico | Limitar a 2–3 y monitorizar |
| Bump dataset abusivo | Tempest de recomputación | Planificar ventanas y usar precarga gradual |
| Shadow DOM activo sin necesidad | Dificultad para overrides de tema | Desactivar si no hay conflictos |
| Log sin rotación | Espacio en disco | Rutina cron de archivado/rotación |
| Slugs RankGen duplicados | Confusión / sobrescritura | Convención de nombres + listado de slugs usados |

---

## Roadmap sugerido (documentación / UX)

- Añadir métricas resumen (tiempo medio API, caché hit-rate) en Acciones de Datos.
- Sección de versiones (changelog interno) en Documentación.
- Botón "Copiar snippet" para constantes de conexión.
- Historial de regeneración Hall of Fame.

---
Fin de la guía.
