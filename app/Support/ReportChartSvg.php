<?php

namespace App\Support;

/**
 * Lightweight SVG bar charts for PDF reports (no external GD/chart library).
 */
class ReportChartSvg
{
    /**
     * @param  array<int, array{label: string, value: float|int}>  $series
     */
    public static function barChart(array $series, string $title, bool $isRtl): string
    {
        $series = array_values(array_filter($series, fn ($r) => is_array($r) && isset($r['label'])));
        if ($series === []) {
            return '';
        }

        $maxPoints = 18;
        if (count($series) > $maxPoints) {
            $step = (int) ceil(count($series) / $maxPoints);
            $sampled = [];
            foreach ($series as $i => $row) {
                if ($i % $step === 0) {
                    $sampled[] = $row;
                }
            }
            $series = $sampled;
        }

        $values = array_map(fn ($r) => (float) ($r['value'] ?? 0), $series);
        $max = max($values) > 0 ? max($values) : 1.0;
        $w = 720;
        $h = 140;
        $padL = $isRtl ? 48 : 56;
        $padR = $isRtl ? 56 : 48;
        $padT = 36;
        $padB = 32;
        $innerW = $w - $padL - $padR;
        $innerH = $h - $padT - $padB;
        $n = count($series);
        $gap = 4;
        $barW = $n > 0 ? max(4, ($innerW - $gap * ($n - 1)) / $n) : 4;

        $gradId = 'g'.substr(sha1($title), 0, 8);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$w.'" height="'.$h.'" viewBox="0 0 '.$w.' '.$h.'">';
        $svg .= '<defs><linearGradient id="'.$gradId.'" x1="0" y1="0" x2="0" y2="1">';
        $svg .= '<stop offset="0%" stop-color="#3b82f6"/><stop offset="100%" stop-color="#1d4ed8"/></linearGradient></defs>';
        $svg .= '<rect x="0" y="0" width="'.$w.'" height="'.$h.'" fill="#f8fafc" rx="6"/>';
        $escTitle = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $svg .= '<text x="'.($isRtl ? $w - 12 : 12).'" y="22" font-family="DejaVu Sans, Arial" font-size="11" font-weight="bold" fill="#0f172a" text-anchor="'.($isRtl ? 'end' : 'start').'">'.$escTitle.'</text>';

        foreach ($series as $i => $row) {
            $v = (float) ($row['value'] ?? 0);
            $bh = $innerH * ($v / $max);
            $x = $padL + $i * ($barW + $gap);
            $y = $padT + ($innerH - $bh);
            $svg .= '<rect x="'.round($x, 2).'" y="'.round($y, 2).'" width="'.round($barW, 2).'" height="'.round($bh, 2).'" fill="url(#'.$gradId.')" rx="2"/>';
            $rawLbl = trim((string) ($row['label'] ?? ''));
            $lbl = htmlspecialchars($rawLbl === '' ? '—' : $rawLbl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $lx = $x + $barW / 2;
            $svg .= '<text x="'.round($lx, 2).'" y="'.($h - 10).'" font-family="DejaVu Sans, Arial" font-size="6" fill="#64748b" text-anchor="middle">'.$lbl.'</text>';
        }

        $svg .= '</svg>';

        return $svg;
    }

    /**
     * @param  array<int, array{label: string, value: float|int}>  $series
     */
    public static function horizontalBars(array $series, string $title, bool $isRtl): string
    {
        $series = array_values(array_filter($series, fn ($r) => is_array($r) && isset($r['label'])));
        if ($series === []) {
            return '';
        }
        $series = array_slice($series, 0, 8);
        $values = array_map(fn ($r) => (float) ($r['value'] ?? 0), $series);
        $max = max($values) > 0 ? max($values) : 1.0;

        $w = 720;
        $rowH = 26;
        $h = 48 + count($series) * $rowH;
        $labelW = $isRtl ? 220 : 220;
        $barX = $isRtl ? 28 : $labelW + 28;
        $maxBarW = $w - $barX - 80;

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$w.'" height="'.$h.'" viewBox="0 0 '.$w.' '.$h.'">';
        $svg .= '<rect x="0" y="0" width="'.$w.'" height="'.$h.'" fill="#f8fafc" rx="6"/>';
        $escTitle = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $svg .= '<text x="'.($isRtl ? $w - 12 : 12).'" y="22" font-family="DejaVu Sans, Arial" font-size="11" font-weight="bold" fill="#0f172a" text-anchor="'.($isRtl ? 'end' : 'start').'">'.$escTitle.'</text>';

        foreach ($series as $i => $row) {
            $v = (float) ($row['value'] ?? 0);
            $bw = $maxBarW * ($v / $max);
            $y = 40 + $i * $rowH;
            $rawLbl = trim((string) ($row['label'] ?? ''));
            $slice = $rawLbl === '' ? '—' : mb_substr($rawLbl, 0, 36);
            $lbl = htmlspecialchars($slice, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $lx = $isRtl ? $w - 32 : 16;
            $anchor = $isRtl ? 'end' : 'start';
            $svg .= '<text x="'.$lx.'" y="'.($y + 14).'" font-family="DejaVu Sans, Arial" font-size="7" fill="#334155" text-anchor="'.$anchor.'">'.$lbl.'</text>';
            $rx = $isRtl ? ($w - 32 - $bw) : $barX;
            $svg .= '<rect x="'.round($rx, 2).'" y="'.($y + 2).'" width="'.round($bw, 2).'" height="16" fill="#2563eb" rx="3"/>';
            $valStr = number_format($v, $v >= 100 ? 0 : 2);
            $svg .= '<text x="'.($isRtl ? $rx - 6 : $rx + $bw + 6).'" y="'.($y + 14).'" font-family="DejaVu Sans, Arial" font-size="7" fill="#0f172a" text-anchor="'.($isRtl ? 'end' : 'start').'">'.$valStr.'</text>';
        }
        $svg .= '</svg>';

        return $svg;
    }
}
