<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Product $product): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole([
            'admin',
            'seller',
        ]);
    }

    public function update(User $user, Product $product): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if (!$user->hasRole('seller')) {
            return false;
        }

        return $product->store
            && $product->store->seller_id === $user->seller?->id;
    }

    public function delete(User $user, Product $product): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if (!$user->hasRole('seller')) {
            return false;
        }

        return $product->store
            && $product->store->seller_id === $user->seller?->id;
    }
}
