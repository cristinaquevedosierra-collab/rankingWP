# Guía para la IA — ranking-futbolin

> **Objetivo**: permitir a la IA analizar el plugin y la API sin que tengas que pegar instrucciones cada vez.

---

## Cómo trabajar (obligatorio)

1) **Carga y valida el master**  
   - Carga `BUENO_master.json` o `BUENO_master.improved.json` (si coexisten, prioriza `…improved.json`).  
   - **Validación mínima (hard-fail si falta algo):**  
     - `meta.baseUrl`, `meta.auth` (al menos esquema de *bearer* y/o mecanismo de refresco).  
     - `endpoints` (incluye variantes/candidatos y wrapper/paginación esperados).  
     - `enums` (`tipoCompeticion`, `modalidad`, `categoriaById`, `categoriaFree`, `temporada`).  
     - `seedIds` (IDs de ejemplo reproducibles).  
     - `anomalies` (casos donde `ALL ≠ PAG`, claves de detección).  
   - Si `meta.baseUrl` o `meta.auth` están vacíos o en blanco, **NECESITA DECISIÓN** (proponer *stub* seguro: `.env`/constante filtrada por `is_admin()` en WP y no *commit* de secretos).

2) **Análisis estático del ZIP del plugin**  
   - Enumera todos los “processors/servicios/clases” que llamen a la API (PHP y JS):  
     - En PHP: `includes/` y `services/`, y cualquier `wp_ajax_*`.  
     - En JS: `assets/js/` (p. ej., `rf-live-wiring.js`, búsquedas), y cualquier `fetch`/XHR.  
   - Extrae todas las rutas usadas en código (regex `/api/…` y `admin-ajax.php?action=…`) y compáralas con endpoints del master:  
     - Marca no listados en el master.  
     - Marca mapeos incompletos (falta wrapper o parámetros).  
   - Clasifica el uso de **ALL vs PAG** por *processor*:  
     - Debe usar **ALL** en “partidos de jugador”.  
     - Si solo existe **PAG**, debe **paginar** hasta completar conjunto (respetando *wrapper* del master).  
   - Verifica filtros y reglas por *processor*:  
     - Exclusión **liguilla** donde aplique (profesional).  
     - Filtros por `tipoCompeticionId`, `modalidadId`, `temporada` conforme a enums.  
     - Normalizaciones de texto (p. ej., `Amater → Amateur`; diacríticos: ñ, acentos) antes de comparar.  
   - **Bearer/baseUrl**:  
     - Prohibido *hardcodear* token/URL. Debe leerse de `meta.auth`/`meta.baseUrl` (inyectado a PHP/JS vía `wp_localize_script` o filtro).  
   - **Wrappers/paginación**:  
     - Verifica campos: `items`, `hasNextPage`, `pageIndex/totalPages` o el *wrapper* alternativo que indique el master.  
     - Señala *off-by-one* y bucles sin tope.  
   - **Búsqueda de jugadores**:  
     - Debe usar la ruta del master (path/querystring correcto) y el contrato de salida.  
     - Normaliza término de consulta (NFD + *strip* diacríticos) solo para comparar/consultar, sin alterar lo mostrado.  
   - **DOM aislado / iframes**:  
     - En *Shadow DOM* e iframes de mismo origen, asegúrate de no romper *wiring* (`listeners` delegados con `composedPath()`).  
     - No dependas de estilos globales para elementos críticos (usa estilos locales o `z-index` alto si hay overlay/botón).

3) **Propuestas de cambio → parches**  
   - Entrega diffs “unified” (`diff …`), cambios mínimos y seguros.  
   - Mantén nombres públicos/firmas para no romper integraciones.  
   - Si hay que “migrar” un endpoint, añade *feature flag*/parámetro para convivencia temporal.

4) **Reglas ambiguas**  
   - Resuelve con enums y rules del master.  
   - Si aún falta info: **NECESITA DECISIÓN** y aporta propuesta concreta (qué, dónde, por qué, impacto).

5) **Reglas de cómputo — Resultados en modalidades profesionales**  
   - “Profesionales” = solo modalidades: Open dobles, Open individual, Pro dobles, España dobles, España individual, Mixto.  
   - Excluir **partidos de liguilla**.  
   - Métrica incluida:  
     - Partidos jugados/ganados/perdidos.  
     - Competiciones jugadas/ganadas/perdidas.  
   - **Algoritmo (por jugador):**  
     - Fuente: endpoint **ALL** de “partidos de jugador” (o **PAG** + paginación exhaustiva).  
     - Filtra por `modalidadId ∈ profesionales` y excluye `liguilla`.  
     - Valida ganador del partido; si falta/ambigua, no contar en partidos ganados/perdidos/jugados (ver globales).  
     - Computa competiciones: agrupa por competición; si resultados determinan ganó/perdió la competición, cuenta en ganadas/perdidas; si solo jugó, en jugadas.

6) **Reglas de cómputo — Resultados globales**  
   - “Globales” = todas las partidas de la BBDD menos:  
     - Partidos con doble ganador o sin ganador por error (no cuentan en jugados/ganados/perdidos).  
   - Sí computan como **competiciones jugadas**; y ganadas/perdidas solo si el estado de la competición es inequívoco según la API.  
   - **Algoritmo (por jugador):**  
     - Fuente: **ALL** (o **PAG** paginando).  
     - Excluye solo los partidos con ganador inválido de las métricas de partidos.  
     - Para competiciones: si la API indica claro ganador final del jugador, suma en ganadas; si indica participación sin título, suma en jugadas (y perdidas si explícito).

---

## Entregables (en este orden)

**A) INFORME DE ACTUACIÓN (breve y preciso)**  
- Resumen ejecutivo (1–2 párrafos).  
- Mapa processors → endpoints → reglas (tabla).  
- Checklist de cumplimiento vs master.  
- Hallazgos (Correcto/Incorrecto con prioridad y `archivo:línea`).  
- Plan de remediación (Quick wins / Estructurales).

**C) LISTA DE PRUEBAS (deterministas)**  
- Pseudocurl con `seedIds` y enums del master.  
- Verificaciones por invariantes (ALL vs PAG, wrapper, filtros, normalizaciones, etc.).

---

## Notas específicas del plugin ranking-futbolin

- Vistas: `/futbolin-ranking`, `/perfil-jugador` (o `?jugador_id=`), `/h2h`.  
- DOM aislado: listeners que puedan cruzar Shadow DOM y iframes de mismo origen (usar `composedPath()` y delegación).  
- Live search: no volcar JSON crudo; normalizar consulta (NFD) para acentos/ñ; respetar contrato del master.  
- Overlay/UX: mantener `z-index` alto y autocierre para no falsear UX en test.

---

## Dónde están los archivos

- `ai/BUENO_master.improved.json` (canónico)  
- `ai/BUENO_master.json` (alias)  
- `ai/esquemas.txt` (esquemas DTO)  
- `ai/.env.example` (variables de entorno)

