@if(session('status'))<div role="status" class="rounded-lg bg-green-50 p-4 text-green-900">{{ session('status') }}</div>@endif
@if($errors->any())<div role="alert" class="rounded-lg bg-red-50 p-4 text-red-900"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
