<?php

namespace App\Http\Controllers\Api;

use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Print orders (spec §1.3, §2.2). Calls Builder C's PrintService when it
 * exists; until then lists are empty and creating returns 503.
 */
class PrintOrderController extends ApiController
{
    public function index(Request $request)
    {
        if (! Schema::hasTable('print_orders')) {
            return $this->ok([]);
        }

        $rows = DB::table('print_orders')->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')->paginate(20);

        return response()->json([
            'data' => $rows->getCollection()->map(fn ($o) => [
                'id' => $o->id, 'order_no' => $o->order_no, 'book_id' => $o->book_id,
                'quantity' => (int) $o->quantity, 'total_bdt' => (int) $o->total_bdt,
                'status' => $o->status, 'tracking_no' => $o->tracking_no,
                'created_at' => $o->created_at,
            ])->values(),
            'links' => ['first' => $rows->url(1), 'last' => $rows->url($rows->lastPage()), 'prev' => $rows->previousPageUrl(), 'next' => $rows->nextPageUrl()],
            'meta' => ['locale' => app()->getLocale(), 'current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage(), 'total' => $rows->total()],
        ]);
    }

    public function store(Request $request, Book $book)
    {
        $data = $request->validate([
            'quantity' => 'required|integer|min:1|max:10',
            'delivery_name' => 'required|string|max:100',
            'delivery_phone' => 'required|string|max:20',
            'delivery_address' => 'required|string|max:1000',
            'district' => 'nullable|string|max:100',
        ]);

        if (! class_exists(\App\Services\PrintService::class)) {
            return $this->fail(__('api.print_unavailable'), 503);
        }

        try {
            $order = app(\App\Services\PrintService::class)->createOrder($request->user(), $book, $data);
        } catch (\Throwable $e) {
            report($e);

            return $this->fail(__('api.print_unavailable'), 503);
        }

        return $this->ok([
            'id' => $order->id, 'order_no' => $order->order_no,
            'total_bdt' => (int) $order->total_bdt, 'status' => $order->status,
        ], 201);
    }
}
