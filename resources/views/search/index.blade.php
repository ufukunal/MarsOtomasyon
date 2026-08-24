@extends('layouts.app')

@section('title', 'Arama')

@section('app-content')
    <section class="search-workspace">
        <div class="page-actions">
            <div>
                <p class="eyebrow">Global Arama</p>
                <h1>Arama</h1>
            </div>
        </div>

        <form method="GET" action="{{ route('search') }}" class="global-search-form">
            <input
                type="search"
                name="q"
                value="{{ $query }}"
                minlength="2"
                maxlength="120"
                placeholder="Şube, kullanıcı veya rol ara…"
                aria-label="Global arama"
                autofocus
                data-dirty-ignore
            >
            <button type="submit" class="button-primary">Ara</button>
        </form>

        @if (mb_strlen($query) < 2)
            <div class="notice-info">Arama için en az 2 karakter girin.</div>
        @elseif ($results === [])
            <div class="notice-info">“{{ $query }}” için erişebildiğiniz kayıtlarda sonuç bulunamadı.</div>
        @else
            <div class="search-results" role="list">
                @foreach ($results as $result)
                    <a href="{{ $result['url'] }}" class="search-result" role="listitem" data-workspace-link>
                        <span class="search-result-type">{{ $result['type'] }}</span>
                        <strong>{{ $result['title'] }}</strong>
                        <small>{{ $result['subtitle'] }}</small>
                    </a>
                @endforeach
            </div>
        @endif
    </section>
@endsection
