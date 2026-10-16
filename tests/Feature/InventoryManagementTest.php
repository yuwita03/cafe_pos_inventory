<?php

use App\InventoryStock;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\OptionIngredient;
use App\Models\ProductVariant;
use App\Models\RecipeItem;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'owner']));
});

/** @return array{stock_item_id: int, quantity_delta: string, reason: string, source_key: string} */
function inventoryAdjustment(StockItem $item, string $delta = '100.000'): array
{
    return ['stock_item_id' => $item->id, 'quantity_delta' => $delta, 'reason' => 'Opening physical count', 'source_key' => (string) Str::uuid()];
}

test('stock items start at zero and persisted inventory pages do not contain sample stock', function (): void {
    $this->post(route('admin.inventory.items.store'), ['name' => 'Beans', 'base_unit' => 'g', 'reorder_point' => '100.125', 'is_active' => true])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('stock_items', ['name' => 'Beans', 'on_hand_qty' => '0.000', 'reorder_point' => '100.125']);
    $this->assertDatabaseCount('stock_movements', 0);
    foreach (['admin.inventory.index' => 'stock', 'admin.inventory.recipes' => 'recipes', 'admin.inventory.history' => 'history'] as $route => $section) {
        $this->get(route($route))->assertOk()->assertInertia(fn (Assert $page) => $page->component('admin/inventory/Index')->where('section', $section)->has('stockItems', 1)->where('stockItems.0.name', 'Beans')->where('stockItems.0.lowStock', true)->has('stockMovements', 0));
    }
});

test('stock metadata edits cannot alter balances or base units', function (): void {
    $item = StockItem::factory()->create();
    $data = ['name' => 'Renamed', 'base_unit' => 'g', 'reorder_point' => '0', 'is_active' => false];

    $this->put(route('admin.inventory.items.update', $item), $data)->assertSessionHasNoErrors();
    $this->put(route('admin.inventory.items.update', $item), [...$data, 'base_unit' => 'ml'])->assertSessionHasErrors('base_unit');
    $this->put(route('admin.inventory.items.update', $item), [...$data, 'on_hand_qty' => 999])->assertSessionHasErrors('on_hand_qty');
    $this->assertDatabaseHas('stock_items', ['id' => $item->id, 'name' => 'Renamed', 'base_unit' => 'g', 'on_hand_qty' => '0.000', 'is_active' => false]);
});

test('owner can create and remove ingredient quick add without changing recipes or stock', function (): void {
    $data = ['name' => 'Quick add milk', 'base_unit' => 'ml', 'reorder_point' => '10', 'is_active' => true, 'is_quick_add' => true];
    $this->post(route('admin.inventory.items.store'), $data)->assertSessionHasNoErrors();
    $item = StockItem::where('name', 'Quick add milk')->firstOrFail();
    $recipe = RecipeItem::factory()->create(['stock_item_id' => $item->id, 'quantity' => '12.125']);

    $this->get(route('admin.inventory.recipes'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('stockItems.0.quickAdd', true));
    $this->put(route('admin.inventory.items.update', $item), [...$data, 'is_quick_add' => false])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('stock_items', ['id' => $item->id, 'is_quick_add' => false, 'on_hand_qty' => '0.000']);
    $this->assertDatabaseHas('recipe_items', ['id' => $recipe->id, 'stock_item_id' => $item->id, 'quantity' => '12.125']);
    $this->assertDatabaseCount('stock_movements', 0);
    $this->get(route('admin.inventory.recipes'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('stockItems.0.quickAdd', false));
});

test('omitted quick add defaults off for new stock and preserves existing preferences on update', function (): void {
    $data = ['name' => 'Unpinned beans', 'base_unit' => 'g', 'reorder_point' => '0', 'is_active' => true];
    $this->post(route('admin.inventory.items.store'), $data)->assertSessionHasNoErrors();
    $this->assertDatabaseHas('stock_items', ['name' => 'Unpinned beans', 'is_quick_add' => false]);
    $item = StockItem::factory()->create(['is_quick_add' => true]);

    $this->put(route('admin.inventory.items.update', $item), [...$data, 'name' => $item->name])->assertSessionHasNoErrors();

    expect($item->fresh()->is_quick_add)->toBeTrue();
});

test('owner can set row quick add independently without overwriting stock metadata recipes or balances', function (): void {
    $item = StockItem::factory()->create(['name' => 'Row toggle milk', 'base_unit' => 'ml', 'on_hand_qty' => '25.125', 'reorder_point' => '5.000', 'is_active' => false]);
    $recipe = RecipeItem::factory()->create(['stock_item_id' => $item->id, 'quantity' => '2.125']);
    $url = route('admin.inventory.items.quickAdd', $item);

    $this->patch($url, ['is_quick_add' => true, 'name' => 'Must not overwrite', 'on_hand_qty' => '999', 'is_active' => true])->assertSessionHasNoErrors();
    $this->patch($url, ['is_quick_add' => true])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('stock_items', ['id' => $item->id, 'name' => 'Row toggle milk', 'is_quick_add' => true, 'base_unit' => 'ml', 'on_hand_qty' => '25.125', 'reorder_point' => '5.000', 'is_active' => false]);
    $this->get(route('admin.inventory.recipes'))->assertInertia(fn (Assert $page) => $page->where('stockItems.0.quickAdd', true));
    $this->patch($url, ['is_quick_add' => false])->assertSessionHasNoErrors();
    expect($item->fresh()->is_quick_add)->toBeFalse();
    $this->assertDatabaseHas('recipe_items', ['id' => $recipe->id, 'quantity' => '2.125']);
    $this->assertDatabaseCount('stock_movements', 0);
});

test('row quick add validates booleans and rejects enabling finished stock', function (): void {
    $item = StockItem::factory()->create(['base_unit' => 'pcs']);
    $url = route('admin.inventory.items.quickAdd', $item);

    foreach ([[], ['is_quick_add' => 'invalid'], ['is_quick_add' => true]] as $payload) {
        $this->patchJson($url, $payload)->assertUnprocessable()->assertJsonValidationErrors('is_quick_add');
    }
    $this->patch($url, ['is_quick_add' => false])->assertSessionHasNoErrors();
    expect($item->fresh()->is_quick_add)->toBeFalse();
});

test('non owners cannot use the row quick add endpoint', function (string $role): void {
    $item = StockItem::factory()->create(['is_quick_add' => true]);
    $this->actingAs(User::factory()->create(['role' => $role]));

    $this->patchJson(route('admin.inventory.items.quickAdd', $item), ['is_quick_add' => false])->assertForbidden();

    expect($item->fresh()->is_quick_add)->toBeTrue();
})->with(['cashier', 'kitchen']);

test('non owners cannot manage quick add preferences', function (string $role): void {
    $item = StockItem::factory()->create(['is_quick_add' => true]);
    $this->actingAs(User::factory()->create(['role' => $role]));
    $data = ['name' => $item->name, 'base_unit' => 'g', 'reorder_point' => '0', 'is_active' => true, 'is_quick_add' => false];

    $this->putJson(route('admin.inventory.items.update', $item), $data)->assertForbidden();
    $this->postJson(route('admin.inventory.items.store'), [...$data, 'name' => 'Unauthorized favorite', 'is_quick_add' => true])->assertForbidden();

    expect($item->fresh()->is_quick_add)->toBeTrue();
    $this->assertDatabaseCount('stock_items', 1);
})->with(['cashier', 'kitchen']);

test('quick add rejects invalid settings and finished stock shortcuts', function (mixed $flag, string $unit): void {
    $this->postJson(route('admin.inventory.items.store'), ['name' => 'Invalid shortcut', 'base_unit' => $unit, 'reorder_point' => '0', 'is_active' => true, 'is_quick_add' => $flag])
        ->assertUnprocessable()->assertJsonValidationErrors('is_quick_add');

    $this->assertDatabaseCount('stock_items', 0);
})->with(['invalid flag' => ['invalid', 'g'], 'finished stock' => [true, 'pcs']]);

test('stock adjustments preserve three decimal places and retries apply exactly once', function (): void {
    $item = StockItem::factory()->create();
    $payload = inventoryAdjustment($item, '0.101');

    $this->post(route('admin.inventory.adjustments'), $payload)->assertSessionHasNoErrors();
    $this->post(route('admin.inventory.adjustments'), $payload)->assertSessionHasNoErrors();
    $this->post(route('admin.inventory.adjustments'), inventoryAdjustment($item, '0.202'))->assertSessionHasNoErrors();
    $this->post(route('admin.inventory.adjustments'), inventoryAdjustment($item, '-0.003'))->assertSessionHasNoErrors();

    $this->assertDatabaseHas('stock_items', ['id' => $item->id, 'on_hand_qty' => '0.300']);
    $this->assertDatabaseCount('stock_movements', 3);
    $this->assertDatabaseHas('stock_movements', ['source_key' => $payload['source_key'], 'quantity_delta' => '0.101', 'reason' => $payload['reason'], 'actor_user_id' => auth()->id()]);
});

test('adjustment requires a reason and rejects invalid or negative quantities without movements', function (string $delta, string $reason): void {
    $item = StockItem::factory()->create();

    $this->post(route('admin.inventory.adjustments'), [...inventoryAdjustment($item, $delta), 'reason' => $reason])->assertSessionHasErrors();

    $this->assertDatabaseHas('stock_items', ['id' => $item->id, 'on_hand_qty' => '0.000']);
    $this->assertDatabaseCount('stock_movements', 0);
})->with(['negative balance' => ['-0.001', 'Physical count'], 'zero delta' => ['0.000', 'Physical count'], 'excess precision' => ['0.0001', 'Physical count'], 'overflow' => ['1000000000', 'Physical count'], 'blank reason' => ['1.000', '   ']]);

test('inactive stock and conflicting adjustment retry keys are rejected', function (): void {
    $item = StockItem::factory()->create();
    $payload = inventoryAdjustment($item);
    $this->post(route('admin.inventory.adjustments'), $payload)->assertSessionHasNoErrors();

    $this->post(route('admin.inventory.adjustments'), [...$payload, 'quantity_delta' => '200'])->assertSessionHasErrors('source_key');
    $item->update(['is_active' => false]);
    $this->post(route('admin.inventory.adjustments'), inventoryAdjustment($item))->assertSessionHasErrors('quantity_delta');

    $this->assertDatabaseHas('stock_items', ['id' => $item->id, 'on_hand_qty' => '100.000']);
    $this->assertDatabaseCount('stock_movements', 1);
});

test('movement insertion failure rolls back the balance change', function (): void {
    $item = StockItem::factory()->create();
    $actor = User::factory()->create();
    $actorId = $actor->id;
    $actor->delete();

    expect(fn () => app(InventoryStock::class)->adjust($item->id, '10', 'Count', (string) Str::uuid(), $actorId))->toThrow(QueryException::class);

    $this->assertDatabaseHas('stock_items', ['id' => $item->id, 'on_hand_qty' => '0.000']);
    $this->assertDatabaseCount('stock_movements', 0);
});

test('low stock includes equality with the reorder point and excludes inactive items', function (): void {
    $low = StockItem::factory()->create(['name' => 'A low', 'reorder_point' => '10']);
    $enough = StockItem::factory()->create(['name' => 'B enough', 'reorder_point' => '10']);
    app(InventoryStock::class)->adjust($low->id, '10', 'Count', (string) Str::uuid(), null);
    app(InventoryStock::class)->adjust($enough->id, '10.001', 'Count', (string) Str::uuid(), null);
    StockItem::factory()->create(['name' => 'C inactive', 'reorder_point' => '10', 'is_active' => false]);

    $this->get(route('admin.inventory.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('stockItems.0.lowStock', true)->where('stockItems.0.reserved', 0)->where('stockItems.1.lowStock', false)->where('stockItems.2.lowStock', false));
});

test('recipes stay attached to their variants and updates replace the ingredient configuration', function (): void {
    $variant = ProductVariant::factory()->create(['name' => 'Iced']);
    $beans = StockItem::factory()->create();
    $milk = StockItem::factory()->create(['base_unit' => 'ml']);
    $url = route('admin.inventory.recipe', $variant);
    $this->put($url, ['kind' => 'ingredients', 'stock_item_id' => null, 'ingredients' => [['stock_item_id' => $beans->id, 'quantity' => '18.125']]])->assertSessionHasNoErrors();

    $this->put($url, ['kind' => 'ingredients', 'stock_item_id' => null, 'ingredients' => [['stock_item_id' => $milk->id, 'quantity' => '150']]])->assertSessionHasNoErrors();

    $this->assertDatabaseCount('recipe_items', 1);
    $this->assertDatabaseHas('recipe_items', ['product_variant_id' => $variant->id, 'stock_item_id' => $milk->id, 'quantity' => '150.000']);
    $this->get(route('admin.inventory.recipes'))->assertOk()->assertInertia(fn (Assert $page) => $page->where("variantRecipes.{$variant->id}.ingredients.0.stockId", $milk->id));
});

test('invalid ingredient assignments preserve the previous recipe', function (string $case): void {
    $variant = ProductVariant::factory()->create();
    $original = RecipeItem::factory()->create(['product_variant_id' => $variant->id]);
    $stock = StockItem::factory()->create(['base_unit' => $case === 'pcs' ? 'pcs' : 'g', 'is_active' => $case !== 'inactive']);
    $rows = [['stock_item_id' => $stock->id, 'quantity' => $case === 'zero' ? 0 : 1]];
    if ($case === 'duplicate') {
        $rows[] = $rows[0];
    }

    $this->put(route('admin.inventory.recipe', $variant), ['kind' => 'ingredients', 'stock_item_id' => null, 'ingredients' => $rows])->assertSessionHasErrors();

    $this->assertDatabaseHas('recipe_items', ['id' => $original->id, 'stock_item_id' => $original->stock_item_id]);
    $this->assertDatabaseCount('recipe_items', 1);
})->with(['pcs', 'inactive', 'duplicate', 'zero']);

test('the recipe endpoint rejects manual finished stock assignments and preserves the recipe', function (): void {
    $variant = ProductVariant::factory()->create(['name' => 'Regular']);
    RecipeItem::factory()->create(['product_variant_id' => $variant->id]);
    $pastry = StockItem::factory()->create(['base_unit' => 'pcs']);

    $this->put(route('admin.inventory.recipe', $variant), ['kind' => 'finished', 'stock_item_id' => $pastry->id, 'ingredients' => []])->assertSessionHasErrors(['kind', 'stock_item_id']);

    $this->assertDatabaseHas('product_variants', ['id' => $variant->id, 'stock_item_id' => null]);
    $this->assertDatabaseCount('recipe_items', 1);
});

test('options persist additive ingredients including zero and an empty option needs no ingredient', function (): void {
    $option = Option::factory()->create();
    $syrup = StockItem::factory()->create(['base_unit' => 'ml']);

    $this->put(route('admin.inventory.option', $option), ['ingredients' => [['stock_item_id' => $syrup->id, 'quantity' => 0]]])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('option_ingredients', ['option_id' => $option->id, 'additional_qty' => '0.000']);
    $this->put(route('admin.inventory.option', $option), ['ingredients' => []])->assertSessionHasNoErrors();

    $this->assertDatabaseCount('option_ingredients', 0);
});

test('requirements combine base and option quantities and never mutate physical stock', function (): void {
    $variant = ProductVariant::factory()->create();
    $ingredient = StockItem::factory()->create(['base_unit' => 'ml']);
    RecipeItem::factory()->create(['product_variant_id' => $variant->id, 'stock_item_id' => $ingredient->id, 'quantity' => '18.125']);
    $group = OptionGroup::factory()->create(['selection_type' => 'single']);
    $option = Option::factory()->create(['option_group_id' => $group->id]);
    $variant->product->optionGroups()->attach($group->id, ['is_required' => true]);
    OptionIngredient::factory()->create(['option_id' => $option->id, 'stock_item_id' => $ingredient->id, 'additional_qty' => '5.250']);

    $this->postJson(route('admin.inventory.requirements'), ['variant_id' => $variant->id, 'option_ids' => [$option->id], 'quantity' => 2])->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.base', '18.125')->assertJsonPath('items.0.extra', '5.250')->assertJsonPath('items.0.total', '46.750');

    $this->assertDatabaseHas('stock_items', ['id' => $ingredient->id, 'on_hand_qty' => '0.000']);
    $this->assertDatabaseCount('stock_movements', 0);
});

test('requirements reject missing required options and options from another product', function (): void {
    $variant = ProductVariant::factory()->create();
    RecipeItem::factory()->create(['product_variant_id' => $variant->id]);
    $group = OptionGroup::factory()->create(['selection_type' => 'single']);
    $assigned = Option::factory()->create(['option_group_id' => $group->id]);
    $variant->product->optionGroups()->attach($group->id, ['is_required' => true]);
    $unassigned = Option::factory()->create();

    $this->postJson(route('admin.inventory.requirements'), ['variant_id' => $variant->id, 'option_ids' => [], 'quantity' => 1])->assertUnprocessable()->assertJsonValidationErrors('option_ids');
    $this->postJson(route('admin.inventory.requirements'), ['variant_id' => $variant->id, 'option_ids' => [$assigned->id, $unassigned->id], 'quantity' => 1])->assertUnprocessable()->assertJsonValidationErrors('option_ids');
});

test('requirements reject missing recipes and inactive ingredients', function (): void {
    $variant = ProductVariant::factory()->create();
    $payload = ['variant_id' => $variant->id, 'option_ids' => [], 'quantity' => 1];
    $this->postJson(route('admin.inventory.requirements'), $payload)->assertUnprocessable()->assertJsonValidationErrors('variant_id');
    $item = StockItem::factory()->create(['is_active' => false]);
    RecipeItem::factory()->create(['product_variant_id' => $variant->id, 'stock_item_id' => $item->id]);

    $this->postJson(route('admin.inventory.requirements'), $payload)->assertUnprocessable()->assertJsonValidationErrors('variant_id');
});

test('stock movement history cannot be edited and keeps stock references intact', function (): void {
    $movement = StockMovement::factory()->create();

    expect(fn () => $movement->update(['reason' => 'Changed']))->toThrow(LogicException::class);
    expect(fn () => $movement->delete())->toThrow(LogicException::class);

    $this->assertDatabaseHas('stock_movements', ['id' => $movement->id, 'reason' => 'Opening balance']);
});

test('menu edits preserve variant recipe identity and prevent removing a referenced variant', function (): void {
    $variant = ProductVariant::factory()->create(['name' => 'Iced']);
    RecipeItem::factory()->create(['product_variant_id' => $variant->id]);
    $product = $variant->product;
    $payload = ['name' => $product->name, 'category_id' => $product->category_id, 'description' => '', 'is_active' => true, 'groups' => [], 'variants' => [['id' => $variant->id, 'name' => 'Iced', 'price_idr' => 30000, 'is_available' => true]]];

    $this->put(route('admin.products.update', $product), $payload)->assertSessionHasNoErrors();
    $this->put(route('admin.products.update', $product), [...$payload, 'variants' => [['name' => 'New', 'price_idr' => 30000, 'is_available' => true]]])->assertSessionHasErrors('variants');

    $this->assertDatabaseHas('recipe_items', ['product_variant_id' => $variant->id]);
    $this->assertDatabaseHas('product_variants', ['id' => $variant->id, 'name' => 'Iced', 'price_idr' => 30000]);
});

test('adjustments record the actual user and reject replay against another stock item', function (): void {
    $actor = User::factory()->create();
    $first = StockItem::factory()->create();
    $second = StockItem::factory()->create();
    $payload = inventoryAdjustment($first, '1');

    $this->actingAs($actor)->post(route('admin.inventory.adjustments'), $payload)->assertSessionHasNoErrors();
    $this->post(route('admin.inventory.adjustments'), [...$payload, 'stock_item_id' => $second->id])->assertSessionHasErrors('source_key');

    $this->assertDatabaseHas('stock_movements', ['stock_item_id' => $first->id, 'actor_user_id' => $actor->id]);
    $this->assertDatabaseHas('stock_items', ['id' => $second->id, 'on_hand_qty' => '0.000']);
    $this->assertDatabaseCount('stock_movements', 1);
});

test('the recipe endpoint cannot replace a finished stock method with ingredients', function (): void {
    $beans = StockItem::factory()->create();
    $pastry = StockItem::factory()->create(['base_unit' => 'pcs']);
    $variant = ProductVariant::factory()->create(['stock_item_id' => $pastry->id]);
    $url = route('admin.inventory.recipe', $variant);
    $this->put($url, ['kind' => 'finished', 'stock_item_id' => $beans->id, 'ingredients' => []])->assertSessionHasErrors('stock_item_id');
    $this->put($url, ['kind' => 'finished', 'stock_item_id' => $pastry->id, 'ingredients' => []])->assertSessionHasErrors(['kind', 'stock_item_id']);

    $this->put($url, ['kind' => 'ingredients', 'stock_item_id' => null, 'ingredients' => [['stock_item_id' => $beans->id, 'quantity' => '18']]])->assertSessionHasErrors('variants');

    $this->assertDatabaseHas('product_variants', ['id' => $variant->id, 'stock_item_id' => $pastry->id]);
    $this->assertDatabaseCount('recipe_items', 0);
    $this->postJson(route('admin.inventory.requirements'), ['variant_id' => $variant->id, 'option_ids' => [], 'quantity' => 3])->assertOk()->assertJsonPath('items.0.total', '3.000');
});

test('requirements reject multiple choices for a single group and an unavailable variant', function (): void {
    $variant = ProductVariant::factory()->create();
    RecipeItem::factory()->create(['product_variant_id' => $variant->id]);
    $group = OptionGroup::factory()->create(['selection_type' => 'single']);
    $choices = Option::factory()->count(2)->create(['option_group_id' => $group->id]);
    $variant->product->optionGroups()->attach($group->id, ['is_required' => false]);

    $this->postJson(route('admin.inventory.requirements'), ['variant_id' => $variant->id, 'option_ids' => $choices->pluck('id')->all(), 'quantity' => 1])->assertUnprocessable()->assertJsonValidationErrors('option_ids');
    $variant->update(['is_available' => false]);
    $this->postJson(route('admin.inventory.requirements'), ['variant_id' => $variant->id, 'option_ids' => [], 'quantity' => 1])->assertUnprocessable()->assertJsonValidationErrors('variant_id');
});
