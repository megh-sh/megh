# Megh

Megh is a command line interface for managing multiple applications (WordPress, Laravel, etc.) running simultaneously on port 80.

## Requirements

- Docker [[Ubuntu](https://docs.docker.com/engine/install/ubuntu/), [Debian](https://docs.docker.com/engine/install/debian/), [Mac](https://docs.docker.com/docker-for-mac/install/)]
- [Docker Compose](https://docs.docker.com/compose/install/)
- PHP CLI

## Installing

Megh is tested on Mac and debian based systems, like Ubuntu.

### Linux

```bash
wget -O - https://raw.githubusercontent.com/megh-sh/megh/master/install.sh | bash
```


### Mac

Download and install Docker Desktop for Mac [from here](https://docs.docker.com/docker-for-mac/install/) or install via brew:

```bash
brew cask install docker
```

## Commands

Available commands:

```bash
  check    Check docker and proxy status
  create   Create a new site
  delete   Delete a site
  disable  Disable a site
  enable   Enable a site
  help     Displays help for a command
  install  Install and Configure Megh
  list     Lists commands
  start    Start Megh services
  stop     Stop Megh services
```

### Initialization

After installing the CLI, a `install` command needs to be run to install the docker configuration.

```bash
# install and configure megh
megh install

# start the services
megh start
```

### Site Commands

Creating, deleting and managing sites

#### Create a new site

Creating a site will automatically create a database and user for that site. For Laravel and WordPress sites, the credentials will be automatically replaced in the config or env file.


```bash
megh create example.com [--type=] [--php=] [--username=] [--email=] [--pass=] [--add-host]
```

_Options:_
`--type`: Type of the site. `php`, `wp`, `laravel`. Defaults to `php`.

`--php`: The PHP version. `7.2`, `7.3`, `7.4`. Defaults to `7.4`.

`--username`: The WordPress admin username if `--type=wp`. Default is `megh`

`--email`: The WordPress admin email if `--type=wp`. Default is `megh@example.com`

`--pass`: The WordPress admin password if `--type=wp`. A default password will be generated in nothing provided.

`--add-host`: Wheather to add the domain to the hosts (`/etc/hosts`) file. Default is `false`


```bash
# WordPress
megh create example.com --type=wp

# WordPress with credentials
megh create example.com --type=wp --username=john --email=john@doe.com --pass=johndoe

# Laravel
megh create example.com --type=laravel
```


#### Delete a site

The site will be deleted along with database and files.

```bash
megh delete example.com
```

#### Disable a site

It temporary disables a site. Docker compose down and removes the site from the network.

```bash
megh disable example.com
```

#### Enable a site

Enables a disabled site. Docker compose up and connects back to the network.

```bash
megh enable example.com
```
