<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAukstructuresTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('aukstructures', function (Blueprint $table) {
            $table->id();
            //$table->foreignId('course_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('course_id')->onDelete('cascade');
            $table->integer('parent_id')->nullable();
            $table->text('title')->nullable();
            $table->integer('type')->nullable();
            $table->string('description')->nullable();
            $table->string('categories')->nullable();
            $table->string('identifier')->nullable();
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
        Schema::dropIfExists('aukstructures');
        //Schema::dropIfExists('aukstructure_category');
    }
}
