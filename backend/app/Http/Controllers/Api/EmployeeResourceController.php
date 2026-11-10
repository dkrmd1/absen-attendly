<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\PayrollRecord;
use App\Models\TaxDocument;
use App\Services\LeaveBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeResourceController extends Controller
{
    public function leaveBalance(Request $request, LeaveBalanceService $leaveBalances): JsonResponse
    {
        return response()->json([
            'data' => $leaveBalances->summary($request->user()),
        ]);
    }

    public function payroll(Request $request): JsonResponse
    {
        $records = $request->user()->payrollRecords()
            ->whereNull('archived_at')
            ->orderByDesc('period_start')
            ->paginate(12);

        $records->through(fn (PayrollRecord $record) => [
            'id' => $record->id,
            'period_start' => $record->period_start->toDateString(),
            'gross_amount' => $record->gross_amount,
            'deductions_amount' => $record->deductions_amount,
            'net_amount' => $record->net_amount,
            'components' => $record->components ?? [],
            'payslip_available' => $record->payslip_path !== null,
        ]);

        return response()->json($records);
    }

    public function payslip(Request $request, PayrollRecord $payrollRecord): StreamedResponse
    {
        abort_unless(
            $payrollRecord->user_id === $request->user()->id
            && ! $payrollRecord->archived_at
            && $payrollRecord->payslip_path,
            404,
        );

        return Storage::disk('local')->download($payrollRecord->payslip_path);
    }

    public function taxDocuments(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()->taxDocuments()
                ->whereNull('archived_at')
                ->whereNotNull('visible_at')
                ->where('visible_at', '<=', now())
                ->orderByDesc('tax_year')
                ->get()
                ->map(fn (TaxDocument $document) => [
                    'id' => $document->id,
                    'tax_year' => $document->tax_year,
                    'title' => $document->title,
                    'description' => $document->description,
                    'download_url' => url('/api/tax-documents/'.$document->id.'/file'),
                ]),
        ]);
    }

    public function taxDocumentFile(Request $request, TaxDocument $taxDocument): StreamedResponse
    {
        abort_unless(
            $taxDocument->user_id === $request->user()->id
            && ! $taxDocument->archived_at
            && $taxDocument->visible_at !== null
            && $taxDocument->visible_at->isPast(),
            404,
        );

        return Storage::disk('local')->download($taxDocument->file_path);
    }

    public function announcements(): JsonResponse
    {
        $announcements = Announcement::query()
            ->whereNull('archived_at')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->latest('published_at')
            ->paginate(20);

        $announcements->through(fn (Announcement $item) => [
            'id' => $item->id,
            'category' => $item->category,
            'title' => $item->title,
            'body' => $item->body,
            'published_at' => $item->published_at,
        ]);

        return response()->json($announcements);
    }
}
