<?php

namespace Tests\Feature;

use App\Domain\CMS\Services\CmsMediaSanitizer;
use App\Models\Cms\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class CmsMediaSanitizerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public-cms');
    }

    public function test_jpeg_is_reencoded_with_random_server_filename(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg', 80, 60);

        $result = app(CmsMediaSanitizer::class)->sanitize($file);

        $this->assertSame('image/jpeg', $result->mimeType);
        $this->assertSame('jpg', $result->extension);
        $this->assertSame(80, $result->width);
        $this->assertSame(60, $result->height);
        $this->assertSame('photo.jpg', $result->originalFilename);
        $this->assertNotSame('photo.jpg', $result->filename);
        $this->assertMatchesRegularExpression('/^[0-9a-hjkmnp-tv-z]{26}\.jpg$/', $result->filename);
        $this->assertSame("\xFF\xD8\xFF", substr($result->binary, 0, 3));
        $this->assertSame(hash('sha256', $result->binary), $result->sha256);
        $this->assertGreaterThan(0, $result->byteSize);
    }

    public function test_png_is_reencoded_and_keeps_raster_type(): void
    {
        $file = UploadedFile::fake()->image('badge.png', 32, 32);

        $result = app(CmsMediaSanitizer::class)->sanitize($file);

        $this->assertSame('image/png', $result->mimeType);
        $this->assertSame('png', $result->extension);
        $this->assertSame("\x89PNG", substr($result->binary, 0, 4));
        $this->assertSame(32, $result->width);
        $this->assertSame(32, $result->height);
    }

    public function test_webp_is_accepted_when_gd_supports_it(): void
    {
        if (! function_exists('imagecreatefromwebp') || ! function_exists('imagewebp')) {
            $this->markTestSkipped('GD WebP support is required for CMS WebP uploads.');
        }

        $canvas = imagecreatetruecolor(12, 8);
        $this->assertNotFalse($canvas);
        ob_start();
        imagewebp($canvas, null, 80);
        $bytes = ob_get_clean();
        imagedestroy($canvas);
        $this->assertIsString($bytes);

        $result = app(CmsMediaSanitizer::class)->sanitize(
            UploadedFile::fake()->createWithContent('cover.webp', $bytes),
        );

        $this->assertSame('image/webp', $result->mimeType);
        $this->assertSame('webp', $result->extension);
        $this->assertSame(12, $result->width);
        $this->assertSame(8, $result->height);
        $this->assertSame('RIFF', substr($result->binary, 0, 4));
        $this->assertSame('WEBP', substr($result->binary, 8, 4));
    }

    public function test_svg_is_rejected_even_with_svg_extension(): void
    {
        $this->expectReject(
            UploadedFile::fake()->createWithContent(
                'logo.svg',
                '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
            ),
        );
    }

    public function test_svg_spoofed_as_jpeg_is_rejected(): void
    {
        $this->expectReject(
            UploadedFile::fake()->createWithContent(
                'photo.jpg',
                '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg"></svg>',
            ),
        );
    }

    public function test_gif_is_rejected_even_when_named_png(): void
    {
        $this->expectReject(
            UploadedFile::fake()->createWithContent('anim.png', 'GIF89a'.str_repeat("\x00", 24)),
        );
    }

    public function test_php_polyglot_named_jpeg_is_rejected(): void
    {
        $this->expectReject(
            UploadedFile::fake()->createWithContent('shell.jpg', '<?php echo "unsafe";'),
        );
    }

    public function test_malformed_jpeg_magic_is_rejected(): void
    {
        $this->expectReject(
            UploadedFile::fake()->createWithContent('broken.jpg', "\xFF\xD8\xFF\xE0not-a-jpeg"),
        );
    }

    public function test_jpeg_polyglot_trailing_svg_is_stripped_on_reencode(): void
    {
        $base = UploadedFile::fake()->image('base.jpg', 24, 24);
        $polyglot = file_get_contents($base->getRealPath()).'<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';

        $result = app(CmsMediaSanitizer::class)->sanitize(
            UploadedFile::fake()->createWithContent('photo.jpg', $polyglot),
        );

        $this->assertSame('image/jpeg', $result->mimeType);
        $this->assertSame(24, $result->width);
        $this->assertStringNotContainsString('<svg', $result->binary);
        $this->assertStringNotContainsString('script', strtolower($result->binary));
    }

    public function test_oversized_pixel_edge_is_rejected(): void
    {
        config(['royadarman.cms.media.max_edge' => 10]);

        $this->expectReject(UploadedFile::fake()->image('wide.jpg', 20, 8));
    }

    public function test_api_upload_stores_sanitized_bytes_not_original_name(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $file = UploadedFile::fake()->image('Team Photo.JPG', 64, 48);

        $this->actingAs($owner)->postJson('/api/v1/cms/media', [
            'file' => $file,
            'locale' => 'fa',
            'alt_text' => 'نمای کلینیک',
        ])->assertCreated();

        $media = Media::query()->first();
        $this->assertNotNull($media);
        $this->assertSame(64, $media->width);
        $this->assertSame(48, $media->height);
        $this->assertSame('image/jpeg', $media->mime_type);
        $this->assertSame('Team Photo.JPG', $media->original_filename);
        $this->assertMatchesRegularExpression('#^media/[0-9a-hjkmnp-tv-z]{26}\.jpg$#', $media->storage_key);
        $this->assertStringNotContainsString('Team Photo', $media->storage_key);
        Storage::disk('public-cms')->assertExists($media->storage_key);
        $stored = Storage::disk('public-cms')->get($media->storage_key);
        $this->assertSame("\xFF\xD8\xFF", substr($stored, 0, 3));
        $this->assertSame(hash('sha256', $stored), $media->sha256);
    }

    public function test_api_rejects_svg_and_stores_nothing(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)->postJson('/api/v1/cms/media', [
            'file' => UploadedFile::fake()->createWithContent(
                'icon.svg',
                '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
            ),
            'locale' => 'en',
        ])->assertStatus(422);

        $this->assertNull(Media::query()->first());
        $this->assertSame([], Storage::disk('public-cms')->allFiles());
    }

    public function test_clinician_cannot_upload_cms_media(): void
    {
        $clinician = User::factory()->create(['role' => 'clinician']);

        $this->actingAs($clinician)->postJson('/api/v1/cms/media', [
            'file' => UploadedFile::fake()->image('p.jpg', 10, 10),
            'locale' => 'en',
        ])->assertForbidden();
    }

    public function test_admin_form_upload_rejects_gif(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)->post('/admin/cms/media', [
            'file' => UploadedFile::fake()->createWithContent('loop.gif', 'GIF89a'.str_repeat("\x00", 16)),
        ])->assertSessionHasErrors('file');

        $this->assertSame(0, Media::query()->count());
    }

    public function test_media_index_uses_application_serve_url(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $this->actingAs($owner)->postJson('/api/v1/cms/media', [
            'file' => UploadedFile::fake()->image('photo.jpg', 20, 20),
            'locale' => 'en',
        ])->assertCreated();

        $media = Media::query()->first();
        $this->assertNotNull($media);

        $this->actingAs($owner)
            ->getJson('/api/v1/cms/media')
            ->assertOk()
            ->assertJsonPath('data.0.url', route('cms.media.serve', $media));
    }

    private function expectReject(UploadedFile $file): void
    {
        try {
            app(CmsMediaSanitizer::class)->sanitize($file);
            $this->fail('Expected CMS media sanitizer to reject the upload.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('file', $exception->errors());
        }
    }
}
