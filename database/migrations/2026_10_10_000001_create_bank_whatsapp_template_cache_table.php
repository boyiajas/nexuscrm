<?php

use App\Models\Bank;
use App\Models\WhatsappTemplateCache;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('bank_whatsapp_template_cache')) {
            Schema::create('bank_whatsapp_template_cache', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('whatsapp_template_cache_id');
                $table->unsignedBigInteger('bank_id');
                $table->string('template_sid')->nullable()->index();
                $table->timestamps();

                $table->foreign('whatsapp_template_cache_id')
                    ->references('id')
                    ->on('whatsapp_templates_cache')
                    ->cascadeOnDelete();

                $table->foreign('bank_id')
                    ->references('id')
                    ->on('banks')
                    ->cascadeOnDelete();

                $table->unique(['whatsapp_template_cache_id', 'bank_id'], 'bank_template_cache_unique');
            });
        }

        $this->seedInitialTemplateBankAssignments();
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_whatsapp_template_cache');
    }

    private function seedInitialTemplateBankAssignments(): void
    {
        if (!Schema::hasTable('whatsapp_templates_cache') || !Schema::hasTable('banks')) {
            return;
        }

        $banks = Bank::all();
        if ($banks->isEmpty()) {
            return;
        }

        $templates = WhatsappTemplateCache::all();
        $now = now();

        foreach ($templates as $template) {
            $name = strtolower($template->sid . ' ' . ($template->friendly_name ?? ''));
            $matchedBankIds = [];

            foreach ($banks as $bank) {
                $bankCode = strtolower(str_replace(['-', '_'], '', $bank->code ?? ''));
                $bankName = strtolower(str_replace(['-', '_'], '', $bank->name ?? ''));
                $cleanName = strtolower(str_replace(['-', '_'], '', $name));

                if ($bankCode !== '' && str_contains($cleanName, $bankCode)) {
                    $matchedBankIds[] = $bank->id;
                } elseif ($bankName !== '' && (str_contains($cleanName, $bankName) || str_contains($name, strtolower($bank->name)))) {
                    $matchedBankIds[] = $bank->id;
                } elseif (str_contains($name, 'capfin') && (str_contains($bankName, 'capfin') || str_contains($bankCode, 'capfin'))) {
                    $matchedBankIds[] = $bank->id;
                } elseif (str_contains($name, 'fnb') && (str_contains($bankName, 'fnb') || str_contains($bankCode, 'fnb'))) {
                    $matchedBankIds[] = $bank->id;
                } elseif ((str_contains($name, 'finchoice') || str_contains($name, 'fin_choice')) && (str_contains($bankName, 'finchoice') || str_contains($bankCode, 'finchoice'))) {
                    $matchedBankIds[] = $bank->id;
                } elseif (str_contains($name, 'tenacity') && (str_contains($bankName, 'tenacity') || str_contains($bankCode, 'tenacity'))) {
                    $matchedBankIds[] = $bank->id;
                }
            }

            if (!empty($matchedBankIds)) {
                $matchedBankIds = array_unique($matchedBankIds);
                foreach ($matchedBankIds as $bId) {
                    DB::table('bank_whatsapp_template_cache')->insertOrIgnore([
                        'whatsapp_template_cache_id' => $template->id,
                        'bank_id' => $bId,
                        'template_sid' => $template->sid,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            } else {
                $defaultBank = $banks->firstWhere('code', 'default-bank') ?? $banks->first();
                if ($defaultBank) {
                    DB::table('bank_whatsapp_template_cache')->insertOrIgnore([
                        'whatsapp_template_cache_id' => $template->id,
                        'bank_id' => $defaultBank->id,
                        'template_sid' => $template->sid,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }
};
