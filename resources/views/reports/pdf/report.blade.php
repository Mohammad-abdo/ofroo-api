@php
    $lang = $filters['language'] ?? 'ar';
    $isAr = $lang === 'ar';
    $chartBlocks = isset($chartBlocks) && is_array($chartBlocks) ? $chartBlocks : [];
    $entityMeta = isset($entityMeta) && is_array($entityMeta) ? $entityMeta : [];
    $heroAr = $entityMeta['title_ar'] ?? ($reportTitles['ar'] ?? 'تقرير');
    $heroEn = $entityMeta['title_en'] ?? ($reportTitles['en'] ?? 'Report');
    $maxRows = 500;
    $headers = $table['headers'] ?? [];
    $rows = $table['rows'] ?? [];
    $mode = $table['mode'] ?? 'tabular';
    $totalBodyRows = count($rows);
    if ($totalBodyRows > $maxRows) {
        $rows = array_slice($rows, 0, $maxRows);
    }
    $colCount = max(count($headers), 1);
    $colPct = round(100 / $colCount, 3);
@endphp
<!DOCTYPE html>
<html dir="{{ $isAr ? 'rtl' : 'ltr' }}" lang="{{ $lang }}">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>{{ $branding['app_name'] ?? 'OFROO' }} — {{ $reportTitles['en'] ?? $reportType }}</title>
    <style>
        /* Keep PDF CSS mPDF-safe: @page size, universal *, linear-gradient, overflow:hidden,
           and hyphens:auto are known to inflate page count or hide content in mPDF 8. */
        body {
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            font-size: 8.5px;
            color: #0f172a;
            line-height: 1.35;
            margin: 0;
            padding: 0;
        }
        .hero {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 14px;
            margin-bottom: 10px;
        }
        .hero-top {
            width: 100%;
            border-collapse: collapse;
        }
        .hero-top td { vertical-align: middle; padding: 2px 6px; }
        .logo-cell { width: 110px; }
        /* mPDF can ignore max-height/max-width on data-uri images; force a fixed height. */
        .logo-cell img { height: 34px; width: auto; max-width: 80px; display: block; }
        .brand-badge {
            display: inline-block;
            background: #2563eb;
            color: #fff;
            font-size: 8px;
            font-weight: bold;
            padding: 3px 10px;
            border-radius: 999px;
            letter-spacing: 0.04em;
        }
        .title-dual {
            margin: 0 0 4px 0;
            font-size: 15px;
            font-weight: bold;
            color: #0f172a;
        }
        .title-dual .ar { display: block; direction: rtl; unicode-bidi: embed; text-align: {{ $isAr ? 'right' : 'center' }}; }
        .title-dual .en { display: block; direction: ltr; unicode-bidi: embed; text-align: {{ $isAr ? 'center' : 'left' }}; font-size: 11px; color: #64748b; font-weight: 600; margin-top: 2px; }
        .meta-grid {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px dashed #e2e8f0;
            font-size: 8px;
            color: #64748b;
        }
        .meta-grid strong { color: #334155; }
        .section-label {
            font-size: 10px;
            font-weight: bold;
            color: #1e40af;
            margin: 10px 0 6px 0;
            padding-bottom: 3px;
            border-bottom: 2px solid #2563eb;
            display: inline-block;
        }
        table.summary-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px;
            margin-bottom: 8px;
        }
        table.summary-table td.sg-cell {
            width: 25%;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 8px 10px;
            vertical-align: top;
        }
        table.summary-table .sg-label {
            font-size: 7.5px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            margin-bottom: 2px;
        }
        table.summary-table .sg-value {
            font-size: 10px;
            font-weight: bold;
            color: #0f172a;
            word-break: break-word;
        }
        .wrap-table {
            width: 100%;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
        }
        table.data-grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 7px;
        }
        table.data-grid th {
            background: #1e40af;
            color: #fff;
            font-weight: bold;
            padding: 5px 4px;
            text-align: {{ $isAr ? 'right' : 'left' }};
            word-wrap: break-word;
            overflow-wrap: anywhere;
            border: 1px solid #1e3a8a;
            font-size: 7px;
            line-height: 1.2;
        }
        table.data-grid td {
            padding: 4px 4px;
            border: 1px solid #e2e8f0;
            vertical-align: top;
            text-align: {{ $isAr ? 'right' : 'left' }};
            word-wrap: break-word;
            overflow-wrap: anywhere;
            word-break: break-word;
            line-height: 1.25;
        }
        table.data-grid tbody tr:nth-child(even) td { background: #f1f5f9; }
        .empty-state {
            padding: 14px;
            text-align: center;
            color: #64748b;
            font-size: 9px;
            background: #fff;
        }
        .note {
            font-size: 7.5px;
            color: #64748b;
            margin-top: 8px;
            padding: 6px 8px;
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 4px;
        }
        .footer {
            margin-top: 12px;
            font-size: 7px;
            color: #94a3b8;
            text-align: center;
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
        }
        .footer span[dir="rtl"] { font-family: DejaVu Sans, Arial, sans-serif; }
        .footer span[dir="ltr"] { font-family: DejaVu Sans, Arial, sans-serif; }
        .subtitle-dual { font-size: 8px; color: #64748b; margin: 4px 0 0 0; }
        .subtitle-dual .ar { display: block; direction: rtl; text-align: {{ $isAr ? 'right' : 'center' }}; }
        .subtitle-dual .en { display: block; direction: ltr; text-align: {{ $isAr ? 'center' : 'left' }}; margin-top: 2px; }
        .tag-inline { display: inline-block; margin: 4px 6px 0 0; padding: 2px 8px; border-radius: 6px; font-size: 7px; background: #e0e7ff; color: #1e3a8a; }
        .entity-cover { max-height: 72px; max-width: 96px; border-radius: 6px; border: 1px solid #e2e8f0; object-fit: cover; display: block; }
        .chart-wrap { margin: 8px 0; text-align: center; page-break-inside: avoid; }
        .chart-wrap svg { max-width: 100%; height: auto; }
        .accent-bar { height: 4px; background: #2563eb; border-radius: 4px; margin-bottom: 8px; }
    </style>
</head>
<body>

    <div class="accent-bar"></div>
    <div class="hero">
        <table class="hero-top">
            <tr>
                <td class="logo-cell">
                    @if(!empty($branding['logo_data_uri']))
                        <img src="{{ $branding['logo_data_uri'] }}" alt="" height="34">
                    @else
                        <span class="brand-badge">OFROO</span>
                    @endif
                </td>
                <td>
                    <div class="title-dual">
                        <span class="ar" lang="ar" dir="rtl">{{ $heroAr }}</span>
                        <span class="en" lang="en" dir="ltr">{{ $heroEn }}</span>
                    </div>
                    @if(!empty($entityMeta['subtitle_ar']) || !empty($entityMeta['subtitle_en']))
                        <div class="subtitle-dual">
                            <span class="ar" lang="ar" dir="rtl">{{ $entityMeta['subtitle_ar'] ?? '' }}</span>
                            <span class="en" lang="en" dir="ltr">{{ $entityMeta['subtitle_en'] ?? '' }}</span>
                        </div>
                    @endif
                    @if(!empty($entityMeta['tags']) && is_array($entityMeta['tags']))
                        <div style="margin-top:6px;">
                            @foreach($entityMeta['tags'] as $tag)
                                <span class="tag-inline">{{ $isAr ? ($tag['ar'] ?? '') : ($tag['en'] ?? '') }}: {{ $tag['value'] ?? '' }}</span>
                            @endforeach
                        </div>
                    @endif
                    <span class="brand-badge">{{ strtoupper($reportType) }}</span>
                    <div class="meta-grid">
                        <strong>{{ $isAr ? 'المنصة' : 'Platform' }}:</strong> {{ $branding['app_name'] ?? 'OFROO Admin' }}
                        &nbsp;·&nbsp;
                        <strong>{{ $isAr ? 'أنشئ في' : 'Generated' }}:</strong> {{ $generated_at->format('Y-m-d H:i') }}
                        @if(!empty($filters['from']) || !empty($filters['to']))
                            &nbsp;·&nbsp;
                            <strong>{{ $isAr ? 'الفترة' : 'Period' }}:</strong>
                            {{ $filters['from'] ?? '—' }} → {{ $filters['to'] ?? '—' }}
                        @endif
                    </div>
                </td>
                <td style="width:100px;vertical-align:middle;text-align:center;">
                    @if(!empty($entityMeta['image_data_uri']))
                        <img src="{{ $entityMeta['image_data_uri'] }}" class="entity-cover" alt="cover">
                    @else
                        &nbsp;
                    @endif
                </td>
            </tr>
        </table>
    </div>

    @if(!empty($summaryBlocks))
        <div class="section-label">{{ $isAr ? 'ملخص' : 'Summary' }} / {{ $isAr ? 'Summary' : 'ملخص' }}</div>
        <table class="summary-table" width="100%" cellpadding="0" cellspacing="0">
            @foreach(array_chunk($summaryBlocks, 4) as $chunk)
                <tr>
                    @foreach($chunk as $block)
                        <td class="sg-cell" width="25%">
                            <div class="sg-label">{{ ($block['label'] ?? '') === '' ? '—' : $block['label'] }}</div>
                            <div class="sg-value">{{ ($block['value'] ?? '') === '' ? '—' : $block['value'] }}</div>
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </table>
    @endif

    @if(!empty($chartBlocks))
        <div class="section-label">{{ $isAr ? 'الرسوم البيانية' : 'Charts' }} / {{ $isAr ? 'Charts' : 'الرسوم البيانية' }}</div>
        @foreach($chartBlocks as $block)
            @if(!empty($block['svg']))
                <div class="chart-wrap">{!! $block['svg'] !!}</div>
            @endif
        @endforeach
    @endif

    <div class="section-label">{{ $isAr ? 'البيانات' : 'Data' }} / {{ $isAr ? 'Data' : 'البيانات' }}</div>
    <div class="wrap-table">
        @if($mode === 'metrics' && count($headers) >= 2)
            <table class="data-grid">
                <colgroup>
                    @foreach($headers as $_)
                        <col style="width: {{ $colPct }}%;" />
                    @endforeach
                </colgroup>
                <thead>
                    <tr>
                        @foreach($headers as $h)
                            <th>{{ ($h === '' || $h === null) ? '—' : $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $r)
                        <tr>
                            @foreach($r as $cell)
                                <td>{{ ($cell === '' || $cell === null) ? '—' : $cell }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($headers) }}" class="empty-state">{{ $isAr ? 'لا توجد بيانات في هذه الفترة.' : 'No data for this period.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        @elseif(count($headers) > 0 && count($rows) > 0)
            <table class="data-grid">
                <colgroup>
                    @foreach($headers as $_)
                        <col style="width: {{ $colPct }}%;" />
                    @endforeach
                </colgroup>
                <thead>
                    <tr>
                        @foreach($headers as $h)
                            <th>{{ ($h === '' || $h === null) ? '—' : $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $r)
                        <tr>
                            @foreach($r as $cell)
                                <td>{{ ($cell === '' || $cell === null) ? '—' : $cell }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="empty-state">
                {{ $isAr ? 'لا توجد صفوف للعرض. جرّب توسيع نطاق التاريخ أو تحقق من الفلاتر.' : 'No rows to display. Try widening the date range or adjusting filters.' }}
            </div>
        @endif
    </div>

    @if($totalBodyRows > $maxRows)
        <p class="note">
            {{ $isAr ? 'عرض أول' : 'Showing first' }} {{ $maxRows }} {{ $isAr ? 'صفاً من أصل' : 'rows out of' }} {{ $totalBodyRows }}.
            {{ $isAr ? 'صدّر Excel لاستخراج كامل البيانات.' : 'Export Excel for the full dataset.' }}
        </p>
    @endif

    <div class="footer">
        <div dir="rtl" style="margin-bottom:3px;">{{ $branding['app_name'] ?? 'OFROO' }} — مستند سري · للاستخدام الإداري فقط</div>
        <div dir="ltr">{{ $branding['app_name'] ?? 'OFROO' }} · Confidential · Administrative use only</div>
    </div>
</body>
</html>
