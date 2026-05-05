<?php

namespace App\Plugins\Weathermap\Hooks;

use App\Models\User;
use App\Plugins\Hooks\MenuEntryHook;

class MenuHook extends MenuEntryHook
{
    public function authorize(User $user): bool
    {
        return $user->can('global-read');
    }

    public function data(array $settings = []): array
    {
        return [];
    }
}
