# Important
The original image was changed to run php code by cron. So following changes were added to the original Dokerfile:
* Base image was changed to `php`
* Added php file
* Changed start command

Below updated the original readme.

# cron-docker-image

Docker image to run cron inside the Docker container

## Adding cron jobs

Suppose you have folder `cron.d` with your cron scripts. The only thing you have to do is copying this folder into the Docker image:

```Dockerfile
# Dockerfile

FROM renskiy/cron

COPY cron.d /etc/cron.d
```

Then build and create container:

```bash
docker build --pull --rm --tag dmitriy/mercury-metrics .
docker run -d --rm --name cron dmitriy/mercury-metrics
```

## Logs

To view logs use Docker [logs](https://docs.docker.com/engine/reference/commandline/logs/) command:

```bash
docker logs --follow cron
```

*All you cron scripts should write logs to `/var/log/cron.log`. Otherwise you won't be able to view any log using this way.*

## Passing cron jobs by arguments

In addition to `cron.d` you can pass any cron job as argument(s) to the `start-cron` command at the moment of container creation (providing optional user with `-u`/`--user` option):

```bash
docker run -d --rm --name cron dmitriy/mercury-metrics --user www-data \
    "0 1 \* \* \* echo '01:00 AM' >> /var/log/cron.log 2>&1" \
    "0 0 1 1 \* echo 'Happy New Year!!' >> /var/log/cron.log 2>&1"
```

## Environ variables

Almost any environ variable you passed to the Docker will be visible to your cron scripts. With the exception of `$SHELL`, `$PATH`, `$PWD`, `$USER`, etc.

```bash
docker run -it --rm --interactive -e MY_VAR=foo dmitriy/mercury-metrics start-cron \
    "\* \* \* \* \* env >> /var/log/cron.log 2>&1"
```

## Special Environ variables

### `CRON_PATH`

This Environ variable let you provide custom directory for your `cron.d` scripts which will be placed instead of default `/etc/cron.d`:

```bash
docker run -d --name cron -e CRON_PATH=/etc/my_app/cron.d dmitriy/mercury-metrics
```

This is very useful when you planning to create more then one Docker container from a single image.
