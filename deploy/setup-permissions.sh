#!/usr/bin/env bash
#
# Права на каталог приложения. Идемпотентный: повторный запуск ничего не ломает.
# Запускается от root из любого каталога.
#
#   sudo ./deploy/setup-permissions.sh [/srv/watchtower]
#
# Зачем скриптом, а не строчкой в runbook: ACL не хранятся в git и не видны
# в конфигурации. Пересоздание машины (Story 2.4) потеряло бы их молча —
# сайт бы поднялся, а статика отдавалась бы с 403.

set -euo pipefail

APP_DIR="${1:-/srv/watchtower}"
APP_USER="watchtower"     # от него работают рабочие процессы php-fpm
WEB_USER="nginx"          # от него работают воркеры nginx

die() { echo "ОШИБКА: $*" >&2; exit 1; }

[[ $EUID -eq 0 ]] || die "нужен root"
[[ -d "$APP_DIR" ]] || die "нет каталога $APP_DIR"
id "$APP_USER" >/dev/null 2>&1 || die "нет пользователя $APP_USER"
id "$WEB_USER" >/dev/null 2>&1 || die "нет пользователя $WEB_USER"
command -v setfacl >/dev/null || die "нет setfacl — поставь пакет acl"

echo "== Владелец и режимы =="

# Код принадлежит root, группа приложения его читает. Захват PHP не даёт
# переписать приложение.
chown -R root:"$APP_USER" "$APP_DIR"
find "$APP_DIR" -type d -exec chmod 750 {} +
find "$APP_DIR" -type f -exec chmod 640 {} +

# Единственные каталоги с записью: логи, кэш, сессии, скомпилированные шаблоны.
chown -R "$APP_USER":"$APP_USER" "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
find "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" -type d -exec chmod 750 {} +

# Исполняемый бит нужен только artisan.
chmod 750 "$APP_DIR/artisan"

echo "== ACL для nginx =="

# Тройка владелец/группа/прочие эту задачу не выражает: право нужно ОДНОМУ
# пользователю и только на два каталога. Добавить nginx в группу watchtower
# было бы одной командой, но дало бы ему чтение всего кода и .env.
#
# --x на корне проекта: пройти сквозь каталог к известному имени можно,
# получить список содержимого — нельзя. .env лежит здесь и остаётся закрыт.
setfacl -m u:"$WEB_USER":--x "$APP_DIR"

# rX на public: заглавная X ставит бит x только каталогам, файлы получают
# чистое чтение.
setfacl -R  -m u:"$WEB_USER":rX "$APP_DIR/public"

# Тот же доступ для файлов, которых ещё нет: git pull, сборка ассетов.
setfacl -R -d -m u:"$WEB_USER":rX "$APP_DIR/public"

echo "== Проверка =="

fail=0
check() { # описание, ожидание (ok|deny), команда...
  local desc="$1" expect="$2"; shift 2
  if "$@" >/dev/null 2>&1; then result=ok; else result=deny; fi
  if [[ "$result" == "$expect" ]]; then
    echo "  OK   $desc"
  else
    echo "  FAIL $desc (ожидалось $expect, получено $result)"; fail=1
  fi
}

check "nginx проходит в корень проекта"      ok   sudo -u "$WEB_USER" test -x "$APP_DIR"
check "nginx читает public/"                 ok   sudo -u "$WEB_USER" test -r "$APP_DIR/public/index.php"
check "nginx НЕ читает .env"                 deny sudo -u "$WEB_USER" cat "$APP_DIR/.env"
check "приложение пишет в storage/"          ok   sudo -u "$APP_USER" test -w "$APP_DIR/storage"
check "приложение пишет в bootstrap/cache"   ok   sudo -u "$APP_USER" test -w "$APP_DIR/bootstrap/cache"
check "приложение НЕ переписывает код"       deny sudo -u "$APP_USER" test -w "$APP_DIR/public/index.php"
check "приложение читает .env"               ok   sudo -u "$APP_USER" test -r "$APP_DIR/.env"

[[ $fail -eq 0 ]] || die "проверки не прошли"
echo "Готово."
