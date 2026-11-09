<x-layouts::app :title="$class->exists ? 'Pengaturan Rombel' : 'Tambah Rombel'"><div class="mx-auto max-w-3xl space-y-6"><flux:heading size="xl">{{ $class->exists ? 'Pengaturan Rombel' : 'Tambah Rombel' }}</flux:heading>@include('admin.classes.messages')
<form method="POST" action="{{ $class->exists ? route('admin.classes.update',$class) : route('admin.classes.store') }}" class="space-y-4">@csrf @if($class->exists) @method('PUT') @endif
<flux:card class="space-y-4">
<flux:select label="Tahun pelajaran" name="academic_year_id" required>@foreach($years as $year)<option value="{{ $year->id }}" @selected(old('academic_year_id',$class->academic_year_id)==$year->id) @disabled($class->exists && $class->academic_year_id != $year->id)>{{ $year->name }}</option>@endforeach</flux:select>
<div class="grid gap-4 md:grid-cols-2"><flux:input name="name" label="Nama rombel" :value="old('name',$class->name)" placeholder="X-A" required /><flux:select name="grade" label="Tingkat">@foreach([10,11,12] as $grade)<option value="{{ $grade }}" @selected(old('grade',$class->grade)==$grade)>{{ $grade }}</option>@endforeach</flux:select></div>
<flux:input name="major" label="Program / jurusan (opsional)" :value="old('major',$class->major)" />
<flux:select name="homeroom_teacher_id" label="Wali kelas"><option value="">Belum ditentukan</option>@foreach($teachers as $teacher)<option value="{{ $teacher->id }}" @selected(old('homeroom_teacher_id',$class->homeroom_teacher_id)==$teacher->id)>{{ $teacher->full_name }}</option>@endforeach</flux:select>
<flux:select name="room_id" label="Ruang kelas"><option value="">Belum ditentukan</option>@foreach($rooms as $room)<option value="{{ $room->id }}" @selected(old('room_id',$class->room_id)==$room->id)>{{ $room->name }}</option>@endforeach</flux:select>
<flux:input type="number" min="1" name="capacity" label="Kapasitas (kosong = tanpa batas)" :value="old('capacity',$class->capacity)" />
<flux:select name="is_active" label="Status rombel"><option value="1" @selected(old('is_active',$class->is_active)==1)>Aktif</option><option value="0" @selected(old('is_active',$class->is_active)==0)>Arsip</option></flux:select>
<flux:text>Rombel yang masih memiliki penempatan berlaku tidak dapat diarsipkan.</flux:text>
<div class="flex gap-3"><flux:button :href="route('admin.classes.index',['academic_year_id'=>$class->academic_year_id])">Kembali</flux:button><flux:button type="submit" variant="primary">Simpan Rombel</flux:button></div>
</flux:card></form>
@if($class->exists)<div class="flex justify-end">@include('admin.classes.delete')</div>@endif
</div></x-layouts::app>
