# Symfony WebServer

This is a CLI for running Symfony apps on your dev server. The majority of the code comes from
the [Symfony/WebServerBundle](https://github.com/symfony/web-server-bundle), which has been deprecated in favor
of the Symfony command.

## Setup

Create the SQLite database file

```
./bin/console doctrine:database:create
./bin/console doctrine:schema:update --force
```

## Usage

To run a server in the background:

```
./bin/console server:start /Users/martins/Projects/sites/project -p 4220 -t project
```

Note that you need the full path. On mac, you can get that with `${PWD}/../../sites/project` from wherever you have this
script stored.