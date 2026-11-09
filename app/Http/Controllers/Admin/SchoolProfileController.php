<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolSetting;
use Illuminate\Http\Request;

class SchoolProfileController extends Controller
{
    public function edit()
    {
        return view('admin.school-profile', ['school' => SchoolSetting::first() ?? new SchoolSetting(['attendance_radius' => 100])]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'school_name' => ['required', 'string', 'max:150'],
            'nsm' => ['nullable', 'string', 'max:30'],
            'npsn' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:5000'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'headmaster_name' => ['nullable', 'string', 'max:255'],
            'headmaster_nip' => ['nullable', 'string', 'max:255'],
            'headmaster_rank' => ['nullable', 'string', 'max:255'],
            'headmaster_position' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'attendance_radius' => ['required', 'integer', 'min:1', 'max:100000'],
        ]);

        $school = SchoolSetting::first() ?? new SchoolSetting;
        $school->fill($data)->save();

        return to_route('admin.school-profile.edit')->with('status', 'Profil madrasah berhasil disimpan.');
    }
}
