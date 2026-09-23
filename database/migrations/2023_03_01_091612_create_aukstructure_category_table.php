<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAukstructureCategoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('aukstructure_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aukstructure_id')->constrained('aukstructures')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();


//            $table->foreignId('aukstructure_id')->nullable()->cascadeOnDelete();
//            $table->foreignId('category_id')->nullable()->cascadeOnDelete();
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
        Schema::dropIfExists('aukstructure_category');
    }
}
