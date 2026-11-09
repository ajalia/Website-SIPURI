@props(['bag'=>'default'])
@if($errors->getBag($bag)->any())<div class="alert alert-danger" role="alert"><strong>Periksa kembali isian Anda.</strong><ul class="mb-0">@foreach($errors->getBag($bag)->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
