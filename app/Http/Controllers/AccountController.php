<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    private function authorizeAccount(Request $req, Account $account): void
    {
        abort_unless($account->user_id === $req->user()->id, 403, 'Forbidden');
    }

    public function index(Request $req)
    {
        return $req->user()->accounts()->get();
    }
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $req)
    {
        $data = $req->validate([
            'iban'     => 'required|unique:accounts',
            'currency' => 'required|size:3',
            'balance'  => 'nullable|numeric'
        ]);

        $acc = $req->user()->accounts()->create([
            'iban'     => $data['iban'],
            'currency' => $data['currency'],
            'balance'  => $data['balance'] ?? 0,
            'status'   => 'active',
        ]);

        return response()->json($acc, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $req, Account $account)
    {
        $this->authorizeAccount($req, $account);
        return $account->load('transactions');
    }

    /**
     * Update the specified resource in storage.
     */
     public function update(Request $req, Account $account)
    {
        $this->authorizeAccount($req, $account);
        $data = $req->validate([
            'status' => 'in:active,blocked'
        ]);
        $account->update($data);
        return $account;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $req, Account $account)
    {
        $this->authorizeAccount($req, $account);
        $account->delete();
        return response()->json(null, 204);
    }
}
