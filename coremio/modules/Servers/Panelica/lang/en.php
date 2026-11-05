<?php

declare(strict_types=1);

return [
    // Product form
    'setting_plan' => 'Panelica plan',
    'setting_plan_description' => 'The plan whose limits every account on this package gets.',
    'setting_role' => 'Account role',
    'setting_role_description' => 'A reseller account may create accounts of its own on the panel.',
    'role_user' => 'Customer',
    'role_reseller' => 'Reseller',
    'plans_unavailable' => 'The plans could not be read from the panel: :error',

    // One service's own form
    'service_plan_description' => 'The plan this one account is on. Changing it here changes it on the panel.',
    'service_account' => 'Panelica account',

    // Refusals
    'plan_required' => 'This package has no Panelica plan. Choose one on the product\'s Module tab.',
    'password_required' => 'A new password is needed.',
    'no_account_returned' => 'The panel accepted the account but returned no id.',
    'no_account_id' => 'This service has no Panelica account yet, so there is nothing to act on.',

    // Service page
    'domain' => 'Domain',
    'username' => 'Username',
    'status' => 'Status',
    'plan' => 'Plan',
    'panel' => 'Control panel',
    'nameservers' => 'Nameservers',
    'disk' => 'Disk',
    'bandwidth' => 'Traffic',
    'status_active' => 'Active',
    'status_suspended' => 'Suspended',
    'status_terminated' => 'Terminated',
    'status_pending' => 'Being set up',

    // Buttons and what happens when one cannot be followed
    'open_panel' => 'Open the panel',
    'open_webmail' => 'Webmail',
    'back' => 'Back',
    'panel_unavailable' => 'The panel could not be opened: :error',
    'no_sso_url' => 'The panel did not return a login link.',
];
