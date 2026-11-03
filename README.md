# Panelica module for WISECP 3

This repository contains the Panelica hosting server module for WISECP 3. It authenticates with Panelica's HMAC-signed External API and keeps API credentials encrypted in WISECP's server record.

## Install

Copy `coremio/modules/Servers/Panelica` into the same path in your WISECP installation. The installed directory must be named `Panelica`.

When adding a server in WISECP, enter:

- **Username:** the matching Panelica Root or Reseller username
- **Password:** leave blank; the module does not use password authentication
- **API_KEY:** the API key belonging to that Root or Reseller
- **API_SECRET:** that key's matching API secret
- **SSL:** enable certificate verification for a trusted HTTPS certificate
- **Port:** `8443` by default

The API key's owner and permissions determine the API access. Use a key with the scopes required for account and hosting provisioning. The module signs requests with HMAC-SHA256 and never sends the API secret over the network.

SSL certificate verification is enabled by default. The WISECP server's SSL verification setting is checked when testing the connection and is applied to all API requests. Only disable verification for a trusted panel with a self-signed certificate.

## Features

The module loads hosting plans, provisions accounts and domains, and supports suspend, unsuspend, terminate, plan changes, single sign-on, and client password changes.

Live connection and lifecycle tests require a WISECP installation and a Panelica server; those were not available in this workspace.
