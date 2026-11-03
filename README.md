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

Use the API key and secret belonging to the Root or Reseller username. The API key's permissions determine API access. For provisioning, allow the needed `accounts:read`, `accounts:write`, `plans:read`, `domains:read`, and `domains:write` scopes. Automatic SSL issuance additionally needs `ssl:read` and `ssl:write`; the usage cards need `bandwidth:read`. The module signs requests with HMAC-SHA256 and never sends the API secret over the network.

The WISECP service editor can create or convert a hosting account to the Panelica `RESELLER` role and assign a plan fetched from Panelica. Panelica only allows ROOT or ADMIN owners to grant the reseller role. Its documented API does not expose the old CyberPanel ACL list or per-reseller account-count/custom bandwidth-limit fields, so the service editor does not offer those controls; Panelica enforces roles and the assigned plan on its side. The same editor applies supported plan, role, and password changes to the Panelica account when saved.

For the current server configuration, `panelica-verify-tls` is set to `false` because the certificate does not match the configured IP address. API traffic still uses HTTPS, but certificate and hostname verification are disabled for the connection test and all API requests. Set this to `true` after installing a trusted certificate matching the panel hostname.

## Features

The module loads hosting plans, provisions accounts and domains, and supports suspend, unsuspend, terminate, plan changes, single sign-on, and client password changes.

Product module settings include **Otomatik SSL sertifikası oluştur**. When enabled, the module checks the new domain's SSL status and requests a certificate if one is not already active. Turning the option off prevents automatic issuance for future provisioning; the supplied API has no endpoint to remove an existing certificate.

Live connection and lifecycle tests require a WISECP installation and a Panelica server; those were not available in this workspace.

