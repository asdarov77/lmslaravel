<?php

/**
 * Конфиг UI feature-flags (REWORK R3).
 *
 * Флаги включаются точечно и снимаются без редеплоя кода — через .env:
 *   UI_COURSE_PLAYER_V2=true
 * Значение прокидывается во фронт как window.__UI_FLAGS__ (см.
 * resources/js/composables/useUiFlags.js) — SPA получает его из blade-шаблона,
 * а не из отдельного HTTP-запроса.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Плеер v2 (S1, docs/course-player-blueprint.md)
    |--------------------------------------------------------------------------
    | true  — маршрут /courses/:id/play (Pages/CoursePlayer.vue) активен;
    | false — маршрут не регистрируется в роутере, legacy /courses/itemmani
    |         (CourseManifestNew.vue) остаётся единственным путём открытия
    |         курса. Аварийный откат = снять env-переменную.
    */
    'course_player_v2' => env('UI_COURSE_PLAYER_V2', false),

];
