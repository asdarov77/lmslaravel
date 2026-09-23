<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCoursesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->increments('id');
            //            $table->string(column: 'title');   связь многие ко многим   categories = models.ManyToManyField(Category)
            //$table->string('slug'); // Field name same as your 'saveSlugsTo'
            $table->text('title');
            $table->text('short_description')->nullable();
            $table->text('long_description')->nullable();
            $table->text('path')->nullable();
//            $table->char('path_hash')->nullable();
            $table->boolean('visible')->default(true);            
            $table->foreignId('aircraft_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('courses');
    }
}
