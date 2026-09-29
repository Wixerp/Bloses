<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $admin = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);

        $seller = Role::firstOrCreate([
            'name' => 'seller',
            'guard_name' => 'web',
        ]);

        $buyer = Role::firstOrCreate([
            'name' => 'buyer',
            'guard_name' => 'web',
        ]);

        $permissions = [
            // Dashboard
            'dashboard.view',

            // Users / Buyers
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'users.status',

            // Sellers
            'sellers.view',
            'sellers.create',
            'sellers.update',
            'sellers.delete',
            'sellers.approve',
            'sellers.reject',
            'sellers.status',

            // Categories
            'categories.view',
            'categories.create',
            'categories.update',
            'categories.delete',
            'categories.status',

            // Brands
            'brands.view',
            'brands.create',
            'brands.update',
            'brands.delete',
            'brands.status',

            // Products
            'products.view',
            'products.create',
            'products.update',
            'products.delete',
            'products.approve',
            'products.reject',
            'products.status',

            // Orders
            'orders.view',
            'orders.update',
            'orders.cancel',

            // Payments
            'payments.view',
            'payments.update',

            // Shipping
            'shipping.view',
            'shipping.create',
            'shipping.update',
            'shipping.delete',

            // Reviews
            'reviews.view',
            'reviews.approve',
            'reviews.delete',

            // Coupons
            'coupons.view',
            'coupons.create',
            'coupons.update',
            'coupons.delete',
            'coupons.status',

            // Returns
            'returns.view',
            'returns.update',
            'returns.approve',
            'returns.reject',

            // Refunds
            'refunds.view',
            'refunds.process',

            // Seller Finance
            'commissions.view',
            'payouts.view',
            'payouts.process',
            'bank_accounts.view',

            // Notifications
            'notifications.view',
            'notifications.create',
            'notifications.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $admin->syncPermissions(
            Permission::where('guard_name', 'web')->get()
        );

        $sellerPermissions = [
            'dashboard.view',

            'products.view',
            'products.create',
            'products.update',
            'products.delete',

            'orders.view',
            'orders.update',

            'shipping.view',
            'shipping.update',

            'reviews.view',

            'coupons.view',
            'coupons.create',
            'coupons.update',
            'coupons.delete',

            'returns.view',
            'returns.update',

            'notifications.view',
        ];

        $seller->syncPermissions(
            Permission::whereIn('name', $sellerPermissions)
                ->where('guard_name', 'web')
                ->get()
        );

        $buyerPermissions = [
            'dashboard.view',
            'orders.view',
            'reviews.view',
            'notifications.view',
        ];

        $buyer->syncPermissions(
            Permission::whereIn('name', $buyerPermissions)
                ->where('guard_name', 'web')
                ->get()
        );

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
