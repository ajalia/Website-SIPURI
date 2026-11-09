@props(['name', 'label', 'autocomplete'=>'new-password', 'placeholder'=>'Masukkan kata sandi', 'help'=>null])
<div class="stitch-field">
    <div class="d-flex justify-content-between align-items-baseline gap-2"><label for="{{ $name }}" class="form-label">{{ $label }} <span class="required" aria-hidden="true">*</span></label><span class="stitch-required">Wajib diisi</span></div>
    <div class="position-relative">
        <input class="form-control @error($name) is-invalid @enderror" id="{{ $name }}" name="{{ $name }}" type="password" placeholder="{{ $placeholder }}" required maxlength="128" autocomplete="{{ $autocomplete }}" @if($autocomplete==='new-password') minlength="8" @endif aria-describedby="{{ $name }}-help @error($name) {{ $name }}-error @enderror" @error($name) aria-invalid="true" @enderror>
        <button class="login-eye" type="button" data-password-target="{{ $name }}" data-icon-toggle aria-controls="{{ $name }}" aria-pressed="false" aria-label="Tampilkan {{ mb_strtolower($label) }}"><x-login-icon name="eye"/><span class="login-eye-slash" aria-hidden="true"></span></button>
    </div>
    <div id="{{ $name }}-help" class="form-text @if(!$help) visually-hidden @endif">{{ $help ?: $label }}</div>
    @error($name)<div class="field-error" id="{{ $name }}-error">{{ $message }}</div>@enderror
</div>
