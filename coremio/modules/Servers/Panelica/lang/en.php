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
    'no_server' => 'No Panelica server is chosen yet, so the panel cannot be asked anything.',
    'plan_unknown' => 'The panel has no plan called :plan. Choose one on the product\'s Module tab.',
    'domain_failed' => 'The account :username was opened, but the panel refused the domain :domain, so the site is not set up: :error. Running create again will reuse the account and try the domain once more.',

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
    'password' => 'Password',

    // The welcome mail and the admin's service screen
    'mail_intro' => 'Your hosting account is ready. These are its details.',
    'service_username_info' => 'The account this service has on the panel. Changing it here only changes what WISECP records.',
    'panel_unavailable' => 'The panel could not be opened: :error',
    'no_sso_url' => 'The panel did not return a login link.',
];
