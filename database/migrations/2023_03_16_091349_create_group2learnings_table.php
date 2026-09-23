<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGroup2learningsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('group2learnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->cascadeOnDelete();
            $table->foreignId('group_id')->cascadeOnDelete();
            $table->foreignId('category_id')->cascadeOnDelete();
            $table->integer('parent_id')->nullable();
            $table->char('teacher');
            $table->char('typeOfLesson');
            $table->date('study_from');
            $table->date('study_to');
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
        Schema::dropIfExists('group2learnings');
    }
}
