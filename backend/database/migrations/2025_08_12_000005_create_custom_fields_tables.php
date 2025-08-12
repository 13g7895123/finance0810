<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_fields', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 50); // customer | lead | case
            $table->string('key', 100); // unique per entity_type
            $table->string('label', 150);
            $table->string('type', 50)->default('text'); // text, textarea, number, date, datetime, select, multiselect, boolean
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_filterable')->default(false);
            $table->boolean('is_visible')->default(true);
            $table->string('group')->nullable();
            $table->integer('sort_order')->default(0);
            $table->json('validation_rules')->nullable();
            $table->string('default_value')->nullable();
            $table->timestamps();

            $table->unique(['entity_type', 'key']);
            $table->index(['entity_type', 'is_filterable']);
        });

        Schema::create('custom_field_values', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 50);
            $table->unsignedBigInteger('entity_id');
            $table->foreignId('field_id')->constrained('custom_fields')->cascadeOnDelete();
            $table->text('value')->nullable(); // store as text; app casts JSON for types
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
            $table->index(['field_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_field_values');
        Schema::dropIfExists('custom_fields');
    }
};
