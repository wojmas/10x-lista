<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The unique index on "name" is a safety net for exact duplicates only —
     * the case-insensitive comparison lives in validation through
     * NameComparison, for the same reason as with categories: LOWER() is
     * ASCII-only in the SQLite used by tests but locale-aware in production
     * Postgres.
     *
     * There is deliberately no position column. §Business Logic settles a tie in
     * the S-04 recommendation in favour of "the shop added first", and the
     * primary key is strictly increasing, so id order is that contract. Sorting
     * by created_at would tie again whenever two shops land in the same fraction
     * of a second (seeder, import, two parallel requests).
     */
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $table) {
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
        Schema::dropIfExists('shops');
    }
};
