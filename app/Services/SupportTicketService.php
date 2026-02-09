<?php

namespace App\Services;

use App\Models\SupportTicket;
use App\Models\TicketAttachment;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class SupportTicketService
{
    /**
     * Generate unique ticket number
     */
    public function generateTicketNumber(): string
    {
        do {
            $number = 'TKT-' . strtoupper(Str::random(8));
        } while (SupportTicket::where('ticket_number', $number)->exists());

        return $number;
    }

    /**
     * Create support ticket
     */
    public function createTicket(array $data, array $attachments = []): SupportTicket
    {
        $ticket = SupportTicket::create([
            'ticket_number' => $this->generateTicketNumber(),
            'user_id' => $data['user_id'],
            'merchant_id' => $data['merchant_id'] ?? null,
            'category' => $data['category'],
            'category_ar' => $data['category_ar'] ?? null,
            'category_en' => $data['category_en'] ?? null,
            'subject' => $data['subject'],
            'description' => $data['description'],
            'priority' => $data['priority'] ?? 'medium',
            'status' => 'open',
            'metadata' => $data['metadata'] ?? null,
        ]);

        // Handle attachments
        foreach ($attachments as $file) {
            $path = $file->store('tickets/' . $ticket->id, 'public');
            TicketAttachment::create([
                'ticket_id' => $ticket->id,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
            ]);
        }

        return $ticket;
    }

    /**
     * Assign ticket to support staff
     */
    public function assignTicket(SupportTicket $ticket, int $staffId): void
    {
        $ticket->update([
            'assigned_to' => $staffId,
            'status' => 'in_progress',
        ]);
    }

    /**
     * Resolve ticket
     */
    public function resolveTicket(SupportTicket $ticket, string $resolution = null): void
    {
        $ticket->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'metadata' => array_merge($ticket->metadata ?? [], [
                'resolution' => $resolution,
            ]),
        ]);
    }
}


