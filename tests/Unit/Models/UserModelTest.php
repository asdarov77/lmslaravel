<?php

namespace Tests\Unit\Models;

use App\Models\User;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_have_role(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        $this->assertEquals('admin', $user->role);
        $this->assertTrue($user->isAdmin());
    }

    public function test_user_can_be_instructor(): void
    {
        $user = User::factory()->create(['role' => 'instructor']);
        
        $this->assertEquals('instructor', $user->role);
        $this->assertTrue($user->isInstructor());
    }

    public function test_user_can_be_trainee(): void
    {
        $user = User::factory()->create(['role' => 'trainee']);
        
        $this->assertEquals('trainee', $user->role);
        $this->assertTrue($user->isTrainee());
    }

    public function test_user_has_permissions(): void
    {
        $user = User::factory()->create();
        $permission = Permission::create(['name' => 'edit_courses']);
        
        $user->permissions()->attach($permission);
        
        $this->assertTrue($user->hasPermission('edit_courses'));
    }

    public function test_user_full_name_attribute(): void
    {
        $user = User::factory()->create([
            'surname' => 'Иванов',
            'name' => 'Иван',
            'patronymic' => 'Иванович'
        ]);
        
        $this->assertEquals('Иванов Иван Иванович', $user->fio);
    }
}
