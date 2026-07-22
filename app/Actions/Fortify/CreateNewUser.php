<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

use App\Models\Organization;
use App\Models\Plant;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'plant_name' => ['required', 'string', 'max:255'],
        ])->validate();

        return DB::transaction(function () use ($input) {
            // 1. Create the Organization
            $organization = Organization::create([
                'name' => $input['name'] . ' Organization',
            ]);

            // 2. Generate slug and code for the plant
            $slug = Str::slug($input['plant_name']);
            $code = Str::upper(Str::limit(preg_replace('/[^A-Za-z0-9]/', '', $input['plant_name']), 5));

            // 3. Create the Plant
            $plant = Plant::create([
                'organization_id' => $organization->id,
                'name' => $input['plant_name'],
                'code' => $code ?: 'PLNT',
                'slug' => $slug ?: 'plant',
                'status' => 'Active',
                'is_default' => true,
            ]);

            // 4. Create the User
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
                'organization_id' => $organization->id,
                'active_plant_id' => $plant->id,
            ]);

            // 5. Assign the Admin Role
            $adminRole = Role::where('slug', 'admin')->first();
            if ($adminRole) {
                $user->roles()->syncWithoutDetaching([$adminRole->id]);
            }

            return $user;
        });
    }
}
