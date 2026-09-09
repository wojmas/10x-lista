<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The list belongs to the family, not to whoever typed the entry, so there
     * is no owner column: the PRD guardrail requires every logged-in member to
     * see every product, and it rules out multi-tenancy. Deleting a category is
     * restricted rather than cascading — no screen deletes categories in the
     * MVP, and silently taking products down with one would break the
     * "no data may be lost" guardrail.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
