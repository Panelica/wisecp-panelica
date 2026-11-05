# Panelica module for WISECP

A WISECP server module that provisions and manages hosting accounts on a
Panelica server through Panelica's External API.

It runs on **both generations of WISECP's module API** — the 3.x line and the
current one — from one copy of the code. See [Two generations](#two-generations).

## Where this came from

The first version of this module was written by **Bilge Kara**
([@Gurbetseli](https://github.com/Gurbetseli)), who runs gurbetseli.net and has
spent twenty years around hosting control panels. He built it against his own
WISECP installation, published it under the MIT licence at
[Gurbetseli/Panelica-Wisecp3](https://github.com/Gurbetseli/Panelica-Wisecp3),
and asked us to carry it on. Every commit before this README is his, with his
name on it, and `LICENSE` keeps his copyright notice as the MIT licence
requires.

Maintenance, releases and support for the module now sit with us, the way they
do for [whmcs-panelica](https://github.com/Panelica/whmcs-panelica).

## State

Everything below is written and covered by tests that run on both generations,
and the whole lifecycle has been run against a **live Panelica** with the real
API client - no fake anywhere - on both generations: connect, the plan list,
open the account and its domain, read it back, the gauges, suspend, unsuspend,
change the password, move the plan, the service form, a one-time panel login,
and delete, with nothing left behind on the panel afterwards.

The module is also **installed on a real WISECP 3.1.9.8.2 install**, and the
whole lifecycle has been run there - on that server, with its own PHP 8.2.35,
against that operator's own Panelica over HTTPS with their own API key:
connect, the plan list, open the account and its domain, the disk and traffic
gauges, the client area page rendered from `pages/`, suspend, unsuspend,
change the password, move the plan, the service form, a one-time panel login,
the account list, and delete - with nothing left behind on the panel
afterwards.

**WISECP itself has still not driven it.** Defining the server, binding a
product to it and letting an order run through the queue is the operator's own
screen, and that is the last unproven step. The module is unreleased and this
repository is private until it is done.

### What the real install corrected

Reading one WISECP 3 sample module was not enough. On the live install:

- **The class name was wrong and would never have been found.** That core
  includes `modules/Servers/<Folder>/<Folder>.php` and looks for
  `<Folder>_Module` **in the global namespace**. All eighteen stock server
  modules are declared that way and not one of them uses a namespace; thirteen
  extend `ServerModule` and five extend nothing. WISECP 5 is the opposite:
  `namespace WISECP\Modules\Servers`, class `<Folder>`. The file now declares
  both - the work in the namespaced class, the older name a two-line subclass
  of it - because a class the core cannot find is the quietest failure there
  is: nothing is called and nothing is logged.
- **The module has to load its own parts.** The core includes `<Folder>.php`
  and nothing else in the directory, so the trait and the API client are
  required at the top of that file. The suite could not have caught this: its
  bootstrap loads them in advance.
- **The buyer is on `$this->user`**, not on the service: `email`, `name`,
  `surname`, `id`, `lang`. Twenty-seven of the stock modules' reads go there
  and nowhere else. Reading the wrong place does not fail - the account is
  opened with `hostmaster@<domain>` and the customer never hears from the
  panel.

And what it confirmed, which matters just as much:

- `KeyHelp` and `ApisCP` - both `type: hosting`, both extending `ServerModule` -
  use exactly the vocabulary this module carries: `testConnect`,
  `config_options`, `create`, `suspend`, `unsuspend`, `terminate`,
  `change_password`, `apply_updowngrade`, `getDisk`, `getBandwidth`,
  `listAccounts`, `clientArea`, `clientArea_buttons`,
  `save_adminArea_service_fields`, `use_clientArea_SingleSignOn`,
  `use_clientArea_webMail`. (The older `createAccount`/`removeAccount` family
  that cPanel, DirectAdmin, HestiaCP and CyberPanel use is a second style the
  same core still supports.)
- `create($domain = '', array $order_options = [])` and its return
  (`username`, `password`, `ftp_info` with `port` 21) match exactly, as does
  `getDisk()`'s (`limit`, `used`, `used-percent`, `format-limit`,
  `format-used`).
- `$this->config["user"]` is the service's username (128 reads),
  `$this->order["id"]`, `["options"]`, `["name"]` the order, and `save_log` is
  static - all as the stand-in models them.

### What the live run found that no faked test could

Three faults, each of them a wrong guess about what an endpoint wants, and each
invisible to a test that fakes the answer:

- `POST /v1/domains` wants **`name`**. `domain_name` was refused with "Field
  validation for 'Name' failed on the required tag", so **every order failed
  after its account had already been opened** - and because the account's id
  had not been stored yet, the cancellation that followed found nothing to
  delete and reported success. An account left open on the panel that nothing
  in WISECP could reach again: the same shape as the delete fault the module
  was reported for. The id is now kept the moment the account exists, a retry
  reuses the account and sees to the domain, and a domain the account already
  has is not added twice.
- `POST /v1/accounts/{id}/change-password` wants **`new_password`**.
  `password` was refused with "Invalid Request Body".
- The panel keeps **one account per email address**, and WISECP hands the same
  buyer's address to every service they order. A customer's second hosting
  package could not be provisioned at all: "Failed to create -
  apiErrors.users.emailAlreadyExists", for an order that had been paid for. The
  first account keeps the plain address; a later one gets
  `buyer+wisecp<service>@example.com`, which still reaches the same inbox. This
  is the same fix, and the same reasoning, as in `whmcs-panelica`.

### The panel's certificate

The module checks the panel's certificate first. If that check is what failed -
and only then - it makes the same call once more without it and remembers that
it had to: the connection test writes a line into the module log saying the
panel answered but could not be proven to be the right one, with the hostname
to get a certificate for. The call is not silently downgraded and the operator
is not silently reassured.

This is the same behaviour as `whmcs-panelica`, down to the list of TLS error
numbers, and it is there because a panel still on its self-signed certificate
would otherwise be unreachable and no message inside WISECP can fix a
certificate. What it is not is a reason to leave one in place: enter the
**hostname the certificate covers** on the server form - not the IP address,
which a certificate almost never covers - and the check passes.

## Two generations

WISECP asks a module for its work by calling a method by name, and it only
calls the names its own version knows. The two lines in the field disagree on
most of them:

| The job | WISECP 3 | WISECP 5 |
| --- | --- | --- |
| test the server | `testConnect()` | `test_connect()` |
| the package form | `config_options()` | `product_configuration()` |
| save that form | — (returned) | `save_product_configuration(array &$v)` |
| the service's own form | `adminArea_service_fields()` | `service_configuration()` |
| delete | `terminate()` | `cancel()` |
| change plan | `apply_updowngrade($o, $p)` | `upgrade($p)` |
| what the customer sees | `clientArea()` + `pages/` | `dashboard_data()` |
| disk and traffic | `getDisk()`, `getBandwidth()` | `metrics_usage()` |
| open the panel | `use_clientArea_SingleSignOn()` | `sso_panel_login()` |
| the account list | `listAccounts()` | — |

They also disagree on three things that are easy to miss because nothing
complains:

- **Where the data is.** WISECP 5 resolves the package's answers onto
  `$this->options` and the service onto `$this->service`. WISECP 3 keeps the
  answers under `$this->order["options"]["creation_info"]` and the service's own
  record on `$this->config`, and has no `$this->service` at all.
- **What the module may store.** WISECP 5 offers `save_options()` and
  `save_login_password()`, and reads every key of `create()`'s return as an
  instruction. WISECP 3 offers neither — calling one is a fatal error — and
  stores exactly `username`, `password` and `ftp_info`.
- **How a failure is reported.** WISECP 5 expects an exception; its own
  documentation calls assigning to `$this->error` "a leftover of the previous
  generation". WISECP 3 is the opposite: its caller does not catch, so a module
  that throws there shows a blank page.

So the module is one core (`PanelicaCore.php`) holding every decision once, a
compatibility layer that asks the host what it has before calling anything only
one of them offers, and two thin sets of verb names in `Panelica.php` that both
hand the work to that core. `config.php` carries both key spellings for the
same reason; a host ignores the keys it does not know.

### Why the first version did not work

Measured, not guessed. The reporter's install stamps its assets `?v=3.1.9.8.2`,
so it is a **WISECP 3** host, and the first version of the module carried the
newer generation's name for most of the jobs where the two differ. On his
server, these were simply absent (`git show a2a4fc38`):

| Name his host calls | Present in the first version |
| --- | --- |
| `clientArea()` | no — it had `clientArea_home()` and `dashboard_data()` |
| `config_options()` | no — it had `product_configuration()` |
| `adminArea_service_fields()` | no |
| `getDisk()`, `getBandwidth()` | no |

A name the core cannot find is not an error anywhere: nothing is called and
nothing is logged. That is the whole of why the customer's service page was
empty, why no plan could be chosen on his host, and why there was no package
form in the admin area.

Deleting had a different cause, in code that was present. `removeAccount()`
read the account id from `$this->options['config']['panelica_account_id']`,
which was only ever written with `$this->options[...] = ...` and never
persisted — WISECP 3 has no `save_options()` — so the id was always missing. It
then looked the account up by username, and **returned `true` when it found
nothing**, so WISECP recorded the cancellation as done while the account stayed
open on the panel.

One more thing worth recording: that file held two classes, `Panelica` and
`Panelica_Module` (line 394 of 791). WISECP loads the one named after the
module's folder, so roughly half the file never ran at all.

## What the module does

1. **The customer sees their account.** Domain, username, status, plan,
   nameservers, a link to the panel, and disk and traffic — as gauges on
   WISECP 5, as a table from `pages/clientArea-home.php` on WISECP 3, using the
   markup its themes already style. Everything the customer or the panel
   supplies is escaped on the way out.
2. **Deleting in WISECP deletes on the panel.** An account already gone counts
   as deleted, so a half-finished cancellation can still finish; any other
   refusal is reported rather than swallowed.
3. **Upgrade and downgrade.** The account moves to the plan the new package
   carries. A package with no plan is refused instead of quietly doing nothing.
4. **The package is editable from the admin panel.** The service's own form
   offers the panel's plans and names the account. The panel is changed first,
   so a refusal stops the save and WISECP never records a plan the account is
   not on. Saving without touching the plan sends nothing.
5. **Order and account stay in step.** `create()` opens the account and its
   domain and recognises a half-finished order instead of opening a second
   account; suspend, unsuspend, password change and delete follow it. A renewal
   answers without touching the panel, because a name the core cannot find is
   recorded as a failed renewal.

### Single sign on

Both generations can open the panel as the account's own user, and the operator
can open it as the panel's operator from the admin side. The module asks
Panelica for a one-time link and never puts a password in a URL.

## Installing

Copy the module into the WISECP installation:

```
<wisecp>/coremio/modules/Servers/Panelica/
```

Then add the server in the admin panel, under Products → Hosting → Servers:

| Field | Value |
| --- | --- |
| Automation type | Panelica |
| Hostname / IP | the panel's hostname or IP |
| Port | the panel's port (8443 by default) |
| Root/Reseller name | anything; the module does not use it |
| Password | the Panelica **API key** |
| Access hash | the Panelica **API secret** |
| NS1 / NS2 | the nameservers the customer should use |

Press Test Connection. It reads the plan list, which needs the key, the secret
and the clock to all be right, and reads nothing else.

The key's scopes, as the External API requires them:

| Scope | What needs it |
| --- | --- |
| `plans:read` | the plan list, and the connection test |
| `accounts:read` | reading the account, its disk usage, the account list |
| `accounts:write` | opening, suspending, moving and deleting an account |
| `domains:write` | the account's domain |
| `bandwidth:read` | the traffic gauge |
| `*:*` | **only** for the one-time panel login, which the API restricts to a full-access key |

A key without `*:*` works for everything except the panel button; the button
then reports that the panel refused it rather than failing silently.

Then set the plan on the product, under its Module tab.

## Tests

The suite runs with no WISECP, no database and no network: the base class is
stood in and the Panelica API is faked, so every line under test is the line
that ships.

```bash
composer install
composer test          # both generations, one run each
composer test:v5       # the shared behaviour and the WISECP 5 verbs
composer test:v3       # the shared behaviour and the WISECP 3 verbs
```

There is also a live harness, which is not part of the suite and needs a
running panel: `tests/` fakes the API to prove the module's decisions, while
`scripts/live-lifecycle.php` runs the real client against a real Panelica to
prove the endpoints. The three faults above came from the second kind, and the
first kind now holds them so they cannot come back.

A PHP process can define `ServerModule` only once, so one run is one host:
`tests/stubs-v5.php` and `tests/stubs-v3.php` are the two stand-ins, and
`WISECP_GENERATION` picks which the bootstrap loads. `tests/Shared` is the
behaviour both hosts must show and runs under each of them; `tests/V3` and
`tests/V5` carry what is true of one host only. "It works on both generations"
means those two runs, and nothing less.

Each stand-in is built from what was read, not from what would be convenient:
the WISECP 3 one has no `save_options()` and no `save_login_password()`, so a
module that reaches for either fails the test the way it would fail on a
customer's server.

## Requirements

PHP 8.1 or newer, which is what the API client already needed. The test runner
needs 8.2; that the module itself parses on 8.1 is checked in CI.

## Licence

MIT, as Bilge Kara released it. See `LICENSE` and `AUTHORS`.
