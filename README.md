# Panelica module for WISECP 3.1.9.8.2

This is a separate WISECP V3 server module. It uses the legacy V3 module contract and is not the WISECP V5 project in the parent directory.

## Install

Copy `coremio/modules/Servers/Panelica` into the matching `coremio/modules/Servers/` directory of your WISECP installation. The final module directory must be named `Panelica` and contain `Panelica.php`, `config.php`, and `lib/PanelicaAPI.php`.

In WISECP, add a server with:

- **Hostname/IP:** Panelica server host name or IP
- **Port:** `8443`
- **Password:** Panelica External API key
- **Access Hash:** Panelica External API secret
- **Secure connection:** enabled

Create the Panelica API key with the scopes needed for account and hosting provisioning: `accounts:read`, `accounts:write`, `accounts:delete`, `domains:write`, `plans:read`, and `bandwidth:read`. The API endpoint must be reachable over HTTPS. To match the supplied Panelica PHP SDK and support the self-signed certificate shown in your setup, `config.php` defaults `panelica-verify-tls` to `false`.

This setting disables certificate and hostname verification for module API requests. Use it only when you trust the Panelica server; installing a publicly trusted certificate and setting the option to `true` is the recommended production setup.

When configuring a WISECP product, select the Panelica hosting plan returned by the server. The module provisions a Panelica account and attaches the product's domain. It supports suspend, unsuspend, terminate, package upgrades/downgrades, client/admin SSO, and client password changes.

## Compatibility and validation

The module follows the archived WISECP V3 sample server module's global `Panelica_Module extends ServerModule` structure and V3 callbacks such as `testConnect()`, `config_options()`, `create()`, and `apply_updowngrade()`.

This source was prepared for WISECP 3.1.9.8.2. A live WISECP installation and Panelica server were not available in this workspace, so end-to-end provisioning and PHP runtime validation must be completed in a staging installation before production use.
