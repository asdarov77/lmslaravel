<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClearDBController extends Controller
{
    public function clear()
    {
      //DB::statement('ALTER TABLE table_name DISABLE TRIGGER ALL;');
      DB::table('aircrafts')->truncate();
      DB::table('aukstructure_category')->truncate();
      DB::table('aukstructures')->truncate();
      DB::table('categories')->truncate();
      DB::table('category_course')->truncate();
      DB::table('courses')->truncate();
      DB::table('group2learnings')->truncate();
      DB::table('links')->truncate();    
      DB::table('answers')->truncate();    
      DB::table('questions')->truncate();    
      //DB::statement('ALTER TABLE table_name ENABLE TRIGGER ALL;');

        return response()->json(['message' => 'Database cleared successfully']);
    }
}
