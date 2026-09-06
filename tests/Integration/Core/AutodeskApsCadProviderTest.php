<?php

use App\Modules\Core\Enums\AttachmentTargetType;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\Attachment;
use App\Modules\Core\Models\CadDerivativeJob;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\FileAsset;
use App\Modules\Core\Models\User;
use App\Modules\Core\Preview\AutodeskApsCadProvider;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(DatabaseMigrations::class);

it('uses OAuth v2, direct S3 upload, SVF2 translation and a viewables-only browser token', function (): void {
    config()->set('services.autodesk_aps.base_url', 'https://developer.api.autodesk.com');
    config()->set('services.autodesk_aps.client_id', 'fixture-client');
    config()->set('services.autodesk_aps.client_secret', 'fixture-secret');

    Storage::fake('local');
    Storage::disk('local')->put('cad/real.dwg', 'AC1032-M32-DWG-FIXTURE');

    $company = Company::query()->create(['code' => 'M32APS', 'name' => 'M32 APS']);
    $user = User::query()->create([
        'name' => 'APS User',
        'email' => 'm32-aps@example.test',
        'password' => 'password',
        'status' => UserStatus::Active,
    ]);
    $asset = FileAsset::query()->create([
        'company_id' => $company->getKey(),
        'uploaded_by_user_id' => $user->getKey(),
        'storage_disk' => 'local',
        'storage_key' => 'cad/real.dwg',
        'original_name' => 'real.dwg',
        'mime_type' => 'application/acad',
        'client_extension' => 'dwg',
        'size_bytes' => 24,
        'sha256' => hash('sha256', 'AC1032-M32-DWG-FIXTURE'),
    ]);
    $attachment = Attachment::query()->create([
        'company_id' => $company->getKey(),
        'file_asset_id' => $asset->getKey(),
        'attachable_type' => AttachmentTargetType::Company,
        'attachable_id' => $company->getKey(),
        'label' => 'APS DWG',
        'attached_by_user_id' => $user->getKey(),
        'attached_at' => now(),
    ]);

    Http::fake(function (Request $request) {
        $url = $request->url();

        if (str_ends_with($url, '/authentication/v2/token')) {
            return Http::response(['access_token' => 'aps-token', 'expires_in' => 3599], 200);
        }
        if (str_ends_with($url, '/oss/v2/buckets')) {
            return Http::response(['reason' => 'already exists'], 409);
        }
        if (str_contains($url, '/signeds3upload') && $request->method() === 'GET') {
            return Http::response([
                'urls' => ['https://signed.example.test/upload'],
                'uploadKey' => 'upload-key',
            ], 200);
        }
        if ($url === 'https://signed.example.test/upload') {
            return Http::response('', 200);
        }
        if (str_contains($url, '/signeds3upload') && $request->method() === 'POST') {
            return Http::response([
                'objectId' => 'urn:adsk.objects:os.object:mars-fixture/real.dwg',
            ], 200);
        }
        if (str_ends_with($url, '/modelderivative/v2/designdata/job')) {
            return Http::response(['result' => 'created'], 201);
        }
        if (str_contains($url, '/modelderivative/v2/designdata/') && str_ends_with($url, '/manifest')) {
            return Http::response(['status' => 'success', 'progress' => 'complete'], 200);
        }

        return Http::response(['unexpected' => $url], 500);
    });

    $provider = new AutodeskApsCadProvider;
    $started = $provider->start($attachment, $asset);
    expect($started->status)->toBe('processing')
        ->and($started->manifest['renderer'])->toBe('autodesk-aps-viewer');

    $job = CadDerivativeJob::query()->create([
        'company_id' => $company->getKey(),
        'attachment_id' => $attachment->getKey(),
        'file_asset_id' => $asset->getKey(),
        'source_sha256' => $asset->sha256,
        'source_extension' => 'dwg',
        'provider' => 'aps',
        'provider_version' => $provider->version(),
        'status' => 'processing',
        'provider_job_id' => $started->providerJobId,
        'manifest' => $started->manifest,
    ]);

    $ready = $provider->refresh($job);
    $job->update([
        'status' => $ready->status,
        'preview_kind' => $ready->previewKind,
        'manifest' => $ready->manifest,
        'derivative_sha256' => $ready->derivativeSha256,
    ]);
    $viewerToken = $provider->viewerToken($job->refresh());

    expect($ready->status)->toBe('ready')
        ->and($ready->previewKind)->toBe('cad_2d')
        ->and($viewerToken)->toMatchArray(['access_token' => 'aps-token']);

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/modelderivative/v2/designdata/job')
        && str_contains($request->body(), '"type":"svf2"'));
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/authentication/v2/token')
        && str_contains($request->body(), 'scope=viewables%3Aread'));
});
