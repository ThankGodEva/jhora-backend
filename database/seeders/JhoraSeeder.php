<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Category;
use App\Models\Product;

class JhoraSeeder extends Seeder
{
    public function run(): void
    {
        // Admin user - only if not exists
        if (!User::where('email', 'admin@jhora.com')->exists()) {
            $admin = User::create([
                'name' => 'Admin User',
                'email' => 'admin@jhora.com',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ]);
            $this->command->info('Admin user created!');
        } else {
            $this->command->info('Admin user already exists - skipping.');
        }

        // iCreator 1 - Tenibags
        if (!User::where('email', 'teni@tenibags.com')->exists()) {
            $creator1 = User::create([
                'name' => 'Teniola Matthews',
                'email' => 'teni@tenibags.com',
                'password' => Hash::make('password'),
                'role' => 'icreator',
            ]);

            Vendor::create([
                'user_id' => $creator1->id,
                'shop_name' => 'Tenibags',
                'slug' => 'tenibags',
                'description' => 'Beautiful handmade bags from Nigeria',
                'verification_status' => 'verified',
                'is_active' => true,
            ]);

            $this->command->info('Tenibags vendor created!');
        } else {
            $this->command->info('Tenibags already exists - skipping.');
        }

        // iCreator 2 - ZaraStudio
        if (!User::where('email', 'zara@zarastudio.com')->exists()) {
            $creator2 = User::create([
                'name' => 'Zara Studio',
                'email' => 'zara@zarastudio.com',
                'password' => Hash::make('password'),
                'role' => 'icreator',
            ]);

            Vendor::create([
                'user_id' => $creator2->id,
                'shop_name' => 'ZaraStudio',
                'slug' => 'zarastudio',
                'description' => 'Modern fashion and accessories',
                'verification_status' => 'verified',
                'is_active' => true,
            ]);

            $this->command->info('ZaraStudio vendor created!');
        } else {
            $this->command->info('ZaraStudio already exists - skipping.');
        }

        // Categories - create if not exist
        $categories = [
            ['name' => 'Bags', 'slug' => 'bags'],
            ['name' => 'Shoes', 'slug' => 'shoes'],
            ['name' => 'Jewelry', 'slug' => 'jewelry'],
            ['name' => 'Interior Decor', 'slug' => 'interior-decor'],
            ['name' => 'Fashion', 'slug' => 'fashion'],
            ['name' => 'Art & Collectibles', 'slug' => 'art-collectibles'],
        ];

        foreach ($categories as $cat) {
            Category::firstOrCreate(
                ['slug' => $cat['slug']],
                $cat
            );
        }
        $this->command->info('Categories seeded!');

        // Sample Products - only if no products exist
        if (Product::count() === 0) {
            $products = [
                [
                    'vendor_id' => Vendor::where('slug', 'tenibags')->first()?->id,
                    'category_id' => Category::where('slug', 'bags')->first()?->id,
                    'name' => 'T-Mank Mini Bag',
                    'slug' => 't-mank-mini-bag',
                    'description' => 'Handmade mini leather bag with adjustable strap',
                    'price' => 48000,
                    'compare_price' => 52000,
                    'stock_quantity' => 15,
                    'sku' => 'TM-MB-001',
                    'status' => 'published',
                    'is_featured' => true,
                ],
                [
                    'vendor_id' => Vendor::where('slug', 'tenibags')->first()?->id,
                    'category_id' => Category::where('slug', 'bags')->first()?->id,
                    'name' => 'Classic Tote Bag',
                    'slug' => 'classic-tote-bag',
                    'description' => 'Large tote for daily use',
                    'price' => 65000,
                    'stock_quantity' => 8,
                    'sku' => 'TM-TB-002',
                    'status' => 'published',
                ],
                [
                    'vendor_id' => Vendor::where('slug', 'zarastudio')->first()?->id,
                    'category_id' => Category::where('slug', 'fashion')->first()?->id,
                    'name' => 'Zara Core Pack',
                    'slug' => 'zara-core-pack',
                    'description' => 'Stylish backpack for modern women',
                    'price' => 45000,
                    'compare_price' => 50000,
                    'stock_quantity' => 20,
                    'sku' => 'ZS-CP-001',
                    'status' => 'published',
                    'is_featured' => true,
                ],
            ];

            foreach ($products as $prod) {
                if ($prod['vendor_id'] && $prod['category_id']) {
                    Product::create($prod);
                }
            }

            $this->command->info('Sample products seeded!');
        } else {
            $this->command->info('Products already exist - skipping.');
        }

        $this->command->info('JHORA test data seeded successfully!');
    }
}