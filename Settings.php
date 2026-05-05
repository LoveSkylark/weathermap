<?php

namespace App\Plugins\Weathermap;

use App\Models\User;
use App\Plugins\Hooks\SettingsHook;

class Settings extends SettingsHook
{
    public function authorize(User $user): bool
    {
        return $user->isAdmin();
    }

    public function data(array $settings = []): array
    {
        return [
            'sort_if_by'      => $settings['sort_if_by'] ?? 'ifAlias',
            'show_interfaces' => $settings['show_interfaces'] ?? 'all',
            'sort_options'    => ['ifAlias', 'ifDescr', 'ifIndex', 'ifName'],
        ];
    }
}
