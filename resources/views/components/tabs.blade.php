@props(['tabs' => [], 'active' => null])
{{-- Keep existing callers, but use the accessible V65 tab system and keyboard handler. --}}
<x-v65.tabs :tabs="$tabs" :active="$active" />
