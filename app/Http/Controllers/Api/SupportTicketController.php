<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Services\SupportTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportTicketController extends Controller
{
    protected SupportTicketService $ticketService;

    public function __construct(SupportTicketService $ticketService)
    {
        $this->ticketService = $ticketService;
    }

    /**
     * Create support ticket
     */
    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'category' => 'required|in:technical,financial,content,fraud,other',
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'merchant_id' => 'nullable|exists:merchants,id',
            'priority' => 'nullable|in:low,medium,high,urgent',
        ]);

        $ticket = $this->ticketService->createTicket(
            array_merge($request->all(), ['user_id' => $request->user()->id]),
            $request->file('attachments', [])
        );

        return response()->json([
            'message' => 'Ticket created successfully',
            'data' => $ticket->load('attachments'),
        ], 201);
    }

    /**
     * List tickets
     */
    public function index(Request $request): JsonResponse
    {
        $query = SupportTicket::with(['user', 'merchant', 'assignedTo', 'attachments']);

        if (!$request->user()->isAdmin()) {
            $query->where('user_id', $request->user()->id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        $tickets = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $tickets->items(),
            'meta' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
            ],
        ]);
    }

    /**
     * Get ticket details
     */
    public function show(string $id): JsonResponse
    {
        $ticket = SupportTicket::with(['user', 'merchant', 'assignedTo', 'attachments'])
            ->findOrFail($id);

        return response()->json([
            'data' => $ticket,
        ]);
    }

    /**
     * Assign ticket (Admin/Support)
     */
    public function assign(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'staff_id' => 'required|exists:users,id',
        ]);

        $ticket = SupportTicket::findOrFail($id);
        $this->ticketService->assignTicket($ticket, $request->staff_id);

        return response()->json([
            'message' => 'Ticket assigned successfully',
            'data' => $ticket->fresh(),
        ]);
    }

    /**
     * Resolve ticket
     */
    public function resolve(Request $request, string $id): JsonResponse
    {
        $ticket = SupportTicket::findOrFail($id);
        $this->ticketService->resolveTicket($ticket, $request->get('resolution'));

        return response()->json([
            'message' => 'Ticket resolved successfully',
            'data' => $ticket->fresh(),
        ]);
    }
}
