# Panelica module for WiseCP

A WiseCP server module that provisions and manages hosting accounts on a
Panelica server through Panelica's External API.

## Where this came from

The first version of this module was written by **Bilge Kara**
([@Gurbetseli](https://github.com/Gurbetseli)), who runs gurbetseli.net and has
spent twenty years around hosting control panels. He built it against his own
WiseCP installation, published it under the MIT licence at
[Gurbetseli/Panelica-Wisecp3](https://github.com/Gurbetseli/Panelica-Wisecp3),
and asked us to carry it on. Every commit before this README is his, with his
name on it, and `LICENSE` keeps his copyright notice as the MIT licence
requires.

Maintenance, releases and support for the module now sit with us, the way they
do for [whmcs-panelica](https://github.com/Panelica/whmcs-panelica).

## State

The five gaps below are written and covered by tests. **Nothing has yet been run
against a real WISECP installation**, so the module is unreleased and this
repository is private until it has been.

What was wrong, and is now fixed:

1. **The customer saw nothing.** The service page is built from
   `dashboard_data()`. The first version answered in `clientArea_home()` and
   `client_area_data()`, which the core does not call, so the page had nothing
   to draw. It now shows the domain, the username, the status, the plan, the
   nameservers, a link to the panel, and disk and traffic as gauges.
2. **Deleting did not reach the panel.** The core calls `cancel()`; the delete
   lived in `removeAccount()` and `terminate()`, which nothing calls. `cancel()`
   now deletes the account, treats one already gone as done, and does not
   swallow any other refusal.
3. **No upgrade or downgrade.** `upgrade()` moves the account to the plan the
   new package carries, and refuses a package that has no plan rather than
   doing nothing quietly.
4. **No package edit from the admin area.** `product_configuration()` offers the
   panel's plans as a dropdown and `save_product_configuration()` refuses a
   package without one. A panel that cannot be reached leaves the form usable
   with the saved value in a text box.
5. **Order and account were not in step.** `create()` opens the account and its
   domain, keeps the account id on the service, and recognises a half-finished
   order instead of opening a second account. `suspend()`, `unsuspend()`,
   `change_password()` and `cancel()` follow it.

### Why it did not work

The first version carried two generations of WISECP's module API at once:
`testConnect()` beside `test_connect()`, `terminate()` and `removeAccount()`
beside `cancel()`, `apply_updowngrade()` and `change_plan()` beside `upgrade()`,
`clientArea_home()` and `client_area_data()` beside `dashboard_data()`. For
every job one name was read and the other sat unused. `config.php` had the same
split: it used the WISECP 3 keys (`access-hash`, `server-info-checker`,
`server-info-port`), which the current core does not read, so the server form
offered no access-hash field and no connection test.

There is one generation here, the current one, as documented at
[dev.wisecp.com](https://dev.wisecp.com/en/writing-a-server-module) and as used
by modules maintained today.

## Tests

```bash
composer install
composer test
```

27 tests, no WISECP and no network: `tests/stubs.php` stands in for the class
WISECP owns, and `tests/FakeApi.php` answers for the panel. A test says what the
panel replies and then reads back which calls the module made, in order. The
module under test is the module that ships: `tests/TestPanelica.php` overrides
`define_server_info()` and nothing else.

What tests cannot prove, and a real installation still has to: that WISECP calls
these methods with the data shapes assumed here, that the service page renders
inside a theme, and that an order runs end to end.

## How a server is defined in WiseCP

| Field | Value |
|---|---|
| Hostname | the Panelica server's hostname |
| NS1 / NS2 | `ns1.<domain>` / `ns2.<domain>` |
| Automation | Panelica |
| IP address | the server's IP or hostname |
| Root/Reseller name | `root` |
| Password | the Panelica **API key** |
| Access hash | the Panelica **API secret** |

The module talks to the External API on port 3002, signed with HMAC-SHA256.

## Layout

```
coremio/modules/Servers/Panelica/
├── Panelica.php        the module: WiseCP calls these methods
├── ApiClient.php       the External API client (HMAC signing, requests)
├── config.php          what WiseCP shows on the server and package forms
├── lang/{en,tr}.php    the strings
└── pages/              the admin and client-area templates
```

## Licence

MIT — see [LICENSE](LICENSE). Copyright (c) 2026 Bilge, and contributors.
