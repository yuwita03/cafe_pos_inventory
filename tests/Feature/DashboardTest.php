<?php

use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\StockItem;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Carbon::setTestNow('2026-10-25 12:00:00');
    $this->actingAs(User::factory()->create(['role' => 'owner']));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function dashboardOrder(string $status, string $createdAt, int $total): Order
{
    return Order::factory()->create(['status' => $status, 'created_at' => $createdAt, 'updated_at' => $createdAt, 'total_idr' => $total]);
}

function dashboardPayment(Order $order, string $status, ?string $paidAt): void
{
    DB::table('payments')->insert([
        'order_id' => $order->id,
        'method' => 'qris',
        'amount_idr' => $order->total_idr,
        'status' => $status,
        'paid_at' => $paidAt,
        'created_at' => $paidAt ?? now(),
    ]);
}

function dashboardItem(Order $order, ProductVariant $variant, string $name, int $quantity): void
{
    DB::table('order_items')->insert([
        'order_id' => $order->id,
        'product_variant_id' => $variant->id,
        'product_name_snapshot' => $name,
        'variant_name_snapshot' => $variant->name,
        'unit_price_idr' => $order->total_idr,
        'quantity' => $quantity,
    ]);
}

test('dashboard reports paid weekly analytics comparisons products reviews and available stock', function (): void {
    $variant = ProductVariant::factory()->create();
    $monday = dashboardOrder('completed', '2026-10-01 08:00:00', 100000);
    dashboardPayment($monday, 'paid', '2026-10-19 10:00:00');
    dashboardItem($monday, $variant, 'Americano', 2);
    $sunday = dashboardOrder('completed', '2026-10-25 08:00:00', 50000);
    dashboardPayment($sunday, 'paid', '2026-10-25 11:59:59');
    dashboardItem($sunday, $variant, 'Latte', 1);

    $previous = dashboardOrder('completed', '2026-10-13 08:00:00', 100000);
    dashboardPayment($previous, 'paid', '2026-10-13 10:00:00');
    dashboardItem($previous, $variant, 'Americano', 2);

    $pending = dashboardOrder('awaiting_payment', '2026-10-20 08:00:00', 200000);
    dashboardPayment($pending, 'pending', null);
    $refunded = dashboardOrder('completed', '2026-10-21 08:00:00', 300000);
    dashboardPayment($refunded, 'refunded', '2026-10-21 10:00:00');
    foreach (['failed', 'expired'] as $status) {
        dashboardPayment(dashboardOrder('awaiting_payment', '2026-10-21 08:00:00', 300000), $status, '2026-10-21 10:00:00');
    }
    $cancelled = dashboardOrder('cancelled', '2026-10-22 08:00:00', 400000);
    dashboardPayment($cancelled, 'paid', '2026-10-22 10:00:00');
    dashboardItem($cancelled, $variant, 'Cancelled item', 9);
    dashboardOrder('payment_review', '2026-10-23 08:00:00', 25000);

    $reserved = StockItem::factory()->create(['name' => 'Reserved beans', 'on_hand_qty' => '10.000', 'reorder_point' => '5.000']);
    DB::table('stock_reservations')->insert(['order_id' => $monday->id, 'stock_item_id' => $reserved->id, 'quantity' => '6.000', 'status' => 'active', 'expires_at' => now()->addDay()]);
    $expired = StockItem::factory()->create(['name' => 'Expired reservation', 'on_hand_qty' => '10.000', 'reorder_point' => '5.000']);
    DB::table('stock_reservations')->insert(['order_id' => $sunday->id, 'stock_item_id' => $expired->id, 'quantity' => '6.000', 'status' => 'active', 'expires_at' => now()->subDay()]);
    StockItem::factory()->create(['name' => 'Inactive stock', 'on_hand_qty' => '0.000', 'reorder_point' => '5.000', 'is_active' => false]);

    $this->get(route('admin.dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('admin/Dashboard')
        ->where('analytics.period.selected', 'this_week')
        ->where('analytics.period.start', '2026-10-19')
        ->where('analytics.period.end', '2026-10-25')
        ->where('analytics.summary.confirmedRevenue', 150000)
        ->where('analytics.summary.paidOrders', 2)
        ->where('analytics.summary.averageOrderValue', 75000)
        ->where('analytics.summary.itemsSold', 3)
        ->where('analytics.summary.paymentReviews', 1)
        ->where('analytics.comparison.confirmedRevenue', 50)
        ->where('analytics.comparison.paidOrders', 100)
        ->where('analytics.comparison.averageOrderValue', -25)
        ->where('analytics.comparison.itemsSold', 50)
        ->has('analytics.chart.points', 7)
        ->where('analytics.chart.points.0.start', '2026-10-19')
        ->where('analytics.chart.points.0.revenue', 100000)
        ->where('analytics.chart.points.6.start', '2026-10-25')
        ->where('analytics.chart.points.6.revenue', 50000)
        ->has('analytics.topProducts', 2)
        ->where('analytics.topProducts.0.name', 'Americano')
        ->where('analytics.topProducts.0.quantity', 2)
        ->where('analytics.topProducts.1.name', 'Latte')
        ->has('analytics.lowStock', 1)
        ->where('analytics.lowStock.0.name', 'Reserved beans')
        ->where('analytics.lowStock.0.available', 4)
        ->where('analytics.lowStock.0.threshold', 5));

    $this->get(route('admin.dashboard', ['period' => 'previous']))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('analytics.period.selected', 'last_week')
        ->where('analytics.period.start', '2026-10-12')
        ->where('analytics.period.end', '2026-10-18')
        ->where('analytics.summary.confirmedRevenue', 100000)
        ->where('analytics.summary.paidOrders', 1)
        ->where('analytics.comparison.confirmedRevenue', null));
});

test('dashboard safely defaults an unknown empty period', function (): void {
    $this->get(route('admin.dashboard', ['period' => 'unknown']))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('analytics.period.selected', 'this_week')
        ->where('analytics.summary.confirmedRevenue', 0)
        ->where('analytics.summary.paidOrders', 0)
        ->where('analytics.summary.averageOrderValue', 0)
        ->where('analytics.summary.itemsSold', 0)
        ->where('analytics.comparison.confirmedRevenue', 0)
        ->where('analytics.comparison.averageOrderValue', 0)
        ->has('analytics.chart.points', 7)
        ->has('analytics.topProducts', 0)
        ->has('analytics.lowStock', 0));
});

test('dashboard resolves presets and elapsed comparisons in WIB', function (string $period, string $start, string $end, string $comparisonStart, string $comparisonUntil): void {
    Carbon::setTestNow('2026-03-31 03:30:00');

    $this->get(route('admin.dashboard', ['period' => $period]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('analytics.period.start', $start)
        ->where('analytics.period.end', $end)
        ->where('analytics.period.today', '2026-03-31')
        ->where('analytics.period.timezone', 'WIB')
        ->where('analytics.period.comparisonStart', $comparisonStart.'+07:00')
        ->where('analytics.period.comparisonEndExclusive', $comparisonUntil.'+07:00'));
})->with([
    'today' => ['today', '2026-03-31', '2026-03-31', '2026-03-30T00:00:00', '2026-03-30T10:30:00'],
    'yesterday' => ['yesterday', '2026-03-30', '2026-03-30', '2026-03-29T00:00:00', '2026-03-30T00:00:00'],
    'this week' => ['this_week', '2026-03-30', '2026-03-31', '2026-03-23T00:00:00', '2026-03-24T10:30:00'],
    'last week' => ['last_week', '2026-03-23', '2026-03-29', '2026-03-16T00:00:00', '2026-03-23T00:00:00'],
    'this month clamps February' => ['this_month', '2026-03-01', '2026-03-31', '2026-02-01T00:00:00', '2026-02-28T10:30:00'],
    'last month' => ['last_month', '2026-02-01', '2026-02-28', '2026-01-01T00:00:00', '2026-02-01T00:00:00'],
    'legacy current' => ['current', '2026-03-30', '2026-03-31', '2026-03-23T00:00:00', '2026-03-24T10:30:00'],
    'legacy previous' => ['previous', '2026-03-23', '2026-03-29', '2026-03-16T00:00:00', '2026-03-23T00:00:00'],
]);

test('custom dates filter all analytics at Jakarta midnight using exclusive UTC boundaries', function (): void {
    $variant = ProductVariant::factory()->create();
    foreach (['2026-10-17 16:59:59', '2026-10-17 17:00:00', '2026-10-18 16:59:59', '2026-10-18 17:00:00'] as $index => $timestamp) {
        $order = dashboardOrder($index === 1 ? 'payment_review' : 'completed', $timestamp, 10000);
        dashboardPayment($order, 'paid', $timestamp);
        dashboardItem($order, $variant, 'Snapshot coffee', 1);
    }

    $this->get(route('admin.dashboard', ['period' => 'custom', 'start' => '2026-10-18', 'end' => '2026-10-18']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('analytics.period.selected', 'custom')
        ->where('analytics.period.label', '18 Okt 2026')
        ->where('analytics.period.comparisonLabel', '17 Okt 2026')
        ->where('analytics.period.cutoff', null)
        ->where('analytics.summary.confirmedRevenue', 20000)
        ->where('analytics.summary.paidOrders', 2)
        ->where('analytics.summary.itemsSold', 2)
        ->where('analytics.summary.paymentReviews', 1)
        ->where('analytics.chart.granularity', 'day')
        ->has('analytics.chart.points', 1)
        ->where('analytics.chart.points.0.revenue', 20000)
        ->where('analytics.topProducts.0.quantity', 2));
});

test('custom range ending today matches the last comparison day time and excludes future payments', function (): void {
    Carbon::setTestNow('2026-10-20 03:30:00');
    foreach (['2026-10-20 03:29:59', '2026-10-20 03:30:00', '2026-10-18 03:29:59', '2026-10-18 03:30:00'] as $timestamp) {
        dashboardPayment(dashboardOrder('completed', $timestamp, 10000), 'paid', $timestamp);
    }
    $this->get(route('admin.dashboard', ['period' => 'custom', 'start' => '2026-10-19', 'end' => '2026-10-20']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('analytics.period.cutoff', '10:30 WIB')
        ->where('analytics.period.comparisonStart', '2026-10-17T00:00:00+07:00')
        ->where('analytics.period.comparisonEndExclusive', '2026-10-18T10:30:00+07:00')
        ->where('analytics.summary.confirmedRevenue', 10000)
        ->where('analytics.comparison.confirmedRevenue', 0));
});

test('invalid calendar filters redirect safely with field errors', function (array $query, string $field): void {
    $this->get(route('admin.dashboard', $query))->assertRedirectToRoute('admin.dashboard')->assertSessionHasErrors($field);
    $this->get(route('admin.dashboard'))->assertOk();
})->with([
    'missing dates' => [['period' => 'custom'], 'start'],
    'missing end' => [['period' => 'custom', 'start' => '2026-10-01'], 'end'],
    'malformed date' => [['period' => 'custom', 'start' => 'hello', 'end' => '2026-10-01'], 'start'],
    'impossible date' => [['period' => 'custom', 'start' => '2026-02-30', 'end' => '2026-03-01'], 'start'],
    'reverse dates' => [['period' => 'custom', 'start' => '2026-10-02', 'end' => '2026-10-01'], 'end'],
    'future date' => [['period' => 'custom', 'start' => '2026-10-01', 'end' => '2026-10-26'], 'end'],
    '367 days' => [['period' => 'custom', 'start' => '2025-01-01', 'end' => '2026-01-02'], 'end'],
    'array preset' => [['period' => ['custom']], 'period'],
    'array date' => [['period' => 'custom', 'start' => ['2026-10-01'], 'end' => '2026-10-02'], 'start'],
]);

test('charts group selected dates and retain clipped buckets and zero values', function (string $start, string $end, string $granularity, int $count, string $firstEnd, string $lastStart): void {
    $first = Carbon::parse($start, 'Asia/Jakarta')->utc()->toDateTimeString();
    $last = Carbon::parse($end, 'Asia/Jakarta')->utc()->toDateTimeString();
    foreach ([$first, $last] as $timestamp) {
        dashboardPayment(dashboardOrder('completed', $timestamp, 10000), 'paid', $timestamp);
    }

    $response = $this->get(route('admin.dashboard', ['period' => 'custom', 'start' => $start, 'end' => $end]));
    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('analytics.chart.granularity', $granularity)
        ->has('analytics.chart.points', $count)
        ->where('analytics.chart.points.0.start', $start)
        ->where('analytics.chart.points.0.end', $firstEnd)
        ->where('analytics.chart.points.'.($count - 1).'.start', $lastStart)
        ->where('analytics.chart.points.'.($count - 1).'.end', $end)
        ->where('analytics.summary.confirmedRevenue', 20000));
    $points = $response->original->getData()['page']['props']['analytics']['chart']['points'];
    expect(array_sum(array_column($points, 'revenue')))->toBe(20000);
    expect($points[1]['revenue'])->toBe(0);
})->with([
    '31 daily days' => ['2026-01-01', '2026-01-31', 'day', 31, '2026-01-01', '2026-01-31'],
    '32 weekly days' => ['2026-01-01', '2026-02-01', 'week', 5, '2026-01-04', '2026-01-26'],
    '90 weekly days' => ['2026-01-01', '2026-03-31', 'week', 14, '2026-01-04', '2026-03-30'],
    '91 monthly days' => ['2026-01-01', '2026-04-01', 'month', 4, '2026-01-31', '2026-04-01'],
    '366 days' => ['2025-01-01', '2026-01-01', 'month', 13, '2025-01-31', '2026-01-01'],
    'leap year 366 days' => ['2024-01-01', '2024-12-31', 'month', 12, '2024-01-31', '2024-12-01'],
]);

test('WIB today can differ from UTC and presets ignore leftover custom dates', function (): void {
    Carbon::setTestNow('2026-12-31 18:00:00');
    $this->get(route('admin.dashboard', ['period' => 'today', 'start' => ['invalid'], 'end' => 'bad']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('analytics.period.start', '2027-01-01')
        ->where('analytics.period.end', '2027-01-01')
        ->where('analytics.period.comparisonLabel', '31 Des 2026')
        ->where('analytics.period.cutoff', '01:00 WIB'));
});

test('leap year and Monday comparisons preserve local calendar boundaries', function (): void {
    Carbon::setTestNow('2024-03-31 03:30:00');
    $this->get(route('admin.dashboard', ['period' => 'this_month']))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('analytics.period.comparisonEndExclusive', '2024-02-29T10:30:00+07:00'));
    Carbon::setTestNow('2026-01-04 18:00:00');
    $this->get(route('admin.dashboard', ['period' => 'this_week']))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('analytics.period.start', '2026-01-05')
        ->where('analytics.period.end', '2026-01-05')
        ->where('analytics.period.comparisonEndExclusive', '2025-12-29T01:00:00+07:00'));
});
