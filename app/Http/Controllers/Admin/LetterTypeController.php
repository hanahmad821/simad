<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LetterType;
use App\Services\LetterTemplateCatalog;
use App\Services\LetterTypeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LetterTypeController extends Controller
{
    public function index(Request $request, LetterTemplateCatalog $catalog): View
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['all', 'active', 'inactive'])]]);
        $search = trim($data['q'] ?? '');
        $status = $data['status'] ?? 'all';
        $letterTypes = LetterType::withCount('outgoingLetters')
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('name', 'like', '%'.$search.'%')->orWhere('code', 'like', '%'.$search.'%')))
            ->when($status !== 'all', fn ($query) => $query->where('is_active', $status === 'active'))
            ->orderBy('name')->paginate(20)->withQueryString();

        return view('admin.letter-types.index', ['letterTypes' => $letterTypes, 'search' => $search, 'status' => $status, 'templates' => $catalog->all()]);
    }

    public function create(LetterTemplateCatalog $catalog): View
    {
        return view('admin.letter-types.form', ['letterType' => new LetterType(['is_active' => true]), 'templates' => $catalog->all()]);
    }

    public function edit(LetterType $letterType, LetterTemplateCatalog $catalog): View
    {
        return view('admin.letter-types.form', ['letterType' => $letterType, 'templates' => $catalog->all()]);
    }

    public function store(Request $request, LetterTypeService $service): RedirectResponse
    {
        $type = $service->save($request->user(), $request->all());

        return to_route('admin.letter-types.edit', $type)->with('status', 'Jenis surat berhasil ditambahkan.');
    }

    public function update(Request $request, LetterType $letterType, LetterTypeService $service): RedirectResponse
    {
        $service->save($request->user(), $request->all(), $letterType);

        return to_route('admin.letter-types.edit', $letterType)->with('status', 'Jenis surat berhasil diperbarui. Surat yang sudah dibuat tetap memakai pilihan template sebelumnya.');
    }

    public function destroy(Request $request, LetterType $letterType, LetterTypeService $service): RedirectResponse
    {
        $service->delete($request->user(), $letterType);

        return to_route('admin.letter-types.index')->with('status', 'Jenis surat berhasil dihapus.');
    }
}
