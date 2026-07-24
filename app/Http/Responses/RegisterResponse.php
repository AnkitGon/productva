<?php

namespace App\Http\Responses;

use App\Services\FactorySetupService;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Symfony\Component\HttpFoundation\Response;

class RegisterResponse implements RegisterResponseContract
{
    /**
     * @param  Request  $request
     * @return Response
     */
    public function toResponse($request)
    {
        $user = $request->user();
        $setup = app(FactorySetupService::class);

        if ($user && $setup->shouldOpenOnLogin($user)) {
            $setup->markPrompted($user);

            return redirect()->route('setup.index');
        }

        return redirect()->intended(config('fortify.home'));
    }
}
