<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $this->ensureSecretColumnsCanStoreCiphertext();

        $secretColumns = [
            'meta_app_secret',
            'meta_access_token',
            'meta_webhook_verify_token',
            'twilio_api_key',
            'twilio_sid',
            'twilio_auth_token',
            'twilio_msg_sid',
            'twilio_template_sid',
            'twilio_whatsapp_from',
            'twilio_status_callback',
        ];

        DB::table('system_settings')->orderBy('id')->get()->each(function ($row) use ($secretColumns) {
            $updates = [];

            foreach ($secretColumns as $column) {
                $value = $row->{$column} ?? null;
                if ($value === null || $value === '') {
                    continue;
                }

                if ($this->isEncrypted($value)) {
                    continue;
                }

                $updates[$column] = Crypt::encryptString((string) $value);
            }

            if ($updates) {
                DB::table('system_settings')->where('id', $row->id)->update($updates);
            }
        });
    }

    public function down(): void
    {
        $secretColumns = [
            'meta_app_secret',
            'meta_access_token',
            'meta_webhook_verify_token',
            'twilio_api_key',
            'twilio_sid',
            'twilio_auth_token',
            'twilio_msg_sid',
            'twilio_template_sid',
            'twilio_whatsapp_from',
            'twilio_status_callback',
        ];

        DB::table('system_settings')->orderBy('id')->get()->each(function ($row) use ($secretColumns) {
            $updates = [];

            foreach ($secretColumns as $column) {
                $value = $row->{$column} ?? null;
                if ($value === null || $value === '') {
                    continue;
                }

                if (!$this->isEncrypted($value)) {
                    continue;
                }

                $updates[$column] = Crypt::decryptString((string) $value);
            }

            if ($updates) {
                DB::table('system_settings')->where('id', $row->id)->update($updates);
            }
        });
    }

    private function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function ensureSecretColumnsCanStoreCiphertext(): void
    {
        if (!Schema::hasTable('system_settings')) {
            return;
        }

        $driver = DB::getDriverName();

        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        $textColumns = [
            'meta_app_secret',
            'meta_access_token',
            'meta_webhook_verify_token',
            'twilio_api_key',
            'twilio_sid',
            'twilio_auth_token',
            'twilio_msg_sid',
            'twilio_template_sid',
            'twilio_whatsapp_from',
            'twilio_status_callback',
        ];

        foreach ($textColumns as $column) {
            if (!Schema::hasColumn('system_settings', $column)) {
                continue;
            }

            DB::statement(sprintf(
                'ALTER TABLE `system_settings` MODIFY `%s` TEXT NULL',
                $column
            ));
        }
    }
};
