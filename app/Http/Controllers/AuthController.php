<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    // Registracija novog korisnika
    public function register(Request $req)
    {
        $data = $req->validate([
            'name'     => 'required|string',
            'email'    => 'required|email|unique:users',
            'password' => 'required|string|min:6'
        ]);

        $user = new User();
        $user->name     = $data['name'];
        $user->email    = $data['email'];
        $user->password = bcrypt($data['password']);
        $user->save();

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user'  => $user,
            'token' => $token
        ], 201);
    }

    // Prijava postojećeg korisnika
    public function login(Request $req)
    {
        $data = $req->validate([
            'email'    => 'required|email',
            'password' => 'required|string'
        ]);

        if (!Auth::attempt($data)) {
            return response()->json(['message' => 'Neispravni kredencijali'], 401);
        }

        $token = $req->user()->createToken('api')->plainTextToken;

        return response()->json([
            'user'  => $req->user(),
            'token' => $token
        ]);
    }

    // Odjava korisnika
    public function logout(Request $req)
    {
        $req->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Uspešno ste se odjavili']);
    }
}
