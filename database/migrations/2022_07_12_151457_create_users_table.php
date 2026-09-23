<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            //$table->string('name');
            //$table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->string('fio',150)->unique();
            $table->string('role',15)->nullable();
            //$table->string('lastname',50)->nullable();
            //$table->string('firstname',50)->nullable();
            //$table->string('patronymic',50)->nullable();
            $table->string('phonenumber',16)->nullable();
            $table->string('city',25)->nullable();
            $table->string('country',30)->nullable();
            $table->json('avatar')->nullable(); // filename path
            $table->string('organization',100)->nullable();
            $table->string('position',100)->nullable();
            $table->string('rank',30)->nullable();
            $table->string('spfere',100)->nullable();
            $table->string('specialization',100)->nullable();
            $table->foreignId('group_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('slug')->nullable();
            $table->string('api_token')->nullable();
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
        Schema::dropIfExists('users');
    }
}
