<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\AssetLoan;
use App\Models\Booking;
use App\Models\CheckInAttempt;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function roomUtilization(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $stats = Booking::where('status', BookingStatus::COMPLETED)
            ->whereBetween('starts_at', $this->range($request))
            ->whereHas('resource', fn ($q) => $q->where('type', 'room'))
            ->select('resource_id', DB::raw('COUNT(*) as total_bookings'), DB::raw('SUM(TIMESTAMPDIFF(MINUTE, starts_at, ends_at)) as total_minutes'))
            ->groupBy('resource_id')
            ->with('resource:id,name')
            ->get();

        return response()->json($stats);
    }

    public function noShowRate(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $range = $this->range($request);

        $total = Booking::whereIn('status', [BookingStatus::COMPLETED, BookingStatus::NO_SHOW, BookingStatus::CHECKED_IN])
            ->whereBetween('starts_at', $range)
            ->count();

        $noShows = Booking::where('status', BookingStatus::NO_SHOW)
            ->whereBetween('starts_at', $range)
            ->count();

        return response()->json([
            'total' => $total,
            'no_shows' => $noShows,
            'rate' => $total > 0 ? round($noShows / $total * 100, 2) : 0,
        ]);
    }

    public function popularAssets(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = AssetLoan::select('asset_id', DB::raw('COUNT(*) as loan_count'))
            ->groupBy('asset_id')
            ->orderByDesc('loan_count')
            ->with('asset.resource:id,name')
            ->limit($request->input('limit', 10));

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $this->boundary($request->input('from'), endOfDay: false));
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', $this->boundary($request->input('to'), endOfDay: true));
        }

        return response()->json($query->get());
    }

    public function verificationSuccess(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date'],
        ]);

        $attempts = CheckInAttempt::whereBetween('created_at', $this->range($request));

        $total = (clone $attempts)->count();
        $qrPass = (clone $attempts)->where('qr_valid', true)->count();
        $gpsPass = (clone $attempts)->where('gps_valid', true)->count();
        $facePass = (clone $attempts)->where('face_valid', true)->count();

        return response()->json([
            'total_attempts' => $total,
            'qr_success' => $total > 0 ? round($qrPass / $total * 100, 2) : 0,
            'gps_success' => $total > 0 ? round($gpsPass / $total * 100, 2) : 0,
            'face_success' => $total > 0 ? round($facePass / $total * 100, 2) : 0,
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function range(Request $request): array
    {
        return [
            $this->boundary($request->input('from'), endOfDay: false),
            $this->boundary($request->input('to'), endOfDay: true),
        ];
    }

    /**
     * A plain date covers the whole day in the display timezone; a value with a time is used as given.
     */
    private function boundary(string $value, bool $endOfDay): Carbon
    {
        $time = Carbon::parse($value, config('app.display_timezone'));

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $time = $endOfDay ? $time->endOfDay() : $time->startOfDay();
        }

        return $time->utc();
    }
}
