<?php

namespace Tests\Unit\Models;

use App\Models\Course;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_course_belongs_to_category(): void
    {
        $category = Category::factory()->create();
        $course = Course::factory()->create(['category_id' => $category->id]);
        
        $this->assertEquals($category->id, $course->category->id);
    }

    public function test_course_has_instructors(): void
    {
        $course = Course::factory()->create();
        $group = \App\Models\Group::factory()->create();
        $instructor = User::factory()->create(['role' => 'Инструктор', 'group_id' => $group->id]);

        // назначаем группу на курс — связь course <-> users раскрывается через group2learnings
        \App\Models\Group2learning::create([
            'course_id' => $course->id,
            'group_id' => $group->id,
            'category_id' => 1,
            'teacher' => $instructor->fio,
            'typeOfLesson' => 'lecture',
            'study_from' => '2026-01-01',
            'study_to' => '2026-12-31',
        ]);

        $this->assertTrue($course->instructors->contains($instructor));
    }

    public function test_course_has_students(): void
    {
        $course = Course::factory()->create();
        $group = \App\Models\Group::factory()->create();
        $student = User::factory()->create(['role' => 'Обучаемый', 'group_id' => $group->id]);

        \App\Models\Group2learning::create([
            'course_id' => $course->id,
            'group_id' => $group->id,
            'category_id' => 1,
            'teacher' => 'Иванов И.И.',
            'typeOfLesson' => 'practice',
            'study_from' => '2026-01-01',
            'study_to' => '2026-12-31',
        ]);

        $this->assertTrue($course->students->contains($student));
    }

    public function test_course_status_enum(): void
    {
        $course = Course::factory()->create(['status' => 'active']);
        
        $this->assertEquals('active', $course->status);
    }
}
