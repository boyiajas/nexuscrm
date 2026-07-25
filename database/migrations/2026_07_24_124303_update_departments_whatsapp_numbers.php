<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->string('primary_whatsapp_number')->nullable()->after('description');
            $table->json('secondary_whatsapp_numbers')->nullable()->after('primary_whatsapp_number');
        });

        // Migrate existing data
        $departments = DB::table('departments')->get();
        foreach ($departments as $department) {
            $numbers = json_decode($department->whatsapp_numbers, true);
            if (is_array($numbers) && count($numbers) > 0) {
                $primary = $numbers[0];
                $secondary = array_slice($numbers, 1);
                
                DB::table('departments')
                    ->where('id', $department->id)
                    ->update([
                        'primary_whatsapp_number' => $primary,
                        'secondary_whatsapp_numbers' => json_encode($secondary)
                    ]);
            } else {
                DB::table('departments')
                    ->where('id', $department->id)
                    ->update([
                        'secondary_whatsapp_numbers' => json_encode([])
                    ]);
            }
        }

        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn('whatsapp_numbers');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->json('whatsapp_numbers')->nullable()->after('description');
        });

        $departments = DB::table('departments')->get();
        foreach ($departments as $department) {
            $primary = $department->primary_whatsapp_number;
            $secondary = json_decode($department->secondary_whatsapp_numbers, true) ?: [];
            
            $numbers = [];
            if ($primary) {
                $numbers[] = $primary;
            }
            $numbers = array_merge($numbers, $secondary);
            
            DB::table('departments')
                ->where('id', $department->id)
                ->update([
                    'whatsapp_numbers' => json_encode($numbers)
                ]);
        }

        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn('primary_whatsapp_number');
            $table->dropColumn('secondary_whatsapp_numbers');
        });
    }
};
