<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Airline;
use Illuminate\Auth\Access\HandlesAuthorization;

class AirlinePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the airline.
     *
     * @param  User  $user
     * @param  Airline  $airline
     * @return mixed
     */
    public function view(User $user, Airline $airline)
    {
        return false;
    }

    /**
     * Determine whether the user can create airlines.
     *
     * @param  User  $user
     * @return mixed
     */
    public function create(User $user)
    {
        return false;
    }

    /**
     * Determine whether the user can update the airline.
     *
     * @param  User  $user
     * @param  Airline  $airline
     * @return mixed
     */
    public function update(User $user, Airline $airline)
    {
        return false;
    }

    /**
     * Determine whether the user can delete the airline.
     *
     * @param  User  $user
     * @param  Airline  $airline
     * @return mixed
     */
    public function delete(User $user, Airline $airline)
    {
        return false;
    }

    /**
     * Determine whether the user can restore the airline.
     *
     * @param  User  $user
     * @param  Airline  $airline
     * @return mixed
     */
    public function restore(User $user, Airline $airline)
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the airline.
     *
     * @param  User  $user
     * @param  Airline  $airline
     * @return mixed
     */
    public function forceDelete(User $user, Airline $airline)
    {
        return false;
    }
}
