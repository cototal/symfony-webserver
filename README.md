# Symfony WebServer

This is a CLI for running Symfony apps on your dev server. The majority of the code comes from
the [Symfony/WebServerBundle](https://github.com/symfony/web-server-bundle), which has been deprecated in favor
of the [Symfony CLI](https://symfony.com/download).

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

You can add these scripts to your bash profile for convenience:

```
export SCRIPT_DIR=$HOME/scripts
function symstart() {
    if [[ $# -eq 1 && $1 = '-h' ]]; then
        echo ""
        echo "Use from the project directory of your Symfony application"
        echo "  symstart [port] [name]"
        echo "If your app is already registered (you can check with symlist):"
        echo "  symstart [name]"
        echo ""
        return 0
    fi

    proj_pwd=$PWD
    cd $SCRIPT_DIR/symfony-webserver

    if [[ $# -eq 0 ]]; then
        ./bin/console server:start $proj_pwd
    elif [[ $# -eq 1 ]]; then
        ./bin/console server:start $1
    elif [[ $# -eq 2 ]]; then
        ./bin/console server:start $proj_pwd -p $1 -t $2
    fi

    cd -
}

function symstop() {
    if [[ $# -eq 1 && $1 = '-h' ]]; then
        echo ""
        echo "Use from the project directory of your Symfony application or specify a project name"
        echo "  symstop"
        echo "  symstop [name]"
        echo ""
        return 0
    fi

    proj_pwd=$PWD
    cd $SCRIPT_DIR/symfony-webserver

    if [[ $# -eq 0 ]]; then
        ./bin/console server:stop $proj_pwd
    elif [[ $# -eq 1 ]]; then
        ./bin/console server:stop $1
    fi

    cd -
}

function symlist() {
    cd $SCRIPT_DIR/symfony-webserver
    ./bin/console project:list
    cd -
}
```