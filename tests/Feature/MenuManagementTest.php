<?php

use App\Models\Category;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\Product;
use App\Models\ProductVariant;
use App\ProductImage;
use Database\Seeders\MenuSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array<string, mixed> */
function menuProductPayload(Category $category): array
{
    return ['name' => 'Americano', 'category_id' => $category->id, 'description' => 'Fresh coffee',
        'is_active' => true, 'variants' => [
            ['name' => 'Hot', 'price_idr' => 22000, 'is_available' => true],
            ['name' => 'Iced', 'price_idr' => 25000, 'is_available' => true],
        ], 'groups' => []];
}

function menuPhotoContents(string $format = 'png'): string
{
    return base64_decode(match ($format) {
        'jpg', 'jpeg' => '/9j/4AAQSkZJRgABAQEAYABgAAD//gA7Q1JFQVRPUjogZ2QtanBlZyB2MS4wICh1c2luZyBJSkcgSlBFRyB2ODApLCBxdWFsaXR5ID0gOTAK/9sAQwADAgIDAgIDAwMDBAMDBAUIBQUEBAUKBwcGCAwKDAwLCgsLDQ4SEA0OEQ4LCxAWEBETFBUVFQwPFxgWFBgSFBUU/9sAQwEDBAQFBAUJBQUJFA0LDRQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQU/8AAEQgAAQACAwEiAAIRAQMRAf/EAB8AAAEFAQEBAQEBAAAAAAAAAAABAgMEBQYHCAkKC//EALUQAAIBAwMCBAMFBQQEAAABfQECAwAEEQUSITFBBhNRYQcicRQygZGhCCNCscEVUtHwJDNicoIJChYXGBkaJSYnKCkqNDU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6g4SFhoeIiYqSk5SVlpeYmZqio6Slpqeoqaqys7S1tre4ubrCw8TFxsfIycrS09TV1tfY2drh4uPk5ebn6Onq8fLz9PX29/j5+v/EAB8BAAMBAQEBAQEBAQEAAAAAAAABAgMEBQYHCAkKC//EALURAAIBAgQEAwQHBQQEAAECdwABAgMRBAUhMQYSQVEHYXETIjKBCBRCkaGxwQkjM1LwFWJy0QoWJDThJfEXGBkaJicoKSo1Njc4OTpDREVGR0hJSlNUVVZXWFlaY2RlZmdoaWpzdHV2d3h5eoKDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uLj5OXm5+jp6vLz9PX29/j5+v/aAAwDAQACEQMRAD8A/Mj4gf8AI+eJP+wlc/8Ao1qKKKwofwoei/I78w/3yt/il+bP/9k=',
        'png' => 'iVBORw0KGgoAAAANSUhEUgAAAAIAAAABCAYAAAD0In+KAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAEUlEQVQImWO4oyHyn4GBgQEADXQCGP+1D18AAAAASUVORK5CYII=',
        'webp' => 'UklGRl4AAABXRUJQVlA4WAoAAAAQAAAAAQAAAAAAQUxQSAMAAAAA/wAAVlA4IDQAAADQAQCdASoCAAEAAMASJaACdLoB+AADsAD+9hNf/uQD2QD2QD+E//99VPvqp99VP+9kgAAA',
    });
}

test('menu pages read persisted catalog rather than prototype data', function (): void {
    $product = Product::factory()->create(['name' => 'Real menu']);
    ProductVariant::factory()->for($product)->create();
    foreach (['admin.menu' => 'products', 'admin.categories' => 'categories', 'admin.options' => 'options'] as $route => $section) {
        $this->get(route($route))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('admin/menu/Index')->where('section', $section)
            ->has('catalog.products', 1)->where('catalog.products.0.name', 'Real menu'));
    }
});

test('categories can be created edited and deactivated without deleting products', function (): void {
    $this->post(route('admin.categories.store'), ['name' => 'Coffee', 'is_active' => true])->assertSessionHasNoErrors();
    $category = Category::where('name', 'Coffee')->firstOrFail();
    $product = Product::factory()->for($category)->create();
    $this->put(route('admin.categories.update', $category), ['name' => 'Coffee Bar', 'is_active' => false])->assertSessionHasNoErrors();
    expect($category->fresh()->is_active)->toBeFalse();
    expect($product->fresh()->category_id)->toBe($category->id);
    $this->post(route('admin.categories.store'), ['name' => 'Coffee Bar', 'is_active' => true])->assertSessionHasErrors('name');
});

test('products persist variants option assignments and status', function (): void {
    $category = Category::factory()->create();
    $group = OptionGroup::factory()->create(['name' => 'Sugar Level']);
    Option::factory()->for($group, 'group')->create(['name' => 'Low']);
    $payload = menuProductPayload($category);
    $payload['groups'] = [['id' => $group->id, 'is_required' => true]];
    $this->post(route('admin.products.store'), $payload)->assertSessionHasNoErrors();
    $product = Product::where('name', 'Americano')->firstOrFail();
    expect($product->variants()->count())->toBe(2);
    expect((bool) $product->optionGroups->first()->pivot->is_required)->toBeTrue();
    $this->patch(route('admin.products.status', $product), ['is_active' => false])->assertSessionHasNoErrors();
    expect($product->fresh()->is_active)->toBeFalse();
    expect($product->variants()->count())->toBe(2);
});

test('invalid product definitions are rejected atomically', function (string $field, mixed $value, string $error): void {
    $payload = menuProductPayload(Category::factory()->create());
    data_set($payload, $field, $value);
    $this->post(route('admin.products.store'), $payload)->assertSessionHasErrors($error);
    expect(Product::count())->toBe(0);
    expect(ProductVariant::count())->toBe(0);
})->with([
    'no variants' => ['variants', [], 'variants'],
    'negative price' => ['variants.0.price_idr', -1, 'variants.0.price_idr'],
    'fractional price' => ['variants.0.price_idr', 1.5, 'variants.0.price_idr'],
    'duplicate variant' => ['variants.1.name', 'hot', 'variants.0.name'],
    'invalid category' => ['category_id', 9999, 'category_id'],
    'invalid assignment' => ['groups', [['id' => 9999, 'is_required' => false]], 'groups.0.id'],
]);

test('variant edits preserve ids and support swapping names', function (): void {
    $product = Product::factory()->create();
    $hot = ProductVariant::factory()->for($product)->create(['name' => 'Hot']);
    $iced = ProductVariant::factory()->for($product)->create(['name' => 'Iced']);
    $payload = menuProductPayload($product->category);
    $payload['variants'] = [
        ['id' => $hot->id, 'name' => 'Iced', 'price_idr' => 29000, 'is_available' => false],
        ['id' => $iced->id, 'name' => 'Hot', 'price_idr' => 27000, 'is_available' => true],
    ];
    $this->put(route('admin.products.update', $product), $payload)->assertSessionHasNoErrors();
    expect($hot->fresh()->name)->toBe('Iced')->and($hot->fresh()->is_available)->toBeFalse();
    expect($iced->fresh()->name)->toBe('Hot');
});

test('updates reject foreign variants and delete omitted own variants', function (): void {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->for($product)->create();
    $foreign = ProductVariant::factory()->create();
    $payload = menuProductPayload($product->category);
    $payload['variants'][0]['id'] = $foreign->id;
    $this->put(route('admin.products.update', $product), $payload)->assertSessionHasErrors('variants.0.id');
    unset($payload['variants'][0]['id']);
    $this->put(route('admin.products.update', $product), $payload)->assertSessionHasNoErrors();
    expect($variant->fresh())->toBeNull();
    expect($foreign->fresh()->name)->toBe('Regular');
    expect($product->variants()->count())->toBe(2);
});

test('sugar level must be single and required when assigned', function (): void {
    $this->post(route('admin.options.store'), [
        'name' => 'Sugar Level', 'selection_type' => 'multiple', 'is_active' => true,
        'options' => [['name' => 'Low', 'price_delta_idr' => 0, 'is_active' => true]],
    ])->assertSessionHasErrors('selection_type');
    $group = OptionGroup::factory()->create(['name' => 'Sugar Level']);
    $payload = menuProductPayload(Category::factory()->create());
    $payload['groups'] = [['id' => $group->id, 'is_required' => false]];
    $this->post(route('admin.products.store'), $payload)->assertSessionHasErrors('groups.0.is_required');
});

test('option groups can be created edited and deactivated while preserving option ids', function (): void {
    $this->post(route('admin.options.store'), ['name' => 'Extras', 'selection_type' => 'multiple', 'is_active' => true,
        'options' => [['name' => 'Extra shot', 'price_delta_idr' => 6000, 'is_active' => true]],
    ])->assertSessionHasNoErrors();
    $group = OptionGroup::where('name', 'Extras')->firstOrFail();
    $option = $group->options->first();
    $this->put(route('admin.options.update', $group), ['name' => 'Extras', 'selection_type' => 'multiple', 'is_active' => false,
        'options' => [['id' => $option->id, 'name' => 'Double shot', 'price_delta_idr' => 7000, 'is_active' => false]],
    ])->assertSessionHasNoErrors();
    expect($group->fresh()->is_active)->toBeFalse();
    expect($option->fresh()->name)->toBe('Double shot')->and($option->fresh()->price_delta_idr)->toBe(7000);
    $this->put(route('admin.options.update', $group), ['name' => 'Extras', 'selection_type' => 'multiple', 'is_active' => true,
        'options' => [['name' => 'New', 'price_delta_idr' => 0, 'is_active' => true]],
    ])->assertSessionHasNoErrors();
    expect($option->fresh())->toBeNull();
    expect($group->options()->count())->toBe(1);
    expect($group->options()->first()->name)->toBe('New');
});

test('option validation prevents duplicate negative and foreign options', function (): void {
    $group = OptionGroup::factory()->create();
    $foreign = Option::factory()->create();
    $payload = ['name' => $group->name, 'selection_type' => 'single', 'is_active' => true,
        'options' => [['id' => $foreign->id, 'name' => 'Low', 'price_delta_idr' => -1, 'is_active' => true], ['name' => 'low', 'price_delta_idr' => 0, 'is_active' => true]]];
    $this->put(route('admin.options.update', $group), $payload)->assertSessionHasErrors(['options.0.id', 'options.0.price_delta_idr', 'options.0.name']);
    expect($foreign->fresh()->price_delta_idr)->toBe(0);
});

test('server calculates iced americano and regular croissant without trusting supplied prices', function (): void {
    $this->seed(MenuSeeder::class);
    $variant = Product::where('name', 'Americano')->firstOrFail()->variants()->where('name', 'Iced')->firstOrFail();
    $low = Option::where('name', 'Low')->firstOrFail();
    $this->postJson(route('admin.menu.price'), ['variant_id' => $variant->id, 'option_ids' => [$low->id], 'quantity' => 2, 'total_idr' => 1, 'unit_price_idr' => 1])
        ->assertOk()->assertJsonPath('unit_price_idr', 25000)->assertJsonPath('total_idr', 50000);
    $croissant = Product::where('name', 'Croissant')->firstOrFail()->variants()->where('name', 'Regular')->firstOrFail();
    $this->postJson(route('admin.menu.price'), ['variant_id' => $croissant->id, 'option_ids' => [], 'quantity' => 1])->assertOk()->assertJsonPath('total_idr', 24000);
});

test('pricing rejects invalid required duplicate and unassigned selections', function (): void {
    $this->seed(MenuSeeder::class);
    $variant = Product::where('name', 'Americano')->firstOrFail()->variants()->where('name', 'Iced')->firstOrFail();
    $options = Option::take(2)->pluck('id')->all();
    $foreign = Option::factory()->create();
    foreach ([[], $options, [$options[0], $options[0]], [$foreign->id]] as $ids) {
        $this->postJson(route('admin.menu.price'), ['variant_id' => $variant->id, 'option_ids' => $ids, 'quantity' => 1])->assertUnprocessable();
    }
});

test('pricing adds multiple extras and rejects inactive menu levels', function (): void {
    $variant = ProductVariant::factory()->create(['price_idr' => 20000]);
    $group = OptionGroup::factory()->create(['selection_type' => 'multiple']);
    $first = Option::factory()->for($group, 'group')->create(['price_delta_idr' => 5000]);
    $second = Option::factory()->for($group, 'group')->create(['price_delta_idr' => 6000]);
    $variant->product->optionGroups()->attach($group, ['is_required' => false]);
    $payload = ['variant_id' => $variant->id, 'option_ids' => [$first->id, $second->id], 'quantity' => 3];
    $this->postJson(route('admin.menu.price'), $payload)->assertOk()->assertJsonPath('total_idr', 93000);
    $first->update(['is_active' => false]);
    $this->postJson(route('admin.menu.price'), $payload)->assertUnprocessable();
    $first->update(['is_active' => true]);
    $group->update(['is_active' => false]);
    $this->postJson(route('admin.menu.price'), $payload)->assertUnprocessable();
    $payload['option_ids'] = [];
    $variant->product->category->update(['is_active' => false]);
    $this->postJson(route('admin.menu.price'), $payload)->assertUnprocessable();
    $variant->product->category->update(['is_active' => true]);
    $variant->product->update(['is_active' => false]);
    $this->postJson(route('admin.menu.price'), $payload)->assertUnprocessable();
    $variant->product->update(['is_active' => true]);
    $variant->update(['is_available' => false]);
    $this->postJson(route('admin.menu.price'), $payload)->assertUnprocessable();
});

test('pricing rejects invalid quantities and unsafe totals', function (): void {
    $variant = ProductVariant::factory()->create(['price_idr' => 9007199254740991]);
    foreach ([0, -1, 1.5, 2] as $quantity) {
        $this->postJson(route('admin.menu.price'), ['variant_id' => $variant->id, 'option_ids' => [], 'quantity' => $quantity])->assertUnprocessable();
    }
});

test('product photos are validated stored served replaced and removed', function (): void {
    Storage::fake('public');
    $payload = menuProductPayload(Category::factory()->create());
    $original = menuPhotoContents();
    $payload['image'] = UploadedFile::fake()->createWithContent('photo.png', $original);
    unset($payload['groups']);
    $this->post(route('admin.products.store'), $payload)->assertSessionHasNoErrors();
    $product = Product::firstOrFail();
    $oldPath = $product->image_path;
    expect($oldPath)->toEndWith('.png');
    expect(Storage::disk('public')->get($oldPath))->toBe($original);
    $this->get(route('admin.products.image', $product))->assertOk()->assertHeader('Content-Type', 'image/png');
    $payload['variants'] = $product->variants->map(fn (ProductVariant $variant): array => ['id' => $variant->id, 'name' => $variant->name, 'price_idr' => $variant->price_idr, 'is_available' => $variant->is_available])->all();
    $replacement = menuPhotoContents('jpg');
    $payload['image'] = UploadedFile::fake()->createWithContent('new.jpg', $replacement);
    $this->put(route('admin.products.update', $product), $payload)->assertSessionHasNoErrors();
    Storage::disk('public')->assertMissing($oldPath);
    $newPath = $product->fresh()->image_path;
    expect($newPath)->toEndWith('.jpg');
    expect(Storage::disk('public')->get($newPath))->toBe($replacement);
    expect(Storage::disk('public')->allFiles())->toBe([$newPath]);
    unset($payload['image']);
    $this->put(route('admin.products.update', $product), $payload)->assertSessionHasNoErrors();
    expect($product->fresh()->image_path)->toBe($newPath);
    expect(Storage::disk('public')->get($newPath))->toBe($replacement);
    $payload['remove_image'] = true;
    $this->put(route('admin.products.update', $product), $payload)->assertSessionHasNoErrors();
    expect($product->fresh()->image_path)->toBeNull();
    expect(Storage::disk('public')->allFiles())->toBe([]);
    $payload['image'] = UploadedFile::fake()->createWithContent('bad.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
    $this->put(route('admin.products.update', $product), $payload)->assertSessionHasErrors('image');
    expect(Storage::disk('public')->allFiles())->toBe([]);
});

test('product photos keep the uploaded bytes format and dimensions through 200 KB', function (string $format, int $bytes): void {
    Storage::fake('public');
    $original = str_pad(menuPhotoContents($format), $bytes, "\0");
    $payload = menuProductPayload(Category::factory()->create());
    $payload['image'] = UploadedFile::fake()->createWithContent('photo.'.$format, $original);
    $this->post(route('admin.products.store'), $payload)->assertSessionHasNoErrors();
    $path = Product::firstOrFail()->image_path;
    $stored = Storage::disk('public')->get($path);
    expect($path)->toEndWith('.'.($format === 'jpeg' ? 'jpg' : $format));
    expect($stored)->toBe($original);
    expect(getimagesizefromstring($stored))->toMatchArray([0 => 2, 1 => 1]);
    expect(Storage::disk('public')->allFiles())->toBe([$path]);
})->with(['jpg', 'jpeg', 'png', 'webp'])->with(['small' => 0, '100 KB' => 100 * 1024, '200 KB' => 200 * 1024]);

test('product photo validation rejects anything above 200 KB without changing existing data', function (): void {
    $disk = Storage::fake('public');
    $original = menuPhotoContents();
    $product = Product::factory()->create(['image_path' => 'products/old.png']);
    $disk->put($product->image_path, $original);
    $variant = ProductVariant::factory()->for($product)->create();
    $payload = menuProductPayload($product->category);
    $payload['image'] = UploadedFile::fake()->createWithContent('large.png', str_pad($original, 200 * 1024 + 1, "\0"));
    $this->post(route('admin.products.store'), $payload)->assertSessionHasErrors('image');
    $this->put(route('admin.products.update', $product), $payload)->assertSessionHasErrors('image');
    expect(Product::count())->toBe(1);
    expect($product->fresh()->image_path)->toBe('products/old.png');
    expect($variant->fresh()->name)->toBe('Regular');
    expect($disk->get('products/old.png'))->toBe($original);
    expect($disk->allFiles())->toBe(['products/old.png']);
});

test('partial product photo writes are cleaned up without changing existing images', function (bool $throws): void {
    $disk = Storage::fake('public');
    $original = menuPhotoContents();
    $product = Product::factory()->create(['image_path' => 'products/old.png']);
    $disk->put('products/old.png', $original);
    ProductVariant::factory()->for($product)->create();
    $failedDisk = Mockery::mock($disk);
    $failedDisk->shouldReceive('put')->once()->andReturnUsing(function (string $path, string $contents) use ($disk, $throws): bool {
        $disk->put($path, $contents);
        if ($throws) {
            throw new RuntimeException('Disk write failed.');
        }

        return false;
    });
    Storage::shouldReceive('disk')->with('public')->andReturn($failedDisk);
    $payload = menuProductPayload($product->category);
    $payload['image'] = UploadedFile::fake()->createWithContent('photo.png', $original);
    $this->put(route('admin.products.update', $product), $payload)->assertSessionHasErrors('image');
    expect($product->fresh()->image_path)->toBe('products/old.png');
    expect($disk->get('products/old.png'))->toBe($original);
    expect($disk->allFiles())->toBe(['products/old.png']);
})->with(['false' => false, 'exception' => true]);

test('existing product photos remain available unchanged even above the new upload limit', function (string $format): void {
    Storage::fake('public');
    $this->mock(ProductImage::class)->shouldNotReceive('store');
    $path = 'products/legacy.'.$format;
    $product = Product::factory()->create(['image_path' => $path]);
    $original = str_pad(menuPhotoContents($format), 201 * 1024, "\0");
    Storage::disk('public')->put($path, $original);
    $this->get(route('admin.products.image', $product))->assertOk()->assertHeader('Content-Type', 'image/'.$format);
    expect($product->fresh()->image_path)->toBe($path);
    expect(Storage::disk('public')->get($path))->toBe($original);
    expect(Storage::disk('public')->allFiles())->toBe([$path]);
})->with(['png', 'webp']);

test('referenced variants cannot be deleted and failed updates preserve images and menu data', function (string $format): void {
    Storage::fake('public');
    $product = Product::factory()->create(['image_path' => 'products/old.png']);
    $original = menuPhotoContents();
    Storage::disk('public')->put('products/old.png', $original);
    $variant = ProductVariant::factory()->for($product)->create();
    Schema::create('test_variant_references', function (Blueprint $table): void {
        $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
    });
    DB::table('test_variant_references')->insert(['product_variant_id' => $variant->id]);
    $payload = menuProductPayload($product->category);
    $payload['image'] = UploadedFile::fake()->createWithContent('new.'.$format, str_pad(menuPhotoContents($format), 150 * 1024, "\0"));
    $this->put(route('admin.products.update', $product), $payload)->assertSessionHasErrors('variants');
    expect($product->fresh()->image_path)->toBe('products/old.png');
    expect($variant->fresh()->name)->toBe('Regular');
    expect(Storage::disk('public')->get('products/old.png'))->toBe($original);
    expect(Storage::disk('public')->allFiles())->toBe(['products/old.png']);
})->with(['jpg', 'png', 'webp']);

test('deleting a saved variant preserves retained ids and can convert the last variant to regular', function (): void {
    $product = Product::factory()->create();
    $hot = ProductVariant::factory()->for($product)->create(['name' => 'Hot']);
    $iced = ProductVariant::factory()->for($product)->create(['name' => 'Iced', 'price_idr' => 29000]);
    $payload = menuProductPayload($product->category);
    $payload['variants'] = [['id' => $iced->id, 'name' => 'Regular', 'price_idr' => 29000, 'is_available' => true]];
    $this->put(route('admin.products.update', $product), $payload)->assertSessionHasNoErrors();
    expect($hot->fresh())->toBeNull();
    expect($iced->fresh()->name)->toBe('Regular')->and($iced->fresh()->price_idr)->toBe(29000);
    expect($product->variants()->count())->toBe(1);
});

test('option deletion preserves retained ids and other groups options', function (): void {
    $group = OptionGroup::factory()->create();
    $retained = Option::factory()->for($group, 'group')->create();
    $removed = Option::factory()->for($group, 'group')->create();
    $unrelated = Option::factory()->create();
    $payload = ['name' => $group->name, 'selection_type' => 'single', 'is_active' => true,
        'options' => [['id' => $retained->id, 'name' => 'Retained', 'price_delta_idr' => 5000, 'is_active' => true]]];
    $this->put(route('admin.options.update', $group), $payload)->assertSessionHasNoErrors();
    expect($removed->fresh())->toBeNull();
    expect($retained->fresh()->name)->toBe('Retained')->and($retained->fresh()->price_delta_idr)->toBe(5000);
    expect($unrelated->fresh())->not->toBeNull();
});

test('actual option references block deletion without partially updating the group', function (): void {
    $group = OptionGroup::factory()->create();
    $option = Option::factory()->for($group, 'group')->create();
    Schema::create('test_option_references', function (Blueprint $table): void {
        $table->foreignId('option_id')->constrained('options')->restrictOnDelete();
    });
    DB::table('test_option_references')->insert(['option_id' => $option->id]);
    $this->put(route('admin.options.update', $group), [
        'name' => 'Changed name', 'selection_type' => 'single', 'is_active' => true,
        'options' => [['name' => 'New', 'price_delta_idr' => 0, 'is_active' => true]],
    ])->assertSessionHasErrors('options');
    expect($option->fresh())->not->toBeNull();
    expect($group->fresh()->name)->toBe($group->name);
    expect($group->options()->count())->toBe(1);
});

test('menu seeding provides a varied catalog and preserves edits when rerun', function (): void {
    $this->seed(MenuSeeder::class);

    expect(Category::count())->toBe(3);
    expect(Product::count())->toBe(20);
    expect(ProductVariant::count())->toBe(31);
    expect(OptionGroup::count())->toBe(3);
    expect(Option::count())->toBe(9);
    expect(Product::doesntHave('variants')->count())->toBe(0);

    $latte = Product::where('name', 'Caffe Latte')->firstOrFail();
    $variant = $latte->variants()->where('name', 'Iced')->firstOrFail();
    $sugar = $latte->optionGroups()->where('name', 'Sugar Level')->firstOrFail();
    expect((bool) $sugar->pivot->is_required)->toBeTrue();
    expect(Product::where('name', 'Croissant')->firstOrFail()->optionGroups()->count())->toBe(0);

    $latte->update(['description' => 'Edited description', 'is_active' => false]);
    $variant->update(['price_idr' => 35000, 'is_available' => false]);
    $latte->optionGroups()->detach();

    $this->seed(MenuSeeder::class);

    expect(Category::count())->toBe(3);
    expect(Product::count())->toBe(20);
    expect(ProductVariant::count())->toBe(31);
    expect(OptionGroup::count())->toBe(3);
    expect(Option::count())->toBe(9);
    expect($latte->fresh()->description)->toBe('Edited description');
    expect($latte->fresh()->is_active)->toBeFalse();
    expect($variant->fresh()->price_idr)->toBe(35000);
    expect($variant->fresh()->is_available)->toBeFalse();
    expect($latte->optionGroups()->count())->toBe(0);
});
