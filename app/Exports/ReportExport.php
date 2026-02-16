<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ReportExport implements FromCollection, WithHeadings, WithMapping
{
    protected array $data;
    protected string $reportType;

    public function __construct(array $data, string $reportType)
    {
        $this->data = $data;
        $this->reportType = $reportType;
    }

    public function collection()
    {
        return collect($this->data['data'] ?? []);
    }

    public function headings(): array
    {
        $type = in_array($this->reportType, ['sales', 'commission']) ? ($this->reportType === 'sales' ? 'orders' : 'financial') : $this->reportType;
        return match($type) {
            'users' => ['ID', 'Name', 'Email', 'Phone', 'Role', 'Created At'],
            'merchants' => ['ID', 'Company Name', 'Email', 'Phone', 'Approved', 'Created At'],
            'orders' => ['ID', 'User', 'Merchant', 'Amount', 'Payment Method', 'Status', 'Created At'],
            'products' => ['ID', 'Title', 'Merchant', 'Price', 'Status', 'Created At'],
            'payments' => ['ID', 'Order ID', 'Amount', 'Gateway', 'Status', 'Created At'],
            'financial' => ['ID', 'Merchant', 'Type', 'Flow', 'Amount', 'Status', 'Created At'],
            default => [],
        };
    }

    protected function formatDate($date): string
    {
        return $date && method_exists($date, 'format') ? $date->format('Y-m-d H:i:s') : 'N/A';
    }

    public function map($row): array
    {
        $type = in_array($this->reportType, ['sales', 'commission']) ? ($this->reportType === 'sales' ? 'orders' : 'financial') : $this->reportType;
        return match($type) {
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
                $row->title_ar ?? $row->title_en ?? 'N/A',
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
            default => [],
        };
    }
}


