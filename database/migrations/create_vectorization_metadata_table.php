<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('vectorization_metadata', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vectorizable_id');
            $table->string('vectorizable_type');

            $table->string('vectorizer');
            $table->string('vectorizer_version');
            $table->string('field_name');
            $table->string('field_hash');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('vectorization_metadata');
    }
};
