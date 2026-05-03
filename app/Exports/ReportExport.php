<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class ReportExport implements FromCollection, ShouldAutoSize, WithCustomStartCell, WithEvents, WithHeadings, WithMapping
{
    protected array $data;

    protected string $reportType;

    protected array $filters;

    protected array $branding;

    public function __construct(array $data, string $reportType, array $filters = [], array $branding = [])
    {
        $this->data = $data;
        $this->reportType = $reportType;
        $this->filters = $filters;
        $this->branding = $branding;
    }

    public function startCell(): string
    {
        return 'A8';
    }

    public function collection()
    {
        return collect($this->data['data'] ?? []);
    }

    public function headings(): array
    {
        $type = in_array($this->reportType, ['sales', 'commission'], true)
            ? ($this->reportType === 'sales' ? 'orders' : 'financial')
            : (str_ends_with($this->reportType, '_insight') ? 'orders' : $this->reportType);

        return match ($type) {
            'users' => ['ID', 'Name', 'Email', 'Phone', 'Role', 'Created At'],
            'merchants' => ['ID', 'Company Name', 'Email', 'Phone', 'Approved', 'Created At'],
            'orders' => ['ID', 'User', 'Merchant', 'Amount', 'Payment Method', 'Status', 'Created At'],
            'products' => ['ID', 'Title', 'Merchant', 'Price', 'Status', 'Created At'],
            'payments' => ['ID', 'Order ID', 'Amount', 'Gateway', 'Status', 'Created At'],
            'financial' => ['ID', 'Merchant', 'Type', 'Flow', 'Amount', 'Status', 'Created At'],
            default => ['Column A', 'Column B', 'Column C'],
        };
    }

    protected function formatDate($date): string
    {
        return $date && method_exists($date, 'format') ? $date->format('Y-m-d H:i:s') : 'N/A';
    }

    public function map($row): array
    {
        $type = in_array($this->reportType, ['sales', 'commission'], true)
            ? ($this->reportType === 'sales' ? 'orders' : 'financial')
            : (str_ends_with($this->reportType, '_insight') ? 'orders' : $this->reportType);

        return match ($type) {
            'users' => [
                $row->id ?? '',
                $row->name ?? 'N/A',
                $row->email ?? 'N/A',
                $row->phone ?? 'N/A',
                $row->role->name ?? 'N/A',
                $this->formatDate($row->created_at ?? null),
            ],
            'merchants' => [
                $row->id ?? '',
                $row->company_name ?? $row->company_name_ar ?? $row->company_name_en ?? 'N/A',
                $row->user->email ?? 'N/A',
                $row->phone ?? 'N/A',
                isset($row->approved) ? ($row->approved ? 'Yes' : 'No') : 'N/A',
                $this->formatDate($row->created_at ?? null),
            ],
            'orders' => [
                $row->id ?? '',
                $row->user->name ?? 'N/A',
                $row->merchant->company_name ?? 'N/A',
                $row->total_amount ?? 0,
                $row->payment_method ?? 'N/A',
                $row->payment_status ?? 'N/A',
                $this->formatDate($row->created_at ?? null),
            ],
            'products' => [
                $row->id ?? '',
                $row->title_ar ?? $row->title_en ?? $row->title ?? 'N/A',
                $row->merchant->company_name ?? 'N/A',
                $row->price ?? 0,
                $row->status ?? 'N/A',
                $this->formatDate($row->created_at ?? null),
            ],
            'payments' => [
                $row->id ?? '',
                $row->order_id ?? '',
                $row->amount ?? 0,
                $row->gateway ?? 'N/A',
                $row->status ?? 'N/A',
                $this->formatDate($row->created_at ?? null),
            ],
            'financial' => [
                $row->id ?? '',
                $row->merchant->company_name ?? 'N/A',
                $row->transaction_type ?? 'N/A',
                $row->transaction_flow ?? 'N/A',
                $row->amount ?? 0,
                $row->status ?? 'N/A',
                $this->formatDate($row->created_at ?? null),
            ],
            default => [
                is_object($row) && method_exists($row, 'getKey') ? $row->getKey() : '',
                'Export',
                '',
                '',
                '',
                '',
                '',
            ],
        };
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $isAr = ($this->filters['language'] ?? 'ar') === 'ar';
                $title = ($this->branding['app_name'] ?? 'OFROO Admin').' — '.($isAr ? 'تقرير' : 'Report').' ('.$this->reportType.')';
                $period = ($this->filters['from'] ?? '—').' → '.($this->filters['to'] ?? '—');

                $headings = $this->headings();
                $colCount = max(count($headings), 1);
                $lastCol = Coordinate::stringFromColumnIndex($colCount);
                $hasLogo = ! empty($this->branding['logo_full_path']) && is_readable($this->branding['logo_full_path']);
                $titleStartCol = ($hasLogo && $colCount > 1) ? 'B' : 'A';

                $sheet->mergeCells($titleStartCol.'1:'.$lastCol.'2');
                $sheet->setCellValue($titleStartCol.'1', $title);
                $sheet->getStyle($titleStartCol.'1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('0F172A');
                $sheet->getStyle($titleStartCol.'1')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);

                $sheet->mergeCells('A3:'.$lastCol.'3');
                $sheet->setCellValue('A3', ($isAr ? 'الفترة: ' : 'Period: ').$period);
                $sheet->getStyle('A3')->getFont()->setSize(10)->getColor()->setRGB('64748B');
                $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->mergeCells('A4:'.$lastCol.'4');
                $sheet->setCellValue('A4', $isAr ? 'سري — للاستخدام الإداري' : 'Confidential — internal use');
                $sheet->getStyle('A4')->getFont()->setSize(9)->getColor()->setRGB('94A3B8');
                $sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->getRowDimension(1)->setRowHeight(26);
                $sheet->getRowDimension(2)->setRowHeight(10);
                $sheet->getRowDimension(3)->setRowHeight(18);
                $sheet->getRowDimension(4)->setRowHeight(16);

                if ($hasLogo) {
                    try {
                        $drawing = new Drawing;
                        $drawing->setName('Logo');
                        $drawing->setDescription('Brand');
                        $drawing->setPath($this->branding['logo_full_path']);
                        $drawing->setHeight(52);
                        $drawing->setCoordinates('A1');
                        $drawing->setOffsetX(6);
                        $drawing->setOffsetY(2);
                        $drawing->setWorksheet($sheet);
                    } catch (\Throwable $e) {
                        // skip broken image
                    }
                }

                $headerRange = 'A8:'.$lastCol.'8';
                $sheet->getStyle($headerRange)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '1E40AF'],
                    ],
                    'alignment' => [
                        'horizontal' => $isAr ? Alignment::HORIZONTAL_RIGHT : Alignment::HORIZONTAL_LEFT,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'CBD5E1'],
                        ],
                    ],
                ]);

                $lastRow = $sheet->getHighestRow();
                if ($lastRow > 8) {
                    $sheet->getStyle('A9:'.$lastCol.$lastRow)->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => 'E2E8F0'],
                            ],
                        ],
                    ]);
                }
            },
        ];
    }
}
