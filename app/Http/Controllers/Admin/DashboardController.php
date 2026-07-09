<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Registration;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Response;

class DashboardController extends Controller
{
    public static string $name = 'dashboard';
    public static int $position = 0;

    public function index(Request $request): Response
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $currentEndDate = Carbon::parse($endDate ?? 'now')->endOfDay();
        $currentStartDate = $startDate ? Carbon::parse($startDate)->startOfDay() : null;
        $previousStartDate = null;
        $previousEndDate = null;

        if ($currentStartDate && $currentEndDate) {
            $durationInDays = $currentStartDate->diffInDays($currentEndDate);
            $previousEndDate = $currentStartDate->copy()->subDay()->endOfDay();
            $previousStartDate = $previousEndDate->copy()->subDays($durationInDays)->startOfDay();
        }

        return inertia('Admin/Dashboard', [
            'stats' => [
                'revenue' => $this->getRevenue($currentStartDate, $currentEndDate, $previousStartDate, $previousEndDate),
                'users' => $this->getUsers($currentStartDate, $currentEndDate, $previousStartDate, $previousEndDate),
                'verifiedTeams' => $this->getVerifiedTeams($currentStartDate, $currentEndDate, $previousStartDate, $previousEndDate),
            ],
            'revenuePerMonth' => $this->getRevenuePerMonth($currentStartDate, $currentEndDate),
            'usersPerMonth' => $this->getUsersPerMonth($currentStartDate, $currentEndDate),
        ]);
    }

    private function getRevenue(?Carbon $currentStartDate, ?Carbon $currentEndDate, ?Carbon $previousStartDate, ?Carbon $previousEndDate): array
    {
        $query = fn($startDate, $endDate) => Registration::where('status', 'approved')
            ->when($startDate, fn($query) => $query->whereDate('submitted_at', '>=', $startDate))
            ->when($endDate, fn($query) => $query->whereDate('submitted_at', '<=', $endDate))
            ->sum('price_at_registration');
        return [
            'current' => $query($currentStartDate, $currentEndDate),
            'previous' => isset($previousStartDate, $previousEndDate) ?
                $query($previousStartDate, $previousEndDate) :
                null,
        ];
    }

    private function getUsers(?Carbon $currentStartDate, ?Carbon $currentEndDate, ?Carbon $previousStartDate, ?Carbon $previousEndDate): array
    {
        $query = fn($startDate, $endDate) => User::when($startDate, fn($query) => $query->whereDate('users.created_at', '>=', $startDate))
            ->when($endDate, fn($query) => $query->whereDate('users.created_at', '<=', $endDate))
            ->count();
        return [
            'current' => $query($currentStartDate, $currentEndDate),
            'previous' => isset($previousStartDate, $previousEndDate) ?
                $query($previousStartDate, $previousEndDate) :
                null,
        ];
    }

    private function getVerifiedTeams(?Carbon $currentStartDate, ?Carbon $currentEndDate, ?Carbon $previousStartDate, ?Carbon $previousEndDate): array
    {
        $query = fn($startDate, $endDate) => Team::whereHas('registrations', function ($query) use ($startDate, $endDate) {
            $query->where('status', 'approved')
                ->when($startDate, fn($q) => $q->where('submitted_at', '>=', $startDate))
                ->when($endDate, fn($q) => $q->where('submitted_at', '<=', $endDate));
        })->count();
        return [

            'current' => $query($currentStartDate, $currentEndDate),
            'previous' => isset($previousStartDate, $previousEndDate) ?
                $query($previousStartDate, $previousEndDate) :
                null,
        ];
    }

    private function getRevenuePerMonth(?Carbon $startDate, ?Carbon $endDate): array
    {
        $data = Registration::query()
            ->where('status', 'approved')
            ->when($startDate, fn($query) => $query->where('submitted_at', '>=', $startDate))
            ->when($endDate, fn($query) => $query->where('submitted_at', '<=', $endDate))
            ->selectRaw("DATE_FORMAT(submitted_at, '%b %Y') as month, SUM(price_at_registration) as total")
            ->groupBy('month')
            ->orderByRaw('MIN(submitted_at)')
            ->get();

        return [
            'labels' => $data->pluck('month'),
            'dataPoints' => $data->pluck('total'),
        ];
    }

    private function getUsersPerMonth(?Carbon $startDate, ?Carbon $endDate): array
    {
        $data = User::query()
            ->when($startDate, fn($query) => $query->where('created_at', '>=', $startDate))
            ->when($endDate, fn($query) => $query->where('created_at', '<=', $endDate))
            ->selectRaw("DATE_FORMAT(created_at, '%b %Y') as month, COUNT(id) as total")
            ->groupBy('month')
            ->orderByRaw('MIN(created_at)')
            ->get();

        return [
            'labels' => $data->pluck('month'),
            'dataPoints' => $data->pluck('total'),
        ];
    }
}
