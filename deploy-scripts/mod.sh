#!/usr/bin/env bash
set -euo pipefail

BASE_DIR="$(cd "$(dirname "$0")" && pwd)"
SITES_DIR="$BASE_DIR/sites"
SERVICE="sass"
WP_CONTAINER="wordpress"
PLUGIN_SLUG="u-theme-styles"

usage() {
    echo "Использование: $0 {prod|dev} [сайт1 сайт2 ...]"
    echo "  prod — остановить $SERVICE + деактивировать плагин $PLUGIN_SLUG"
    echo "  dev  — запустить $SERVICE + активировать плагин $PLUGIN_SLUG"
    echo ""
    echo "  Если сайты не указаны — применяется ко всем в $SITES_DIR"
    echo "  Примеры:"
    echo "    $0 dev                        — все сайты в режим dev"
    echo "    $0 prod domen.com domen2.com  — только указанные сайты в prod"
    exit 1
}

# Обновить .htaccess: переключить режим доступа к wp-admin/wp-login
update_htaccess() {
    local htaccess="$1"
    local mode="$2"

    if [[ ! -f "$htaccess" ]]; then
        echo "[ПРОПУСК]  .htaccess не найден: $htaccess"
        return
    fi

    # Извлекаем IP из строки «Require ip» (может быть закомментирована)
    local ip
    ip=$(awk '/Require ip[[:space:]]/{
        sub(/.*Require ip[[:space:]]+/, "")
        gsub(/[[:space:]]+$/, "")
        print; exit
    }' "$htaccess")

    if [[ -z "$ip" ]]; then
        echo "[ОШИБКА]   $htaccess — строка 'Require ip' не найдена, пропуск"
        return
    fi

    # Полностью перезаписываем блок <RequireAny>:
    # удаляем все его содержимое и вставляем только две нужные строки.
    # Лишние инструкции внутри блока таким образом удаляются.
    local tmp
    tmp=$(mktemp)

    awk -v mode="$mode" -v ip="$ip" '
        /[[:space:]]*<RequireAny>/ {
            # Запоминаем отступ тега и добавляем ещё 4 пробела для содержимого
            match($0, /^[[:space:]]*/); indent = substr($0, 1, RLENGTH) "    "
            in_block = 1
            print
            if (mode == "dev") {
                print indent "Require all granted"
                print indent "# Require ip " ip
            } else {
                print indent "# Require all granted"
                print indent "Require ip " ip
            }
            next
        }
        in_block && /[[:space:]]*<\/RequireAny>/ {
            in_block = 0
            print
            next
        }
        in_block { next }   # пропускаем все строки внутри блока
        { print }
    ' "$htaccess" > "$tmp" && cat "$tmp" > "$htaccess" && rm -f "$tmp"

    echo "[HTACCESS] $htaccess → $mode (Require ip $ip)"
}

# Выполнить wp-cli команду внутри контейнера wordpress
wp_cmd() {
    local cfile="$1"; shift
    docker compose -f "$cfile" exec -T "$WP_CONTAINER" \
        bash -c '
            if [ ! -f /usr/local/bin/wp ]; then
                curl -sSf -o /usr/local/bin/wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
                chmod +x /usr/local/bin/wp
            fi
            wp '"$*"' --allow-root
        ' 2>&1
}

[[ $# -lt 1 ]] && usage

MODE="$1"; shift
[[ "$MODE" != "prod" && "$MODE" != "dev" ]] && usage

# Собираем список сайтов
if [[ $# -gt 0 ]]; then
    # Указаны конкретные сайты
    SITES=("$@")
else
    # Все папки в sites/
    SITES=()
    for site_dir in "$SITES_DIR"/*/; do
        [[ -d "$site_dir" ]] && SITES+=("$(basename "$site_dir")")
    done
fi

for site_name in "${SITES[@]}"; do
    site_dir="$SITES_DIR/$site_name"
    compose_file="$site_dir/docker-compose.yml"

    # Проверяем что папка существует
    if [[ ! -d "$site_dir" ]]; then
        echo "[ОШИБКА]   $site_name — папка не найдена в $SITES_DIR"
        continue
    fi

    # Проверяем что есть docker-compose.yml
    if [[ ! -f "$compose_file" ]]; then
        echo "[ПРОПУСК]  $site_name — нет docker-compose.yml"
        continue
    fi

    # Проверяем что сервис sass описан в compose
    if ! grep -q "$SERVICE" "$compose_file" 2>/dev/null; then
        echo "[ПРОПУСК]  $site_name — нет сервиса $SERVICE"
        continue
    fi

    htaccess_file="$site_dir/core/.htaccess"

    case "$MODE" in
        prod)
            echo "[СТОП]     $site_name → $SERVICE"
            docker compose -f "$compose_file" stop "$SERVICE" 2>&1 || \
                echo "[ОШИБКА]   $site_name — не удалось остановить $SERVICE"

            echo "[ПЛАГИН]   $site_name → деактивация $PLUGIN_SLUG"
            wp_cmd "$compose_file" "plugin deactivate $PLUGIN_SLUG" || \
                echo "[ОШИБКА]   $site_name — не удалось деактивировать плагин"

            update_htaccess "$htaccess_file" "prod"
            ;;
        dev)
            echo "[СТАРТ]    $site_name → $SERVICE"
            docker compose -f "$compose_file" up -d "$SERVICE" 2>&1 || \
                echo "[ОШИБКА]   $site_name — не удалось запустить $SERVICE"

            echo "[ПЛАГИН]   $site_name → активация $PLUGIN_SLUG"
            wp_cmd "$compose_file" "plugin activate $PLUGIN_SLUG" || \
                echo "[ОШИБКА]   $site_name — не удалось активировать плагин"

            update_htaccess "$htaccess_file" "dev"
            ;;
    esac
done

echo ""
echo "Готово: режим $MODE применён"