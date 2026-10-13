<?php

namespace App\Http\Controllers;

use App\Support\Gamification;
use Illuminate\Http\Request;

/**
 * Геймификация: свои показатели и лидерборд группы.
 *
 * Данные только свои: чужой прогресс — не предмет этого раздела.
 * Лидерборд ограничен своей группой, потому что сравнивать человека
 * с группой другого набора бессмысленно.
 */
class GamificationController extends Controller
{
    /**
     * GET /api/gamification/me
     *
     * XP, уровень, streak, календарь активности, галерея бейджей и
     * лидерборд группы. Один эндпоинт вместо четырёх: страница
     * показывает всё это вместе, и четыре запроса означали бы четыре
     * рассинхронизированных снимка на одном экране.
     */
    public function me(Request $request)
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $xp = Gamification::totalXp($user);

        return response()->json([
            'data' => [
                'xp' => $xp,
                'level' => Gamification::levelProgress($xp),
                'streak' => Gamification::streak($user),
                // 35 дней: недельная полоса плюс четыре полные недели.
                'calendar' => Gamification::activityDays($user, 35),
                'badges' => Gamification::badgeGallery($user),
                'leaderboard' => Gamification::leaderboard($user),
                'group' => $user->group_id
                    ? optional($user->group)->groupname
                    : null,
            ],
        ]);
    }
}