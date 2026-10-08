<?php

namespace App\Services\Admin\User;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function getUsers(array $filters = [])
    {
        $orderStats = DB::table('order_items')
            ->select('seller_id')
            ->selectRaw('COUNT(DISTINCT order_id) as total_orders')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as earnings')
            ->groupBy('seller_id');

        $query = DB::table('users')
            ->leftJoin('master_types as gender','gender.id','=','users.gender_id')
            ->leftJoin('master_types as status_type','status_type.id','=','users.status_id')
            ->leftJoinSub($orderStats,'order_stats',
                function ($join) {
                    $join->on('order_stats.seller_id','=','users.id');
                }
            )
            ->select(
                'users.id','users.name','users.email','users.email_verified_at','users.phone','users.gender_id',
                'gender.value as gender','gender.description as gender_label','users.status_id',
                'status_type.value as account_status','status_type.description as account_status_label',
                DB::raw("CASE WHEN users.is_block = 1 THEN 'Blocked' ELSE 'Active' END as status"),
                'users.last_login_at','users.created_at','users.updated_at'
            )
            ->selectRaw('COALESCE(order_stats.total_orders, 0) as total_orders')
            ->selectRaw('COALESCE(order_stats.earnings, 0) as earnings');

        // Search
        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('users.name', 'ILIKE', "%{$search}%")
                    ->orWhere('users.email', 'ILIKE', "%{$search}%")
                    ->orWhere('users.phone', 'ILIKE', "%{$search}%");
            });
        }

        if (isset($filters['status_id'])) {
            $query->where('users.status_id', $filters['status_id']);
        }

        // Gender filter
        if (isset($filters['gender_id'])) {
            $query->where('users.gender_id', $filters['gender_id']);
        }

        return $query->orderByDesc('users.created_at')->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                    'phone' => $user->phone,
                    'gender_id' => $user->gender_id,
                    'gender' => $user->gender,
                    'gender_label' => $user->gender_label,
                    'status_id' => $user->status_id,
                    'account_status' => $user->account_status,
                    'account_status_label' => $user->account_status_label,
                    'status' => $user->status,
                    'total_orders' => (int) $user->total_orders,
                    'earnings' => (float) $user->earnings,
                    'last_login_at' => $user->last_login_at,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ];

            })->values();
    }

    public function getUser(int $id): object
    {
        $user = DB::table('users')
            ->leftJoin('master_types as gender','gender.id','=','users.gender_id')
            ->leftJoin('master_types as status','status.id','=','users.status_id')
            ->select(
                'users.id','users.name','users.email','users.email_verified_at','users.phone','users.gender_id',
                'gender.value as gender','gender.description as gender_label','users.status_id','status.value as status',
                'status.description as status_label','users.last_login_at','users.created_at','users.updated_at'
            )->where('users.id', $id)->first();

        if (!$user) {
            abort(404, 'User not found.');
        }

        return $user;
    }

    public function update(int $id, array $data): object
    {
        $user = DB::table('users')->where('id', $id)->first();

        if (!$user) {
            abort(404, 'User not found.');
        }

        // Check duplicate email
        if (isset($data['email'])) {
            $exists = DB::table('users')
                ->whereRaw('LOWER(email) = ?', [
                    strtolower(trim($data['email']))
                ])->where('id', '!=', $id)->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'email' => 'This email is already in use.',
                ]);
            }
        }

        $updateData = [
            'name' => $data['name'],
            'email' => strtolower(trim($data['email'])),
            'phone' => $data['phone'] ?? null,
            'gender_id' => $data['gender_id'] ?? null,
            'status_id' => $data['status_id'] ?? null,
            'updated_at' => now(),
        ];

        // Password only update if provided
        if (!empty($data['password'])) {
            $updateData['password'] = Hash::make(
                $data['password']
            );
        }

        DB::table('users')->where('id', $id)->update($updateData);

        return $this->getUser($id);
    }

    public function delete(int $id): bool
    {
        $user = DB::table('users')
            ->where('id', $id)
            ->first();

        if (!$user) {
            abort(404, 'User not found.');
        }

        DB::table('users')
            ->where('id', $id)
            ->delete();

        return true;
    }

    public function is_blocked(int $id, int $isBlocked): bool
    {
        $user = DB::table('users')
            ->where('id', $id)
            ->first();

        if (!$user) {
            abort(404, 'User not found.');
        }

        DB::table('users')
            ->where('id', $id)
            ->update([
                'is_block' => $isBlocked,
                'updated_at' => now(),
            ]);

        return true;
    }
}
