<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Config;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CoursesController extends Controller
{
    public function index()
    {
        $course_path = public_path() . Config::get('app.courses_path');
        $courses = array();
        if (file_exists($course_path)) {
            $courses = array_diff(scandir(public_path() . Config::get('app.courses_path')), array('..', '.'));
        }
        //$array = array_chunk($courses, 1,true);       
        return $courses;

        // $files = Storage::files($course_path);        
        // return $files;

    }

    // public function show(string $course)
    // //public function show()
    // {
    //   //  $course = '01_mi-38.lyx';
    //     $ext = substr($course, -4);
    //     if ($ext !== '.lyx') {
    //         $course_titles = array_diff(scandir(public_path() . Config::get('app.courses_path') . '/' . $course), array('..', '.'));
    //         //return view('courses.show',['course' => $course,'course_titles' => $course_titles]);
    //         return $course_titles;
           
    //      } else {
    //          $lyxParser = new LyxParser;  
    //          //$title ="Конструкция вертолета и его системы. Техническое описание";
    //          $course_data = $lyxParser->get_course_data($course, $title);
    //          return $course_data;
            
    //      }

        
    // }


    public function show(string $courses)
    {
        $course_titles = array_diff(scandir(public_path() . Config::get('app.courses_path').'/'.$courses), array('..', '.'));
        return $course_titles;
    }




    //public function lern()
    public function lern(string $course,string $title)
    {
        $lyxParser = new LyxParser;
        $course_data = $lyxParser->get_course_data($course,$title);        
        
        //return view('courses.lern',['course' => $course,'title' => $title,'data' => $course_data]);
        
        return $course_data;
        //return json_encode($course_data);
       
        
    }

    public function lern_chapter(string $course, string $title, string $chapter)
    {
        $lyxParser = new LyxParser;
        $course_data = $lyxParser->get_course_data($course,$title);
        //return 'thats ok courses.lern.chapter';
        //return view('courses.lern',['course' => $course,'title' => $title,'data' => $course_data,'chapter' => $chapter]);
        return $course_data;
    }

    public function lern_section(string $course, string $title, string $chapter, string $section)
    {
        $lyxParser = new LyxParser;
        $course_data = $lyxParser->get_course_data($course,$title);
        //return 'thats ok courses.lern.chapter.section';
        return view('courses.lern',['course' => $course,'title' => $title,'data' => $course_data,'chapter' => $chapter,'section' => $section]);

    }
    public function lern_subsection(string $course, string $title, string $chapter, string $section, string $subsection)
    {
        $lyxParser = new LyxParser;
        $course_data = $lyxParser->get_course_data($course,$title);
        //return 'thats ok courses.lern.chapter.section.subsection';
        return view('courses.lern',['course' => $course,'title' => $title,'data' => $course_data,'chapter' => $chapter,'section' => $section,'subsection' => $subsection]);
    }
     public function lern_subsubsection(string $course,string $title,string $chapter,string $section,string $subsection,string $subsubsection)
     {
         $lyxParser = new LyxParser;
         $course_data = $lyxParser->get_course_data($course,$title);

         return view('courses.lern',['course' => $course,'title' => $title,'data' => $course_data,'chapter' => $chapter,'section' => $section,'subsection' => $subsection,'subsubsection' => $subsubsection]);
     }



}
