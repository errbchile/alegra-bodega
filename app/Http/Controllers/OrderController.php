<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Jobs\ProcessOrder;


class OrderController extends Controller
{
    public function create(Request $request)
    {
        $ingredient_names = ['tomato', 'lemon', 'potato', 'rice', 'ketchup', 'lettuce', 'onion', 'cheese', 'meat', 'chicken'];

        $request->validate([
            'order_code' => 'required',
            'ingredients' => 'required|array|min:1',
            'ingredients.*.name' => ['required', 'string', 'in:' . implode(',', $ingredient_names)],
            'ingredients.*.quantity' => 'required|integer|min:1',
        ]);

        $order = Order::create([
            'order_code' => $request->order_code,
            'ingredients' => $request->ingredients,
            'status' => 'pending',
        ]);

        ProcessOrder::dispatch($order);

        return response()->json([
            'message' => "order received",
            'order' => $order->id
        ], 201);
    }
}
