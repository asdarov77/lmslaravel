<?php

namespace App\Http\Controllers;

use App\Models\Course;

use Illuminate\Http\Request;

class CoursesListController extends Controller
{
//   public function getCourses(Request $request)
public function getCourses(Request $request)
    {
        $courses = Course::all();                       
        //$courses = Course::where('visible', true)->get(); // вывод всех курсов                         
        //$courses->visible = request('visible');
        //$courses = Course::all();                         
        return $courses;        

        //return 'thats ok';
        
    }
}
