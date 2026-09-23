# GS Redan CMS — Spec

This folder is the product + implementation spec for rebuilding gsredan.be as a
skeleton application in this repository (`gsredanupdate`).

Documents:

| File | Content |
| --- | --- |
| [00-overview.md](00-overview.md) | Product overview, goals, tech stack, user roles |
| [01-data-model.md](01-data-model.md) | Database schema, models, migration list |
| [02-public-site.md](02-public-site.md) | Public one-pager: blocks, rendering, menu, language |
| [03-admin.md](03-admin.md) | Login, dashboard, block admin, settings |
| [04-download-center.md](04-download-center.md) | Password-gated download center |
| [05-i18n.md](05-i18n.md) | Translation strategy: po trans tags + DB block content |
| [06-testing.md](06-testing.md) | Tests, commands, verification checklist |

Source references:

- Static site to transplant: `../redan/` (FR: `index.html`, NL: `nl/index.html`,
  EN: `en/index.html`; assets in `../redan/assets/`).
- Admin/login pattern to copy: `../vvsjongeren/` (skeleton 4, tabler).
