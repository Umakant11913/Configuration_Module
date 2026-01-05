<?php

namespace App\Services;

use App\Models\User;

class CentralUserService
{
    public static function resolve($request)
    {
        $centralUser = $request->central_user ?? null;

        if (!$centralUser || !isset($centralUser['userId'])) {
            return null;
        }

        $user = User::find($centralUser['userId']);

        if ($user && $user->parent_id) {
            $user = User::find($user->parent_id);
        }

        return $user;
    }
}
