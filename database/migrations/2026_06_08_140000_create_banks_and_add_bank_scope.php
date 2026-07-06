<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('banks', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code')->unique();
            $table->string('status')->default('Active');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('bank_id')->nullable()->after('department_id')->constrained('banks')->nullOnDelete();
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('bank_id')->nullable()->after('id')->constrained('banks')->nullOnDelete();
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->foreignId('bank_id')->nullable()->after('id')->constrained('banks')->nullOnDelete();
        });

        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->foreignId('bank_id')->nullable()->after('id')->constrained('banks')->nullOnDelete();
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->foreignId('bank_id')->nullable()->after('user_id')->constrained('banks')->nullOnDelete();
        });

        $defaultBankId = DB::table('banks')->insertGetId([
            'name' => 'Default Bank',
            'code' => 'default-bank',
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $distinctBankNames = DB::table('clients')
            ->whereNotNull('bank_name')
            ->where('bank_name', '!=', '')
            ->distinct()
            ->pluck('bank_name');

        foreach ($distinctBankNames as $bankName) {
            $name = trim((string) $bankName);
            if ($name === '' || DB::table('banks')->where('name', $name)->exists()) {
                continue;
            }

            $code = Str::slug($name);
            $originalCode = $code;
            $suffix = 1;
            while (DB::table('banks')->where('code', $code)->exists()) {
                $code = $originalCode . '-' . $suffix++;
            }

            DB::table('banks')->insert([
                'name' => $name,
                'code' => $code,
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $banks = DB::table('banks')->pluck('id', 'name');

        DB::table('clients')->orderBy('id')->get(['id', 'bank_name'])->each(function ($client) use ($banks, $defaultBankId) {
            $bankId = $banks[$client->bank_name] ?? $defaultBankId;
            DB::table('clients')->where('id', $client->id)->update(['bank_id' => $bankId]);
        });

        DB::table('campaigns')->orderBy('id')->get(['id'])->each(function ($campaign) use ($defaultBankId) {
            $clientBankId = DB::table('campaign_clients')
                ->join('clients', 'clients.id', '=', 'campaign_clients.client_id')
                ->where('campaign_clients.campaign_id', $campaign->id)
                ->value('clients.bank_id');

            DB::table('campaigns')->where('id', $campaign->id)->update([
                'bank_id' => $clientBankId ?: $defaultBankId,
            ]);
        });

        DB::table('chat_sessions')->orderBy('id')->get(['id', 'client_id'])->each(function ($session) use ($defaultBankId) {
            $bankId = null;
            if ($session->client_id) {
                $bankId = DB::table('clients')->where('id', $session->client_id)->value('bank_id');
            }

            DB::table('chat_sessions')->where('id', $session->id)->update([
                'bank_id' => $bankId ?: $defaultBankId,
            ]);
        });

        DB::table('users')->orderBy('id')->get(['id', 'role'])->each(function ($user) use ($defaultBankId) {
            $bankId = in_array($user->role, ['SUPER_ADMIN', 'ADMIN'], true) ? null : $defaultBankId;
            DB::table('users')->where('id', $user->id)->update(['bank_id' => $bankId]);
        });

        DB::table('audit_logs')->orderBy('id')->get(['id', 'user_id'])->each(function ($log) use ($defaultBankId) {
            $bankId = null;
            if ($log->user_id) {
                $bankId = DB::table('users')->where('id', $log->user_id)->value('bank_id');
            }

            DB::table('audit_logs')->where('id', $log->id)->update([
                'bank_id' => $bankId ?: $defaultBankId,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_id');
        });

        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_id');
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_id');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_id');
        });

        Schema::dropIfExists('banks');
    }
};
