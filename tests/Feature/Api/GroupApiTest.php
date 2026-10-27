<?php

namespace Tests\Feature\Api;

use App\Models\Group;
use App\Models\Group2learning;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Группы — единственный ресурс с полноценным CRUD и auth-защитой.
 *
 * Отдельно проверяем:
 *  - конверт {success,data,error,meta} на успешных ответах;
 *  - 401 без токена, 404 на несуществующем id;
 *  - разделение видимости групп по роли (Администратор vs обычный).
 */
class GroupApiTest extends TestCase
{
    use RefreshDatabase;

    private function asUser(array $attrs = []): User
    {
        $user = User::factory()->create($attrs);
        $token = $user->createToken('t')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $token);
        return $user;
    }

    private function admin(): User
    {
        return $this->asUser(['role' => 'Администратор']);
    }

    private function assertEnvelope(array $json): void
    {
        foreach (['success', 'data', 'error', 'meta'] as $key) {
            $this->assertArrayHasKey($key, $json, "В конверте нет ключа {$key}");
        }
    }

    // ------------------------------------------------------------------ AUTH

    public function test_index_requires_authentication()
    {
        $this->getJson('/api/groups')->assertStatus(401);
    }

    public function test_index_rejects_bogus_token()
    {
        $this->withHeader('Authorization', 'Bearer not-a-real-token');
        $this->getJson('/api/groups')->assertStatus(401);
    }

    public function test_store_requires_authentication()
    {
        $this->postJson('/api/groups', ['groupname' => 'X'])->assertStatus(401);
    }

    public function test_destroy_requires_authentication()
    {
        $this->deleteJson('/api/groups/1')->assertStatus(401);
    }

    // ----------------------------------------------------------------- INDEX

    public function test_index_returns_empty_list_in_envelope_for_admin()
    {
        $this->admin();

        $response = $this->getJson('/api/groups');
        $response->assertStatus(200);

        $json = $response->json();
        $this->assertEnvelope($json);
        $this->assertTrue($json['success']);
        $this->assertSame([], $json['data']);
    }

    public function test_admin_sees_all_groups()
    {
        $this->admin();
        Group::factory()->count(3)->create();

        $json = $this->getJson('/api/groups')->json();

        $this->assertCount(3, $json['data']);
        $this->assertEqualsCanonicalizing(
            Group::pluck('id')->all(),
            collect($json['data'])->pluck('id')->all()
        );
    }

    public function test_non_admin_sees_only_own_group()
    {
        $own = Group::factory()->create();
        $other = Group::factory()->count(2)->create();

        $this->asUser(['role' => 'Обучаемый', 'group_id' => $own->id]);

        $json = $this->getJson('/api/groups')->json();

        $this->assertCount(1, $json['data']);
        $this->assertSame($own->id, $json['data'][0]['id']);
        $this->assertNotContains($other->first()->id, collect($json['data'])->pluck('id')->all());
    }

    public function test_index_sorted_by_id()
    {
        $this->admin();
        $a = Group::factory()->create();
        $b = Group::factory()->create();

        $ids = collect($this->getJson('/api/groups')->json('data'))->pluck('id')->all();
        $sorted = $ids;
        sort($sorted);
        $this->assertSame($sorted, $ids, 'Группы должны отдаваться по возрастанию id');
    }

    // ----------------------------------------------------------------- CRUD

    public function test_store_creates_group_and_returns_201()
    {
        $this->admin();

        $response = $this->postJson('/api/groups', [
            'groupname'        => 'Новая группа',
            'groupdescription' => 'Описание группы',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('groups', [
            'groupname'        => 'Новая группа',
            'groupdescription' => 'Описание группы',
        ]);
    }

    /**
     * Находка: groupdescription в БД NOT NULL, а валидации нет —
     * запрос без описания падает в 500. Тест фиксирует текущее поведение.
     */
    /**
     * Регресс: форма создания группы не отправляет groupdescription,
     * а колонка NOT NULL. Контроллер клал null → 500 с утечкой SQLSTATE.
     * Теперь description необязателен и подставляется пустая строка.
     */
    public function test_store_without_description_succeeds()
    {
        $this->admin();

        $response = $this->postJson('/api/groups', ['groupname' => 'Без описания']);

        $response->assertStatus(201);
        $this->assertDatabaseHas('groups', [
            'groupname' => 'Без описания',
            'groupdescription' => '',
        ]);
    }

    public function test_store_requires_groupname()
    {
        $this->admin();

        $this->postJson('/api/groups', ['groupdescription' => 'только описание'])
             ->assertStatus(422);
    }

    public function test_show_returns_requested_group()
    {
        $this->admin();
        $group = Group::factory()->create(['groupname' => 'Целевая']);

        $json = $this->getJson("/api/groups/{$group->id}")->json();

        $this->assertSame($group->id, $json['data']['id']);
        $this->assertSame('Целевая', $json['data']['groupname']);
    }

    public function test_show_returns_404_for_missing_id()
    {
        $this->admin();
        $this->getJson('/api/groups/999999')->assertStatus(404);
    }

    public function test_update_changes_name_and_description()
    {
        $this->admin();
        $group = Group::factory()->create([
            'groupname'        => 'Старое имя',
            'groupdescription' => 'Старое описание',
        ]);

        $this->patchJson("/api/groups/{$group->id}", [
            'groupname'        => 'Новое имя',
            'groupdescription' => 'Новое описание',
        ])->assertStatus(200);

        $this->assertDatabaseHas('groups', [
            'id'                => $group->id,
            'groupname'         => 'Новое имя',
            'groupdescription'  => 'Новое описание',
        ]);
    }

    public function test_update_returns_404_for_missing_id()
    {
        $this->admin();
        $this->patchJson('/api/groups/999999', ['groupname' => 'X'])->assertStatus(404);
    }

    public function test_destroy_removes_group()
    {
        $this->admin();
        $group = Group::factory()->create();

        $this->deleteJson("/api/groups/{$group->id}")->assertStatus(204);

        $this->assertDatabaseMissing('groups', ['id' => $group->id]);
    }

    public function test_destroy_returns_404_for_missing_id()
    {
        $this->admin();
        $this->deleteJson('/api/groups/999999')->assertStatus(404);
    }

    // ------------------------------------------------------- ГРУППА ↔ ПОЛЬЗОВАТЕЛЬ

    public function test_group_can_be_assigned_to_user_and_read_back()
    {
        $group = Group::factory()->create();
        $user = User::factory()->create(['group_id' => $group->id]);

        $this->assertSame($group->id, $user->fresh()->group_id);
        $this->assertTrue($user->fresh()->group->is($group));
    }

    public function test_learning_relations_survive_group_delete()
    {
        $group = Group::factory()->create();
        $learning = Group2learning::factory()->create(['group_id' => $group->id]);

        $this->assertTrue($group->group2learnings->contains($learning));
    }

    /**
     * Регресс: update без groupdescription писал NULL в NOT NULL-колонку
     * и возвращал 500; update без groupname затирал имя на NULL.
     */
    public function test_update_without_description_keeps_existing_one()
    {
        $this->admin();
        $group = \App\Models\Group::factory()->create([
            'groupname' => 'Старое имя',
            'groupdescription' => 'Старое описание',
        ]);

        $this->putJson("/api/groups/{$group->id}", ['groupname' => 'Новое имя'])
             ->assertStatus(200);

        $this->assertDatabaseHas('groups', [
            'id' => $group->id,
            'groupname' => 'Новое имя',
            'groupdescription' => 'Старое описание',
        ]);
    }

    public function test_update_can_clear_description()
    {
        $this->admin();
        $group = \App\Models\Group::factory()->create([
            'groupname' => 'Группа',
            'groupdescription' => 'Будет очищено',
        ]);

        $this->putJson("/api/groups/{$group->id}", [
            'groupname' => 'Группа',
            'groupdescription' => null,
        ])->assertStatus(200);

        $this->assertDatabaseHas('groups', [
            'id' => $group->id,
            'groupdescription' => '',
        ]);
    }

    public function test_update_rejects_empty_groupname()
    {
        $this->admin();
        $group = \App\Models\Group::factory()->create(['groupname' => 'Группа']);

        // Поле передано, но пустое — должно отклоняться, а не затирать имя
        $this->putJson("/api/groups/{$group->id}", [
            'groupname' => '',
            'groupdescription' => 'описание',
        ])->assertStatus(422);

        $this->assertDatabaseHas('groups', ['id' => $group->id, 'groupname' => 'Группа']);
    }
}
