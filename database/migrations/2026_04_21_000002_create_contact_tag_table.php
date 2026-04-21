<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_tag', function (Blueprint $table) {
            $table->uuid('contact_id');
            $table->uuid('tag_id');

            $table->primary(['contact_id', 'tag_id']);
            $table->foreign('contact_id')->references('id')->on('contacts')->cascadeOnDelete();
            $table->foreign('tag_id')->references('id')->on('tags')->cascadeOnDelete();

            // Lookup from tag side (e.g. "how many contacts have this tag")
            $table->index('tag_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_tag');
    }
};
