<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Order;
use App\Models\Ingredient;
use App\Models\Purchase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $order;


    /**
     * Create a new job instance.
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        foreach ($this->order->ingredients as $ingredient_needed) {
            $ingredient = Ingredient::where('name', $ingredient_needed['name'])->first();
            $need_more_ingredients = $ingredient->quantity < $ingredient_needed['quantity'];
            if ($need_more_ingredients) {
                $quantity_needed = $ingredient_needed['quantity'] - $ingredient->quantity;
                $this->buy_more_ingredients($ingredient, $quantity_needed);
            }
        }
        // deliver ingredients
        $this->deliver_ingredients();
    }

    private function buy_more_ingredients($ingredient, $quantity_needed): void
    {
        $quantity_bought = 0;
        while ($quantity_bought < $quantity_needed) {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
            ])->get("https://recruitment.alegra.com/api/farmers-market/buy?ingredient={$ingredient->name}");

            if ($response->successful()) {

                if ($response['quantitySold'] < 1) {
                    continue;
                }

                $quantity_bought += $response['quantitySold'];
                $this->register_purchase($ingredient, $quantity_bought);
            }
        }
    }

    private function register_purchase($ingredient, $quantity_bought)
    {
        $purchase = new Purchase();
        $purchase->ingredient_id = $ingredient->id;
        $purchase->quantity = $quantity_bought;
        $purchase->save();

        // update inventory
        $ingredient->quantity += $quantity_bought;
        $ingredient->save();
    }

    private function deliver_ingredients()
    {
        $body = [
            'order_code' => $this->order->order_code,
            'ingredients' => $this->order->ingredients,
        ];
        Log::info($body);
        $response = Http::withHeaders([
            'Accept' => 'application/json',
        ])->post("http://127.0.0.1:8001/api/orders/get-ingredients", $body);

        Log::info($response->json());
        $this->order->status = "delivered";
        $this->order->save();
        Log::info("order delivered and finished");
    }
}
