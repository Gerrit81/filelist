FROM php:8.2-apache

# 安装系统级依赖（GD 图像库编译所需）
RUN apt-get update && apt-get install -y \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    && rm -rf /var/lib/apt/lists/*

# 编译安装 GD（含 FreeType/JPEG 支持，用于生成缩略图）
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd

# Apache 基础配置
RUN a2enmod rewrite \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

# PHP 自定义配置
RUN { \
        echo 'upload_max_filesize = 1024M'; \
        echo 'post_max_size = 1024M'; \
        echo 'memory_limit = 256M'; \
        echo 'date.timezone = Asia/Shanghai'; \
    } > /usr/local/etc/php/conf.d/custom.ini

# 复制项目代码
COPY . /var/www/html/

# 预建运行所需目录
RUN mkdir -p /var/www/html/data /var/www/html/thumbs /var/www/html/session /var/www/html/cache /var/www/html/admin/backups

# 入口脚本
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
