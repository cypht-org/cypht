[![Coverage Status](https://coveralls.io/repos/github/jasonmunro/cypht/badge.svg?branch=master)](https://coveralls.io/github/jasonmunro/cypht?branch=master)
[![CII Best Practices](https://bestpractices.coreinfrastructure.org/projects/127/badge)](https://bestpractices.coreinfrastructure.org/projects/127)

#### Cypht
[https://cypht.org](https://cypht.org)

All your email, from all your accounts, in one place. Supports IMAP/SMTP,
[JMAP](https://github.com/cypht-org/cypht/issues/180) and
[EWS](https://github.com/cypht-org/cypht/issues/247). Cypht is like a
news reader, but for E-mail. Cypht does not replace your existing accounts - it
combines them into one. And it's also a news reader.

![screenshot](https://github.com/cypht-org/cypht-website/blob/master/static/img/Inbox.PNG "Inbox View").

The driving force behind Cypht development is to provide combined views for
multiple accounts, but it's also a standard E-mail client that lets you browse
and manage IMAP folders and send outbound messages with SMTP.


Cypht is an application built entirely of plugins, or as we call them, module
sets (which is obviously way cooler sounding than plugins), that are executed
by the framework. Modules provide a flexible way to add new features or
customize the program without hacking the code.


Installation instructions
* All Cypht latest and master versions: [https://cypht.org/install](https://cypht.org/install)

Troubleshooting
* PHP version: Cypht requires PHP 8.1 or higher. Check your version with `php -v`. If needed, update PHP or use a different path to a newer PHP binary.
* Missing PHP extensions: Common required extensions include curl, mbstring, xml, and sodium. If you see errors about missing extensions, install them via your system package manager (e.g., `apt-get install php-curl php-mbstring php-xml php-sodium`).
* Database connection issues: Double-check your `.env` file settings for database host, port, username, and password. Ensure the database server is running and accessible.
* Session/cookie problems in development: If you experience login issues or session errors, verify that PHP can write to the configured session save path. Also check that cookies are not being blocked by your browser.

Monthly community meetings: [https://github.com/cypht-org/cypht/wiki/Monthly-Community-Meetings](https://github.com/cypht-org/cypht/wiki/Monthly-Community-Meetings)

Security details: [https://cypht.org/security](https://cypht.org/security)

Module info: [https://cypht.org/modules](https://cypht.org/modules)

Features: [https://cypht.org/features](https://cypht.org/features)

License: [https://cypht.org/license](https://cypht.org/license)

Community chat room: [https://gitter.im/cypht-org/community](https://gitter.im/cypht-org/community)

For developers, get via Composer: [https://packagist.org/packages/jason-munro/cypht](https://packagist.org/packages/jason-munro/cypht)

Docker: [https://hub.docker.com/r/cypht/cypht](https://hub.docker.com/r/cypht/cypht)

LinkedIn group: [https://www.linkedin.com/groups/13804559/](https://www.linkedin.com/groups/13804559/)

An interview with the project's founder: https://github.com/cypht-org/cypht/wiki/AMA-Jason-Munro
