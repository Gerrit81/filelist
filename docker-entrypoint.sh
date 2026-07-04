#!/bin/bash
set -e

APP_DATA_DIR="/var/www/html/appdata"
mkdir -p "$APP_DATA_DIR"

# ── 确保 appdata 卷中文件存在，防止 Docker 把文件挂载点建成目录 ──
for f in config.json app.db; do
    # 如果 root 下有同名目录（Docker 误建），删掉
    if [ -d "/var/www/html/$f" ]; then
        rm -rf "/var/www/html/$f"
    fi
    # 如果 appdata 卷中还不存在，创建空文件
    if [ ! -f "$APP_DATA_DIR/$f" ]; then
        touch "$APP_DATA_DIR/$f"
    fi
    # 创建软链接（如果还不是链接也不是文件）
    if [ ! -L "/var/www/html/$f" ] && [ ! -f "/var/www/html/$f" ]; then
        ln -s "$APP_DATA_DIR/$f" "/var/www/html/$f"
    fi
done
# config.json 需要初始内容，否则 PHP 解析失败
if [ ! -s "$APP_DATA_DIR/config.json" ]; then
    echo '{}' > "$APP_DATA_DIR/config.json"
fi

# ── 运行时目录权限 ──
for dir in \
    /var/www/html/data \
    /var/www/html/thumbs \
    /var/www/html/session \
    /var/www/html/cache \
    "$APP_DATA_DIR"; do
    mkdir -p "$dir"
    chown -R www-data:www-data "$dir" 2>/dev/null || true
done

exec "$@"
