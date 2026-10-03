@props(['title' => 'Emin misiniz?', 'message' => 'Bu işlem geri alınamaz.'])
<div class="confirm-box">
    <strong>{{ $title }}</strong>
    <p>{{ $message }}</p>
    {{ $slot }}
</div>
