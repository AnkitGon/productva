<?php

namespace App\Http\Responses;

use App\Services\FactorySetupService;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    /**
     * Create an HTTP response that represents the value of a successful login.
     *
     * @param  Request  $request
     * @return Response
     */
    public function toResponse($request)
    {
        $user = $request->user();

        if ($user?->hasRole('super-admin')) {
            return redirect()->intended('/admin/dashboard');
        }

        $setup = app(FactorySetupService::class);

        if ($user && $setup->shouldOpenOnLogin($user)) {
            $setup->markPrompted($user);

            return redirect()->route('setup.index');
        }

        return redirect()->intended('/dashboard');
    }
}
