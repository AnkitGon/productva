<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function toResponse($request)
    {
        $user = $request->user();

        if ($user?->hasRole('super-admin')) {
            return redirect()->intended('/admin/dashboard');
        }

        if ($user?->hasRole('admin')) {
            return redirect()->intended('/dashboard');
        }

        return redirect()->intended('/dashboard');
    }

}
