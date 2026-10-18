{{-- Document and financial record deletion is available only in opened record detail. --}}
<footer {{ $attributes->class(['v65-record-delete-footer']) }} role="group" aria-label="Kayıt silme işlemi">
    {{ $slot }}
</footer>
