<?php

use App\Actions\Attachments\StoreAttachment;
use App\Models\Period\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\IsolatedPostgres;

it('refuses PHP uploads before storing any attachment or writing business metadata', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        Storage::fake('attachments');

        $product = new Product;
        $bad = UploadedFile::fake()->createWithContent('invoice.php', '<?php echo 1;');

        expect(fn () => app(StoreAttachment::class)->handle($product, $bad))
            ->toThrow(ValidationException::class);

        expect(Storage::disk('attachments')->allFiles())->toBe([])
            ->and(DB::connection('period')->table('attachments')->count())->toBe(0);
    });
});
