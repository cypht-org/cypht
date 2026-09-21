# Installing Cypht on shared hosting without root access

This guide covers a non-root install layout for shared hosting control panels
such as Virtualmin, cPanel, or Plesk, where you have SSH access to your own
account but no root privileges. It follows up on the recurring request in
[issue #17](https://github.com/cypht-org/cypht/issues/17): keep the Cypht
source and its data directories outside the web root, using only the
directories your hosting account already owns.

For a standard install with root access, see [https://cypht.org/install](https://cypht.org/install).

## Layout

Shared hosting accounts typically give you a home directory with a
`public_html` folder that the web server serves directly. Everything else in
your home directory is private. Cypht should live outside `public_html`, and
only its built, static output should be reachable from the web:

```
/home/youraccount/
├── cypht/                 # source, outside the web root
│   ├── .env
│   ├── scripts/
│   └── site/              # built by config_gen.php, this is what gets served
├── cypht-data/             # outside the web root
│   ├── users/
│   └── attachments/
└── public_html/
    └── webmail -> ../cypht/site   # symlink, or your host's alias/subdomain feature
```

None of `cypht/`, `.env`, or `cypht-data/` are inside `public_html`, so they
are not reachable by a URL regardless of what the web server config allows.

## Steps

1. Upload or clone Cypht into `~/cypht`, next to (not inside) `public_html`.

2. Install PHP dependencies:

   ```sh
   cd ~/cypht
   composer install --no-dev
   ```

3. Run the install wizard:

   ```sh
   php scripts/install.php
   ```

   When it asks for the settings and attachment directories, point them
   outside the web root, e.g. `/home/youraccount/cypht-data/users` and
   `/home/youraccount/cypht-data/attachments`. The wizard creates them,
   writes `.env`, sets up the database, and builds `site/`.

4. Expose `cypht/site` under `public_html`. If your account can create
   symlinks:

   ```sh
   ln -s ~/cypht/site ~/public_html/webmail
   ```

   If symlinks are not available, check whether your control panel offers a
   "point a subdomain/alias at this folder" option (Virtualmin and cPanel
   both do) and point it at `~/cypht/site` directly instead of copying files.

5. Confirm the parts outside the web root are not reachable. From outside
   your network, requesting the equivalent of `https://yourdomain/../.env` or
   any path resolving to `cypht/.env` or `cypht-data/` should return a 404,
   not the file content.

## Re-running the wizard

`scripts/install.php` refuses to run once `.env` exists, so it cannot
overwrite a working install by accident. To reconfigure from scratch, remove
`.env` first.
