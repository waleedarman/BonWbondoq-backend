@php
    $priority = $priority ?? '';
    $labels = ['low' => 'منخفضة', 'medium' => 'متوسطة', 'urgent' => 'عاجلة'];
    $classes = ['low' => 'badge-neutral', 'medium' => 'badge-assigned', 'urgent' => 'badge-cancelled'];
@endphp
<span class="badge-soft {{ $classes[$priority] ?? 'badge-neutral' }}">{{ $labels[$priority] ?? $priority }}</span>
