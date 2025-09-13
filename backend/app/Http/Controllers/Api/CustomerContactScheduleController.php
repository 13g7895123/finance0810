<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerContactSchedule;
use App\Models\Customer;
use Illuminate\Http\Request;
use Carbon\Carbon;

class CustomerContactScheduleController extends Controller
{
    public function index(Request $request)
    {
        $query = CustomerContactSchedule::with(['customer', 'user']);

        // 根據使用者權限過濾資料
        if (!auth()->user()->hasRole(['admin', 'executive'])) {
            $query->where('user_id', auth()->id());
        }

        // 日期篩選
        if ($request->has('date')) {
            $query->forDate($request->date);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->forDateRange($request->start_date, $request->end_date);
        }

        // 月份檢視（行事曆用）
        if ($request->has('month') && $request->has('year')) {
            $startDate = Carbon::create($request->year, $request->month, 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();
            $query->forDateRange($startDate->toDateString(), $endDate->toDateString());
        }

        // 狀態篩選
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $schedules = $query->orderBy('scheduled_date', 'asc')
                          ->orderBy('scheduled_time', 'asc')
                          ->get();

        return response()->json([
            'success' => true,
            'data' => $schedules
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'scheduled_date' => 'required|date',
            'scheduled_time' => 'nullable|date_format:H:i:s',
            'contact_type' => 'required|in:phone,line,email,meeting,other',
            'priority' => 'required|in:high,medium,low',
            'notes' => 'nullable|string',
        ]);

        $validated['user_id'] = auth()->id();
        $validated['status'] = CustomerContactSchedule::STATUS_SCHEDULED;

        $schedule = CustomerContactSchedule::create($validated);

        return response()->json([
            'success' => true,
            'message' => '聯絡計畫已建立',
            'data' => $schedule->load(['customer', 'user'])
        ]);
    }

    public function show($id)
    {
        $schedule = CustomerContactSchedule::with(['customer', 'user'])->findOrFail($id);

        // 權限檢查
        if (!auth()->user()->hasRole(['admin', 'executive']) && $schedule->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => '無權限查看此聯絡計畫'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $schedule
        ]);
    }

    public function update(Request $request, $id)
    {
        $schedule = CustomerContactSchedule::findOrFail($id);

        // 權限檢查
        if (!auth()->user()->hasRole(['admin', 'executive']) && $schedule->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => '無權限修改此聯絡計畫'
            ], 403);
        }

        $validated = $request->validate([
            'scheduled_date' => 'sometimes|date',
            'scheduled_time' => 'sometimes|nullable|date_format:H:i:s',
            'contact_type' => 'sometimes|in:phone,line,email,meeting,other',
            'priority' => 'sometimes|in:high,medium,low',
            'notes' => 'sometimes|nullable|string',
            'status' => 'sometimes|in:scheduled,contacted,rescheduled,missed,completed',
        ]);

        $schedule->update($validated);

        return response()->json([
            'success' => true,
            'message' => '聯絡計畫已更新',
            'data' => $schedule->fresh(['customer', 'user'])
        ]);
    }

    public function markAsContacted(Request $request, $id)
    {
        $schedule = CustomerContactSchedule::findOrFail($id);

        // 權限檢查
        if (!auth()->user()->hasRole(['admin', 'executive']) && $schedule->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => '無權限修改此聯絡計畫'
            ], 403);
        }

        $schedule->markAsContacted($request->notes);

        return response()->json([
            'success' => true,
            'message' => '已標記為已聯絡',
            'data' => $schedule->fresh(['customer', 'user'])
        ]);
    }

    public function reschedule(Request $request, $id)
    {
        $schedule = CustomerContactSchedule::findOrFail($id);

        // 權限檢查
        if (!auth()->user()->hasRole(['admin', 'executive']) && $schedule->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => '無權限修改此聯絡計畫'
            ], 403);
        }

        $validated = $request->validate([
            'new_date' => 'required|date',
            'new_time' => 'nullable|date_format:H:i:s',
            'notes' => 'nullable|string',
        ]);

        $schedule->reschedule($validated['new_date'], $validated['new_time'] ?? null, $validated['notes'] ?? null);

        return response()->json([
            'success' => true,
            'message' => '已改約到新時間',
            'data' => $schedule->fresh(['customer', 'user'])
        ]);
    }

    public function getOverdue()
    {
        $query = CustomerContactSchedule::with(['customer', 'user'])->overdue();

        if (!auth()->user()->hasRole(['admin', 'executive'])) {
            $query->where('user_id', auth()->id());
        }

        $overdueSchedules = $query->get();

        return response()->json([
            'success' => true,
            'data' => $overdueSchedules
        ]);
    }

    public function getToday()
    {
        $query = CustomerContactSchedule::with(['customer', 'user'])->today();

        if (!auth()->user()->hasRole(['admin', 'executive'])) {
            $query->where('user_id', auth()->id());
        }

        $todaySchedules = $query->get();

        return response()->json([
            'success' => true,
            'data' => $todaySchedules
        ]);
    }

    public function getNeedingReminder()
    {
        $query = CustomerContactSchedule::with(['customer', 'user'])->needingReminder();

        if (!auth()->user()->hasRole(['admin', 'executive'])) {
            $query->where('user_id', auth()->id());
        }

        $reminderSchedules = $query->get();

        return response()->json([
            'success' => true,
            'data' => $reminderSchedules
        ]);
    }

    public function getCalendarData(Request $request)
    {
        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:2030'
        ]);

        $startDate = Carbon::create($request->year, $request->month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $query = CustomerContactSchedule::with(['customer', 'user'])
                ->forDateRange($startDate->toDateString(), $endDate->toDateString());

        if (!auth()->user()->hasRole(['admin', 'executive'])) {
            $query->where('user_id', auth()->id());
        }

        $schedules = $query->get();

        // 按日期分組
        $calendarData = [];
        foreach ($schedules as $schedule) {
            $date = $schedule->scheduled_date->format('Y-m-d');
            if (!isset($calendarData[$date])) {
                $calendarData[$date] = [];
            }
            $calendarData[$date][] = $schedule;
        }

        return response()->json([
            'success' => true,
            'data' => $calendarData,
            'month' => $request->month,
            'year' => $request->year
        ]);
    }

    public function destroy($id)
    {
        $schedule = CustomerContactSchedule::findOrFail($id);

        // 權限檢查
        if (!auth()->user()->hasRole(['admin', 'executive']) && $schedule->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => '無權限刪除此聯絡計畫'
            ], 403);
        }

        $schedule->delete();

        return response()->json([
            'success' => true,
            'message' => '聯絡計畫已刪除'
        ]);
    }
}