# Cypht

This is the official docker image of [Cypht](https://cypht.org/). It replaces [sailfrog/cypht-docker](https://hub.docker.com/r/sailfrog/cypht-docker)

## Features of this image

* Alpine linux based image
* Bundled nginx and PHP provides everything in one image
* Performs same install steps as found on [Cypht install page](https://cypht.org/install.html)
* All Cypht mods and configuration options can be set via environment variables
* Automatic database setup (if configured to use database)

It recommended that you choose a specific version number tag instead of using 'latest' since 'latest' may represent master which may not be stable.

## Example docker-compose

See example file here:
https://github.com/cypht-org/cypht/blob/master/docker/docker-compose.yaml

* Copy it to where ever you want. It does not need to be in the repo.
* Starts a database container to be for user authentication.
* Starts the Cypht container available on port 80 of the host with:
  * A local volume declared for persisting user settings across container reboots
  * An initial user account for authentication
  * Environment variables for accessing the database container

*NOTE: Please change usernames and passwords before using this docker-compose in your environment*

## Environment variables

See all the environment variables you can set here:
https://github.com/cypht-org/cypht/blob/master/.env.example

To see the meaning of what each variable see descriptions here:
https://github.com/cypht-org/cypht/blob/master/config/app.php


It is recommended that in production you instead make a copy of this file:
```
cp .env.example /etc/cypht-prod.env
```

Make changes to it and source it in to the docker-compose via 'env_file':
```yaml
    env_file:
      - /etc/cypht-prod.env
```

In order to avoid confusion, it is best to use only the env file and not set addition env vars in the docker compose file if possilbe.
