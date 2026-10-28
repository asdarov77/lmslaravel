<?php
namespace Tests\Feature;

use App\Models\Aukstructure;
use App\Models\Course;
use App\Models\Link;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DbgTest extends TestCase
{
    use RefreshDatabase, AuthenticatesApi;

    public function test_dbg(): void
    {
        $this->asUser();
        $course = Course::create([
            'title' => 'К', 'short_description' => 'О', 'long_description' => 'О',
            'path' => 'dbg-course', 'visible' => true,
        ]);
        $root = Aukstructure::create(['course_id'=>$course->id,'parent_id'=>0,'title'=>'Р','type'=>0,'identifier'=>'R1']);
        $topic = Aukstructure::create(['course_id'=>$course->id,'parent_id'=>$root->id,'title'=>'Т','type'=>2,'identifier'=>'T1']);
        $mat = Aukstructure::create(['course_id'=>$course->id,'parent_id'=>$topic->id,'title'=>'Ф','type'=>3,'identifier'=>'M1']);
        Link::create(['aukstructure_id'=>$mat->id,'link'=>'Ф.html']);
        $n3 = Aukstructure::create(['course_id'=>$course->id,'parent_id'=>$topic->id,'title'=>'без файла','type'=>3,'identifier'=>'M2']);
        $n1 = Aukstructure::create(['course_id'=>$course->id,'parent_id'=>$topic->id,'title'=>'обычный','type'=>1,'identifier'=>'N1']);

        $resp = $this->getJson("/api/course/{$course->id}/player");
        fwrite(STDERR, "STATUS: ".$resp->getStatusCode()."\n");
        fwrite(STDERR, "BODY: ".substr($resp->getContent(), 0, 4000)."\n");
        $this->assertTrue(true);
    }
}
