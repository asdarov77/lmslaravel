<?php

namespace App\Http\Controllers;

use App\Mail\ResetPasswordMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Восстановление пароля.
 *
 * Сценарий: пользователь вводит ФИО, система создаёт токен и шлёт
 * ссылку на email; по ссылке — новая страница с формой нового пароля.
 *
 * Что здесь принципиально:
 *
 *  1. ОТВЕТ ОДИНАКОВ В ЛЮБОМ СЛУЧАЕ. Неизвестное ФИО, пользователь без
 *     email и нормальный запрос получают один и тот же 200-ответ. Иначе
 *     по разнице в ответе можно было бы перебрать существующие ФИО.
 *
 *  2. ТОКЕН ОДНОРАЗОВЫЙ И КОРОТКИЙ ПО ВРЕМЕНИ. Старые токены удаляются
 *     при новом запросе, а срок действия ограничен часом: ссылка в
 *     почтовом ящике не должна работать через неделю.
 *
 *  3. СБРОС ПО ФИО, А НЕ ПО EMAIL. Вход в систему идёт по ФИО, поэтому
 *     требовать email в форме восстановления нельзя — пользователь
 *     указывает то, чем он входит.
 *
 *  4. ПАРОЛЬ ХЭШИРУЕТСЯ ЧЕРЕЗ Hash::make, как и в остальном приложении:
 *     bcrypt() напрямую — устаревший вызов без явной настройки раундов.
 */
class ForgotPasswordController extends Controller
{
    /** Срок жизни токена восстановления. */
    private const TOKEN_TTL_MINUTES = 60;

    /**
     * Запрос ссылки восстановления.
     *
     * POST /api/password/forgot
     */
    public function forgot(Request $request)
    {
        $validated = $request->validate(
            ['fio' => ['required', 'string', 'max:150']],
            [],
            ['fio' => 'ФИО']
        );

        $user = User::where('fio', trim($validated['fio']))->first();

        /*
         * Пользователь найден, но почты нет: токен всё равно создаём.
         * Иначе по времени ответа или по телу можно было бы отличить
         * «такого ФИО нет» от «ФИО есть, но почта пустая». Письмо
         * отправлять некуда, и это будет видно только в логах.
         */
        if ($user !== null) {
            $this->issueToken($user);
        }

        // Одинаковый ответ во всех случаях: не раскрываем, есть ли такой
        // пользователь. Формулировка про «если ФИО найдено» честна и не
        // обещает письма там, где почты нет.
        return response()->json([
            'data' => [
                'message' => 'Если указанное ФИО найдено в системе, ссылка для восстановления пароля отправлена на почту.',
            ],
        ]);
    }

    /**
     * Установка нового пароля по токену.
     *
     * POST /api/password/reset
     */
    public function reset(Request $request)
    {
        $validated = $request->validate([
            'fio' => ['required', 'string', 'max:150'],
            'token' => ['required', 'string', 'max:64'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ], [], [
            'fio' => 'ФИО',
            'token' => 'токен',
            'password' => 'новый пароль',
        ]);

        $token = DB::table('password_resets')
            ->where('fio', trim($validated['fio']))
            ->where('token', $validated['token'])
            ->first();

        if ($token === null) {
            // Токена нет: либо ФИО не запрашивалось, либо токен уже
            // использован (одноразовый). Разница не раскрывается.
            abort(422, 'Ссылка недействительна или уже использована. Запросите новую.');
        }

        if (Carbon::parse($token->created_at)->diffInMinutes(Carbon::now()) > self::TOKEN_TTL_MINUTES) {
            DB::table('password_resets')->where('token', $token->token)->delete();

            abort(422, 'Срок действия ссылки истёк. Запросите новую.');
        }

        $user = User::where('fio', trim($validated['fio']))->first();

        if ($user === null) {
            // Пользователя удалили после запроса: токен больше не
            // действует, иначе он оставался бы в базе мёртвым грузом.
            DB::table('password_resets')->where('token', $token->token)->delete();

            abort(422, 'Пользователь не найден.');
        }

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            // Все токены сессии становятся недействительными: иначе
            // оставленная в браузере сессия осталась бы открытой после
            // смены пароля.
            'remember_token' => null,
        ])->save();

        DB::table('password_resets')->where('token', $token->token)->delete();

        return response()->json([
            'data' => [
                'message' => 'Пароль изменён. Войдите с новым паролем.',
            ],
        ]);
    }

    /**
     * Создаёт токен и отправляет письмо со ссылкой.
     *
     * Старые токены этого ФИО удаляются: ссылка из старого письма не
     * должна оставаться рабочей после нового запроса.
     */
    private function issueToken(User $user): void
    {
        DB::table('password_resets')->where('fio', $user->fio)->delete();

        $token = hash('sha256', Str::random(40) . $user->id . microtime());

        DB::table('password_resets')->insert([
            'email' => $user->email,
            'fio' => $user->fio,
            'token' => $token,
            'created_at' => Carbon::now(),
        ]);

        // Письмо отправляем только когда есть куда: без email токен
        // создан, но не доставлен, и это видно только в логах — ответ
        // API от этого не меняется.
        if ($user->email !== null && $user->email !== '') {
            try {
                Mail::to($user->email)->send(new ResetPasswordMail($user->fio, $token));
            } catch (\Throwable $e) {
                // Почтовый сервис может быть недоступен: токен в базе
                // остаётся, а запрос повторяется без ошибки 500 — иначе
                // недоступность SMTP выглядела бы как сломанное
                // восстановление пароля.
                report($e);
            }
        }
    }
}