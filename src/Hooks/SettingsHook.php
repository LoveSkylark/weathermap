<?php

namespace App\Plugins\Weathermap\Hooks;

use App\Models\User;
use App\Plugins\Hooks\SettingsHook as BaseSettingsHook;

class SettingsHook extends BaseSettingsHook
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
