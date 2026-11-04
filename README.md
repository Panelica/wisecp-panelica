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

**Not finished, and not released.** This repository is private until the module
does what it says. What works today is the connection, defining a Panelica
server in WiseCP, and creating an account. Bilge named the gaps himself, and
they are the work ahead:

1. **The customer sees nothing.** A hosting package's detail page in the WiseCP
   client area shows no domain, username, password or nameservers.
2. **Deleting does not reach the panel.** Deleting a hosting account in WiseCP
   leaves the account on the Panelica server.
3. **No upgrade or downgrade.** A customer cannot move between packages.
4. **No package edit from the admin area.** Changes an operator makes in WiseCP
   are not carried to the Panelica server.
5. **Order and account are not in step.** Creating an order, opening the
   account, showing its details, changing the package, suspending and deleting
   should follow each other without hand-holding.

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
