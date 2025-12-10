<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            $table->string('model')->nullable()->after('provider');
        });

        // Backfill existing chats with default models based on their provider
        $defaultModels = config('llm.default_models', []);

        foreach ($defaultModels as $provider => $model) {
            DB::table('chats')
                ->where('provider', $provider)
                ->whereNull('model')
                ->update(['model' => $model]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            $table->dropColumn('model');
        });
    }
};
