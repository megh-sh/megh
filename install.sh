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

function pull_images() {
    docker pull jwilder/nginx-proxy:alpine
    docker pull mariadb:10.3
    docker pull nginx:alpine
    docker pull meghsh/php:7.4
}

function install_php() {
    if ! command -v php >/dev/null 2>&1; then
        add-apt-repository -y ppa:ondrej/php
        apt-get update
        apt-get -y install php7.3-cli php7.3-mbstring unzip
    fi
}

function install_composer() {
    if [ ! -f /usr/local/bin/composer ]; then
        curl -sSL "https://getcomposer.org/installer" | php
        mv composer.phar /usr/local/bin/composer
    fi
}

function setup_firewall() {
    ufw allow 22
    ufw allow 80
    ufw allow 443
    ufw --force enable
}

function create_user() {
    # Add sudo user and grant privileges
    useradd --create-home --shell "/bin/bash" --groups sudo megh

    # Setup bash profiles
    cp /root/.profile /home/megh/.profile
    cp /root/.bashrc /home/megh/.bashrc

    # Create SSH key
    mkdir -p /home/megh/.ssh
    cp /root/.ssh/authorized_keys /home/megh/.ssh/authorized_keys

    ssh-keygen -f /home/megh/.ssh/id_rsa -t rsa -N ''

    # Fix Directory Permissions
    chown -R megh:megh /home/megh
    chmod -R 755 /home/megh
    chmod 700 /home/megh/.ssh/id_rsa

    # Add docker permissions
    usermod -aG docker megh
    chmod 666 /var/run/docker.sock
    systemctl restart docker

    # Login as megh
    su megh
}

function install_megh() {
    composer global require megh/megh-cli

    echo 'export PATH="$PATH:$HOME/.config/composer/vendor/bin"' >>~/.bashrc
    source ~/.bashrc

    megh install
}

# Run the commands
bootstrap
install_docker
pull_images
install_php
install_composer
create_user
install_megh
