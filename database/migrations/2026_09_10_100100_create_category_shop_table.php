<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The unique constraint on the (shop_id, category_id) pair is not a form
     * rule but a data invariant: without it S-04 would count the same category
     * twice and recommend the wrong shop, silently. Deleting a shop drops its
     * assignments; deleting a category is restricted, as with products.
     */
    public function up(): void
    {
        Schema::create('category_shop', function (Blueprint $table) {
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();

            $table->unique(['shop_id', 'category_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_shop');
    }
};
