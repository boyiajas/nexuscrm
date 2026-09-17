<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Bank;
use App\Models\Department;
use App\Models\ImportUpload;
use App\Models\Client;
use App\Jobs\ImportClientsJob;
use App\Traits\HasImportHelpers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

class ClientImportHeaderDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    private function getHelper()
    {
        return new class {
            use HasImportHelpers;

            public function getDiagnostics($header)
            {
                return $this->buildImportHeaderDiagnostics($header);
            }

            public function isValid($header)
            {
                return $this->isValidImportHeader($header);
            }

            public function normalize($header)
            {
                return $this->normalizeImportHeader($header);
            }
        };
    }

    public function test_header_diagnostics_marks_missing_name_as_invalid(): void
    {
        $helper = $this->getHelper();
        $header = ['Account Number', 'Cell', 'ID Number', 'Outstanding Balance'];

        $diagnostics = $helper->getDiagnostics($header);

        $this->assertFalse($diagnostics['is_valid']);
        $this->assertFalse($diagnostics['has_required_name']);
        $this->assertContains('name', $diagnostics['missing_required_headers']);
        $this->assertCount(4, $diagnostics['recognized_columns']);
    }

    public function test_header_diagnostics_accepts_various_name_columns(): void
    {
        $helper = $this->getHelper();

        $variations = [
            ['Name', 'Cell', 'Account Number'],
            ['First Name', 'Surname', 'Cell', 'Account Number'],
            ['Full Name', 'Cell', 'Account Number'],
            ['Client Name', 'Cell', 'Account Number'],
            ['Debtor Name', 'Cell', 'Account Number'],
            ['Surname', 'Title', 'Initials', 'Cell', 'Account Number'],
            ['Known As', 'Cell', 'Account Number'],
        ];

        foreach ($variations as $header) {
            $diagnostics = $helper->getDiagnostics($header);
            $this->assertTrue($diagnostics['is_valid'], "Header [" . implode(',', $header) . "] should be valid.");
            $this->assertTrue($diagnostics['has_required_name']);
            $this->assertEmpty($diagnostics['missing_required_headers']);
        }
    }

    public function test_utf8_bom_is_stripped_from_header_columns(): void
    {
        $helper = $this->getHelper();
        $bomHeader = ["\xEF\xBB\xBFName", "Account Number", "Cell"];

        $diagnostics = $helper->getDiagnostics($bomHeader);

        $this->assertTrue($diagnostics['is_valid']);
        $this->assertTrue($diagnostics['has_required_name']);
        $this->assertSame('Name', $diagnostics['detected_headers'][0]);
    }

    public function test_import_job_stores_diagnostics_on_header_failure(): void
    {
        $bank = Bank::create(['name' => 'FinChoice Test', 'code' => 'FINCHOICE_TEST']);
        $dept = Department::create(['name' => 'Collections', 'bank_id' => $bank->id]);
        $user = User::factory()->create(['role' => 'SUPER_ADMIN', 'bank_id' => $bank->id]);

        $invalidCsv = "Account Number,Cell,Balance\n1001,0821234567,500.00\n";
        $tempPath = tempnam(sys_get_temp_dir(), 'inv_') . '.csv';
        file_put_contents($tempPath, $invalidCsv);

        $upload = ImportUpload::create([
            'bank_id' => $bank->id,
            'user_id' => $user->id,
            'dataset' => 'clients',
            'original_filename' => 'invalid.csv',
            'import_batch_number' => 'IMP-TEST-001',
            'stored_path' => $tempPath,
            'import_status' => 'uploaded',
        ]);

        try {
            $job = new ImportClientsJob(
                $upload->id,
                $user->id,
                $bank->id,
                [$dept->id],
                $tempPath,
                'invalid.csv',
                'IMP-TEST-001',
                ['status' => 'skipped']
            );
            $job->handle();
            $this->fail('Expected exception on missing name header.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('missing', strtolower($e->getMessage()));
        }

        $upload->refresh();
        $this->assertSame('import_failed', $upload->import_status);
        $this->assertNotNull($upload->import_summary);
        $this->assertArrayHasKey('header_diagnostics', $upload->import_summary);
        $this->assertFalse($upload->import_summary['header_diagnostics']['is_valid']);
        $this->assertContains('name', $upload->import_summary['header_diagnostics']['missing_required_headers']);

        @unlink($tempPath);
    }

    public function test_import_job_succeeds_with_bank_handover_format(): void
    {
        $bank = Bank::create(['name' => 'Capfin Test', 'code' => 'CAPFIN_TEST']);
        $dept = Department::create(['name' => 'Legal', 'bank_id' => $bank->id]);
        $user = User::factory()->create(['role' => 'SUPER_ADMIN', 'bank_id' => $bank->id]);

        // Simulating Capfin / FinChoice export where some rows have First Name empty but Surname, Title, Initials present
        $csvContent = "Acc Code,Account Number,Title,Initials,Known As,Name,Surname,ID Number,Cell,Outstanding balance\n" .
                      "4964194,36889408,MS,N,,,NDLANGAMANDLA,0009301143088,0649355738,125.32\n" .
                      "4964195,36889409,MR,T,,TERENCE,GERTZE,8101195041080,0837309861,2417.56\n";

        $tempPath = tempnam(sys_get_temp_dir(), 'cap_') . '.csv';
        file_put_contents($tempPath, $csvContent);

        $upload = ImportUpload::create([
            'bank_id' => $bank->id,
            'user_id' => $user->id,
            'dataset' => 'clients',
            'original_filename' => 'CAPFIN_TEST.csv',
            'import_batch_number' => 'IMP-TEST-CAPFIN',
            'stored_path' => $tempPath,
            'import_status' => 'uploaded',
        ]);

        $job = new ImportClientsJob(
            $upload->id,
            $user->id,
            $bank->id,
            [$dept->id],
            $tempPath,
            'CAPFIN_TEST.csv',
            'IMP-TEST-CAPFIN',
            ['status' => 'skipped']
        );
        $job->handle();

        $upload->refresh();
        $this->assertSame('imported', $upload->import_status);
        $this->assertSame(2, $upload->import_summary['imported']);

        // Verify both clients were created without skipping
        $client1 = Client::where('account_number', '36889408')->first();
        $this->assertNotNull($client1);
        $this->assertStringContainsString('NDLANGAMANDLA', $client1->name);

        $client2 = Client::where('account_number', '36889409')->first();
        $this->assertNotNull($client2);
        $this->assertStringContainsString('TERENCE GERTZE', $client2->name);

        @unlink($tempPath);
    }
}
