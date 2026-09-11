<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Consent\Services\ConsentService;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Services\QuarantineClinicalDocument;
use App\Http\Controllers\Controller;
use App\Models\ClinicalDocument;
use App\Models\PatientCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DocumentController extends Controller
{
    public function store(Request $request, PatientCase $case, QuarantineClinicalDocument $quarantine, ConsentService $consent): JsonResponse
    {
        abort_unless((int) $case->patient_user_id === (int) $request->user()->id, 404);
        $request->validate(['document' => ['required', 'file', 'max:'.config('royadarman.opg.max_kilobytes')]]);

        $consentEvent = $consent->latestActiveFor($request->user(), 'opg_document_sharing', $case->id, 'opg_document_sharing');
        if ($consentEvent === null) {
            return response()->json(['error' => ['code' => 'document.consent_required'], 'request_id' => $request->attributes->get('request_id')], 403);
        }

        $document = $quarantine->handle($case, $request->user(), $request->file('document'), $consentEvent);

        return response()->json(['data' => ['id' => $document->id, 'status' => $document->status->value]], 202);
    }

    public function status(Request $request, PatientCase $case, ClinicalDocument $document): JsonResponse
    {
        abort_unless($document->case_id === $case->id, 404);
        abort_unless($request->user()->can('view', $document), 404);

        return response()->json(['data' => ['id' => $document->id, 'status' => $document->status->value, 'mime' => $document->detected_mime, 'bytes' => $document->byte_size]])->header('Cache-Control', 'private, no-store');
    }

    public function content(Request $request, PatientCase $case, ClinicalDocument $document): StreamedResponse|JsonResponse
    {
        abort_unless($document->case_id === $case->id, 404);
        if ($document->status === DocumentStatus::Rejected) {
            return response()->json(['error' => ['code' => 'document.rejected'], 'request_id' => $request->attributes->get('request_id')], 403);
        }
        if ($document->status === DocumentStatus::ScanFailed) {
            return response()->json(['error' => ['code' => 'document.scan_failed'], 'request_id' => $request->attributes->get('request_id')], 503);
        }
        abort_unless($request->user()->can('view', $document), 404);

        DB::table('document_access_events')->insert(['id' => (string) Str::ulid(), 'document_id' => $document->id, 'actor_user_id' => $request->user()->id, 'case_id' => $case->id, 'purpose' => 'authorised_review', 'action' => 'stream', 'result' => 'success', 'context' => null, 'created_at' => now()]);
        $disk = Storage::disk($document->storage_disk);
        $stream = $disk->readStream($document->storage_key);
        abort_if($stream === false || $stream === null, 404);

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $document->detected_mime,
            'Content-Length' => (string) $document->byte_size,
            'Content-Disposition' => 'inline; filename="opg-image"',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}
