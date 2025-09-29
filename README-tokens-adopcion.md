# Guía de Adopción de Design Tokens (Ranking Futbolín)

Esta guía describe un plan incremental para sustituir valores hardcode (colores, tamaños, sombras, radios, espaciados) por los tokens definidos en `design-tokens-propuesta.css` sin introducir regresiones visuales.

## Principios
- Cambios pequeños, reversibles y con captura visual antes/después.
- No mezclar distintos tipos de reemplazo en un mismo commit (ej: colores + tipografías → separarlos).
- Mantener los valores legacy coexistiendo hasta finalizar cada fase.
- Evitar incrementar especificidad CSS (no añadir `!important` nuevos salvo que ya existiera y sea imprescindible).

## Fases Resumidas
1. Podium + Badge GM
2. Texto y Fondos comunes
3. Bordes, Sombras, Radios, Tamaños tipográficos
4. Espaciados y Limpieza final

---
## Fase 1: Podium + Badge GM
Objetivo: Reemplazar únicamente estilos muy aislados (clases `.ranking-row.top-*` y badge GM) para validar la mecánica.

Pasos:
1. Importar (o concatenar) temporalmente `design-tokens-propuesta.css` sólo en entorno de prueba (no producción todavía).
2. Cambiar gradientes fallback oro/plata/bronce a combinaciones de:
   ```css
   background: linear-gradient(180deg, var(--rf-podium-gold-fallback-top) 0%, var(--rf-podium-gold-fallback-bottom) 100%);
   border-color: var(--rf-podium-gold-fallback-border);
   box-shadow: var(--rf-shadow-ring-gold);
   ```
   (Equivalentes para silver / bronze.)
3. Sustituir aros (box-shadow inset) por tokens ring.
4. Badge GM: #111 → var(--rf-gm-bg); #ffd700 (color, borde, glow) → var(--rf-gm-accent) / var(--rf-gm-accent-glow).
5. Verificación visual: capturas antes/después (3 posiciones podium + perfil con badge GM) en navegadores con y sin soporte `:has()` si es posible.
6. Commit: `feat(tokens): podium + gm badge tokens (fase 1)`.

Rollback sencillo: revertir commit; tokens no afectan otras áreas.

---
## Fase 2: Texto y Fondos
Objetivo: Centralizar colores de texto/base y backgrounds.

Orden recomendado:
1. Texto headings: reemplazar usos directos #111827 → var(--rf-color-heading).
2. Texto principal: #1f2937 → var(--rf-color-text).
3. Texto muted: #6b7280 → var(--rf-color-text-muted).
4. Fondos: #fff/#ffffff → var(--rf-bg-surface); #f9fafb → var(--rf-bg-surface-alt); #eff6ff → var(--rf-bg-highlight).
5. Sólo cambiar si la regla no está ya delegando a otra variable. No duplicar.
6. Capturas: ranking listado, perfil, tablas y cards.
7. Commit(s) separados: `feat(tokens): text colors` y luego `feat(tokens): surface backgrounds`.

---
## Fase 3: Bordes, Sombras, Radios, Tipografía
Dividir en sublotes.

### 3A Bordes
- #e5e7eb → var(--rf-border-color).
- Validar que no existan variantes deliberadas (si las hay, crear token específico antes de sustituir).

### 3B Sombras
- Sustituir la sombra card repetida por var(--rf-shadow-card).
- Confirmar que no cambie la percepción de elevación en hover/focus.

### 3C Radios
- Mapear 5px a --rf-radius-sm, 10px a --rf-radius-lg, 12px a --rf-radius-xl, 20px a --rf-radius-pill.
- Si aparece 4px u 8px de manera orgánica, adoptar tokens xs/md según casos.

### 3D Tipografía
- Reemplazar de mayor a menor impacto: títulos secundarios (1.5em → --rf-font-size-2xl), badges/iconos (1.35em → --rf-font-size-xl), etc.
- Evitar introducir saltos de layout: comprobar wrapping antes/después.

Commits esperados (mínimo uno por sublote). Ej: `feat(tokens): border colors`, `feat(tokens): card shadows`, etc.

---
## Fase 4: Espaciados y Limpieza
1. Reemplazar paddings/margins comunes por tokens --rf-space-* comenzando por unidades grandes (50px, 25px, 20px) que son más fáciles de validar.
2. Homogeneizar 5px (space-2) y evaluar si puede normalizarse a 4px o 6px (decisión de diseño). Mantener mientras no se decida.
3. Eliminar gradientes fallback duplicados si los tokens bg1/bg2 ya cubren necesidades.
4. Revisar reglas muertas (grep de clases no presentes en HTML/PHP/JS) – borrar en bloque final.
5. Commit final: `refactor(tokens): spacing + cleanup`.

---
## Checklist de Control Visual por Commit
- [ ] Screenshot ranking (top 10 filas) antes/después.
- [ ] Screenshot perfil jugador con medallas.
- [ ] Contraste texto principal vs. fondo (usar DevTools / Lighthouse rápido).
- [ ] Revisión responsive (mínimo 360px ancho) para ver si cambios de tipografía o espaciamiento rompen layout.

## Riesgos y Mitigación
| Riesgo | Mitigación |
|--------|------------|
| Especificidad cambia (token dentro de regla reordenada) | No mover reglas, sólo reemplazar valores. |
| Cambio perceptual de color por perfil distinto de monitor | Comparar hex directo antes/después (debe ser igual). |
| Shadow DOM aislamiento | Asegurar que archivo de tokens se cargue/inyecte también en shadow host si se usa. |
| Caché navegador | Bump versión query param al introducir tokens (`?ver=tokens1`). |

## Integración Técnica Propuesta
1. Incluir `design-tokens-propuesta.css` muy temprano (enqueue con prioridad alta) o empaquetarlo en el build final.
2. Tras completar Fase 3, renombrar archivo a `design-tokens.css` y referenciar oficialmente.
3. Opcional: generar `:where()` wrappers si se decide bajar especificidad (no necesario de inicio).

## Convenciones de Nomenclatura
- Prefijo `--rf-` para tokens nuevos; reusar `--futbolin-` existentes sin duplicar significado.
- Evitar tokens “mágicos” (no crear tokens sólo usados una vez salvo que expresen semántica de diseño futura).

## Ejemplo de Reemplazo Controlado (Podium Oro)
Antes:
```css
.ranking-row.top-1 { background: linear-gradient(180deg, #ffe082 0%, #ffc107 100%); border-color: #e0b000; }
```
Después:
```css
.ranking-row.top-1 { background: linear-gradient(180deg, var(--rf-podium-gold-fallback-top) 0%, var(--rf-podium-gold-fallback-bottom) 100%); border-color: var(--rf-podium-gold-fallback-border); }
```
Verificación: diff visual idéntico (valores hex iguales), commit aislado.

## Métricas de Éxito
- % de colores hardcode reducidos (objetivo >80%).
- Ningún bug visual reportado entre releases intermedios.
- Facilidad para cambiar esquema de color (probar sustitución de primary/accent en un solo lugar).

## Roadmap Futuro (Opcional)
- Dark Mode: introducir set paralelo (prefijo `--rf-dark-*`) y usar media query `@media (prefers-color-scheme: dark)`.
- Theming dinámico por categoría: mapear gradientes a variables derivadas (ej. `--rf-cat-master-gradient`).
- Generación automatizada de tokens desde JSON/Build para evitar drift.

---
**Fin de la guía.**
