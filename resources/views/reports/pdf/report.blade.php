<!DOCTYPE html>
<html dir="{{ ($filters['language'] ?? 'ar') === 'ar' ? 'rtl' : 'ltr' }}" lang="{{ $filters['language'] ?? 'ar' }}">
<head>
    <meta charset="UTF-8">
    <title>{{ ($filters['language'] ?? 'ar') === 'ar' ? 'تقرير' : 'Report' }} - {{ $reportType }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; padding: 20px; }
        h1 { font-size: 18px; border-bottom: 1px solid #333; padding-bottom: 8px; }
        h2 { font-size: 14px; margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: {{ ($filters['language'] ?? 'ar') === 'ar' ? 'right' : 'left' }}; }
        th { background: #f5f5f5; font-weight: bold; }
        .summary { margin: 15px 0; padding: 10px; background: #f9f9f9; border-radius: 4px; }
        .summary p { margin: 4px 0; }
        .meta { color: #666; font-size: 10px; margin-top: 20px; }
    </style>
</head>
<body>
    <h1>{{ ($filters['language'] ?? 'ar') === 'ar' ? 'تقرير' : 'Report' }}: {{ $reportType }}</h1>
    <p class="meta">{{ ($filters['language'] ?? 'ar') === 'ar' ? 'تاريخ الإنشاء' : 'Generated at' }}: {{ $generated_at->format('Y-m-d H:i') }}</p>
    @if(!empty($filters['from']) || !empty($filters['to']))
    <p class="meta">
        {{ ($filters['language'] ?? 'ar') === 'ar' ? 'الفترة' : 'Period' }}:
        {{ $filters['from'] ?? '-' }} {{ ($filters['language'] ?? 'ar') === 'ar' ? 'إلى' : 'to' }} {{ $filters['to'] ?? '-' }}
    </p>
    @endif

    @if(!empty($data['summary']))
    <div class="summary">
        <h2>{{ ($filters['language'] ?? 'ar') === 'ar' ? 'ملخص' : 'Summary' }}</h2>
        @foreach($data['summary'] as $key => $value)
            @if(is_scalar($value))
            <p><strong>{{ $key }}:</strong> {{ is_numeric($value) ? number_format((float)$value, 2) : $value }}</p>
            @endif
        @endforeach
    </div>
    @endif

    @if(!empty($data['data']) && (is_array($data['data']) || $data['data'] instanceof \Illuminate\Support\Collection))
    <h2>{{ ($filters['language'] ?? 'ar') === 'ar' ? 'البيانات' : 'Data' }}</h2>
    @php
        $rows = is_array($data['data']) ? $data['data'] : $data['data']->all();
        $first = $rows[0] ?? null;
        $cols = [];
        if ($first !== null) {
            $attrs = is_object($first) && method_exists($first, 'getAttributes') ? $first->getAttributes() : (array) $first;
            $cols = array_values(array_diff(array_keys($attrs), ['password', 'remember_token', 'email_verified_at', 'metadata']));
        }
    @endphp
    <table>
        <thead>
            <tr>
                @if(!empty($cols))
                    @foreach($cols as $col)
                        <th>{{ $col }}</th>
                    @endforeach
                @else
                    <th>{{ ($filters['language'] ?? 'ar') === 'ar' ? 'لا توجد سجلات' : 'No records' }}</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
            <tr>
                @php $arr = is_object($row) && method_exists($row, 'getAttributes') ? $row->getAttributes() : (array) $row; @endphp
                @foreach($cols as $col)
                    @php $v = $arr[$col] ?? null; @endphp
                    <td>
                        @if($v instanceof \DateTimeInterface) {{ $v->format('Y-m-d H:i') }}
                        @elseif(is_array($v) || is_object($v)) —
                        @else {{ is_string($v) ? \Str::limit($v, 50) : ($v ?? '—') }}
                        @endif
                    </td>
                @endforeach
            </tr>
            @endforeach
        </tbody>
    </table>
    @elseif(!empty($data['data']) && is_array($data['data']) && !isset($data['data'][0]) && (isset($data['data']['total_orders']) || isset($data['data']['total_users'])))
    <div class="summary">
        @foreach($data['data'] as $k => $v)
            @if(is_scalar($v))<p><strong>{{ $k }}:</strong> {{ $v }}</p>@endif
        @endforeach
    </div>
    @endif
</body>
</html>
