<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $banks = [
            ['name' => 'Absa Bank Limited', 'code' => 'absa-bank', 'status' => 'Active'],
            ['name' => 'Access Bank South Africa Limited', 'code' => 'access-bank-sa', 'status' => 'Active'],
            ['name' => 'African Bank Limited', 'code' => 'african-bank', 'status' => 'Active'],
            ['name' => 'Albaraka Bank Limited', 'code' => 'albaraka-bank', 'status' => 'Active'],
            ['name' => 'Bank Zero Mutual Bank', 'code' => 'bank-zero', 'status' => 'Active'],
            ['name' => 'Bank of China Johannesburg Branch', 'code' => 'bank-of-china-jhb', 'status' => 'Active'],
            ['name' => 'Bank of Communications Johannesburg Branch', 'code' => 'bank-of-communications-jhb', 'status' => 'Active'],
            ['name' => 'Bank of Taiwan South Africa Branch', 'code' => 'bank-of-taiwan-sa', 'status' => 'Active'],
            ['name' => 'Bidvest Bank Limited', 'code' => 'bidvest-bank', 'status' => 'Active'],
            ['name' => 'Capitec Bank Limited', 'code' => 'capitec-bank', 'status' => 'Active'],
            ['name' => 'Citibank N.A. South Africa Branch', 'code' => 'citibank-sa-branch', 'status' => 'Active'],
            ['name' => 'China Construction Bank Johannesburg Branch', 'code' => 'china-construction-bank-jhb', 'status' => 'Active'],
            ['name' => 'Development Bank of Southern Africa', 'code' => 'dbsa', 'status' => 'Active'],
            ['name' => 'Deutsche Bank AG Johannesburg Branch', 'code' => 'deutsche-bank-jhb', 'status' => 'Active'],
            ['name' => 'Discovery Bank Limited', 'code' => 'discovery-bank', 'status' => 'Active'],
            ['name' => 'Finbond Mutual Bank', 'code' => 'finbond-mutual-bank', 'status' => 'Active'],
            ['name' => 'FirstRand Bank Limited', 'code' => 'firstrand-bank', 'status' => 'Active'],
            ['name' => 'GBS Mutual Bank', 'code' => 'gbs-mutual-bank', 'status' => 'Active'],
            ['name' => 'Goldman Sachs International Bank Johannesburg Branch', 'code' => 'goldman-sachs-jhb', 'status' => 'Active'],
            ['name' => 'Habib Overseas Bank Limited', 'code' => 'habib-overseas-bank', 'status' => 'Active'],
            ['name' => 'HBZ Bank Limited', 'code' => 'hbz-bank', 'status' => 'Active'],
            ['name' => 'HSBC Bank plc South Africa Branch', 'code' => 'hsbc-sa-branch', 'status' => 'Active'],
            ['name' => 'Investec Bank Limited', 'code' => 'investec-bank', 'status' => 'Active'],
            ['name' => 'J.P. Morgan South Africa Branch', 'code' => 'jp-morgan-sa-branch', 'status' => 'Active'],
            ['name' => 'Land and Agricultural Development Bank of South Africa', 'code' => 'land-bank', 'status' => 'Active'],
            ['name' => 'Nedbank Limited', 'code' => 'nedbank', 'status' => 'Active'],
            ['name' => 'Old Mutual Bank Limited', 'code' => 'old-mutual-bank', 'status' => 'Active'],
            ['name' => 'Postbank SOC Limited', 'code' => 'postbank', 'status' => 'Active'],
            ['name' => 'Sasfin Bank Limited', 'code' => 'sasfin-bank', 'status' => 'Active'],
            ['name' => 'Standard Bank of South Africa Limited', 'code' => 'standard-bank-sa', 'status' => 'Active'],
            ['name' => 'Standard Chartered Bank South Africa Limited', 'code' => 'standard-chartered-sa', 'status' => 'Active'],
            ['name' => 'State Bank of India South Africa Branch', 'code' => 'state-bank-of-india-sa', 'status' => 'Active'],
            ['name' => 'TymeBank Limited', 'code' => 'tymebank', 'status' => 'Active'],
        ];

        foreach ($banks as $bank) {
            $existing = DB::table('banks')
                ->where('name', $bank['name'])
                ->orWhere('code', $bank['code'])
                ->first();

            if ($existing) {
                DB::table('banks')
                    ->where('id', $existing->id)
                    ->update([
                        'name' => $bank['name'],
                        'code' => $bank['code'],
                        'status' => $bank['status'],
                        'updated_at' => now(),
                    ]);
                continue;
            }

            DB::table('banks')->insert([
                'name' => $bank['name'],
                'code' => $bank['code'],
                'status' => $bank['status'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $codes = [
            'absa-bank',
            'access-bank-sa',
            'african-bank',
            'albaraka-bank',
            'bank-zero',
            'bank-of-china-jhb',
            'bank-of-communications-jhb',
            'bank-of-taiwan-sa',
            'bidvest-bank',
            'capitec-bank',
            'citibank-sa-branch',
            'china-construction-bank-jhb',
            'dbsa',
            'deutsche-bank-jhb',
            'discovery-bank',
            'finbond-mutual-bank',
            'firstrand-bank',
            'gbs-mutual-bank',
            'goldman-sachs-jhb',
            'habib-overseas-bank',
            'hbz-bank',
            'hsbc-sa-branch',
            'investec-bank',
            'jp-morgan-sa-branch',
            'land-bank',
            'nedbank',
            'old-mutual-bank',
            'postbank',
            'sasfin-bank',
            'standard-bank-sa',
            'standard-chartered-sa',
            'state-bank-of-india-sa',
            'tymebank',
        ];

        DB::table('banks')->whereIn('code', $codes)->delete();
    }
};
