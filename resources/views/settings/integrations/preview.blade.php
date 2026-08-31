@extends('layouts.settings')

@section('title', 'Bildirim Önizleme')
@section('heading', 'Bildirim Önizleme')

@section('content')
    <p><a href="{{ route('settings.integrations.index') }}">← Entegrasyonlara dön</a></p>
    @if ($preview['subject'] !== null)
        <h2>{{ $preview['subject'] }}</h2>
    @endif
    <pre style="white-space:pre-wrap">{{ $preview['body'] }}</pre>
@endsection
