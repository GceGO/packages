<?php

declare(strict_types=1);

use Gcego\Luminaries\Icons\Alias;
use Gcego\Settings\SettingsPage;

return new SettingsPage(name: 'school', title: 'School settings', group: 'institution', scope: 'tenant', description: 'Settings of your institution.', icon: Alias::SettingsGeneral);
