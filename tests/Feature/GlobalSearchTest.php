<?php

namespace Tests\Feature;

use App\Models\Aukstructure;
use App\Models\Category;
use App\Models\Course;
use App\Models\Group;
use App\Models\Group2learning;
use App\Models\Permission;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Глобальный поиск: область видимости и права.
 *
 * Поиск — это не «найти текст», а выдать то, что пользователю видно.
 * Поэтому проверяется ровно то, что закрывает контроллер:
 *
 *  - обучаемый не находит курсы и темы, на которые его группу не
 *    записали (иначе поиск становится обходом скоупа каталога);
 *  - группы, люди и вопросы показываются только по правам: поиск не
 *    должен превращаться в способ узнать, что группа существует;
 *  - пустой результат отдаётся объектом, а не списком: иначе на
 *    фронте тип ответа плавает (была ошибка array_filter() на stdClass);
 *  - слишком короткий запрос отклоняется, иначе «а» выгружал бы
 *    всю таблицу.
 *
 * Отдельно проверяется поиск по содержимому приватных файлов курса:
 * он был доступен ЛЮБОМУ вошедшему — aircraft и path приходили из тела
 * запроса, то есть материал чужого курса читался перебором.
 */
class GlobalSearchTest extends TestCase
{
    use AuthenticatesApi;
    use RefreshDatabase;

    private Course $course;

    private Course $foreignCourse;

    private Group $ownGroup;

    private Group $otherGroup;

    protected function setUp(): void
    {
        parent::setUp();

        $category = Category::factory()->create(['title' => 'Специальность поиска']);

        $this->course = Course::factory()->create(['title' => 'Конструкция самолёта']);
        $this->foreignCourse = Course::factory()->create(['title' => 'Конструкция двигателя']);

        foreach ([$this->course, $this->foreignCourse] as $course) {
            $course->categories()->attach($category->id);
        }

        $this->ownGroup = Group::factory()->create(['groupname' => 'Своя группа']);
        $this->otherGroup = Group::factory()->create(['groupname' => 'Чужая группа']);

        // Запись только на свой курс: второй остаётся за скобами.
        Group2learning::factory()->create([
            'group_id' => $this->ownGroup->id,
            'course_id' => $this->course->id,
        ]);

        Aukstructure::factory()->create([
            'title' => 'Тема конструкции',
            'course_id' => $this->course->id,
        ]);
        Aukstructure::factory()->create([
            'title' => 'Тема двигателя',
            'course_id' => $this->foreignCourse->id,
        ]);

        // Диагностика: что реально лежит в таблице после setUp.
        fwrite(STDERR, "\nAUK: ".json_encode(
            \App\Models\Aukstructure::all(['id', 'title', 'course_id'])->toArray(),
            JSON_UNESCAPED_UNICODE
        )."\n");
    }

    private function grant(User $user, string ...$slugs): User
    {
        foreach ($slugs as $slug) {
            Permission::firstOrCreate(['slug' => $slug], ['name' => $slug]);
        }

        $user->givePermissionsTo(...$slugs);
        $user->forgetPermissionCache();

        return $user->fresh();
    }

    private function trainee(): User
    {
        return User::factory()->create([
            'role' => 'Обучаемый',
            'group_id' => $this->ownGroup->id,
        ]);
    }

    private function titles(array $groups, string $key): array
    {
        return array_column($groups[$key] ?? [], 'title');
    }

    // ------------------------------------------------------------- скоуп

    public function test_trainee_finds_only_enrolled_course(): void
    {
        $this->asExistingUser($this->trainee());

        $groups = $this->getJson('/api/search?q=Конструкция')->assertOk()->json('data.groups');

        $this->assertContains('Конструкция самолёта', $this->titles($groups, 'courses'));
        $this->assertNotContains(
            'Конструкция двигателя',
            $this->titles($groups, 'courses'),
            'поиск не должен выдавать неназначенный курс'
        );
    }

    public function test_trainee_finds_only_topics_of_enrolled_course(): void
    {
        // Темы ищутся внутри видимых курсов. Без этого поиск по темам
        // обходил бы скоуп каталога.
        $this->asExistingUser($this->trainee());

        $groups = $this->getJson('/api/search?q=Тема')->assertOk()->json('data.groups');

        $this->assertContains('Тема конструкции', $this->titles($groups, 'modules'));
        $this->assertNotContains('Тема двигателя', $this->titles($groups, 'modules'));
    }

    public function test_trainee_without_group_finds_nothing(): void
    {
        // Пустая группа — «нет своей части», а не «весь каталог».
        $user = User::factory()->create(['role' => 'Обучаемый', 'group_id' => null]);
        $this->asExistingUser($user);

        $data = $this->getJson('/api/search?q=Конструкция')->assertOk()->json('data');

        $this->assertSame(0, $data['total']);
    }

    public function test_empty_result_is_object_not_list(): void
    {
        // Пустой PHP-массив сериализуется как [], и на фронте groups
        // становился списком вместо словаря.
        $user = User::factory()->create(['role' => 'Обучаемый', 'group_id' => null]);
        $this->asExistingUser($user);

        $data = $this->getJson('/api/search?q=Конструкция')->assertOk()->json('data');

        $this->assertIsArray($data['groups'], 'пустой groups должен быть объектом {}');
        $this->assertSame([], $data['groups']);
    }

    // ------------------------------------------------------------- права

    public function test_trainee_gets_no_groups_users_or_questions(): void
    {
        Group::factory()->create(['groupname' => 'Группа для поиска']);
        User::factory()->create(['fio' => 'Иванов Для Поиска', 'group_id' => $this->ownGroup->id]);
        Question::factory()->create(['question_text' => 'Вопрос для поиска']);

        $this->asExistingUser($this->trainee());

        $groups = $this->getJson('/api/search?q=Группа')->assertOk()->json('data.groups');

        $this->assertArrayNotHasKey('groups', $groups);
        $this->assertArrayNotHasKey('users', $groups);
        $this->assertArrayNotHasKey('questions', $groups);
    }

    public function test_manager_sees_groups(): void
    {
        $manager = User::factory()->create([
            'role' => 'Инструктор',
            'group_id' => $this->ownGroup->id,
        ]);
        $this->grant($manager, 'groups.view');
        $this->asExistingUser($manager);

        $groups = $this->getJson('/api/search?q=Группа')->assertOk()->json('data.groups');

        $this->assertContains('Своя группа', $this->titles($groups, 'groups'));
    }

    public function test_instructor_with_groups_view_sees_only_own_group(): void
    {
        $manager = User::factory()->create([
            'role' => 'Инструктор',
            'group_id' => $this->ownGroup->id,
        ]);
        $this->grant($manager, 'groups.view');
        $this->asExistingUser($manager);

        $groups = $this->getJson('/api/search?q=Группа')->assertOk()->json('data.groups');

        $this->assertContains('Своя группа', $this->titles($groups, 'groups'));
        $this->assertNotContains('Чужая группа', $this->titles($groups, 'groups'));
    }

    public function test_admin_sees_all_groups_users_and_questions(): void
    {
        Question::factory()->create(['question_text' => 'Вопрос для поиска']);
        User::factory()->create(['fio' => 'Иванов Для Поиска', 'group_id' => $this->otherGroup->id]);

        $this->admin();

        // Отдельный запрос на каждую сущность: общий «Поиска» не
        // совпадает с названием группы, и проверка искала несуществующее
        // совпадение вместо проверки прав.
        $this->assertContains(
            'Чужая группа',
            $this->titles($this->getJson('/api/search?q=Группа')->json('data.groups'), 'groups')
        );
        $this->assertContains(
            'Иванов Для Поиска',
            $this->titles($this->getJson('/api/search?q=Иванов')->json('data.groups'), 'users')
        );
        $this->assertContains(
            'Вопрос для поиска',
            $this->titles($this->getJson('/api/search?q=Вопрос')->json('data.groups'), 'questions')
        );
    }

    public function test_search_results_never_include_answers(): void
    {
        // Вопрос попадает в поиск только текстом: варианты ответа в
        // выдаче означали бы утечку правильных ответов.
        Question::factory()->create(['question_text' => 'Вопрос с ответами для поиска']);

        $this->admin();
        $item = $this->getJson('/api/search?q=ответами')->assertOk()->json('data.groups.questions.0');

        $this->assertIsArray($item);
        $this->assertArrayNotHasKey('answers', $item);
        // Строкой, а не массивом: assertArrayNotHasKey принимает массив,
        // а json_encode возвращает строку — проверка проходила бы всегда.
        $encoded = json_encode($item, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('is_correct', $encoded);
        $this->assertStringNotContainsString('"answers"', $encoded);
    }

    // ------------------------------------------------------------- контракт

    public function test_search_requires_authentication(): void
    {
        $this->getJson('/api/search?q=Конструкция')->assertUnauthorized();
    }

    public function test_short_query_is_rejected(): void
    {
        $this->asExistingUser($this->trainee());

        $this->getJson('/api/search?q=К')->assertStatus(422);
        $this->getJson('/api/search')->assertStatus(422);
        $this->getJson('/api/search?q='.str_repeat('я', 200))->assertStatus(422);
    }

    public function test_limit_is_respected(): void
    {
        Course::factory()->count(7)->create(['title' => 'Курс для поиска']);

        $this->admin();

        $data = $this->getJson('/api/search?q=Курс%20для%20поиска&limit=3')->assertOk()->json('data');

        $this->assertCount(3, $data['groups']['courses']);
        $this->getJson('/api/search?q=Курс&limit=99')->assertStatus(422);
    }

    // ------------------------------------- поиск по содержимому курса

    public function test_content_search_is_closed_to_trainee(): void
    {
        // Регресс: маршрут висел только на auth:sanctum, а aircraft и path
        // приходили из тела запроса — материал чужого курса читался
        // перебором.
        $this->asExistingUser($this->trainee());

        $this->postJson('/api/search-files/', [
            'query' => 'компрессор',
            'aircraft' => 1,
            'path' => 'theme',
        ])->assertStatus(403);
    }

    public function test_content_search_requires_manager_permission(): void
    {
        $manager = User::factory()->create([
            'role' => 'Инструктор',
            'group_id' => $this->ownGroup->id,
        ]);
        $this->grant($manager, 'content.manage');
        $this->asExistingUser($manager);

        // Достигает контроллера и отвечает внятно, а не 500 из-за
        // обращения к свойству у несуществующего самолёта.
        $this->postJson('/api/search-files/', [
            'query' => 'компрессор',
            'aircraft' => 999999,
            'path' => 'theme',
        ])->assertStatus(422);
    }

    public function test_content_search_requires_authentication(): void
    {
        $this->postJson('/api/search-files/', [
            'query' => 'компрессор',
            'aircraft' => 1,
            'path' => 'theme',
        ])->assertUnauthorized();
    }
}
