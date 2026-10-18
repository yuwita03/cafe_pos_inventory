<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RecipeItem;
use App\Models\StockItem;
use App\Models\User;
use App\ProductStockConfiguration;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create(['role' => 'owner']));
});

/** @return array<string, mixed> */
function finishedProductPayload(int $opening = 20): array
{
    return ['name' => 'Donat', 'category_id' => Category::factory()->create()->id, 'description' => '', 'is_active' => true,
        'variants' => [['name' => 'Regular', 'price_idr' => 10000, 'is_available' => true,
            'stock' => ['kind' => 'finished', 'stock_item_id' => null, 'opening_qty' => $opening, 'reorder_point' => 5, 'ingredients' => []]]],
        'groups' => []];
}

test('creating a finished product links pcs stock and records opening stock before its first cash sale', function (): void {
    $payload = finishedProductPayload();
    $this->post(route('admin.products.store'), $payload)->assertSessionHasNoErrors();
    $variant = ProductVariant::firstOrFail();
    $stock = $variant->stockItem;
    expect($stock)->not->toBeNull();
    expect($stock->name)->toBe('Donat')->and($stock->base_unit)->toBe('pcs')
        ->and($stock->on_hand_qty)->toBe('20.000')->and($stock->reorder_point)->toBe('5.000');
    $this->assertDatabaseHas('stock_movements', ['stock_item_id' => $stock->id, 'quantity_delta' => 20, 'reason' => 'Stok awal', 'actor_user_id' => auth()->id()]);
    $this->get(route('admin.menu'))->assertInertia(fn (Assert $page) => $page
        ->where('catalog.products.0.variants.0.stock.method', 'finished')
        ->where('catalog.products.0.variants.0.stock.stockId', $stock->id)
        ->where('stockItems.0.name', 'Donat'));

    $sale = $this->postJson(route('admin.pos.checkout'), ['checkout_key' => (string) Str::uuid(), 'customer_name' => 'Alya',
        'fulfillment_type' => 'takeaway', 'table_id' => null, 'expected_total_idr' => 20000, 'cash_received_idr' => 20000,
        'items' => [['variant_id' => $variant->id, 'option_ids' => [], 'quantity' => 2]],
    ])->assertOk();
    expect($stock->fresh()->on_hand_qty)->toBe('20.000');
    $this->assertDatabaseHas('stock_reservations', ['order_id' => $sale->json('order.id'), 'stock_item_id' => $stock->id, 'quantity' => 2, 'status' => 'active']);
    $this->post(route('admin.kitchen.advance', $sale->json('order.id')), ['expected_status' => 'queued'])->assertRedirect()->assertSessionHasNoErrors();
    expect($stock->fresh()->on_hand_qty)->toBe('18.000');
    $this->assertDatabaseCount('stock_movements', 2);
});

test('zero opening stock produces no movement and stock name collisions preserve the existing stock', function (): void {
    $existing = StockItem::factory()->create(['name' => 'Donat', 'base_unit' => 'pcs', 'on_hand_qty' => 7]);
    $this->post(route('admin.products.store'), finishedProductPayload(0))->assertSessionHasNoErrors();
    $variant = ProductVariant::firstOrFail();
    expect($variant->stock_item_id)->not->toBe($existing->id);
    expect($variant->stockItem->name)->toBe('Donat · '.$variant->id)
        ->and($variant->stockItem->on_hand_qty)->toBe('0.000')
        ->and($existing->fresh()->on_hand_qty)->toBe('7.000');
    $this->assertDatabaseCount('stock_movements', 0);
});

test('product recipes share ingredients across variants without changing ingredient stock', function (string $balance): void {
    $beans = StockItem::factory()->create(['base_unit' => 'g', 'on_hand_qty' => $balance]);
    $payload = finishedProductPayload();
    $payload['name'] = 'Americano';
    $payload['variants'] = collect(['Hot', 'Iced'])->map(fn ($name) => ['name' => $name, 'price_idr' => 20000, 'is_available' => true,
        'stock' => ['kind' => 'ingredients', 'ingredients' => [['stock_item_id' => $beans->id, 'quantity' => '18.125']]],
    ])->all();
    $this->post(route('admin.products.store'), $payload)->assertSessionHasNoErrors();
    expect(RecipeItem::count())->toBe(2)->and($beans->fresh()->on_hand_qty)->toBe($balance);
    expect(ProductVariant::whereNotNull('stock_item_id')->count())->toBe(0);
    $this->assertDatabaseCount('stock_movements', 0);
})->with(['0.000', '1000.000']);

test('editing a finished product preserves its balance connection and opening movement across repeated saves', function (): void {
    $payload = finishedProductPayload();
    $this->post(route('admin.products.store'), $payload)->assertSessionHasNoErrors();
    $variant = ProductVariant::firstOrFail();
    $stockId = $variant->stock_item_id;
    $payload['name'] = 'Donat Baru';
    $payload['variants'][0]['id'] = $variant->id;
    $payload['variants'][0]['price_idr'] = 12000;
    $payload['variants'][0]['stock']['stock_item_id'] = $stockId;
    unset($payload['variants'][0]['stock']['opening_qty']);
    foreach ([1, 2] as $attempt) {
        $this->put(route('admin.products.update', $variant->product_id), $payload)->assertSessionHasNoErrors();
    }
    expect($variant->fresh()->stock_item_id)->toBe($stockId)
        ->and($variant->fresh()->price_idr)->toBe(12000)
        ->and($variant->stockItem->fresh()->on_hand_qty)->toBe('20.000');
    $this->assertDatabaseCount('stock_items', 1);
    $this->assertDatabaseCount('stock_movements', 1);

    $payload['variants'][0]['stock']['opening_qty'] = 99;
    $this->put(route('admin.products.update', $variant->product_id), $payload)->assertSessionHasErrors('variants.0.stock.opening_qty');
    expect($variant->stockItem->fresh()->on_hand_qty)->toBe('20.000');
});

test('finished stock cannot be reassigned or cleared through product edits', function (bool $clear): void {
    $payload = finishedProductPayload();
    $this->post(route('admin.products.store'), $payload)->assertSessionHasNoErrors();
    $variant = ProductVariant::firstOrFail();
    $originalStock = $variant->stockItem;
    $sharedStock = StockItem::factory()->create(['base_unit' => 'pcs', 'on_hand_qty' => 30]);
    $payload['variants'][0]['id'] = $variant->id;
    $payload['name'] = 'Must roll back';
    $payload['variants'][0]['stock']['stock_item_id'] = $clear ? null : $sharedStock->id;
    unset($payload['variants'][0]['stock']['opening_qty']);
    $this->put(route('admin.products.update', $variant->product_id), $payload)->assertSessionHasErrors('variants');
    expect($variant->fresh()->stock_item_id)->toBe($originalStock->id)
        ->and($variant->product->fresh()->name)->toBe('Donat')
        ->and($originalStock->fresh()->on_hand_qty)->toBe('20.000')
        ->and($sharedStock->fresh()->on_hand_qty)->toBe('30.000');
    $this->assertDatabaseCount('stock_movements', 1);
})->with([false, true]);

test('metadata only legacy saves preserve finished stock connections', function (): void {
    $stock = StockItem::factory()->create(['base_unit' => 'pcs', 'on_hand_qty' => 11]);
    $variant = ProductVariant::factory()->create(['stock_item_id' => $stock->id]);
    $payload = ['name' => 'Renamed', 'category_id' => $variant->product->category_id, 'is_active' => true,
        'variants' => [['id' => $variant->id, 'name' => $variant->name, 'price_idr' => 15000, 'is_available' => true]], 'groups' => []];
    $this->put(route('admin.products.update', $variant->product_id), $payload)->assertSessionHasNoErrors();
    expect($variant->fresh()->stock_item_id)->toBe($stock->id)->and($stock->fresh()->on_hand_qty)->toBe('11.000');
    $this->assertDatabaseCount('stock_movements', 0);
});

test('an unconfigured existing variant can receive its first stock setup', function (string $kind): void {
    $variant = ProductVariant::factory()->create(['stock_item_id' => null]);
    $beans = StockItem::factory()->create(['base_unit' => 'g', 'on_hand_qty' => 500]);
    $payload = finishedProductPayload();
    $payload['variants'][0]['id'] = $variant->id;
    $payload['variants'][0]['stock'] = $kind === 'finished'
        ? ['kind' => 'finished', 'reorder_point' => 5]
        : ['kind' => 'ingredients', 'ingredients' => [['stock_item_id' => $beans->id, 'quantity' => 18]]];

    $this->put(route('admin.products.update', $variant->product_id), $payload)->assertSessionHasNoErrors();

    $variant->refresh();
    if ($kind === 'finished') {
        expect($variant->stockItem)->not->toBeNull()
            ->and($variant->stockItem->base_unit)->toBe('pcs')
            ->and($variant->stockItem->on_hand_qty)->toBe('0.000')
            ->and($variant->stockItem->reorder_point)->toBe('5.000');
        $this->assertDatabaseCount('recipe_items', 0);
    } else {
        expect($variant->stock_item_id)->toBeNull();
        $this->assertDatabaseHas('recipe_items', ['product_variant_id' => $variant->id, 'stock_item_id' => $beans->id, 'quantity' => 18]);
    }
    expect($beans->fresh()->on_hand_qty)->toBe('500.000');
    $this->assertDatabaseCount('stock_movements', 0);
})->with(['finished', 'ingredients']);

test('a failure in a later variant rolls back the product new stock opening movement and recipes together', function (): void {
    $inactive = StockItem::factory()->create(['base_unit' => 'g', 'is_active' => false]);
    $payload = finishedProductPayload();
    $payload['variants'][] = ['name' => 'Other', 'price_idr' => 20000, 'is_available' => true,
        'stock' => ['kind' => 'ingredients', 'ingredients' => [['stock_item_id' => $inactive->id, 'quantity' => 18]]]];
    $this->post(route('admin.products.store'), $payload)->assertSessionHasErrors('variants.1.stock.ingredients.0.stock_item_id');
    $this->assertDatabaseCount('products', 0);
    $this->assertDatabaseCount('product_variants', 0);
    $this->assertDatabaseCount('stock_items', 1);
    $this->assertDatabaseCount('stock_movements', 0);
});

test('finished opening quantities reject negative fractional and excessive values', function (mixed $quantity): void {
    $payload = finishedProductPayload();
    $payload['variants'][0]['stock']['opening_qty'] = $quantity;
    $this->post(route('admin.products.store'), $payload)->assertSessionHasErrors('variants.0.stock.opening_qty');
    $this->assertDatabaseCount('products', 0);
    $this->assertDatabaseCount('stock_items', 0);
})->with([-1, 1.5, 1000000000]);

test('duplicate recipe rows and finished items used as ingredients are rejected', function (): void {
    $stock = StockItem::factory()->create(['base_unit' => 'g']);
    $payload = finishedProductPayload();
    $payload['variants'][0]['stock'] = ['kind' => 'ingredients', 'ingredients' => [
        ['stock_item_id' => $stock->id, 'quantity' => 18], ['stock_item_id' => $stock->id, 'quantity' => 2],
    ]];
    $this->post(route('admin.products.store'), $payload)->assertSessionHasErrors('variants.0.stock.ingredients');
    $stock->update(['base_unit' => 'pcs']);
    $payload['variants'][0]['stock']['ingredients'] = [['stock_item_id' => $stock->id, 'quantity' => 1]];
    $this->post(route('admin.products.store'), $payload)->assertSessionHasErrors('variants.0.stock.ingredients.0.stock_item_id');
    $this->assertDatabaseCount('products', 0);
});

test('empty stock configuration and attempts to link old stock during creation are rejected', function (): void {
    $payload = finishedProductPayload();
    $payload['variants'][0]['stock'] = [];
    $this->post(route('admin.products.store'), $payload)->assertSessionHasErrors('variants.0.stock.kind');
    $stock = StockItem::factory()->create(['base_unit' => 'pcs', 'on_hand_qty' => 8]);
    $payload['variants'][0]['stock'] = ['kind' => 'finished', 'stock_item_id' => $stock->id, 'opening_qty' => 20];
    $this->post(route('admin.products.store'), $payload)->assertSessionHasErrors('variants.0.stock.stock_item_id');
    expect($stock->fresh()->on_hand_qty)->toBe('8.000');
    $this->assertDatabaseCount('products', 0);
    $this->assertDatabaseCount('stock_movements', 0);
});

test('configured methods are fixed and recipes cannot be emptied but quantities remain editable', function (): void {
    $payload = finishedProductPayload();
    $this->post(route('admin.products.store'), $payload)->assertSessionHasNoErrors();
    $variant = ProductVariant::firstOrFail();
    $original = $variant->stockItem;
    $beans = StockItem::factory()->create(['base_unit' => 'g', 'on_hand_qty' => 500]);
    $payload['variants'][0]['id'] = $variant->id;
    $payload['variants'][0]['stock'] = ['kind' => 'ingredients', 'ingredients' => [['stock_item_id' => $beans->id, 'quantity' => '18.125']]];
    $this->put(route('admin.products.update', $variant->product_id), $payload)->assertSessionHasErrors('variants');
    expect($variant->fresh()->stock_item_id)->toBe($original->id)
        ->and($original->fresh()->on_hand_qty)->toBe('20.000')->and($beans->fresh()->on_hand_qty)->toBe('500.000');

    $recipeVariant = ProductVariant::factory()->create(['name' => 'Regular']);
    RecipeItem::factory()->create(['product_variant_id' => $recipeVariant->id, 'stock_item_id' => $beans->id, 'quantity' => '18.125']);
    $payload['variants'][0]['id'] = $recipeVariant->id;
    $payload['variants'][0]['stock'] = ['kind' => 'finished', 'reorder_point' => 5];
    $url = route('admin.products.update', $recipeVariant->product_id);
    $this->put($url, $payload)->assertSessionHasErrors('variants');
    $payload['variants'][0]['stock'] = ['kind' => 'ingredients', 'ingredients' => []];
    $this->put($url, $payload)->assertSessionHasErrors('variants.0.stock.ingredients');
    expect($recipeVariant->fresh()->stock_item_id)->toBeNull()->and($recipeVariant->recipeItems()->first()->quantity)->toBe('18.125');
    $payload['variants'][0]['stock']['ingredients'] = [['stock_item_id' => $beans->id, 'quantity' => '25.000']];
    $this->put($url, $payload)->assertSessionHasNoErrors();
    expect($recipeVariant->recipeItems()->first()->quantity)->toBe('25.000');
    $this->assertDatabaseCount('stock_items', 2);
    $this->assertDatabaseCount('stock_movements', 1);
});

test('long product names generate distinct stock names within the stock name limit', function (): void {
    $payload = finishedProductPayload(0);
    $payload['name'] = str_repeat('A', 120);
    $payload['variants'][0]['name'] = 'Hot';
    $payload['variants'][] = [...$payload['variants'][0], 'name' => 'Iced'];
    $this->post(route('admin.products.store'), $payload)->assertSessionHasNoErrors();
    $names = StockItem::pluck('name');
    expect($names)->toHaveCount(2)->and($names->unique())->toHaveCount(2);
    foreach ($names as $name) {
        expect(mb_strlen($name))->toBeLessThanOrEqual(120);
    }
});

test('shared finished stock rejects conflicting thresholds atomically and accepts matching thresholds', function (): void {
    $stock = StockItem::factory()->create(['base_unit' => 'pcs', 'on_hand_qty' => 20, 'reorder_point' => 5]);
    $product = Product::factory()->create();
    $originalName = $product->name;
    $first = ProductVariant::factory()->for($product)->create(['name' => 'Plain', 'stock_item_id' => $stock->id]);
    $second = ProductVariant::factory()->for($product)->create(['name' => 'Warm', 'stock_item_id' => $stock->id]);
    $payload = finishedProductPayload();
    $payload['variants'] = collect([$first, $second])->map(fn (ProductVariant $variant, int $index): array => [
        'id' => $variant->id, 'name' => $variant->name, 'price_idr' => 10000, 'is_available' => true,
        'stock' => ['kind' => 'finished', 'stock_item_id' => $stock->id, 'reorder_point' => $index === 0 ? 10 : 5],
    ])->all();
    $this->put(route('admin.products.update', $product), $payload)->assertSessionHasErrors('variants');
    expect($stock->fresh()->reorder_point)->toBe('5.000')->and($product->fresh()->name)->toBe($originalName)
        ->and($first->fresh()->name)->toBe('Plain')->and($second->fresh()->name)->toBe('Warm');
    $payload['variants'][1]['stock']['stock_item_id'] = '+'.$stock->id;
    $this->put(route('admin.products.update', $product), $payload)->assertSessionHasErrors('variants');
    expect($stock->fresh()->reorder_point)->toBe('5.000');
    unset($payload['variants'][0]['stock']['stock_item_id'], $payload['variants'][1]['stock']['stock_item_id']);
    $payload['variants'][1]['id'] = '+'.$second->id;
    $this->put(route('admin.products.update', $product), $payload)->assertSessionHasErrors('variants');
    expect($stock->fresh()->reorder_point)->toBe('5.000');
    $payload['variants'][1]['stock']['reorder_point'] = 10;
    $this->put(route('admin.products.update', $product), $payload)->assertSessionHasNoErrors();
    expect($stock->fresh()->reorder_point)->toBe('10.000')->and($stock->fresh()->on_hand_qty)->toBe('20.000');
    $this->assertDatabaseCount('stock_movements', 0);
});

test('new variants require stock setup including variants added to an existing product', function (bool $editing): void {
    $payload = finishedProductPayload(0);
    unset($payload['variants'][0]['stock']);
    if ($editing) {
        $variant = ProductVariant::factory()->create();
        $payload['variants'][] = ['id' => $variant->id, 'name' => 'Existing', 'price_idr' => 20000, 'is_available' => true];
        $this->put(route('admin.products.update', $variant->product_id), $payload)->assertSessionHasErrors('variants.0.stock.kind');
        expect($variant->product->fresh()->name)->not->toBe('Donat');
        $this->assertDatabaseCount('product_variants', 1);
    } else {
        $this->post(route('admin.products.store'), $payload)->assertSessionHasErrors('variants.0.stock.kind');
        $this->assertDatabaseCount('products', 0);
    }
    $this->assertDatabaseCount('stock_items', 0);
})->with([false, true]);

test('ingredient mode requires a nonempty recipe when creating and editing', function (bool $editing, bool $omit): void {
    $payload = finishedProductPayload(0);
    $payload['variants'][0]['stock'] = ['kind' => 'ingredients'];
    if (! $omit) {
        $payload['variants'][0]['stock']['ingredients'] = [];
    }
    if ($editing) {
        $variant = ProductVariant::factory()->create();
        $originalName = $variant->product->name;
        $payload['variants'][0]['id'] = $variant->id;
        $this->put(route('admin.products.update', $variant->product_id), $payload)->assertSessionHasErrors('variants.0.stock.ingredients');
        expect($variant->product->fresh()->name)->toBe($originalName);
    } else {
        $this->post(route('admin.products.store'), $payload)->assertSessionHasErrors('variants.0.stock.ingredients');
        $this->assertDatabaseCount('products', 0);
    }
    $this->assertDatabaseCount('recipe_items', 0);
    $this->assertDatabaseCount('stock_movements', 0);
})->with([false, true])->with([false, true]);

test('one incomplete recipe prevents saving all variants and preserves existing recipes', function (): void {
    $stock = StockItem::factory()->create(['base_unit' => 'g', 'on_hand_qty' => 0]);
    $variant = ProductVariant::factory()->create(['name' => 'Hot']);
    RecipeItem::factory()->create(['product_variant_id' => $variant->id, 'stock_item_id' => $stock->id, 'quantity' => 18]);
    $originalName = $variant->product->name;
    $payload = finishedProductPayload(0);
    $payload['variants'] = [
        ['id' => $variant->id, 'name' => 'Hot', 'price_idr' => 20000, 'is_available' => true, 'stock' => ['kind' => 'ingredients', 'ingredients' => [['stock_item_id' => $stock->id, 'quantity' => 25]]]],
        ['name' => 'Iced', 'price_idr' => 25000, 'is_available' => true, 'stock' => ['kind' => 'ingredients', 'ingredients' => []]],
    ];
    $this->put(route('admin.products.update', $variant->product_id), $payload)->assertSessionHasErrors('variants.1.stock.ingredients');
    expect($variant->product->fresh()->name)->toBe($originalName)
        ->and($variant->recipeItems()->first()->quantity)->toBe('18.000');
    $this->assertDatabaseCount('product_variants', 1);
    $this->assertDatabaseCount('stock_movements', 0);
});

test('invalid recipe quantities and inactive ingredients cannot be saved', function (mixed $quantity, bool $active, string $field): void {
    $stock = StockItem::factory()->create(['base_unit' => 'g', 'on_hand_qty' => 0, 'is_active' => $active]);
    $payload = finishedProductPayload(0);
    $payload['variants'][0]['stock'] = ['kind' => 'ingredients', 'ingredients' => [['stock_item_id' => $stock->id, 'quantity' => $quantity]]];
    $this->post(route('admin.products.store'), $payload)->assertSessionHasErrors('variants.0.stock.ingredients.0.'.$field);
    $this->assertDatabaseCount('products', 0);
    $this->assertDatabaseCount('recipe_items', 0);
})->with([[0, true, 'quantity'], [null, true, 'quantity'], ['0.0001', true, 'quantity'], [18, false, 'stock_item_id']]);

test('stock configuration rejects empty recipes even outside product form validation', function (): void {
    $variant = ProductVariant::factory()->create();
    expect(fn () => app(ProductStockConfiguration::class)->save($variant, ['kind' => 'ingredients', 'ingredients' => []], true, auth()->id()))
        ->toThrow(ValidationException::class);
    $this->assertDatabaseCount('recipe_items', 0);
});
