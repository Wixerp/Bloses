<?php

namespace App\Http\Controllers\Api\Support;

use App\Http\Controllers\Controller;
use App\Services\Support\AdminSupportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminSupportController extends Controller
{
    public function __construct(
        protected AdminSupportService $supportService
    ) {}

    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'search' => ['nullable', 'string', 'max:255'],
            'status_id' => ['nullable', 'integer'],
            'priority_id' => ['nullable', 'integer'],
            'complaint_type_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $tickets = $this->supportService->getTickets(
            $validator->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Support tickets retrieved successfully.',
            'data' => $tickets,
        ]);
    }

    public function show(int $id)
    {
        $data = $this->supportService->getTicketById($id);

        return response()->json([
            'success' => true,
            'message' => 'Support ticket retrieved successfully.',
            'data' => $data,
        ]);
    }

    public function reply(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'message' => ['required', 'string', 'max:10000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $adminId = (int) $request->user()->id;

        $reply = $this->supportService->replyToTicket(
            $id,
            $adminId,
            $validator->validated()['message']
        );

        return response()->json([
            'success' => true,
            'message' => 'Reply sent successfully.',
            'data' => $reply,
        ], 201);
    }

    public function updateStatus(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'status_id' => [
                'required',
                'integer',
                'exists:master_types,id',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $ticket = $this->supportService->updateTicketStatus(
            $id,
            (int) $validator->validated()['status_id']
        );

        return response()->json([
            'success' => true,
            'message' => 'Ticket status updated successfully.',
            'data' => $ticket,
        ]);
    }

}
