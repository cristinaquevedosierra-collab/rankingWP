# Hitos (Años nº1/2/3) – Fallback y Debug

> Actualización Sept 2025: La API ahora sólo se consume desde el dominio oficial `https://ranking.fefm.net` (antes se usaba host temporal `illozapatillo.zapto.org`). Cualquier referencia antigua debe considerarse obsoleta.

Este plugin calcula los hitos de nº1/2/3 por temporada consultando el endpoint:

- GET /api/Ranking/GetRankingPorModalidadPorTemporadaESPGlicko2/{modalidadId}/{temporada}

Si la API responde vacía (o el cliente devuelve cache vacía), se activa una cache negativa para no martillear al servidor. Para facilitar pruebas y continuidad, existen:

## Parámetros de depuración

- rf_debug_hitos=1 → Fuerza la pestaña “Hitos” y muestra panel de debug.
- rf_debug_hitos_reset=1 → Limpia el flag global de “vacío” y también borra la cache negativa por (modalidad, temporada) para reintentar llamadas ahora.
- rf_debug_hitos_bypass_cache=1 → Añade `_rf_bust` a las URLs para saltar caches por URL y evita escribir marcas negativas en esta carga.

Ejemplo:

?rf_debug_hitos=1&rf_debug_hitos_reset=1&rf_debug_hitos_bypass_cache=1#tab-hitos

## Regla de cálculo por temporada

- Temporadas con ordinal >= 11: ignorar el campo `posicion` y determinar top‑3 por el orden del array (tras filtrar ES si procede).
- Temporadas con ordinal < 11: preferir `posicion` si es 1/2/3; si no está, usar orden del array.

## Fallback local (opcional)

Si la API no aporta datos, puede definirse un fallback en archivo local:

- Ruta: DOCUMENTACION/podiums_per_season.fallback.json
- Estructura:

```json
{
  "i": { "<temporadaOrd>": [jugadorId1, jugadorId2, jugadorId3], ... },
  "d": { "<temporadaOrd>": [jugadorId1, jugadorId2, jugadorId3], ... }
}
```

- Ejemplo de plantilla: `DOCUMENTACION/podiums_per_season.fallback.json.example`.
- Cuando el fallback está en uso, aparece un aviso verde “Modo fallback” en la pestaña Hitos.

## Notas

- “Temporada N” es el ordinal mostrado. La normalización de ID↔ordinal y año está automatizada.
- El endpoint ESP normalmente ya excluye extranjeros. Si incluyese, se filtra por ES y se usa ese array para el orden.
- Las coronas aparecerán para el `jugadorId` del perfil abierto. Asegúrate de que el perfil corresponde al jugador esperado.
