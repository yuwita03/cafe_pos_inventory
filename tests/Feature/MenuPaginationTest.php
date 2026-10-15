<?php

use App\Models\Category;
use App\Models\OptionGroup;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Inertia\Testing\AssertableInertia as Assert;

test('product table is limited ordered and paginated in the database', function (): void {
    $category = Category::factory()->create();
    Product::factory()->count(23)->for($category)->has(ProductVariant::factory(), 'variants')
        ->sequence(fn (Sequence $sequence): array => ['name' => sprintf('Product %02d', $sequence->index)])->create();
    $this->get(route('admin.menu'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('catalog.products', 10)->where('catalog.products.0.name', 'Product 00')
        ->where('pagination.total', 23)->where('pagination.per_page', 10)
        ->where('pagination.current_page', 1)->where('pagination.last_page', 3)
        ->where('pagination.prev_page_url', null)->where('summary.products', 23));
    $this->get(route('admin.menu', ['page' => 3]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('catalog.products', 3)->where('catalog.products.0.name', 'Product 20')
        ->where('pagination.from', 21)->where('pagination.to', 23)->where('pagination.next_page_url', null));
});

test('product search category and status filter the full catalog before pagination', function (): void {
    $category = Category::factory()->create();
    Product::factory()->count(14)->for($category)->create(['name' => 'Iced Coffee', 'is_active' => true]);
    Product::factory()->for($category)->create(['name' => 'Iced Coffee', 'is_active' => false]);
    Product::factory()->create(['name' => 'Iced Coffee', 'is_active' => true]);
    Product::factory()->for($category)->create(['name' => 'Tea', 'is_active' => true]);
    $filters = ['q' => 'coFFee', 'category_id' => $category->id, 'status' => 'active', 'per_page' => 10];
    $this->get(route('admin.menu', $filters))->assertOk()->assertInertia(function (Assert $page) use ($filters): void {
        $page->has('catalog.products', 10)->where('pagination.total', 14)->where('summary.products', 17)
            ->where('summary.activeProducts', 16)->where('filters.q', 'coFFee')
            ->where('filters.category_id', $filters['category_id'])
            ->where('pagination.next_page_url', function (string $url) use ($filters): bool {
                parse_str(parse_url($url, PHP_URL_QUERY), $query);

                return $query['q'] === $filters['q'] && (int) $query['category_id'] === $filters['category_id']
                    && $query['status'] === 'active' && (int) $query['per_page'] === 10 && (int) $query['page'] === 2;
            });
    });
    $this->get(route('admin.menu', [...$filters, 'page' => 2]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('catalog.products', 4)->where('pagination.total', 14)->where('pagination.current_page', 2));
    $this->get(route('admin.menu', [...$filters, 'status' => 'inactive']))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('catalog.products', 1)->where('catalog.products.0.active', false));
});

test('all supported product page sizes are bounded', function (int $limit): void {
    Product::factory()->count(51)->for(Category::factory())->create();
    $this->get(route('admin.menu', ['per_page' => $limit]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('catalog.products', $limit)->where('pagination.per_page', $limit)->where('pagination.total', 51));
})->with([10, 25, 50]);

test('invalid pagination and filter parameters are rejected', function (array $query, string $field): void {
    $this->getJson(route('admin.menu', $query))->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'unlimited' => [['per_page' => 0], 'per_page'],
    'excessive limit' => [['per_page' => 100000], 'per_page'],
    'array limit' => [['per_page' => [10]], 'per_page'],
    'negative page' => [['page' => -1], 'page'],
    'invalid page' => [['page' => 'all'], 'page'],
    'invalid status' => [['status' => 'hidden'], 'status'],
    'array search' => [['q' => ['Coffee']], 'q'],
    'long search' => [['q' => str_repeat('a', 121)], 'q'],
    'invalid category' => [['category_id' => 'all'], 'category_id'],
]);

test('empty results and out of range pages return no unbounded fallback', function (): void {
    Product::factory()->count(12)->for(Category::factory())->create(['name' => 'Coffee']);
    $this->get(route('admin.menu', ['q' => 'unmatched']))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('catalog.products', 0)->where('pagination.total', 0)->where('pagination.from', null)->where('pagination.to', null));
    $this->get(route('admin.menu', ['page' => 999]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('catalog.products', 0)->where('pagination.total', 12));
});

test('category group and summary counts remain global when products are paginated', function (): void {
    $category = Category::factory()->create();
    $group = OptionGroup::factory()->create();
    $products = Product::factory()->count(14)->for($category)->create(['is_active' => true]);
    foreach ($products as $product) {
        $product->optionGroups()->attach($group);
    }
    Product::factory()->for($category)->create(['is_active' => false]);
    foreach (['admin.menu', 'admin.categories', 'admin.options'] as $route) {
        $this->get(route($route, ['page' => 2]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('catalog.products', 5)->where('catalog.categories.0.productCount', 15)
            ->where('catalog.groups.0.productCount', 14)->where('summary.products', 15)->where('summary.activeProducts', 14));
    }
});
