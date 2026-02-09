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
        return match($this->reportType) {
            'users' => ['ID', 'Name', 'Email', 'Phone', 'Role', 'Created At'],
            'merchants' => ['ID', 'Company Name', 'Email', 'Phone', 'Approved', 'Created At'],
            'orders' => ['ID', 'User', 'Merchant', 'Amount', 'Payment Method', 'Status', 'Created At'],
            'products' => ['ID', 'Title', 'Merchant', 'Price', 'Status', 'Created At'],
            'payments' => ['ID', 'Order ID', 'Amount', 'Gateway', 'Status', 'Created At'],
            'financial' => ['ID', 'Merchant', 'Type', 'Flow', 'Amount', 'Status', 'Created At'],
            default => [],
        };
    }

    public function map($row): array
    {
        return match($this->reportType) {
            'users' => [
                $row->id,
                $row->name,
                $row->email,
                $row->phone,
                $row->role->name ?? 'N/A',
                $row->created_at->format('Y-m-d H:i:s'),
            ],
            'merchants' => [
                $row->id,
                $row->company_name,
                $row->user->email ?? 'N/A',
                $row->phone,
                $row->approved ? 'Yes' : 'No',
                $row->created_at->format('Y-m-d H:i:s'),
            ],
            'orders' => [
                $row->id,
                $row->user->name ?? 'N/A',
                $row->merchant->company_name ?? 'N/A',
                $row->total_amount,
                $row->payment_method,
                $row->payment_status,
                $row->created_at->format('Y-m-d H:i:s'),
            ],
            'products' => [
                $row->id,
                $row->title_ar ?? $row->title_en,
                $row->merchant->company_name ?? 'N/A',
                $row->price,
                $row->status,
                $row->created_at->format('Y-m-d H:i:s'),
            ],
            'payments' => [
                $row->id,
                $row->order_id,
                $row->amount,
                $row->gateway ?? 'N/A',
                $row->status,
                $row->created_at->format('Y-m-d H:i:s'),
            ],
            'financial' => [
                $row->id,
                $row->merchant->company_name ?? 'N/A',
                $row->transaction_type,
                $row->transaction_flow,
                $row->amount,
                $row->status,
                $row->created_at->format('Y-m-d H:i:s'),
            ],
            default => [],
        };
    }
}


