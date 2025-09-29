Este paquete ha sido ajustado para usar bundles CSS purgados si existen.

- Se añadió: includes/core/class-futbolin-css-loader.php (modo "soft").
- Se añadió en ranking-futbolin.php: require_once del loader.
- Se protegieron plantillas que linkaban CSS legacy directamente, para que no lo hagan si RF_CSS_OVERRIDE_STRONG está definido.
- Si NO existen archivos en dist/assets/css-purged/ (al menos core.css y components.css), NO se hace override y seguirá cargando el CSS legacy.

Para activar el override:
1) Copia tus bundles a: dist/assets/css-purged/core.css y dist/assets/css-purged/components.css (+ opcionales perfil.css, ranking.css, stats.css, tournaments.css).
2) Limpia cachés y recarga. El loader detectará los bundles y dejará de cargar assets/css/… (salvo rf-live.css y 90-compat-override.css).
