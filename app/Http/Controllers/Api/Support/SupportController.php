<?php

namespace App\Http\Controllers\Api\Support;

use App\Http\Controllers\Controller;
use App\Services\Support\SupportService;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    public function __construct(
        protected SupportService $supportService
    ) {}

    public function store(Request $request)
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'complaint_type_id' => ['nullable', 'integer'],
            'priority_id' => ['nullable', 'integer'],
        ]);

        $ticket = $this->supportService->createTicket($data);

        return response()->json([
            'success' => true,
            'message' => 'Support request submitted successfully.',
            'data' => $ticket,
        ], 201);
    }
}
