<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\StockItem;
use App\StockQuantity;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const TIMEZONE = 'Asia/Jakarta';

    private const PERIODS = ['today' => 'Hari ini', 'yesterday' => 'Kemarin', 'this_week' => 'Minggu ini', 'last_week' => 'Minggu lalu', 'this_month' => 'Bulan ini', 'last_month' => 'Bulan lalu', 'custom' => 'Rentang khusus'];

    private const MONTH_LABELS = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    public function __invoke(Request $request): Response|RedirectResponse
    {
        $now = CarbonImmutable::instance(now())->setTimezone(self::TIMEZONE);
        $today = $now->startOfDay();
        $validator = Validator::make($request->query(), [
            'period' => ['nullable', 'string'],
            'start' => $request->query('period') === 'custom' ? ['bail', 'required', 'string', 'date_format:Y-m-d', 'before_or_equal:'.$today->toDateString()] : ['exclude'],
            'end' => $request->query('period') === 'custom' ? ['bail', 'required', 'string', 'date_format:Y-m-d', 'before_or_equal:'.$today->toDateString()] : ['exclude'],
        ]);
        $validator->after(function ($validator) use ($request): void {
            if ($request->query('period') !== 'custom' || $validator->errors()->isNotEmpty()) {
                return;
            }
            $start = CarbonImmutable::parse($request->query('start'), self::TIMEZONE);
            $end = CarbonImmutable::parse($request->query('end'), self::TIMEZONE);
            if ($end->lt($start)) {
                $validator->errors()->add('end', 'Tanggal akhir harus sama atau setelah tanggal mulai.');

                return;
            }
            if ($start->diffInDays($end) >= 366) {
                $validator->errors()->add('end', 'Rentang tanggal maksimal 366 hari.');
            }
        });
        if ($validator->fails()) {
            return to_route('admin.dashboard')->withErrors($validator);
        }

        $input = $validator->validated();
        $period = match ($input['period'] ?? '') {
            'current' => 'this_week',
            'previous' => 'last_week',
            default => array_key_exists($input['period'] ?? '', self::PERIODS) ? $input['period'] : 'this_week',
        };
        $start = match ($period) {
            'today' => $today,
            'yesterday' => $today->subDay(),
            'last_week' => $today->startOfWeek(CarbonInterface::MONDAY)->subWeek(),
            'this_month' => $today->startOfMonth(),
            'last_month' => $today->startOfMonth()->subMonth(),
            'custom' => CarbonImmutable::parse($input['start'], self::TIMEZONE),
            default => $today->startOfWeek(CarbonInterface::MONDAY),
        };
        $endDate = match ($period) {
            'yesterday' => $start,
            'last_week' => $start->addDays(6),
            'last_month' => $start->endOfMonth()->startOfDay(),
            'custom' => CarbonImmutable::parse($input['end'], self::TIMEZONE),
            default => $today,
        };
        $ongoing = $endDate->isSameDay($today);
        $until = $ongoing ? $now : $endDate->addDay();
        $days = (int) $start->diffInDays($endDate) + 1;
        $comparisonStart = match ($period) {
            'today', 'yesterday' => $start->subDay(),
            'this_week', 'last_week' => $start->subWeek(),
            'this_month', 'last_month' => $start->subMonth(),
            default => $start->subDays($days),
        };
        $comparisonUntil = match ($period) {
            'today', 'yesterday' => $until->subDay(),
            'this_week', 'last_week' => $until->subWeek(),
            'this_month' => $now->subMonthNoOverflow(),
            'last_month' => $start,
            default => $until->subDays($days),
        };
        $comparisonEndDate = $ongoing ? $comparisonUntil : $comparisonUntil->subDay();
        $summary = $this->salesSummary($start, $until);
        $comparison = $this->salesSummary($comparisonStart, $comparisonUntil);

        return Inertia::render('admin/Dashboard', [
            'analytics' => [
                'period' => [
                    'selected' => $period,
                    'label' => $this->periodLabel($start, $endDate),
                    'start' => $start->toDateString(),
                    'end' => $endDate->toDateString(),
                    'comparisonLabel' => $this->periodLabel($comparisonStart, $comparisonEndDate),
                    'comparisonStart' => $comparisonStart->toIso8601String(),
                    'comparisonEndExclusive' => $comparisonUntil->toIso8601String(),
                    'timezone' => 'WIB',
                    'today' => $today->toDateString(),
                    'cutoff' => $ongoing ? $now->format('H:i').' WIB' : null,
                    'options' => collect(self::PERIODS)->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])->values()->all(),
                ],
                'summary' => [
                    ...$summary,
                    'paymentReviews' => Order::where('status', 'payment_review')->where('created_at', '>=', $start->utc()->toDateTimeString())->where('created_at', '<', $until->utc()->toDateTimeString())->count(),
                ],
                'comparison' => [
                    'confirmedRevenue' => $this->percentageChange($summary['confirmedRevenue'], $comparison['confirmedRevenue']),
                    'paidOrders' => $this->percentageChange($summary['paidOrders'], $comparison['paidOrders']),
                    'averageOrderValue' => $this->percentageChange($summary['averageOrderValue'], $comparison['averageOrderValue']),
                    'itemsSold' => $this->percentageChange($summary['itemsSold'], $comparison['itemsSold']),
                ],
                'chart' => $this->salesChart($start, $until, $endDate, $days),
                'topProducts' => $this->topProducts($start, $until),
                'lowStock' => $this->lowStock(),
            ],
        ]);
    }

    private function paidPayments(CarbonInterface $start, CarbonInterface $until): Builder
    {
        return DB::table('payments')
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->where('payments.status', 'paid')
            ->where('orders.status', '!=', 'cancelled')
            ->where('payments.paid_at', '>=', $start->copy()->utc()->toDateTimeString())
            ->where('payments.paid_at', '<', $until->copy()->utc()->toDateTimeString());
    }

    /** @return array{confirmedRevenue: int, paidOrders: int, averageOrderValue: int, itemsSold: int} */
    private function salesSummary(CarbonInterface $start, CarbonInterface $end): array
    {
        $payments = $this->paidPayments($start, $end);
        $totals = (clone $payments)->selectRaw('COUNT(*) AS paid_orders, COALESCE(SUM(payments.amount_idr), 0) AS confirmed_revenue')->first();
        $paidOrders = (int) $totals->paid_orders;
        $confirmedRevenue = (int) $totals->confirmed_revenue;
        $itemsSold = (int) $this->paidPayments($start, $end)
            ->join('order_items', 'order_items.order_id', '=', 'payments.order_id')
            ->sum('order_items.quantity');

        return [
            'confirmedRevenue' => $confirmedRevenue,
            'paidOrders' => $paidOrders,
            'averageOrderValue' => $paidOrders > 0 ? intdiv($confirmedRevenue, $paidOrders) : 0,
            'itemsSold' => $itemsSold,
        ];
    }

    /** @return array{granularity: string, points: list<array{start: string, end: string, label: string, revenue: int}>} */
    private function salesChart(CarbonImmutable $start, CarbonImmutable $until, CarbonImmutable $endDate, int $dayCount): array
    {
        $dateExpression = "DATE(payments.paid_at AT TIME ZONE 'UTC' AT TIME ZONE 'Asia/Jakarta')";
        $revenueByDate = $this->paidPayments($start, $until)
            ->selectRaw($dateExpression.' AS sale_date, SUM(payments.amount_idr) AS revenue')
            ->groupByRaw($dateExpression)
            ->pluck('revenue', 'sale_date');
        $granularity = $dayCount <= 31 ? 'day' : ($dayCount <= 90 ? 'week' : 'month');
        $points = [];

        for ($date = $start; $date->lte($endDate); $date = $date->addDay()) {
            $bucket = match ($granularity) {
                'week' => $date->startOfWeek(CarbonInterface::MONDAY),
                'month' => $date->startOfMonth(),
                default => $date,
            };
            $key = $bucket->toDateString();
            if (! isset($points[$key])) {
                $points[$key] = ['start' => $date->toDateString(), 'end' => $date->toDateString(), 'label' => '', 'revenue' => 0];
            }
            $points[$key]['end'] = $date->toDateString();
            $points[$key]['label'] = $this->periodLabel(CarbonImmutable::parse($points[$key]['start'], self::TIMEZONE), $date);
            $points[$key]['revenue'] += (int) ($revenueByDate[$date->toDateString()] ?? 0);
        }

        return ['granularity' => $granularity, 'points' => array_values($points)];
    }

    /** @return list<array{name: string, quantity: int}> */
    private function topProducts(CarbonInterface $start, CarbonInterface $end): array
    {
        $products = $this->paidPayments($start, $end)
            ->join('order_items', 'order_items.order_id', '=', 'payments.order_id')
            ->groupBy('order_items.product_name_snapshot')
            ->selectRaw('order_items.product_name_snapshot AS name, SUM(order_items.quantity) AS quantity')
            ->orderByDesc('quantity')
            ->orderBy('name')
            ->limit(5)
            ->get()
            ->map(fn (object $product): array => ['name' => (string) $product->name, 'quantity' => (int) $product->quantity])
            ->all();

        return array_values($products);
    }

    /** @return list<array{id: int, name: string, unit: string, available: float, threshold: float}> */
    private function lowStock(): array
    {
        $reservations = DB::table('stock_reservations')
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->groupBy('stock_item_id')
            ->selectRaw('stock_item_id, SUM(quantity) AS quantity')
            ->pluck('quantity', 'stock_item_id');

        $items = StockItem::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function (StockItem $item) use ($reservations): ?array {
                $available = StockQuantity::milli($item->on_hand_qty) - StockQuantity::milli($reservations[$item->id] ?? '0');
                if ($available > StockQuantity::milli($item->reorder_point)) {
                    return null;
                }

                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'unit' => $item->base_unit,
                    'available' => (float) ($available / 1000),
                    'threshold' => (float) $item->reorder_point,
                ];
            })
            ->filter()
            ->all();

        return array_values($items);
    }

    private function percentageChange(int $current, int $previous): ?float
    {
        if ($previous === 0) {
            return $current === 0 ? 0.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function periodLabel(CarbonInterface $start, CarbonInterface $end): string
    {
        if ($start->isSameDay($end)) {
            return $start->format('j').' '.self::MONTH_LABELS[(int) $start->format('n')].' '.$start->format('Y');
        }
        if ($start->format('Y') !== $end->format('Y')) {
            return $this->periodLabel($start, $start).'–'.$this->periodLabel($end, $end);
        }
        $startMonth = self::MONTH_LABELS[(int) $start->format('n')];
        $endMonth = self::MONTH_LABELS[(int) $end->format('n')];

        if ($startMonth === $endMonth) {
            return $start->format('j').'–'.$end->format('j').' '.$endMonth.' '.$end->format('Y');
        }

        return $start->format('j').' '.$startMonth.'–'.$end->format('j').' '.$endMonth.' '.$end->format('Y');
    }
}
