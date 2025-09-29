<?php
/**
 * Configuración centralizada de endpoints para la caché masiva.
 * Ajusta aquí las rutas reales de tu API sin tocar el motor.
 * Placeholders soportados:
 *   {modalidad}  -> id de modalidad
 *   {temporada}  -> id de temporada
 *   {playerId}   -> id de jugador
 */
return [
    // Endpoints base según Swagger (orden recomendado)
    'base' => [
        // Orden: primero torneos (para derivar temporadas), luego modalidades
        'torneos'     => '/api/Torneo/GetTorneos',
        'modalidades' => '/api/Modalidad/GetModalidades',
        'categorias'  => '/api/Categorias/GetAllCategoriasGlicko2',
        'estadisticas'=> '/api/Estadisticas/totales',
        'campeones'   => '/api/Jugador/GetCampeonesEspania',
    ],

    // Plantillas ranking
    'ranking_templates' => [
        'modalidad_temporada' => '/api/Ranking/GetRankingPorModalidadPorTemporadaESPGlicko2/{modalidad}/{temporada}',
        'modalidad'           => '/api/Ranking/GetRankingPorModalidadESPGlicko2/{modalidad}',
        // Adicional (por posición) si hiciera falta:
        // 'modalidad_temporada_posicion' => '/api/Ranking/GetRankingPorModalidadPorTemporadaPorPosicionESP/{modalidad}/{temporada}'
    ],

    // Límites (ajusta según rendimiento del servidor)
    'limits' => [
        'max_temporadas' => 80,
        'max_modalidades'=> 20,
        'max_rankings'   => 800,
        'players_per_ranking' => 80,
        'max_player_profiles' => 4000,
    ],

    // Perfil de jugador consolidado
    'player_endpoints' => [
        'datos'               => '/api/Jugador/{playerId}/GetDatosJugador',
        'posiciones_torneos'  => '/api/Jugador/{playerId}/GetJugadorPosicionPorTorneos',
        'puntuacion_categoria'=> '/api/Jugador/{playerId}/GetJugadorPuntuacionCategoria',
        'partidos'            => '/api/Jugador/GetJugadorPartidos/{playerId}',
        // Paginados / alternativos: 'partidos_pag' => '/api/Jugador/GetJugadorPartidosPag/{playerId}',
    ],
];
