<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('option_values', function (Blueprint $table) {
            // Opsional: nilai opsi yang tidak butuh penjelasan (mis. "Pendek"
            // vs "Panjang") boleh dibiarkan tanpa bahan terkait.
            $table->foreignId('material_id')->nullable()->after('label')
                ->constrained('materials')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('option_values', function (Blueprint $table) {
            $table->dropConstrainedForeignId('material_id');
        });
    }
};
