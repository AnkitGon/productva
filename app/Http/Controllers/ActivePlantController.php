<?php

namespace App\Http\Controllers;

use App\Models\Plant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ActivePlantController extends Controller
{
    /**
     * Activate the specified plant for the authenticated user.
     */
    public function __invoke(Request $request, Plant $plant): RedirectResponse
    {
        $user = $request->user();

        // Verify the plant belongs to the user's organization
        if ($user && $plant->organization_id === $user->organization_id) {
            $user->update([
                'active_plant_id' => $plant->id,
            ]);
        }

        return redirect()->back();
    }
}
