@if(session('success'))<div role="status" class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div role="alert" class="alert alert-danger"><strong>Periksa kembali isian Anda.</strong><ul class="mb-0 mt-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
