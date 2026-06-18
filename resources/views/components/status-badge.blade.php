@props(['active', 'labelActive' => 'activo', 'labelInactive' => 'inactivo'])
<span class="{{ $active
    ? 'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-50 text-green-700'
    : 'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-miga text-masa-madre' }}">
    {{ $active ? $labelActive : $labelInactive }}
</span>
