<?php

namespace Tests\Unit;

use App\Services\DocumentUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DocumentUploadPolicyTest extends TestCase
{
    public static function extensions(): array
    {
        return array_map(fn ($extension) => [$extension], DocumentUpload::ALLOWED_EXTENSIONS);
    }

    #[DataProvider('extensions')]
    public function test_supported_extensions_are_case_insensitive(string $extension): void
    {
        $file = UploadedFile::fake()->create('evidence.'.strtoupper($extension), 1);
        $this->assertSame($extension, DocumentUpload::extension($file));
        $this->assertTrue(DocumentUpload::allows($file));
    }

    public static function prohibitedNames(): array
    {
        return array_map(fn ($name) => [$name], ['payload.php', 'image.jpg.php', 'script.exe', 'page.html', 'run.sh', 'README']);
    }

    #[DataProvider('prohibitedNames')]
    public function test_prohibited_extensions_are_rejected_with_the_correct_field(string $name): void
    {
        $file = UploadedFile::fake()->create($name, 1);
        $this->assertFalse(DocumentUpload::allows($file));
        try {
            DocumentUpload::assertAllowed($file, 'evidence');
            $this->fail('Executable and unlisted extensions must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('evidence', $exception->errors());
        }
    }

    public function test_size_limit_includes_exactly_twenty_megabytes_but_rejects_one_kilobyte_more(): void
    {
        DocumentUpload::assertAllowed(UploadedFile::fake()->create('scan.pdf', DocumentUpload::MAX_KB));
        $this->expectException(ValidationException::class);
        DocumentUpload::assertAllowed(UploadedFile::fake()->create('scan.pdf', DocumentUpload::MAX_KB + 1));
    }

    public function test_optional_and_required_upload_controls_share_the_same_policy(): void
    {
        $required = DocumentUpload::rules();
        $optional = DocumentUpload::rules(false);
        $this->assertSame('required', $required[0]);
        $this->assertSame('nullable', $optional[0]);
        $this->assertSame(array_slice($required, 1), array_slice($optional, 1));
        $this->assertContains('max:20480', $required);
    }
}
