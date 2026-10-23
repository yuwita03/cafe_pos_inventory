<?php

namespace App\Http\Controllers;

use App\QrisPayments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class MidtransNotificationController extends Controller
{
    public function __invoke(Request $request, QrisPayments $payments): JsonResponse
    {
        try {
            $payments->handleNotification($request->json()->all());
        } catch (RuntimeException) {
            return response()->json(['message' => 'Invalid notification.'], 400);
        }

        return response()->json(['ok' => true]);
    }
}
