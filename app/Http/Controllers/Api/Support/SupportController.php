<?php

namespace App\Http\Controllers\Api\Support;

use App\Http\Controllers\Controller;
use App\Services\Support\SupportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SupportController extends Controller
{
    public function __construct(
        protected SupportService $supportService
    ) {}

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:10000'],
            'complaint_type_id' => ['nullable', 'integer'],
            'priority_id' => ['nullable', 'integer'],
            'image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $ticket = $this->supportService->createTicket(
            $validator->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Support request submitted successfully.',
            'data' => $ticket,
        ], 201);
    }

    public function myTickets()
    {
        $tickets = $this->supportService->getMyTickets();

        return response()->json([
            'success' => true,
            'message' => 'Support tickets retrieved successfully.',
            'data' => $tickets,
        ]);
    }

    public function myTicketDetail(int $id)
    {
        $data = $this->supportService->getMyTicketById($id);

        return response()->json([
            'success' => true,
            'message' => 'Support ticket details retrieved successfully.',
            'data' => $data,
        ]);
    }
}
