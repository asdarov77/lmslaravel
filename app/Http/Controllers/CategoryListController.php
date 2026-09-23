<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Course;
use Illuminate\Http\Request;

class CategoryListController extends Controller
{
    public function getCatCourses(Request $request)
    {
        $category = Category::all(); // вывод всех категорий

        return $category;
    }

    public function getCatCoursesID($id)
    {

        $categories = Category::find($id); // вывод всех категорий
        //$category = Category::all();// вывод всех категорий       
        //$courses = Course::where('visible',$show)->get();// вывод всех категорий
        $courses=$categories->courses;
       
       
        //$courses = $categories->courses;
        return $courses;
    }
}
