#!/usr/bin/env bash

USERNAME=megh

if [[ $EUID -ne 0 ]]; then
    echo "This script must be run as root."

    exit 1
fi

function bootstrap() {
    apt-get update
    apt-get install -y --force-yes software-properties-common
}

function create_swap() {
    if [ -f /swapfile ]; then
        echo "Swap exists."
    else
        fallocate -l 1G /swapfile
        chmod 600 /swapfile
        mkswap /swapfile
        swapon /swapfile
        echo "/swapfile none swap sw 0 0" >>/etc/fstab
        echo "vm.swappiness=30" >>/etc/sysctl.conf
        echo "vm.vfs_cache_pressure=50" >>/etc/sysctl.conf
    fi
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
    useradd --create-home --shell "/bin/bash" --groups sudo "${USERNAME}"

    # Check whether the root account has a real password set
    encrypted_root_pw="$(grep root /etc/shadow | cut --delimiter=: --fields=2)"

    if [ "${encrypted_root_pw}" != "*" ]; then
        # Transfer auto-generated root password to user if present
        # and lock the root account to password-based access
        echo "${USERNAME}:${encrypted_root_pw}" | chpasswd --encrypted
        passwd --lock root
    else
        # Delete invalid password for user if using keys so that a new password
        # can be set without providing a previous value
        passwd --delete "${USERNAME}"
    fi

    # Setup bash profiles
    cp /root/.profile /home/"${USERNAME}"/.profile
    cp /root/.bashrc /home/"${USERNAME}"/.bashrc

    # Create SSH key
    mkdir -p /home/"${USERNAME}"/.ssh
    cp /root/.ssh/authorized_keys /home/"${USERNAME}"/.ssh/authorized_keys

    ssh-keygen -f /home/"${USERNAME}"/.ssh/id_rsa -t rsa -N ''

    # Fix Directory Permissions
    chown -R "${USERNAME}":"${USERNAME}" /home/"${USERNAME}"
    chmod -R 755 /home/"${USERNAME}"
    chmod 700 /home/"${USERNAME}"/.ssh/id_rsa

    # Add docker permissions
    usermod -aG docker "${USERNAME}"
    chmod 666 /var/run/docker.sock
    systemctl restart docker

    # Login as user
    su "${USERNAME}"
}

function install_megh() {
    composer global require megh/megh-cli

    echo 'export PATH="$PATH:$HOME/.config/composer/vendor/bin"' >>~/.bashrc
    source ~/.bashrc

    megh install
    megh start
}

function install_megh_git() {
    sudo git clone https://github.com/megh-sh/megh.git /opt/megh

    sudo ln -s /opt/megh/megh /usr/local/bin/megh

    megh install
    megh start
}

# Run the commands
bootstrap
create_swap
install_docker
pull_images
install_php
install_composer
create_user
install_megh_git
