# Development base image for the Proveedores La Sociedad codebase.
#
# Matches the production stack documented in AUDIT_PROVEEDORES_LA_SOCIEDAD.md:
#   - PHP 8.2 (production runs ea-php82)
#   - PDO / MySQL (production uses MySQL; MariaDB is drop-in for local dev)
#   - PhpSpreadsheet + PHPMailer friendly extensions (mbstring, xml, gd, zip, curl, intl, bcmath)
#
# The application itself (SystemSuplidor/) is gitignored and not shipped here,
# so this image only provisions the language toolchain, Composer, and a local
# MySQL-compatible database used by the validation logic in patches/rnc/.

FROM ubuntu:24.04

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=America/Santo_Domingo

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        software-properties-common ca-certificates apt-transport-https lsb-release gnupg sudo \
    && add-apt-repository -y ppa:ondrej/php \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        php8.2-cli \
        php8.2-common \
        php8.2-mysql \
        php8.2-mbstring \
        php8.2-xml \
        php8.2-gd \
        php8.2-zip \
        php8.2-curl \
        php8.2-intl \
        php8.2-bcmath \
        composer \
        mariadb-server \
        mariadb-client \
        git \
    && rm -rf /var/lib/apt/lists/*

# Allow the agent user to manage the local MariaDB service without a password.
RUN echo "ubuntu ALL=(ALL) NOPASSWD:ALL" > /etc/sudoers.d/90-ubuntu-nopasswd \
    && chmod 0440 /etc/sudoers.d/90-ubuntu-nopasswd

USER ubuntu
