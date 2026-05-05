<?php

namespace App\Plugins\Weathermap;

use App\Models\User;
use App\Plugins\Hooks\MenuEntryHook;

class Menu extends MenuEntryHook
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
