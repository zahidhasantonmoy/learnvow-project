<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Models\PrintOrder;
use App\Services\PrintService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/** Print Partner dashboard (Phase 4 spec §2.2, §14.4). */
class PrintPartnerController extends Controller
{
    use BuildsSeo;

    /** Only orders assigned to this partner — never another partner's. */
    public function index(string $locale)
    {
        $orders = PrintOrder::where('partner_id', auth()->id())
            ->with(['book', 'user'])->latest()->paginate(12);

        return view('print-partner.dashboard', [
            'orders' => $orders,
            'seo' => $this->seo(__('print.partner_dashboard_title'), null, ['noindex' => true]),
        ]);
    }

    /** Partner moves their own order: printing / shipped (+ tracking) / note. */
    public function updateStatus(string $locale, Request $request, PrintOrder $printOrder, PrintService $prints)
    {
        Gate::authorize('fulfill', $printOrder);

        $data = $request->validate([
            'status' => 'required|in:printing,shipped',
            'tracking_no' => 'nullable|string|max:100',
            'admin_note' => 'nullable|string|max:1000',
        ]);

        try {
            if ($data['status'] === 'printing') {
                $prints->markPrinting($printOrder, $request->user());
            } else {
                $prints->markShipped($printOrder, $request->user(), $data['tracking_no'] ?? null);
            }

            if (! empty($data['admin_note'])) {
                $prints->partnerNote($printOrder->fresh(), $request->user(), $data['admin_note']);
            }
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('status', __('print.partner_status_updated'));
    }
}
