@php
    $status = $status ?? '';
    $labels = [
        'pending' => 'معلق',
        'accepted' => 'مقبول',
        'rejected' => 'مرفوض',
        'assigned' => 'معين',
        'in_progress' => 'قيد التنفيذ',
        'completed' => 'مكتمل',
        'cancelled' => 'ملغي',
        'ready_for_pickup' => 'جاهز للاستلام',
        'transferred' => 'منقول',
        'delivered' => 'تم التسليم',
        'in' => 'إدخال',
        'out' => 'إخراج',
        'adjustment' => 'تعديل',
    ];
@endphp
<span class="badge-soft badge-{{ $status ?: 'neutral' }}">{{ $labels[$status] ?? $status }}</span>
