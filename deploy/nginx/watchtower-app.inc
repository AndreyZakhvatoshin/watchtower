# Тело приложения, общее для публичного хоста и внутреннего хоста управления.
#
# ПОЧЕМУ ОТДЕЛЬНЫЙ ФАЙЛ И ПОЧЕМУ НЕ В conf.d
# nginx.conf подключает conf.d/*.conf на уровне http. Директива location
# на уровне http недопустима — файл с телом приложения, положенный в conf.d,
# роняет nginx -t. Отсюда расширение .inc и размещение в /etc/nginx:
# под шаблон *.conf он не попадает и подключается только явно.
#
# Устанавливается в /etc/nginx/watchtower-app.inc

    root /srv/watchtower/public;
    index index.php;
    charset utf-8;
    client_max_body_size 2m;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Единственная точка входа в PHP. Знак = означает точное совпадение:
    # интерпретатору доступен ровно один файл, и поиск location на нём
    # прекращается сразу, не доходя до регулярных выражений.
    #
    # Опасен не суффикс, а передача PATH_INFO. При схеме
    # `location ~ \.php` (без $) или при cgi.fix_pathinfo=1 запрос
    # /uploads/avatar.jpg/x.php приводит к тому, что SCRIPT_FILENAME
    # разрешается в существующий файл-префикс, и загруженная картинка
    # исполняется как код. Точное совпадение закрывает оба пути.
    location = /index.php {
        fastcgi_pass unix:/run/php/watchtower.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $document_root;

        # PATH_INFO не передаётся, cgi.fix_pathinfo не участвует —
        # классическая дыра «исполнение произвольного файла как PHP» закрыта
        # тем, что интерпретатору доступен ровно один скрипт.
        fastcgi_hide_header X-Powered-By;

        # Страховка второго уровня — на стороне php-fpm:
        # security.limit_extensions = .php в пуле. Она не полагается на то,
        # что nginx настроен верно.

        fastcgi_read_timeout 30s;
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
    }
    # Любой другой .php в public/ — 404, а не отдача исходника.
    location ~ \.php$ {
        return 404;
    }

    # Точечные файлы (.env, .git/, .htaccess) не отдаются.
    # Исключение .well-known оставлено: там живут security.txt и проверки,
    # не связанные с ACME.
    location ~ /\.(?!well-known).* {
        deny all;
    }
