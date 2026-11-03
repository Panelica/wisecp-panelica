# Panelica module for WISECP 3

This repository contains the Panelica hosting server module for WISECP 3. It authenticates with Panelica's HMAC-signed External API and keeps API credentials encrypted in WISECP's server record.

## Install

Copy the repository's `Panelica` folder into `coremio/modules/Servers/Panelica` in your WISECP installation.

When adding a server in WISECP, enter:

- **Username:** the matching Panelica Root or Reseller username
- **Password:** leave blank; the module does not use password authentication
- **Access Hash:** enter the two matching API credentials in this format: `API_KEY:API_SECRET` (for example, `pk_live_xxxxx:sk_live_yyyyy`)
- **SSL:** enable certificate verification for a trusted HTTPS certificate
- **Port:** `8443` by default

Use the API key and secret belonging to the Root or Reseller username. The API key's permissions determine API access. Use a key with the scopes required for account and hosting provisioning. The module signs requests with HMAC-SHA256 and never sends the API secret over the network.

SSL certificate verification is enabled by default. The WISECP server's SSL verification setting is checked when testing the connection and is applied to all API requests. Only disable verification for a trusted panel with a self-signed certificate.

## Features

The module loads hosting plans, provisions accounts and domains, and supports suspend, unsuspend, terminate, plan changes, single sign-on, and client password changes.

Product module settings include **Otomatik SSL sertifikası oluştur**. When enabled, the module checks the new domain's SSL status and requests a certificate if one is not already active. Turning the option off prevents automatic issuance for future provisioning; the supplied API has no endpoint to remove an existing certificate.

Live connection and lifecycle tests require a WISECP installation and a Panelica server; those were not available in this workspace.
