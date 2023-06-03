<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('vectorization_metadata', function (Blueprint $table) {
            // The model that's being vectorized
            $table->morphs('vectorizable');

            // This is used to track the vectorizer that created the vector
            $table->string('vectorizer');
            $table->text('vectorizer_options');

            // The field that's being vectorized
            $table->string('field_name');
            $table->string('field_hash');
        });
    }

    public function down()
    {
        Schema::dropIfExists('vectorization_metadata');
    }
};
