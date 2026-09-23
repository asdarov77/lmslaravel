<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
//class CreateGradeBoundariesTable extends Migration
{
    public function up()
    {
        Schema::create('grade_boundaries', function (Blueprint $table) {
            $table->id();
            $table->integer('boundary');
            $table->string('grade');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('grade_boundaries');
    }
};