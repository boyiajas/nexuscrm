<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
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
};
