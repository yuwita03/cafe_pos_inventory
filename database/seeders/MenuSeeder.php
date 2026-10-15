<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $groups = [];
            foreach ([
                'Sugar Level' => ['selection' => 'single', 'options' => ['Zero' => 0, 'Low' => 0, 'Medium' => 0, 'High' => 0]],
                'Extra Shot' => ['selection' => 'single', 'options' => ['Single shot' => 6000, 'Double shot' => 10000]],
                'Syrup' => ['selection' => 'multiple', 'options' => ['Vanilla' => 4000, 'Caramel' => 4000, 'Hazelnut' => 4000]],
            ] as $name => $definition) {
                $group = OptionGroup::firstOrCreate(['name' => $name], ['selection_type' => $definition['selection'], 'is_active' => true]);
                foreach ($definition['options'] as $optionName => $price) {
                    Option::firstOrCreate(['option_group_id' => $group->id, 'name' => $optionName], ['price_delta_idr' => $price, 'is_active' => true]);
                }
                $groups[$name] = $group;
            }

            $menu = [
                'Coffee' => [
                    ['name' => 'Americano', 'description' => 'Espresso dengan air, ringan dan aromatik.', 'variants' => ['Hot' => 22000, 'Iced' => 25000], 'groups' => ['Sugar Level', 'Extra Shot']],
                    ['name' => 'Espresso', 'description' => 'Satu sajian espresso dengan rasa kopi yang pekat.', 'variants' => ['Regular' => 18000], 'groups' => ['Extra Shot']],
                    ['name' => 'Long Black', 'description' => 'Espresso di atas air dengan crema yang terjaga.', 'variants' => ['Hot' => 23000, 'Iced' => 26000], 'groups' => ['Sugar Level', 'Extra Shot']],
                    ['name' => 'Caffe Latte', 'description' => 'Espresso dan susu dengan rasa lembut seimbang.', 'variants' => ['Hot' => 28000, 'Iced' => 32000], 'groups' => ['Sugar Level', 'Extra Shot', 'Syrup']],
                    ['name' => 'Cappuccino', 'description' => 'Espresso, susu, dan lapisan busa yang tebal.', 'variants' => ['Hot' => 28000, 'Iced' => 32000], 'groups' => ['Sugar Level', 'Extra Shot', 'Syrup']],
                    ['name' => 'Flat White', 'description' => 'Espresso dengan susu bertekstur halus, disajikan panas.', 'variants' => ['Hot' => 30000], 'groups' => ['Sugar Level', 'Extra Shot']],
                    ['name' => 'Mocha', 'description' => 'Perpaduan espresso, cokelat, dan susu.', 'variants' => ['Hot' => 32000, 'Iced' => 36000], 'groups' => ['Sugar Level', 'Extra Shot', 'Syrup']],
                    ['name' => 'Caramel Latte', 'description' => 'Espresso dan susu dengan aroma karamel.', 'variants' => ['Hot' => 34000, 'Iced' => 38000], 'groups' => ['Sugar Level', 'Extra Shot']],
                ],
                'Non-Coffee' => [
                    ['name' => 'Chocolate', 'description' => 'Cokelat dengan susu yang lembut dan kaya rasa.', 'variants' => ['Hot' => 28000, 'Iced' => 32000], 'groups' => ['Sugar Level', 'Syrup']],
                    ['name' => 'Matcha Latte', 'description' => 'Matcha dan susu dengan aroma teh hijau.', 'variants' => ['Hot' => 30000, 'Iced' => 34000], 'groups' => ['Sugar Level', 'Syrup']],
                    ['name' => 'Chai Latte', 'description' => 'Teh rempah dengan susu dan aroma hangat.', 'variants' => ['Hot' => 28000, 'Iced' => 32000], 'groups' => ['Sugar Level']],
                    ['name' => 'Lemon Tea', 'description' => 'Teh dengan lemon segar dan rasa ringan.', 'variants' => ['Hot' => 20000, 'Iced' => 24000], 'groups' => ['Sugar Level']],
                    ['name' => 'Jasmine Tea', 'description' => 'Teh melati dengan aroma bunga yang lembut.', 'variants' => ['Hot' => 18000, 'Iced' => 22000], 'groups' => ['Sugar Level']],
                    ['name' => 'Peach Tea', 'description' => 'Teh dingin dengan rasa dan aroma persik.', 'variants' => ['Iced' => 24000], 'groups' => ['Sugar Level']],
                ],
                'Pastry' => [
                    ['name' => 'Croissant', 'description' => 'Croissant mentega dengan lapisan renyah.', 'variants' => ['Regular' => 24000], 'groups' => []],
                    ['name' => 'Almond Croissant', 'description' => 'Croissant dengan isian dan taburan almond.', 'variants' => ['Regular' => 32000], 'groups' => []],
                    ['name' => 'Pain au Chocolat', 'description' => 'Pastry berlapis dengan isian cokelat.', 'variants' => ['Regular' => 28000], 'groups' => []],
                    ['name' => 'Cinnamon Roll', 'description' => 'Roti gulung kayu manis dengan lapisan gula.', 'variants' => ['Regular' => 26000], 'groups' => []],
                    ['name' => 'Blueberry Muffin', 'description' => 'Muffin lembut dengan blueberry.', 'variants' => ['Regular' => 24000], 'groups' => []],
                    ['name' => 'Chocolate Brownie', 'description' => 'Brownie cokelat padat dengan tekstur lembut.', 'variants' => ['Regular' => 25000], 'groups' => []],
                ],
            ];

            foreach ($menu as $categoryName => $products) {
                $category = Category::firstOrCreate(['name' => $categoryName], ['is_active' => true]);
                foreach ($products as $definition) {
                    $product = Product::firstOrCreate(['name' => $definition['name'], 'category_id' => $category->id], ['description' => $definition['description'], 'is_active' => true]);
                    foreach ($definition['variants'] as $name => $price) {
                        ProductVariant::firstOrCreate(['product_id' => $product->id, 'name' => $name], ['price_idr' => $price, 'is_available' => true]);
                    }
                    if ($product->wasRecentlyCreated) {
                        $assignments = [];
                        foreach ($definition['groups'] as $groupName) {
                            $assignments[$groups[$groupName]->id] = ['is_required' => $groupName === 'Sugar Level'];
                        }
                        $product->optionGroups()->syncWithoutDetaching($assignments);
                    }
                }
            }
        });
    }
}
