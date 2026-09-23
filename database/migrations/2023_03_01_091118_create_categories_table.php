<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCategoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->increments('id');
//            $table->string(column: 'title');   связь многие ко многим   categories = models.ManyToManyField(Category)
            $table->char('title');
//            $table->string('slug'); // Field name same as your 'saveSlugsTo'
            $table->text('description');
            $table->char('code');
//            $table->integer('parent_id')->unsigned()->nullable();
            $table->foreignId('aircraft_id')->nullable()->cascadeOnDelete();//->onDelete('cascade'); //constrained() ограничения добавить потом
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
        Schema::dropIfExists('categories');        
    }
}
