<?php

namespace App\Support;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Создание уведомлений.
 *
 * Зачем сервис, а не Notification::create в контроллерах: уведомление
 * должно слаться из места, где произошло событие (сдача экзамена,
 * публикация объявления, ответ в теме), а не из того, кто его читает.
 * Когда создание сидилось в контроллере чтения, уведомлений не
 * появлялось вообще — колокольчик был всегда пуст.
 *
 * Здесь же гасятся две типовые ошибки рассылки:
 *  - уведомление самому себе (автору ответа, автору объявления);
 *  - дубли: один и тот же ответ не должен слать уведомление дважды,
 *    поэтому уникальность проверяется по (user_id, type, ключ в data).
 */
class Notifications
{
    /**
     * Уведомление одному пользователю.
     *
     * @param  string  $type  машинный тип: exam_passed, announcement, forum_answer
     * @param  array<string, mixed>  $data  произвольный контекст (id объектов)
     */
    public static function toUser(User $user, string $type, string $title, ?string $body = null, ?string $link = null, array $data = []): ?Notification
    {
        if ($user->id === null) {
            return null;
        }

        // Ключ дедупликации: без него повторное событие создавало бы
        // столько же уведомлений, сколько раз сработало.
        if (! empty($data['dedup_key'])) {
            $existing = Notification::where('user_id', $user->id)
                ->where('type', $type)
                ->where('data->dedup_key', $data['dedup_key'])
                ->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        return Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => mb_substr($title, 0, 255),
            'body' => $body === null ? null : mb_substr($body, 0, 2000),
            'link' => $link,
            'data' => $data === [] ? null : $data,
        ]);
    }

    /**
     * Уведомление нескольким пользователям.
     *
     * @param  iterable<User>  $users
     */
    public static function toMany(iterable $users, string $type, string $title, ?string $body = null, ?string $link = null, array $data = []): int
    {
        $sent = 0;

        foreach ($users as $user) {
            if (self::toUser($user, $type, $title, $body, $link, $data) !== null) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Уведомить пользователей, у которых есть право.
     *
     * Так объявляется системное событие («новый экзамен», «курс
     * опубликован»): рассылать его всем подряд нельзя, а перебирать
     * роли вручную в каждом месте — источник ошибок.
     *
     * @return int сколько уведомлений создано
     */
    public static function toPermission(string $permission, string $type, string $title, ?string $body = null, ?string $link = null, array $data = []): int
    {
        $users = User::query()->get()->filter(fn (User $user) => $user->hasPermission($permission));

        return self::toMany($users, $type, $title, $body, $link, $data);
    }

    /**
     * Уведомить всю группу, кроме исключённых.
     *
     * Исключение нужно, чтобы автор не получил уведомление о своём же
     * действии: это самая заметная лишняя запись в ленте.
     *
     * @param  Collection<int, User>  $users
     * @return int
     */
    public static function toGroupUsers(Collection $users, User $except, string $type, string $title, ?string $body = null, ?string $link = null, array $data = []): int
    {
        return self::toMany(
            $users->reject(fn (User $user) => $user->id === $except->id),
            $type,
            $title,
            $body,
            $link,
            $data
        );
    }
}