<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Business;
use App\Models\ProcessedEmail;
use App\Models\WithdrawalRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StatsController extends Controller
{
    public function index(Request $request)
    {
        $period = $request->get('period', 'daily'); // daily, monthly, yearly
        
        $stats = $this->getStats($period);
        
        return view('admin.stats.index', compact('stats', 'period'));
    }
    
    private function getStats($period)
    {
        $stats = [];
        
        switch ($period) {
            case 'daily':
                $stats = $this->getDailyStats();
                break;
            case 'monthly':
                $stats = $this->getMonthlyStats();
                break;
            case 'yearly':
                $stats = $this->getYearlyStats();
                break;
        }
        
        return $stats;
    }
    
    private function getDailyStats()
    {
        $today = now()->startOfDay();
        $last30Days = now()->subDays(30)->startOfDay();
        
        // Daily amounts for chart (last 30 days)
        $dailyAmounts = Payment::where('status', Payment::STATUS_APPROVED)
            ->where('created_at', '>=', $last30Days)
            ->selectRaw('DATE(created_at) as date, SUM(COALESCE(received_amount, amount)) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => Carbon::parse($item->date)->format('M d'),
                    'amount' => (float) $item->total,
                ];
            });
        
        // Daily transaction counts
        $dailyCounts = Payment::where('created_at', '>=', $last30Days)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => Carbon::parse($item->date)->format('M d'),
                    'count' => (int) $item->count,
                ];
            });
        
        return [
            'period' => 'daily',
            'summary' => [
                'total_amount' => Payment::where('status', Payment::STATUS_APPROVED)
                    ->where('created_at', '>=', $last30Days)
                    ->sum(DB::raw('COALESCE(received_amount, amount)')),
                'total_transactions' => Payment::where('created_at', '>=', $last30Days)->count(),
                'approved_transactions' => Payment::where('status', Payment::STATUS_APPROVED)
                    ->where('created_at', '>=', $last30Days)
                    ->count(),
                'pending_transactions' => Payment::where('status', Payment::STATUS_PENDING)
                    ->where('created_at', '>=', $last30Days)
                    ->count(),
                'average_daily' => Payment::where('status', Payment::STATUS_APPROVED)
                    ->where('created_at', '>=', $last30Days)
                    ->selectRaw('AVG(COALESCE(received_amount, amount)) as avg')
                    ->value('avg') ?: 0,
            ],
            'today' => [
                'amount' => Payment::where('status', Payment::STATUS_APPROVED)
                    ->whereDate('created_at', $today)
                    ->sum(DB::raw('COALESCE(received_amount, amount)')),
                'transactions' => Payment::whereDate('created_at', $today)->count(),
                'approved' => Payment::where('status', Payment::STATUS_APPROVED)
                    ->whereDate('created_at', $today)
                    ->count(),
                'pending' => Payment::where('status', Payment::STATUS_PENDING)
                    ->whereDate('created_at', $today)
                    ->count(),
            ],
            'chart_data' => [
                'amounts' => $dailyAmounts,
                'counts' => $dailyCounts,
            ],
            'top_businesses' => $this->getTopBusinesses($last30Days),
            'recent_payments' => Payment::with('business')
                ->where('created_at', '>=', $last30Days)
                ->latest()
                ->limit(10)
                ->get(),
        ];
    }
    
    private function getMonthlyStats()
    {
        $last12Months = now()->subMonths(12)->startOfMonth();
        
        // Monthly amounts for chart
        $monthlyAmounts = Payment::where('status', Payment::STATUS_APPROVED)
            ->where('created_at', '>=', $last12Months)
            ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, SUM(COALESCE(received_amount, amount)) as total')
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => Carbon::create($item->year, $item->month, 1)->format('M Y'),
                    'amount' => (float) $item->total,
                ];
            });
        
        // Monthly transaction counts
        $monthlyCounts = Payment::where('created_at', '>=', $last12Months)
            ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, COUNT(*) as count')
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => Carbon::create($item->year, $item->month, 1)->format('M Y'),
                    'count' => (int) $item->count,
                ];
            });
        
        $currentMonth = now()->startOfMonth();
        
        return [
            'period' => 'monthly',
            'summary' => [
                'total_amount' => Payment::where('status', Payment::STATUS_APPROVED)
                    ->where('created_at', '>=', $last12Months)
                    ->sum(DB::raw('COALESCE(received_amount, amount)')),
                'total_transactions' => Payment::where('created_at', '>=', $last12Months)->count(),
                'approved_transactions' => Payment::where('status', Payment::STATUS_APPROVED)
                    ->where('created_at', '>=', $last12Months)
                    ->count(),
                'average_monthly' => Payment::where('status', Payment::STATUS_APPROVED)
                    ->where('created_at', '>=', $last12Months)
                    ->selectRaw('AVG(COALESCE(received_amount, amount)) as avg')
                    ->value('avg') ?: 0,
                'average_transaction' => Payment::where('status', Payment::STATUS_APPROVED)
                    ->where('created_at', '>=', $last12Months)
                    ->selectRaw('AVG(COALESCE(received_amount, amount)) as avg')
                    ->value('avg') ?: 0,
            ],
            'current_month' => [
                'amount' => Payment::where('status', Payment::STATUS_APPROVED)
                    ->where('created_at', '>=', $currentMonth)
                    ->sum(DB::raw('COALESCE(received_amount, amount)')),
                'transactions' => Payment::where('created_at', '>=', $currentMonth)->count(),
                'approved' => Payment::where('status', Payment::STATUS_APPROVED)
                    ->where('created_at', '>=', $currentMonth)
                    ->count(),
            ],
            'chart_data' => [
                'amounts' => $monthlyAmounts,
                'counts' => $monthlyCounts,
            ],
            'top_businesses' => $this->getTopBusinesses($last12Months),
            'recent_payments' => Payment::with('business')
                ->where('created_at', '>=', $last12Months)
                ->latest()
                ->limit(10)
                ->get(),
        ];
    }
    
    private function getYearlyStats()
    {
        $last5Years = now()->subYears(5)->startOfYear();
        
        // Yearly amounts for chart
        $yearlyAmounts = Payment::where('status', Payment::STATUS_APPROVED)
            ->where('created_at', '>=', $last5Years)
            ->selectRaw('YEAR(created_at) as year, SUM(COALESCE(received_amount, amount)) as total')
            ->groupBy('year')
            ->orderBy('year')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => (string) $item->year,
                    'amount' => (float) $item->total,
                ];
            });
        
        // Yearly transaction counts
        $yearlyCounts = Payment::where('created_at', '>=', $last5Years)
            ->selectRaw('YEAR(created_at) as year, COUNT(*) as count')
            ->groupBy('year')
            ->orderBy('year')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => (string) $item->year,
                    'count' => (int) $item->count,
                ];
            });
        
        $currentYear = now()->startOfYear();
        
        return [
            'period' => 'yearly',
            'summary' => [
                'total_amount' => Payment::where('status', Payment::STATUS_APPROVED)
                    ->where('created_at', '>=', $last5Years)
                    ->sum(DB::raw('COALESCE(received_amount, amount)')),
                'total_transactions' => Payment::where('created_at', '>=', $last5Years)->count(),
                'approved_transactions' => Payment::where('status', Payment::STATUS_APPROVED)
                    ->where('created_at', '>=', $last5Years)
                    ->count(),
                'average_yearly' => Payment::where('status', Payment::STATUS_APPROVED)
                    ->where('created_at', '>=', $last5Years)
                    ->selectRaw('AVG(COALESCE(received_amount, amount)) as avg')
                    ->value('avg') ?: 0,
                'average_transaction' => Payment::where('status', Payment::STATUS_APPROVED)
                    ->where('created_at', '>=', $last5Years)
                    ->selectRaw('AVG(COALESCE(received_amount, amount)) as avg')
                    ->value('avg') ?: 0,
            ],
            'current_year' => [
                'amount' => Payment::where('status', Payment::STATUS_APPROVED)
                    ->where('created_at', '>=', $currentYear)
                    ->sum(DB::raw('COALESCE(received_amount, amount)')),
                'transactions' => Payment::where('created_at', '>=', $currentYear)->count(),
                'approved' => Payment::where('status', Payment::STATUS_APPROVED)
                    ->where('created_at', '>=', $currentYear)
                    ->count(),
            ],
            'chart_data' => [
                'amounts' => $yearlyAmounts,
                'counts' => $yearlyCounts,
            ],
            'top_businesses' => $this->getTopBusinesses($last5Years),
            'recent_payments' => Payment::with('business')
                ->where('created_at', '>=', $last5Years)
                ->latest()
                ->limit(10)
                ->get(),
        ];
    }
    
    private function getTopBusinesses($since)
    {
        return Payment::where('status', Payment::STATUS_APPROVED)
            ->where('created_at', '>=', $since)
            ->join('businesses', 'payments.business_id', '=', 'businesses.id')
            ->select('businesses.id', 'businesses.name', DB::raw('SUM(COALESCE(payments.received_amount, payments.amount)) as total_amount'), DB::raw('COUNT(*) as transaction_count'))
            ->groupBy('businesses.id', 'businesses.name')
            ->orderByDesc('total_amount')
            ->limit(10)
            ->get();
    }
}
