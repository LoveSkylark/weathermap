<?php

namespace App\Plugins\Weathermap;

use App\Models\User;

class Menu
{
    public function authorize(User $user): bool
    {
        return true;
    }

    public function data(array $settings = []): array
    {
        return [];
    }
}
