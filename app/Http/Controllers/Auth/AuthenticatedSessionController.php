<?php

namespace App\Http\Controllers\Auth;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController
{
    public function store(LoginRequest $request)
    {
        return $request->authenticate(null);
    }

    public function customer(LoginRequest $request)
    {
        return $request->authenticate('customer');
    }

    public function login_as_pdo_owner(Request $request)
    {
        return $request->authenticate_as_pdo_owner();
    }
}
