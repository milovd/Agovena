<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\CreditNoteResource;
use App\Http\Resources\Api\V1\InvoiceResource;
use App\Http\Resources\Api\V1\OrderResource;
use App\Http\Resources\Api\V1\SupportTicketResource;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CommerceController
{
    public function orders(): AnonymousResourceCollection
    {
        $customer = authenticated_customer();

        return OrderResource::collection(
            $customer->orders()->with(['items', 'payment', 'invoice', 'creditNotes'])->latest('id')->paginate(20),
        );
    }

    public function order(Order $order): OrderResource
    {
        $this->assertOwnedOrder($order);

        return new OrderResource($order->load(['items', 'payment.refunds', 'invoice', 'creditNotes']));
    }

    public function invoices(): AnonymousResourceCollection
    {
        $customer = authenticated_customer();

        return InvoiceResource::collection(
            $customer->invoices()->with(['items', 'creditNotes'])->latest('id')->paginate(20),
        );
    }

    public function invoice(Invoice $invoice): InvoiceResource
    {
        abort_unless((int) $invoice->customer_id === (int) authenticated_customer()->id, 404);

        return new InvoiceResource($invoice->load(['items', 'creditNotes']));
    }

    public function creditNotes(): AnonymousResourceCollection
    {
        $customer = authenticated_customer();

        return CreditNoteResource::collection(
            $customer->creditNotes()->with('items')->latest('id')->paginate(20),
        );
    }

    public function creditNote(CreditNote $creditNote): CreditNoteResource
    {
        abort_unless((int) $creditNote->customer_id === (int) authenticated_customer()->id, 404);

        return new CreditNoteResource($creditNote->load('items'));
    }

    public function tickets(): AnonymousResourceCollection
    {
        $customer = authenticated_customer();

        return SupportTicketResource::collection(
            $customer->tickets()->latest('id')->paginate(20),
        );
    }

    public function ticket(Ticket $ticket): SupportTicketResource
    {
        abort_unless((int) $ticket->customer_id === (int) authenticated_customer()->id, 404);

        return new SupportTicketResource($ticket);
    }

    private function assertOwnedOrder(Order $order): void
    {
        abort_unless((int) $order->customer_id === (int) authenticated_customer()->id, 404);
    }
}
