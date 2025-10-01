FROM php:8.2-fpm

# Accept proxy values as build args so image can be built behind a corporate proxy
ARG HTTP_PROXY
ARG HTTPS_PROXY
ARG NO_PROXY
ENV HTTP_PROXY=${HTTP_PROXY}
ENV HTTPS_PROXY=${HTTPS_PROXY}
ENV NO_PROXY=${NO_PROXY}
# Also export lowercase variables which some tools check
ENV http_proxy=${HTTP_PROXY}
ENV https_proxy=${HTTPS_PROXY}
ENV no_proxy=${NO_PROXY}

# Install system dependencies. If HTTP_PROXY/HTTPS_PROXY are provided, configure apt to use them
RUN set -eux; \
    if [ -n "${HTTP_PROXY}" ] || [ -n "${HTTPS_PROXY}" ]; then \
        echo "Configuring APT to use proxy"; \
        if [ -n "${HTTP_PROXY}" ]; then echo "Acquire::http::Proxy \"${HTTP_PROXY}\";" > /etc/apt/apt.conf.d/01proxy; fi; \
        if [ -n "${HTTPS_PROXY}" ]; then echo "Acquire::https::Proxy \"${HTTPS_PROXY}\";" >> /etc/apt/apt.conf.d/01proxy; fi; \
    fi; \
    apt-get update && apt-get install -y \
        git \
        curl \
        libpng-dev \
        libonig-dev \
        libxml2-dev \
        zip \
        unzip \
        libzip-dev \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libmcrypt-dev \
        libicu-dev \
        libpq-dev \
        libxpm-dev \
        libvpx-dev

# Configure git and curl to use proxy (if provided) and clear apt cache
RUN set -eux; \
    if [ -n "${HTTP_PROXY}" ]; then \
        git config --global http.proxy "${HTTP_PROXY}" || true; \
    fi; \
    if [ -n "${HTTPS_PROXY}" ]; then \
        git config --global https.proxy "${HTTPS_PROXY}" || true; \
    fi; \
    # curl will pick up HTTP_PROXY/HTTPS_PROXY from environment
    apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip intl

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# If a proxy is provided, configure Composer global proxy so composer operations during build/use honor it
RUN set -eux; \
    if [ -n "${HTTP_PROXY}" ]; then \
        /usr/bin/composer config -g -- http-proxy "${HTTP_PROXY}" || true; \
    fi; \
    if [ -n "${HTTPS_PROXY}" ]; then \
        /usr/bin/composer config -g -- secure-http false || true; \
    fi

# Set working directory
WORKDIR /var/www/html

# Create user for PHP processes
RUN groupadd -g 1000 www
RUN useradd -u 1000 -ms /bin/bash -g www www

# Copy existing application directory contents
COPY . /var/www/html

# Copy existing application directory permissions
COPY --chown=www:www . /var/www/html

# Copy PHP-FPM pool configuration
COPY www.conf /usr/local/etc/php-fpm.d/www.conf

# Change current user to www
USER www

# Expose port 9000 and start php-fpm server
EXPOSE 9000
CMD ["php-fpm"]