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
        $instructor = User::factory()->create(['role' => 'instructor']);
        
        $course->instructors()->attach($instructor);
        
        $this->assertTrue($course->instructors->contains($instructor));
    }

    public function test_course_has_students(): void
    {
        $course = Course::factory()->create();
        $student = User::factory()->create(['role' => 'trainee']);
        
        $course->students()->attach($student);
        
        $this->assertTrue($course->students->contains($student));
    }

    public function test_course_status_enum(): void
    {
        $course = Course::factory()->create(['status' => 'active']);
        
        $this->assertEquals('active', $course->status);
    }
}
