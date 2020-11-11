# Megh

A Docker based PHP server management client.

## Commands

```bash
megh check
```

```bash
megh proxy-start
```

```bash
megh install
```

### Site Commands

Create and delete sites

**Create:** Create a new site

```bash
megh create site.com [--type=] [--php=] [--root=]
```

_Options:_
`--type`: Type of the site. `php`, `wp`, `bedrock`, `laravel`, `static`. Defaults to `php`.

`--php`: The PHP version. `7.2`, `7.3`, `7.4` or `latest`. Defaults to `latest`.

`--root`: The web root for nginx. Default is `/`.

**Delete:** Delete a site

```bash
megh delete site.com
```

**Disable:** Temporary disable a site.

Docker compose down and removes the site from the network.

```bash
megh disable site.com
```

**Enable:** Enables a disabled site.

Docker compose up and connects back to the network.

```bash
megh enable site.com
```
