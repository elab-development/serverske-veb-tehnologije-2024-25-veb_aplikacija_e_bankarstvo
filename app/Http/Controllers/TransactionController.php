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
        $req->validate([
            'search'      => 'nullable|string',
            'category_id' => 'nullable|integer',
            'type'        => 'nullable|in:debit,credit',
            'date_from'   => 'nullable|date',
            'date_to'     => 'nullable|date',
            'account_id'  => 'nullable|integer',
        ]);

        $q = Transaction::query()
            ->whereHas('account', fn($a) => $a->where('user_id', $req->user()->id));

        if ($req->filled('account_id'))  $q->where('account_id', $req->account_id);
        if ($req->filled('search'))      $q->where('title', 'like', '%'.$req->search.'%');
        if ($req->filled('category_id')) $q->where('category_id', $req->category_id);
        if ($req->filled('type'))        $q->where('type', $req->type);
        if ($req->filled('date_from'))   $q->whereDate('booked_at', '>=', $req->date_from);
        if ($req->filled('date_to'))     $q->whereDate('booked_at', '<=', $req->date_to);

        return $q->orderBy('booked_at','desc')->paginate(20);
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
