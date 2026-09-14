<?php

use App\Actions\Printing\CustomerReceiptData;
use App\Actions\Printing\KitchenTicketData;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Printer;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class);

function printableOrder(): Order
{
    $store = (new Store)->forceFill([
        'name' => 'Oson Cafe',
        'address' => 'Tashkent, Amir Temur street 1',
        'phone' => '+998901234567',
        'timezone' => 'Asia/Tashkent',
    ]);
    $creator = (new User)->forceFill(['name' => 'Ali Cashier']);
    $order = (new Order)->forceFill([
        'id' => '01PRINTTEST000000000000000',
        'display_number' => '#0042',
        'type' => OrderType::Takeaway,
        'subtotal' => 65000,
        'delivery_fee' => 0,
        'total' => 65000,
        'opened_at' => Carbon::parse('2026-09-13 12:30:00', 'Asia/Tashkent'),
        'closed_at' => Carbon::parse('2026-09-13 12:35:00', 'Asia/Tashkent'),
    ]);

    return $order->setRelation('store', $store)
        ->setRelation('creator', $creator)
        ->setRelation('table', null)
        ->setRelation('customer', null)
        ->setRelation('deliveryDetail', null);
}

function printableItem(): OrderItem
{
    return (new OrderItem)->forceFill([
        'id' => '01PRINTITEM000000000000000',
        'product_name' => 'Double Cheeseburger',
        'quantity' => 2,
        'unit_price' => 32500,
        'total' => 65000,
        'note' => 'No onions',
    ]);
}

function thermalPrinter(int $paperWidth): Printer
{
    return (new Printer)->forceFill([
        'id' => 1,
        'system_name' => 'Thermal Printer',
        'paper_width' => $paperWidth,
    ]);
}

it('builds a kitchen-first ticket with order context, notes and item count', function () {
    $order = printableOrder();
    $item = printableItem();
    $data = (new KitchenTicketData($order, thermalPrinter(58), collect([$item]), false))->toArray();

    expect($data['lines'])->toContain('2 x DOUBLE CHEESEBURGER')
        ->toContain('  ! No onions')
        ->toContain(str_repeat('=', 32))
        ->and(collect($data['lines'])->contains(
            fn (string $line): bool => str_contains($line, 'JAMI MAHSULOT') && str_ends_with($line, '2'),
        ))->toBeTrue();
});

it('builds a customer receipt with aligned totals and payment breakdown', function () {
    $order = printableOrder();
    $item = printableItem();
    $payment = (new Payment)->forceFill([
        'method' => PaymentMethod::Cash,
        'amount' => 65000,
    ]);
    $order->setRelation('items', collect([$item]))
        ->setRelation('payments', collect([$payment]));

    $data = (new CustomerReceiptData($order, thermalPrinter(80), false))->toArray();

    expect($data['lines'])->toContain(str_repeat('=', 48))
        ->and(collect($data['lines'])->contains(
            fn (string $line): bool => str_contains($line, 'JAMI UZS') && str_ends_with($line, '65 000'),
        ))->toBeTrue()
        ->and(collect($data['lines'])->contains(
            fn (string $line): bool => str_starts_with($line, 'NAQD') && str_ends_with($line, '65 000'),
        ))->toBeTrue();
});
