<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Policies\GroupPolicy;
use App\Policies\PermissionPolicy;
use App\Policies\CoursePolicy;
use App\Policies\ExamPolicy;
use App\Policies\Group2learningPolicy;
use App\Policies\QuestionBankPolicy;
use App\Policies\UserPolicy;
/**
 * Регистрация политик и Gate::before.
 *
 * Gate::before возвращает true ТОЛЬКО для суперадмина и null во всех
 * остальных случаях: null, а не false, иначе он перебил бы остальные
 * политики и каждый запрос проходил бы как разрешённый или как запрещённый
 * без их участия.
 */
class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
        'App\Models\Group' => 'App\Policies\GroupPolicy',
        'App\Models\Permission' => 'App\Policies\PermissionPolicy',
        // Область полномочий по пользователям (своя запись против любой).
        'App\Models\User' => 'App\Policies\UserPolicy',
        // Учебные записи: своя группа против любой.
        'App\Models\Group2learning' => 'App\Policies\Group2learningPolicy',
        // Курсы: «видеть» (по подписке группы) и «управлять» — разные.
        'App\Models\Course' => 'App\Policies\CoursePolicy',
        // Экзамены: управлять и сдавать — разные.
        'App\Models\Exam' => 'App\Policies\ExamPolicy',
        // Банк вопросов: чтение уносит в браузер правильные ответы.
        'App\Models\Question' => 'App\Policies\QuestionBankPolicy',
        'App\Models\Category' => 'App\Policies\QuestionBankPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        // Супер-администратор проходит любую проверку Gate (Moodle site admin /
        // Canvas root admin). ВАЖНО: возвращать true только для admin, иначе — null,
        // чтобы не переопределять политики других пользователей.
        Gate::before(function ($user, $ability) {
            return method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()
                ? true
                : null;
        });

        // Каждое право из каталога (config/permissions.php) становится
        // ability'ю Gate: $user->can('users.view'), @can('courses.manage') в Blade.
        foreach (array_keys(config('permissions.permissions', [])) as $slug) {
            Gate::define($slug, fn ($user) => $user->hasPermission($slug));
        }

        Gate::define('view-group', [GroupPolicy::class, 'view']);
        Gate::define('delete-group', [GroupPolicy::class, 'delete']);

        // Пользователи. Способы с аргументом записи ($user) Laravel
        // разрешает через UserPolicy автоматически, а вот одноимённые
        // «своя запись / любая запись» удобно различать в контроллере
        // и в тестах по имени, а не по вендорённому каталогу.
        Gate::define('change-password', [UserPolicy::class, 'changePassword']);
        Gate::define('manage-permissions', [UserPolicy::class, 'managePermissions']);
        Gate::define('assign-role', [UserPolicy::class, 'assignRole']);

        // Курсы: видеть назначенное, управлять всем, публиковать — отдельно.
        Gate::define('view-course', [CoursePolicy::class, 'view']);
        Gate::define('publish-course', [CoursePolicy::class, 'publish']);

        // Экзамены: manage без аргумента записи (создание) и take с записью.
        Gate::define('manage-exams', [ExamPolicy::class, 'manage']);
        Gate::define('take-exam', [ExamPolicy::class, 'take']);

        // Банк вопросов: сводка открыта тому же, кому и сам банк.
        Gate::define('question-bank-statistics', [QuestionBankPolicy::class, 'statistics']);
        
        //Gate::define('view-user', [PermissionPolicy::class, 'viewAny']);
        //Gate::define('delete-user', [PermissionPolicy::class, 'update']);
        //Gate::define('update-user', [PermissionPolicy::class, 'delete']);
        Gate::resource('permissions', PermissionPolicy::class);
        //Gate::define('manage-users', [PermissionPolicy::class, 'delete']);
        //Gate::define('manage-users', [PermissionPolicy::class, 'update']);

        
   
    }
}
