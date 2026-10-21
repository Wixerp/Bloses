<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AdminProductQuestionService
{
    /**
     * Admin: list and filter buyer-seller questions.
     */
    public function index(array $filters = []): array
    {
        $query = DB::table('product_questions as q')
            ->join('products as p', 'p.id', '=', 'q.product_id')
            ->join('users as u', 'u.id', '=', 'q.user_id')
            ->leftJoin('sellers as s', 's.id', '=', 'q.seller_id')
            ->select(
                'q.id',
                'q.product_id',
                'p.name as product_name',
                'q.user_id',
                'u.name as buyer_name',
                'q.seller_id',
                'q.question',
                'q.answer',
                'q.answered_at',
                'q.is_approved',
                'q.created_at',
                'q.updated_at'
            );

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);

            $query->where(function ($q) use ($search) {
                $q->where('q.question', 'ILIKE', "%{$search}%")
                    ->orWhere('q.answer', 'ILIKE', "%{$search}%")
                    ->orWhere('u.name', 'ILIKE', "%{$search}%")
                    ->orWhere('p.name', 'ILIKE', "%{$search}%");
            });
        }

        if (isset($filters['is_approved'])) {
            $query->where(
                'q.is_approved',
                filter_var(
                    $filters['is_approved'],
                    FILTER_VALIDATE_BOOLEAN
                )
            );
        }

        if (!empty($filters['product_id'])) {
            $query->where('q.product_id', $filters['product_id']);
        }

        if (!empty($filters['seller_id'])) {
            $query->where('q.seller_id', $filters['seller_id']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('q.user_id', $filters['user_id']);
        }

        if (($filters['answer_status'] ?? null) === 'answered') {
            $query->whereNotNull('q.answer')
                ->whereRaw("BTRIM(q.answer) <> ''");
        } elseif (($filters['answer_status'] ?? null) === 'unanswered') {
            $query->where(function ($q) {
                $q->whereNull('q.answer')
                    ->orWhereRaw("BTRIM(q.answer) = ''");
            });
        }

        $perPage = min(
            max((int) ($filters['per_page'] ?? 15), 1),
            100
        );

        $paginator = $query
            ->orderByDesc('q.created_at')
            ->orderByDesc('q.id')
            ->paginate($perPage);

        return [
            'items' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    /**
     * Admin: view one question.
     */
    public function show(int $id): object
    {
        $question = DB::table('product_questions as q')
            ->join('products as p', 'p.id', '=', 'q.product_id')
            ->join('users as u', 'u.id', '=', 'q.user_id')
            ->leftJoin('sellers as s', 's.id', '=', 'q.seller_id')
            ->where('q.id', $id)
            ->select(
                'q.id',
                'q.product_id',
                'p.name as product_name',
                'q.user_id',
                'u.name as buyer_name',
                'u.email as buyer_email',
                'q.seller_id',
                'q.question',
                'q.answer',
                'q.answered_at',
                'q.is_approved',
                'q.created_at',
                'q.updated_at'
            )
            ->first();

        if (!$question) {
            throw new ModelNotFoundException(
                'Product question not found.'
            );
        }

        return $question;
    }

    /**
     * Admin: approve or reject a question.
     */
    public function updateApproval(int $id, bool $isApproved): object
    {
        $exists = DB::table('product_questions')
            ->where('id', $id)
            ->exists();

        if (!$exists) {
            throw new ModelNotFoundException(
                'Product question not found.'
            );
        }

        DB::table('product_questions')
            ->where('id', $id)
            ->update([
                'is_approved' => $isApproved,
                'updated_at' => now(),
            ]);

        return $this->show($id);
    }
}
