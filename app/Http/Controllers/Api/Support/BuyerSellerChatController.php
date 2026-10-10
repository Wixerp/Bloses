<?php

namespace App\Http\Controllers\Api\Support;

use App\Http\Controllers\Controller;
use App\Services\Support\BuyerSellerChatService;
use Illuminate\Http\Request;

class BuyerSellerChatController extends Controller
{
    public function __construct(
        protected BuyerSellerChatService $chatService
    ) {}

    // Buyer: order ID se apni seller chat open kare
    public function openBuyerChat(Request $request, int $orderId)
    {
        return response()->json([
            'success' => true,
            'data' => $this->chatService->openChat($orderId),
        ]);
    }

    // Buyer: seller ko message bheje
    public function buyerSendMessage(Request $request, int $orderId)
    {
        $data = $request->validate([
            'message' => 'nullable|string|max:5000|required_without:image',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120|required_without:message',
        ]);

        $result = $this->chatService->sendMessage($orderId, $data);

        return response()->json($result, 201);
    }

    // Seller: apni buyer conversations dekhe
    public function sellerChats()
    {
        return response()->json(
            $this->chatService->getSellerChats()
        );
    }

    // Seller: particular buyer conversation open kare
    public function openSellerChat(int $ticketId)
    {
        return response()->json(
            $this->chatService->getSellerChat($ticketId)
        );
    }

    // Seller: usi buyer ko reply kare
    public function sellerSendMessage(Request $request, int $ticketId)
    {
        $data = $request->validate([
            'message' => 'nullable|string|max:5000|required_without:image',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120|required_without:message',
        ]);

        $result = $this->chatService->sellerSendMessage(
            $ticketId,
            $data
        );

        return response()->json($result, 201);
    }
}
