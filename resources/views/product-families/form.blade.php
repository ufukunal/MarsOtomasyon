@extends('layouts.app')

@section('title', $family ? 'Ürün Ailesini Düzenle' : 'Yeni Ürün Ailesi')

@section('app-content')
<div class="page-header"><h1>{{ $family ? 'Ürün Ailesini Düzenle' : 'Yeni Ürün Ailesi' }}</h1></div>
@if ($errors->any())<div class="notice-error">{{ $errors->first() }}</div>@endif
<form class="panel" method="POST" action="{{ $family ? route('inventory.product-families.update', $family) : route('inventory.product-families.store') }}">
    @csrf
    @if ($family) @method('PUT') @endif
    <div class="form-grid">
        <label>Kod<input name="code" maxlength="64" required value="{{ old('code', $family?->code) }}"></label>
        <label>Ad<input name="name" maxlength="191" required value="{{ old('name', $family?->name) }}"></label>
    </div>
    <label>Paylaşılan içerik (JSON)
        <textarea name="shared_content" rows="8">{{ old('shared_content', $family?->shared_content ? json_encode($family->shared_content, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) : '') }}</textarea>
    </label>
    <button class="button-primary" type="submit">Kaydet</button>
</form>
@endsection
