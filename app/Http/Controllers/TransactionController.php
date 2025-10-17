<?php

namespace App\Http\Controllers;
use App\Models\Transaction;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $req)
    {
        // 1) Validacija query parametara
        $data = $req->validate([
            'q'           => 'nullable|string',        // pretraga po naslovu
            'category_id' => 'nullable|integer|exists:categories,id',
            'type'        => 'nullable|in:debit,credit',
            'account_id'  => 'nullable|integer|exists:accounts,id',
            'from'        => 'nullable|date',
            'to'          => 'nullable|date',
            'per_page'    => 'nullable|integer|min:1|max:100',
        ]);

        // 2) Osnovni upit — po default-u vidi transakcije samo svojih naloga
        $user = $req->user();

        $query = \App\Models\Transaction::query()
            ->with(['account:id,iban,currency,user_id', 'category:id,name'])
            ->when(!($user->role === 'admin' || $user->role === 'operator'), function ($q) use ($user) {
                $q->whereHas('account', fn($qa) => $qa->where('user_id', $user->id));
            });

        // 3) Filteri
        if (!empty($data['q'])) {
            $query->where('title', 'like', '%'.$data['q'].'%');
        }
        if (!empty($data['category_id'])) {
            $query->where('category_id', $data['category_id']);
        }
        if (!empty($data['type'])) {
            $query->where('type', $data['type']);
        }
        if (!empty($data['account_id'])) {
            $query->where('account_id', $data['account_id']);
        }
        if (!empty($data['from'])) {
            $query->whereDate('booked_at', '>=', $data['from']);
        }
        if (!empty($data['to'])) {
            $query->whereDate('booked_at', '<=', $data['to']);
        }

        // 4) Sort i straničenje
        $perPage = $data['per_page'] ?? 20;

        $result = $query
            ->orderBy('booked_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        return response()->json($result);
    }
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
