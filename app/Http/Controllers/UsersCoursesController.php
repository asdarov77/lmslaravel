<?php

namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Course;
use App\Models\Group;
use App\Models\Groups;

class UsersCoursesController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    // public function __invoke(Request $request)
    // {
    //     //
    // }

    public function index()
    {
        // $users = User::all();
        // foreach($users as $_user) 
        //     $_user->group;
        // return $users;
        // $courses = Course::all();
        // foreach($courses as $_courses) 
        //     $_courses->groups;
        //     return $courses;
       
       
        // $users = User::select (['fio'])
        // //->withCount('group')
        // ->whereHas('courses.groups', function($query){
        //     $query -> where ('courses.id',1);
        // })
        // ->get();
        // foreach($users as $user) {
        //     echo $user->fio ;
        // }



        // $courses = Course::all();
        // foreach($courses as $_courses) 
        //        $_courses->groups;
        // return $courses;
        // $groups = Group::all();
        // foreach($groups as $_groups) 
        // $_groups->courses;
        // return $groups;
        //$users = User::where('group_id',)->get();
        // $users = User::select('id','fio', 'group_id')
        // ->where('group_id',)
        // ->get();
        // //->get();
        // //  foreach($users as $_users) {
        // //           echo $_users->group_id ;
        // //       }
        // return $users;
        $groups = Group::all();
        $users = User::all();
        $courses = Course::all();
        $users = User::all();
        //foreach($groups as $_groups) 
        //$_groups->courses;
        foreach($users as $_users) 
        echo $_users->fio."\r\n";
        // /return $users;


    }
}
