#!/usr/bin/env bash

if [[ $EUID -ne 0 ]]; then
    echo "This script must be run as root."

    exit 1
fi

function bootstrap() {
    apt-get update
    apt-get install -y --force-yes software-properties-common
}

function install_docker() {
    if ! command -v docker >/dev/null 2>&1; then
        wget --quiet get.docker.com -O docker-setup.sh
        sh docker-setup.sh
        rm docker-setup.sh
    fi

    if ! command -v docker-compose >/dev/null 2>&1; then
        curl -L "https://github.com/docker/compose/releases/download/1.27.4/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
        chmod +x /usr/local/bin/docker-compose
    fi
}

function install_php() {
    if ! command -v php >/dev/null 2>&1; then
        add-apt-repository -y ppa:ondrej/php
        apt-get update
        apt-get -y install php7.3-cli php7.3-mbstring unzip
    fi
}

function install_composer() {
    curl -sSL "https://getcomposer.org/installer" | php && mv composer.phar /usr/local/bin/composer
}

function install_megh() {
    composer global require megh/megh-cli
}

# Run the commands
bootstrap
install_docker
install_php
install_composer
install_megh
