<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TicketController extends Controller
{
    /**
     * Get all tickets for the authenticated user with pagination and filtering
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $query = Ticket::where('user_id', $user->id);

        // Filter by status
        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        // Search by ticket number
        if ($request->has('search') && $request->search !== '') {
            $searchTerm = '%' . $request->search . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('ticket_number', 'like', $searchTerm)
                  ->orWhere('notes', 'like', $searchTerm);
            });
        }

        // Date range filter
        if ($request->has('from_date') && $request->from_date !== '') {
            $query->where('created_at', '>=', $request->from_date . ' 00:00:00');
        }

        if ($request->has('to_date') && $request->to_date !== '') {
            $query->where('created_at', '<=', $request->to_date . ' 23:59:59');
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        $allowedSortFields = ['created_at', 'stake', 'total_odds', 'potential_winning', 'status'];
        if (!in_array($sortBy, $allowedSortFields)) {
            $sortBy = 'created_at';
        }

        $sortOrder = strtolower($sortOrder) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 10);
        $tickets = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $tickets->items(),
            'current_page' => $tickets->currentPage(),
            'per_page' => $tickets->perPage(),
            'total' => $tickets->total(),
            'last_page' => $tickets->lastPage(),
        ]);
    }

    /**
     * Store a new ticket
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        // Validate input
        $validated = $request->validate([
            'bets' => 'required|array|min:1',
            'stake' => 'required|numeric|min:0.1',
            'total_odds' => 'required|numeric|min:1',
            'potential_winning' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $ticket = new Ticket();
            $ticket->user_id = $user->id;
            $ticket->bets = $validated['bets'];
            $ticket->stake = $validated['stake'];
            $ticket->total_odds = $validated['total_odds'];
            $ticket->potential_winning = $validated['potential_winning'];
            $ticket->notes = $validated['notes'] ?? null;
            $ticket->status = 'pending';
            $ticket->ticket_number = Ticket::generateTicketNumber();

            $ticket->save();

            return response()->json([
                'success' => true,
                'message' => 'Ticket saved successfully',
                'data' => $ticket,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save ticket: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a specific ticket
     */
    public function show($id)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $ticket = Ticket::where('user_id', $user->id)->find($id);

        if (!$ticket) {
            return response()->json(['success' => false, 'message' => 'Ticket not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $ticket,
        ]);
    }

    /**
     * Update a ticket
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $ticket = Ticket::where('user_id', $user->id)->find($id);

        if (!$ticket) {
            return response()->json(['success' => false, 'message' => 'Ticket not found'], 404);
        }

        // Validate input
        $validated = $request->validate([
            'bets' => 'sometimes|array|min:1',
            'stake' => 'sometimes|numeric|min:0.1',
            'total_odds' => 'sometimes|numeric|min:1',
            'potential_winning' => 'sometimes|numeric|min:0',
            'status' => 'sometimes|in:pending,won,lost,cancelled',
            'notes' => 'sometimes|nullable|string|max:500',
        ]);

        try {
            $ticket->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Ticket updated successfully',
                'data' => $ticket,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update ticket: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a ticket
     */
    public function destroy($id)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $ticket = Ticket::where('user_id', $user->id)->find($id);

        if (!$ticket) {
            return response()->json(['success' => false, 'message' => 'Ticket not found'], 404);
        }

        try {
            $ticket->delete();

            return response()->json([
                'success' => true,
                'message' => 'Ticket deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete ticket: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get ticket statistics
     */
    public function statistics()
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $tickets = Ticket::where('user_id', $user->id);

        $stats = [
            'total_tickets' => $tickets->count(),
            'pending_tickets' => $tickets->byStatus('pending')->count(),
            'won_tickets' => $tickets->byStatus('won')->count(),
            'lost_tickets' => $tickets->byStatus('lost')->count(),
            'total_stake' => $tickets->sum('stake') ?? 0,
            'total_winnings' => $tickets->byStatus('won')->sum('potential_winning') ?? 0,
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
