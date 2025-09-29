Fuentes de CSS en runtime
=========================

Este plugin carga estilos desde:

1) dist/assets/css-purged/ (opcional, si existe)
   - Bundles purgados: core.css, components.css, etc.
   - Si están presentes, tienen prioridad y sobreescriben el CSS legacy.

2) assets/css/ (por defecto)
   - Fuente canónica de estilos cuando no hay bundles purgados.

No se usa: public/css/
----------------------

- La carpeta public/css contiene copias históricas/duplicadas que no se encolan en WordPress.
- Para evitar confusión y conflictos, estos ficheros se han eliminado.

Cómo verificar qué CSS está activo
----------------------------------

- Añade ?rf_debug_css=1 a cualquier URL pública del sitio para registrar en logs los estilos encolados y su origen.
- El orden de prioridad efectivo es: dist/assets/css-purged > assets/css.

Notas
-----

- El tab de Hitos renderiza su CSS inline por defecto para garantizar fidelidad visual 1:1. Hay un modo opcional por enlace controlado vía constantes/flags internas.
