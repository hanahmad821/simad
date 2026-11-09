<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RoomController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['all', 'active', 'inactive'])],
        ]);
        $search = trim($filters['q'] ?? '');
        $status = $filters['status'] ?? 'all';
        $rooms = Room::with('schoolClasses:id,name,room_id')->withCount(['schoolClasses', 'schedules'])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', '%'.$search.'%')->orWhere('code', 'like', '%'.$search.'%')
                ->orWhere('building', 'like', '%'.$search.'%')))
            ->when($status !== 'all', fn ($query) => $query->where('is_active', $status === 'active'))
            ->orderBy('name')->paginate(20)->withQueryString();

        return view('admin.rooms.index', compact('rooms', 'search', 'status'));
    }

    public function create(): View
    {
        return view('admin.rooms.form', ['room' => new Room(['is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Room::create($this->validated($request));

        return to_route('admin.rooms.index')->with('status', 'Ruangan berhasil ditambahkan.');
    }

    public function edit(Room $room): View
    {
        return view('admin.rooms.form', compact('room'));
    }

    public function update(Request $request, Room $room): RedirectResponse
    {
        $room->update($this->validated($request, $room));

        return to_route('admin.rooms.index')->with('status', 'Ruangan berhasil diperbarui.');
    }

    private function validated(Request $request, ?Room $room = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('rooms', 'code')->ignore($room?->id)],
            'name' => ['required', 'string', 'max:100'],
            'building' => ['nullable', 'string', 'max:100'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    public function destroy(Room $room): RedirectResponse
    {
        DB::transaction(function () use ($room) {
            $room = Room::whereKey($room->id)->lockForUpdate()->firstOrFail();
            if ($room->schoolClasses()->exists() || $room->schedules()->exists()) {
                throw ValidationException::withMessages(['room' => 'Ruangan sudah digunakan oleh kelas atau jadwal. Ubah status menjadi nonaktif jika ruangan tidak digunakan lagi.']);
            }
            $room->delete();
        });

        return to_route('admin.rooms.index')->with('status', 'Ruangan berhasil dihapus.');
    }
}
