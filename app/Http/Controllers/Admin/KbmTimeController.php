<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KbmDayPattern;
use App\Services\KbmTimeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KbmTimeController extends Controller
{
    public function index(Request $request, KbmTimeService $service): View
    {
        $data = $request->validate(['day' => ['nullable', 'integer', 'between:1,6']]);
        $day = (int) ($data['day'] ?? 1);
        $patterns = KbmDayPattern::orderBy('day_of_week')->get()->keyBy('day_of_week');
        $pattern = $patterns->get($day);
        $values = $pattern ? [
            'morning_start' => substr($pattern->morning_start, 0, 5), 'morning_end' => substr($pattern->morning_end, 0, 5),
            'teaching_periods' => $pattern->teaching_periods, 'breaks' => $pattern->breaks,
        ] : $service->defaults($day);
        $slots = $service->slots($values);
        $days = KbmTimeService::DAYS;

        return view('admin.kbm-times.index', compact('day', 'days', 'patterns', 'values', 'slots'));
    }

    public function update(Request $request, KbmTimeService $service): RedirectResponse
    {
        $data = $request->validate(['days' => ['required', 'array', 'min:1'], 'days.*' => ['required', 'integer', 'between:1,6']]);
        $service->save($request->user(), $data['days'], $request->only(['morning_start', 'morning_end', 'teaching_periods']) + ['breaks' => $request->input('breaks', [])]);

        return to_route('admin.kbm-times.index', ['day' => array_values($data['days'])[0]])->with('status', 'Pola waktu KBM berhasil disimpan. Jadwal mengikuti jam pada hari masing-masing.');
    }
}
