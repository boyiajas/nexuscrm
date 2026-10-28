<?php

namespace Tests\Feature;

use App\Contracts\WhatsAppServiceInterface;
use App\Models\Bank;
use App\Models\User;
use App\Models\WhatsappTemplateCache;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class WhatsAppTemplateMediaAndValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Bank $bank;
    private $whatsappMock;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->bank = Bank::create(['name' => 'African Bank', 'code' => 'AB01']);

        $adminRole = Role::firstOrCreate(
            ['code' => User::ROLE_SUPER_ADMIN],
            ['name' => 'Super Administrator', 'is_system' => true, 'is_active' => true]
        );
        $permWabaTemplates = Permission::firstOrCreate(['code' => 'settings_waba_templates'], ['name' => 'WABA Templates', 'module' => 'Settings']);
        $adminRole->permissions()->syncWithoutDetaching([$permWabaTemplates->id]);

        $this->admin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin_' . uniqid() . '@example.com',
            'password' => Hash::make('password123'),
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => 'Active',
            'password_reset_required' => false,
            'password_changed_at' => now(),
            'is_super_admin' => true,
            'bank_id' => $this->bank->id,
        ]);
        $this->admin->roles()->attach($adminRole->id);

        $this->whatsappMock = Mockery::mock(WhatsAppServiceInterface::class);
        $this->app->instance(WhatsAppServiceInterface::class, $this->whatsappMock);
    }

    public function test_can_create_image_template_with_valid_jpg_header(): void
    {
        $this->whatsappMock->shouldReceive('uploadMediaHandle')
            ->once()
            ->andReturn('meta_image_handle_123');

        $this->whatsappMock->shouldReceive('createWhatsAppTemplate')
            ->once()
            ->with(
                'summer_promo',
                'Check out our special offer!',
                'en_US',
                'MARKETING',
                Mockery::type('array'),
                [],
                [],
                'IMAGE',
                null,
                'meta_image_handle_123',
                null
            )
            ->andReturn([
                'meta_id' => '11223344',
                'sid' => 'summer_promo',
                'friendly_name' => 'summer_promo',
                'language' => 'en_US',
                'preview' => 'Check out our special offer!',
                'media' => ['http://localhost/storage/uploads/whatsapp_templates/sample.jpg'],
                'header_format' => 'IMAGE',
                'header_text' => null,
                'footer_text' => null,
                'buttons' => [],
                'whatsapp' => ['status' => 'PENDING', 'category' => 'marketing'],
            ]);

        $file = UploadedFile::fake()->image('banner.jpg', 600, 400)->size(3000); // 3 MB (within 5 MB limit)

        $response = $this->actingAs($this->admin)
            ->post('/api/whatsapp-templates', [
                'friendly_name' => 'summer_promo',
                'body' => 'Check out our special offer!',
                'language' => 'en_US',
                'category' => 'MARKETING',
                'template_type' => 'image',
                'header_format' => 'IMAGE',
                'header_file' => $file,
                'bank_ids' => [$this->bank->id],
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('whatsapp_templates_cache', [
            'sid' => 'summer_promo',
            'header_format' => 'IMAGE',
        ]);
    }

    public function test_rejects_image_template_exceeding_5mb_limit(): void
    {
        // 6000 KB = ~5.85 MB (exceeds 5120 KB limit)
        $file = UploadedFile::fake()->create('large_banner.jpg', 6000, 'image/jpeg');

        $response = $this->actingAs($this->admin)
            ->postJson('/api/whatsapp-templates', [
                'friendly_name' => 'large_image_template',
                'body' => 'Huge image test',
                'language' => 'en_US',
                'category' => 'MARKETING',
                'template_type' => 'image',
                'header_format' => 'IMAGE',
                'header_file' => $file,
                'bank_ids' => [$this->bank->id],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['header_file']);
    }

    public function test_can_create_video_template_with_valid_mp4(): void
    {
        $this->whatsappMock->shouldReceive('uploadMediaHandle')
            ->once()
            ->andReturn('meta_video_handle_456');

        $this->whatsappMock->shouldReceive('createWhatsAppTemplate')
            ->once()
            ->with(
                'intro_video_template',
                'Watch our introduction video.',
                'en_US',
                'UTILITY',
                Mockery::type('array'),
                [],
                [],
                'VIDEO',
                null,
                'meta_video_handle_456',
                'Reply STOP to opt out'
            )
            ->andReturn([
                'meta_id' => '998877',
                'sid' => 'intro_video_template',
                'friendly_name' => 'intro_video_template',
                'language' => 'en_US',
                'preview' => 'Watch our introduction video.',
                'media' => ['http://localhost/storage/uploads/whatsapp_templates/intro.mp4'],
                'header_format' => 'VIDEO',
                'header_text' => null,
                'footer_text' => 'Reply STOP to opt out',
                'buttons' => [],
                'whatsapp' => ['status' => 'PENDING', 'category' => 'utility'],
            ]);

        $file = UploadedFile::fake()->create('intro.mp4', 12000, 'video/mp4'); // 12 MB (within 16 MB limit)

        $response = $this->actingAs($this->admin)
            ->post('/api/whatsapp-templates', [
                'friendly_name' => 'intro_video_template',
                'body' => 'Watch our introduction video.',
                'language' => 'en_US',
                'category' => 'UTILITY',
                'template_type' => 'video',
                'header_format' => 'VIDEO',
                'header_file' => $file,
                'footer_text' => 'Reply STOP to opt out',
                'bank_ids' => [$this->bank->id],
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('whatsapp_templates_cache', [
            'sid' => 'intro_video_template',
            'header_format' => 'VIDEO',
            'footer_text' => 'Reply STOP to opt out',
        ]);
    }

    public function test_rejects_video_template_exceeding_16mb_limit(): void
    {
        // 17000 KB = ~16.6 MB (exceeds 16384 KB limit)
        $file = UploadedFile::fake()->create('oversize_video.mp4', 17000, 'video/mp4');

        $response = $this->actingAs($this->admin)
            ->postJson('/api/whatsapp-templates', [
                'friendly_name' => 'oversize_video',
                'body' => 'Video too big',
                'language' => 'en_US',
                'category' => 'UTILITY',
                'template_type' => 'video',
                'header_format' => 'VIDEO',
                'header_file' => $file,
                'bank_ids' => [$this->bank->id],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['header_file']);
    }

    public function test_can_create_document_template_with_valid_pdf(): void
    {
        $this->whatsappMock->shouldReceive('uploadMediaHandle')
            ->once()
            ->andReturn('meta_doc_handle_789');

        $this->whatsappMock->shouldReceive('createWhatsAppTemplate')
            ->once()
            ->with(
                'monthly_statement',
                'Attached is your monthly statement.',
                'en_US',
                'UTILITY',
                Mockery::type('array'),
                [],
                [],
                'DOCUMENT',
                null,
                'meta_doc_handle_789',
                null
            )
            ->andReturn([
                'meta_id' => '556677',
                'sid' => 'monthly_statement',
                'friendly_name' => 'monthly_statement',
                'language' => 'en_US',
                'preview' => 'Attached is your monthly statement.',
                'media' => ['http://localhost/storage/uploads/whatsapp_templates/statement.pdf'],
                'header_format' => 'DOCUMENT',
                'header_text' => null,
                'footer_text' => null,
                'buttons' => [],
                'whatsapp' => ['status' => 'PENDING', 'category' => 'utility'],
            ]);

        $file = UploadedFile::fake()->create('statement.pdf', 5000, 'application/pdf'); // 5 MB (within 100 MB limit)

        $response = $this->actingAs($this->admin)
            ->post('/api/whatsapp-templates', [
                'friendly_name' => 'monthly_statement',
                'body' => 'Attached is your monthly statement.',
                'language' => 'en_US',
                'category' => 'UTILITY',
                'template_type' => 'document',
                'header_format' => 'DOCUMENT',
                'header_file' => $file,
                'bank_ids' => [$this->bank->id],
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('whatsapp_templates_cache', [
            'sid' => 'monthly_statement',
            'header_format' => 'DOCUMENT',
        ]);
    }

    public function test_can_create_youtube_template_with_valid_youtube_button(): void
    {
        $this->whatsappMock->shouldReceive('createWhatsAppTemplate')
            ->once()
            ->with(
                'youtube_tutorial',
                'Watch our step-by-step tutorial on YouTube.',
                'en_US',
                'MARKETING',
                [],
                [],
                Mockery::on(function ($buttons) {
                    return count($buttons) === 1
                        && $buttons[0]['type'] === 'URL'
                        && $buttons[0]['text'] === 'Watch Tutorial'
                        && str_contains($buttons[0]['url'], 'youtube.com/watch?v=dQw4w9WgXcQ');
                }),
                null,
                null,
                null,
                null
            )
            ->andReturn([
                'meta_id' => '334455',
                'sid' => 'youtube_tutorial',
                'friendly_name' => 'youtube_tutorial',
                'language' => 'en_US',
                'preview' => 'Watch our step-by-step tutorial on YouTube.',
                'media' => [],
                'header_format' => null,
                'header_text' => null,
                'footer_text' => null,
                'buttons' => [
                    [
                        'type' => 'URL',
                        'text' => 'Watch Tutorial',
                        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                    ],
                ],
                'whatsapp' => ['status' => 'PENDING', 'category' => 'marketing'],
            ]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/whatsapp-templates', [
                'friendly_name' => 'youtube_tutorial',
                'body' => 'Watch our step-by-step tutorial on YouTube.',
                'language' => 'en_US',
                'category' => 'MARKETING',
                'template_type' => 'youtube',
                'bank_ids' => [$this->bank->id],
                'buttons' => [
                    [
                        'type' => 'URL',
                        'text' => 'Watch Tutorial',
                        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                    ],
                ],
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('whatsapp_templates_cache', [
            'sid' => 'youtube_tutorial',
        ]);
    }

    public function test_rejects_youtube_template_with_invalid_youtube_url(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/whatsapp-templates', [
                'friendly_name' => 'bad_youtube_template',
                'body' => 'Check this video out.',
                'language' => 'en_US',
                'category' => 'MARKETING',
                'template_type' => 'youtube',
                'bank_ids' => [$this->bank->id],
                'buttons' => [
                    [
                        'type' => 'URL',
                        'text' => 'Watch Video',
                        'url' => 'https://example.com/not-youtube',
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['buttons']);
    }

    public function test_rejects_body_exceeding_1024_characters(): void
    {
        $longBody = str_repeat('a', 1025);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/whatsapp-templates', [
                'friendly_name' => 'oversize_body',
                'body' => $longBody,
                'language' => 'en_US',
                'category' => 'UTILITY',
                'bank_ids' => [$this->bank->id],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['body']);
    }

    public function test_rejects_header_text_exceeding_60_characters(): void
    {
        $longHeader = str_repeat('H', 61);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/whatsapp-templates', [
                'friendly_name' => 'oversize_header',
                'body' => 'Short body text',
                'header_format' => 'TEXT',
                'header_text' => $longHeader,
                'language' => 'en_US',
                'category' => 'UTILITY',
                'bank_ids' => [$this->bank->id],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['header_text']);
    }

    public function test_rejects_footer_text_exceeding_60_characters(): void
    {
        $longFooter = str_repeat('F', 61);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/whatsapp-templates', [
                'friendly_name' => 'oversize_footer',
                'body' => 'Short body text',
                'footer_text' => $longFooter,
                'language' => 'en_US',
                'category' => 'UTILITY',
                'bank_ids' => [$this->bank->id],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['footer_text']);
    }
}
