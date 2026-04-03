<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NigtaxProSavedQuery;
use App\Models\NigtaxProUser;
use App\Services\NigtaxProSubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NigtaxProQueryController extends Controller
{
    public function __construct(
        protected NigtaxProSubscriptionService $proSubscription
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $this->requireProUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $rows = NigtaxProSavedQuery::query()
            ->where('nigtax_pro_user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn (NigtaxProSavedQuery $q) => $this->listPayload($q));

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->requireProUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $validated = $request->validate([
            'mode' => 'required|in:business,personal',
            'snapshot' => 'required|json',
            'statement' => 'nullable|file|mimes:pdf|max:15360',
        ]);

        $snapshot = json_decode($validated['snapshot'], true);
        if (! is_array($snapshot)) {
            return response()->json(['message' => 'Invalid snapshot JSON.'], 422);
        }

        $statementFilename = null;
        $pdfPath = null;

        if ($request->hasFile('statement')) {
            $file = $request->file('statement');
            $statementFilename = $file->getClientOriginalName();
            $pdfPath = $file->store('nigtax_pro_statements/'.$user->id, 'local');
        }

        $row = NigtaxProSavedQuery::query()->create([
            'nigtax_pro_user_id' => $user->id,
            'mode' => $validated['mode'],
            'snapshot' => $snapshot,
            'statement_filename' => $statementFilename,
            'statement_pdf_path' => $pdfPath,
        ]);

        return response()->json([
            'data' => $this->detailPayload($row),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $this->requireProUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $row = NigtaxProSavedQuery::query()
            ->where('id', $id)
            ->where('nigtax_pro_user_id', $user->id)
            ->firstOrFail();

        return response()->json(['data' => $this->detailPayload($row)]);
    }

    public function downloadStatement(Request $request, int $id): StreamedResponse|JsonResponse
    {
        $user = $this->requireProUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $row = NigtaxProSavedQuery::query()
            ->where('id', $id)
            ->where('nigtax_pro_user_id', $user->id)
            ->firstOrFail();

        if ($row->statement_pdf_path === null || $row->statement_pdf_path === '') {
            return response()->json(['message' => 'No statement PDF stored for this query.'], 404);
        }

        if (! Storage::disk('local')->exists($row->statement_pdf_path)) {
            return response()->json(['message' => 'Statement file is no longer available.'], 404);
        }

        $name = $row->statement_filename ?: 'statement.pdf';

        return Storage::disk('local')->download($row->statement_pdf_path, $name, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function destroyStatement(Request $request, int $id): JsonResponse
    {
        $user = $this->requireProUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $row = NigtaxProSavedQuery::query()
            ->where('id', $id)
            ->where('nigtax_pro_user_id', $user->id)
            ->firstOrFail();

        $this->deleteStoredPdf($row);
        $row->statement_filename = null;
        $row->statement_pdf_path = null;
        $row->save();

        return response()->json(['ok' => true, 'data' => $this->detailPayload($row->fresh())]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $this->requireProUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $row = NigtaxProSavedQuery::query()
            ->where('id', $id)
            ->where('nigtax_pro_user_id', $user->id)
            ->firstOrFail();

        $this->deleteStoredPdf($row);
        $row->delete();

        return response()->json(['ok' => true]);
    }

    private function requireProUser(Request $request): NigtaxProUser|JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof NigtaxProUser) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! $this->proSubscription->hasActiveSubscription($user->email)) {
            return response()->json([
                'message' => 'Your NigTax PRO subscription is not active.',
                'subscription_required' => true,
            ], 403);
        }

        return $user;
    }

    private function deleteStoredPdf(NigtaxProSavedQuery $row): void
    {
        if ($row->statement_pdf_path && Storage::disk('local')->exists($row->statement_pdf_path)) {
            Storage::disk('local')->delete($row->statement_pdf_path);
        }
    }

    private function listPayload(NigtaxProSavedQuery $q): array
    {
        $snap = $q->snapshot ?? [];

        return [
            'id' => $q->id,
            'mode' => $q->mode,
            'created_at' => $q->created_at?->toIso8601String(),
            'statement_filename' => $q->statement_filename,
            'has_statement_pdf' => $q->statement_pdf_path !== null && $q->statement_pdf_path !== '',
            'summary' => $this->summaryFromSnapshot($q->mode, $snap),
        ];
    }

    private function detailPayload(NigtaxProSavedQuery $q): array
    {
        return [
            'id' => $q->id,
            'mode' => $q->mode,
            'created_at' => $q->created_at?->toIso8601String(),
            'statement_filename' => $q->statement_filename,
            'has_statement_pdf' => $q->statement_pdf_path !== null && $q->statement_pdf_path !== '',
            'snapshot' => $q->snapshot,
        ];
    }

    /**
     * @param  array<string, mixed>  $snap
     */
    private function summaryFromSnapshot(string $mode, array $snap): string
    {
        if ($mode === 'personal') {
            $name = (string) ($snap['individual_name'] ?? 'Personal');
            $inc = $snap['annual_income'] ?? null;

            return $inc !== null ? "{$name} · income ".number_format((float) $inc, 2) : $name;
        }

        $name = (string) ($snap['business_name'] ?? 'Business');
        $inf = $snap['total_inflows'] ?? null;

        return $inf !== null ? "{$name} · inflows ".number_format((float) $inf, 2) : $name;
    }
}
