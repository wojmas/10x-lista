<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Categories are the vocabulary shared by products and — from S-03 onwards —
     * by shops, which is what makes the S-04 recommendation rule comparable at
     * all. The unique index catches exact duplicates; case-insensitive matching
     * lives in validation, because a functional index on LOWER(name) would
     * differ between production Postgres and the SQLite used in tests.
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
