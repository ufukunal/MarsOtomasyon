@props([
    'title' => null,
    'pageTitle' => null,
    'pageDescription' => null,
])

@include('layouts.app', [
    'title' => $title,
    'pageTitle' => $pageTitle,
    'pageDescription' => $pageDescription,
    'slot' => $slot,
])
