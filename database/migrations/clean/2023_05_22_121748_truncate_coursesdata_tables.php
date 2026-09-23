<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
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
    //DB::statement('ALTER TABLE table_name ENABLE TRIGGER ALL;');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
