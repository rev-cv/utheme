# алиас с разрешением запускать wp от root
alias wp='wp --allow-root'

# Принимаем переменные из окружения (передаются через setup.py -> docker compose exec -e)
# Используем синтаксис ${VAR:-default} для установки значений по умолчанию
SITE_URL="${SITE_URL:-http://localhost:8080}"
THEME_SLUG="${THEME_SLUG:-utheme}"
ADMIN_USER="${ADMIN_USER:-admin}"
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@example.com}"
SITE_LANG="${SITE_LANG:-EN}"
SITE_TITLE="${SITE_TITLE:-WordPress Site}"

# =======================================================

# случайный пароль (требует openssl)
# ADMIN_PASS=$(openssl rand -base64 32 | tr -dc 'a-zA-Z0-9!@#$%^&*()_+=-' | head -c 30)
ADMIN_PASS=$(tr -dc 'a-zA-Z0-9!@#$%^&*()_+=-' < /dev/urandom | head -c 30)

# проверка нахождения в папке WP (наличие wp-config.php)
if [ ! -f wp-config.php ]; then
    echo "Ошибка: Запускайте скрипт из корневой папки WordPress!"
    exit 1
fi

echo "--- Автоматическая настройка WordPress ---"

# ===========================================================================
# 1. УСТАНОВКА WORDPRESS

wp core install \
    --url="$SITE_URL" \
    --title="Initial Setup" \
    --admin_user="$ADMIN_USER" \
    --admin_password="$ADMIN_PASS" \
    --admin_email="$ADMIN_EMAIL" \
    --skip-email

# Обновление ядра WordPress до последней версии
# echo "Checking for WordPress updates..."
# wp core update
# wp core update-db

# выбор языка сайта
declare -A LANG_MAP=(
    [EN]="en_US"
    [FR]="fr_FR"
    [DE]="de_DE"
    [PL]="pl_PL"
    [CZ]="cs_CZ"
    [CS]="cs_CZ"
    [PT]="pt_PT"
    [IT]="it_IT"
    [NL]="nl_NL"
    [ES]="es_ES"
    [SK]="sk_SK"
    [ET]="et_EE"
    [LV]="lv_LV"
    [RO]="ro_RO"
    [SV]="sv_SE"
    [LT]="lt_LT"
    [BG]="bg_BG"
    [SL]="sl_SI"
    [HU]="hu_HU"
    [FI]="fi_FI"
    [DA]="da_DK"
    [RU]="ru_RU"
    [GR]="el"
)

WP_LANG_SLUG="${LANG_MAP[$SITE_LANG]}"

if [ -z "$WP_LANG_SLUG" ]; then
    echo "Предупреждение: Неизвестный код языка ($SITE_LANG). Используем en_US."
    WP_LANG_SLUG="en_US"
fi

echo "Setting language to $WP_LANG_SLUG ($SITE_LANG)..."
wp language core install $WP_LANG_SLUG --activate
echo "Success: Installed 1 of 1 languages."
echo "Success: Language activated."

# Генерация случайного имени автора в зависимости от языка
declare -a AUTHOR_NAMES
case "$SITE_LANG" in
    EN) AUTHOR_NAMES=("Oliver Bennett" "Lucas Hedges" "Mason Wright" "Ethan Brooks" "Liam Carter" "Noah Richardson" "Charlotte Hayes" "Amelia Foster" "Harper Vance" "Sophia Thorne") ;;
    FR) AUTHOR_NAMES=("Benoît Lefebvre" "Mathieu Morel" "Guillaume Mercier" "Julien Girard" "Romain Lambert" "Nicolas Faure" "Aurélie Gauthier" "Léa Roussel" "Manon Fontaine" "Camille Perrin") ;;
    DE) AUTHOR_NAMES=("Lukas Baumann" "Maximilian Krauß" "Felix Ziegler" "Julian Vogel" "Jonas Winkler" "Tobias Busch" "Leonie Koch" "Hannah Franke" "Lina Beck" "Laura Seidel") ;;
    PL) AUTHOR_NAMES=("Mateusz Mazur" "Jakub Kaczmarek" "Kacper Grabowski" "Michał Zając" "Szymon Król" "Bartosz Wieczorek" "Aleksandra Jabłońska" "Natalia Majewska" "Wiktoria Adamczyk" "Zuzanna Dudek") ;;
    CZ|CS) AUTHOR_NAMES=("Marek Sedláček" "Lukáš Mach" "Tomáš Marek" "Jakub Kolář" "Filip Čížek" "Adam Hájek" "Tereza Králová" "Lucie Benešová" "Adéla Valentová" "Barbora Blažková") ;;
    PT) AUTHOR_NAMES=("Ricardo Bragança" "Gonçalo Tavares" "Tiago Mendes" "Afonso Viegas" "Diogo Antunes" "Nuno Valente" "Beatriz Figueiredo" "Catarina Simões" "Leonor Mourão" "Margarida Lópes") ;;
    IT) AUTHOR_NAMES=("Lorenzo Fontana" "Matteo Mariani" "Riccardo Barbieri" "Leonardo Moretti" "Gabriele Riva" "Davide Gallo" "Chiara Donati" "Giulia Valentini" "Sofia Messina" "Alice Pellegrini") ;;
    NL) AUTHOR_NAMES=("Daan Hendriks" "Thijs van Leeuwen" "Sem Postma" "Bram Verhoeven" "Luuk de Wit" "Stijn Jacobs" "Lotte Sanders" "Fleur van Vliet" "Emma Meijer" "Lieke Brouwer") ;;
    ES) AUTHOR_NAMES=("Adrián Ibáñez" "Marcos Vidal" "Hugo Ortega" "Alejandro Garrido" "Pablo Iglesias" "Sergio Ramos" "Lucía Beltrán" "Paula Navarro" "Irene Cano" "Alba Serrano") ;;
    SK) AUTHOR_NAMES=("Patrik Polák" "Lukáš Rusnák" "Matej Greguš" "Tomáš Oravec" "Jakub Urban" "Filip Hudák" "Simona Kováčiková" "Dominika Ševčíková" "Veronika Lišková" "Lucia Kubicová") ;;
    ET) AUTHOR_NAMES=("Kristjan Raud" "Markus Ilves" "Sander Mägi" "Rasmus Koppel" "Tanel Pärn" "Kaspar Luik" "Triin Mets" "Kadri Sarap" "Kertu Oja" "Liis Jõgi") ;;
    LV) AUTHOR_NAMES=("Mārtiņš Ziedonis" "Artūrs Krauja" "Gints Strautiņš" "Raitis Ozols" "Kaspars Lācis" "Jānis Krūze" "Kristīne Saulīte" "Aiga Purviņa" "Zane Priede" "Laura Kalve") ;;
    RO) AUTHOR_NAMES=("Andrei Ionescu" "Dragos Munteanu" "Mihai Enache" "Cristian Diaconescu" "Alexandru Moldovan" "Bogdan Stancu" "Raluca Voinea" "Simona Drăghici" "Oana Ardeleanu" "Adina Neagu") ;;
    SV) AUTHOR_NAMES=("Oscar Lindberg" "Viktor Holm" "Emil Nyström" "Anton Bergman" "Filip Sjöberg" "Linus Wallin" "Hanna Lindgren" "Elin Nyberg" "Maja Hellström" "Saga Viklund") ;;
    LT) AUTHOR_NAMES=("Lukas Navickas" "Marius Rimkus" "Andrius Giedraitis" "Mantas Savickas" "Karolis Urbonas" "Tomas Baranauskas" "Eglė Stankutė" "Rūta Mickevičiūtė" "Aistė Kairytė" "Gintarė Jociūtė") ;;
    BG) AUTHOR_NAMES=("Мартин Колев" "Александър Ангелов" "Калоян Стоянов" "Стефан Цветков" "Боян Драганов" "Виктор Маринов" "Йорданка Петрова" "Силвия Димитрова" "Радослава Борисова" "Десислава Костадинова") ;;
    SL) AUTHOR_NAMES=("Luka Hribar" "Nejc Kos" "Žiga Turk" "Rok Pirc" "Matic Vidmar" "Aljaž Zupan" "Nika Kavčič" "Anja Oblak" "Tjaša Korošec" "Maja Bizjak") ;;
    HU) AUTHOR_NAMES=("Bence Balogh" "Ádám Mészáros" "Gergő Simon" "Márk Takács" "Péter Juhász" "Tamás Hegedűs" "Dóra Somogyi" "Zsófia Sipos" "Luca Veres" "Eszter Balla") ;;
    FI) AUTHOR_NAMES=("Eetu Heiskanen" "Lauri Salonen" "Ville Koski" "Aleksi Rantanen" "Mikko Turunen" "Juho Peltonen" "Sanni Karjalainen" "Anniina Saari" "Noora Ahonen" "Iida Jokinen") ;;
    DA) AUTHOR_NAMES=("Magnus Poulsen" "Rasmus Knudsen" "Mathias Møller" "Emil Thomsen" "Christian Iversen" "Jonas Bang" "Sofie Winther" "Freja Dahl" "Ida Nygaard" "Lærke Bruun") ;;
    GR) AUTHOR_NAMES=("Νίκος Παπαδόπουλος" "Γιώργος Οικονόμου" "Δημήτρης Βασιλείου" "Γιάννης Παππάς" "Κώστας Παπαγεωργίου" "Μαρία Παπαδοπούλου" "Ελένη Οικονόμου" "Κατερίνα Βασιλείου" "Άννα Παππά" "Σοφία Παπαγεωργίου") ;;
    RU) AUTHOR_NAMES=("Артем Соловьев" "Даниил Волков" "Игорь Воробьев" "Кирилл Зайцев" "Антон Матвеев" "Роман Степанов" "Валерия Егорова" "Марина Савельева" "Алина Беляева" "Наталья афанасьева") ;;
    *) AUTHOR_NAMES=("Oliver Bennett" "Lucas Hedges" "Mason Wright" "Ethan Brooks" "Liam Carter" "Noah Richardson" "Charlotte Hayes" "Amelia Foster" "Harper Vance" "Sophia Thorne") ;;
esac

if [ ${#AUTHOR_NAMES[@]} -gt 0 ]; then
    RANDOM_INDEX=$((RANDOM % ${#AUTHOR_NAMES[@]}))
    SELECTED_NAME="${AUTHOR_NAMES[$RANDOM_INDEX]}"
    # Разделяем на имя и фамилию
    FIRST_NAME=$(echo "$SELECTED_NAME" | cut -d ' ' -f 1)
    LAST_NAME=$(echo "$SELECTED_NAME" | cut -d ' ' -f 2-)
else
    SELECTED_NAME="Admin User"
    FIRST_NAME="Admin"
    LAST_NAME="User"
fi
echo "Selected Author Name: $SELECTED_NAME"

# создание юзера и сохранение пароля
# проверка, существует ли юзер, если нет - создаем, если да - обновляем пароль
if wp user get $ADMIN_USER > /dev/null 2>&1; then
    echo "User exists, updating password and profile..."
    wp user update $ADMIN_USER --user_pass="$ADMIN_PASS" --role=administrator --display_name="$FIRST_NAME $LAST_NAME" --first_name="$FIRST_NAME" --last_name="$LAST_NAME"
else
    echo "Creating admin user..."
    wp user create $ADMIN_USER $ADMIN_EMAIL --user_pass="$ADMIN_PASS" --role=administrator --display_name="$FIRST_NAME $LAST_NAME" --first_name="$FIRST_NAME" --last_name="$LAST_NAME"
fi

ADMIN_ID=$(wp user get "$ADMIN_USER" --field=ID --allow-root)
echo "Admin ID for content creation: $ADMIN_ID"

# установка языка админа на EN_US
echo "Setting admin user ($ADMIN_USER) language to English (en_US)..."
# ID пользователя, созданного через wp core install, всегда равен 1
wp user update 1 --locale=en_US
echo "Admin language set to English."

# темы (активация кастомной темы, удаление мусора)
echo "Activating theme $THEME_SLUG..."
wp theme activate $THEME_SLUG

echo "Deleting stock themes (keeping active one)..."
# получить список всех тем, кроме активной, и удалить их
wp theme list --status=inactive --field=name | xargs wp theme delete

# удаление дефолтного мусора (Привет мир, Пример страницы)
echo "Cleaning up default content..."
wp post delete $(wp post list --post_type=post,page --format=ids) --force

# ===========================================================================
# 2. НАСТРОЙКА АДМИНКИ

echo "Configuring settings..."
wp option update blogname "$SITE_TITLE"

# Пермалинки (ЧПУ) - выставляем /%postname%/
wp rewrite structure '/%postname%/' --hard
wp rewrite flush
echo "Permalink Settings applied."

echo "Configuring Discussion and Media Settings..."

# DISCUSSION SETTINGS (Настройки обсуждения)

# Дефолтный статус для новых постов: 
# [ ] Attempt to notify any blogs linked (0)
wp option update default_pingback_flag 0

# [ ] Allow link notifications (pingbacks and trackbacks) (closed)
wp option update default_ping_status closed

# [ ] Allow people to submit comments (closed)
wp option update default_comment_status closed

# [x] Comment author must fill out name and email (1)
wp option update require_name_email 1

# [x] Users must be registered and logged in to comment (1)
wp option update comment_registration 1

# [x] Automatically close comments on old posts (1)
# (По умолчанию 14 дней, это значение WP трогать не будем)
wp option update close_comments_for_old_posts 1

# [x] Enable threaded (nested) comments (1)
wp option update thread_comments 1
# глубина вложенности (например, 5 уровней)
wp option update thread_comments_depth 5 

# [x] Break comments into pages (1)
wp option update page_comments 1
# количество комментариев на страницу (например, 50)
wp option update comments_per_page 50 
# порядок отображения (newest/oldest)
wp option update default_comments_page newest 

# Уведомления по Email:
# [ ] Anyone posts a comment (0)
wp option update moderation_notify 0

# [ ] A comment is held for moderation (0)
wp option update comments_notify 0

# Модерация:
# [x] Comment must be manually approved (1)
wp option update comment_moderation 1

# [x] Comment author must have a previously approved comment (1)
wp option update comment_whitelist 1

echo "Discussion settings applied."

# MEDIA SETTINGS (Настройки медиафайлов)

# [ ] Organize my uploads into month- and year-based folders (0)
wp option update uploads_use_yearmonth_folders 0

echo "Media settings applied."

# ===========================================================================
# 3. СОЗДАНИЕ СТРУКТУРЫ СТРАНИЦ

#!/bin/bash

# Объявляем ассоциативные массивы
declare -A T_HOME T_ALL_POSTS T_ABOUT T_COOKIE T_PRIVACY T_LEGAL T_SITEMAP

# --- HOME ---
T_HOME=(
    [EN]="Home"
    [RU]="Главная"
    [FR]="Accueil"
    [DE]="Startseite"
    [PL]="Strona główna"
    [CZ]="Domů"
    [CS]="Domů"
    [PT]="Início"
    [IT]="Home"
    [NL]="Home"
    [ES]="Inicio"
    [SK]="Domov"
    [ET]="Avaleht"
    [LV]="Sākums"
    [RO]="Acasă"
    [SV]="Hem"
    [LT]="Pradžia"
    [BG]="Начало"
    [SL]="Domov"
    [HU]="Főoldal"
    [FI]="Koti"
    [DA]="Forside"
    [GR]="Αρχική"
)

# --- ВСЕ СТАТЬИ ---
T_ALL_POSTS=(
    [EN]="All Posts"
    [RU]="Все статьи"
    [FR]="Tous les articles"
    [DE]="Alle Artikel"
    [PL]="Wszystkie artykuły"
    [CZ]="Všechny články"
    [CS]="Všechny články"
    [PT]="Todos os artigos"
    [IT]="Tutti gli articoli"
    [NL]="Alle artikelen"
    [ES]="Todos los artículos"
    [SK]="Všetky články"
    [ET]="Kõik postitused"
    [LV]="Visi raksti"
    [RO]="Toate articolele"
    [SV]="Alla inlägg"
    [LT]="Visi įrašai"
    [BG]="Всички статии"
    [SL]="Vsi prispevki"
    [HU]="Összes bejegyzés"
    [FI]="Kaikki kirjoitukset"
    [DA]="Alle indlæg"
    [GR]="Όλα τα άρθρα"
)

# --- ABOUT US ---
T_ABOUT=(
    [EN]="About Us"
    [RU]="О нас"
    [FR]="À propos"
    [DE]="Über uns"
    [PL]="O nas"
    [CZ]="O nás"
    [CS]="O nás"
    [PT]="Sobre nós"
    [IT]="Chi Siamo"
    [NL]="Over ons"
    [ES]="Sobre nosotros"
    [SK]="O nás"
    [ET]="Meist"
    [LV]="Par mums"
    [RO]="Despre noi"
    [SV]="Om oss"
    [LT]="Apie mus"
    [BG]="За нас"
    [SL]="O nas"
    [HU]="Rólunk"
    [FI]="Meistä"
    [DA]="Om os"
    [GR]="Σχετικά με εμάς"
)

# --- COOKIE POLICY ---
T_COOKIE=(
    [EN]="Cookie Policy"
    [RU]="Политика Cookie"
    [FR]="Politique cookies"
    [DE]="Cookie-Richtlinie"
    [PL]="Polityka cookies"
    [CZ]="Zásady cookies"
    [CS]="Zásady cookies"
    [PT]="Política de cookies"
    [IT]="Informativa sui Cookie"
    [NL]="Cookiebeleid"
    [ES]="Política de cookies"
    [SK]="Zásady cookies"
    [ET]="Küpsiste eeskirjad"
    [LV]="Sīkdatņu politika"
    [RO]="Politică de cookie"
    [SV]="Cookiepolicy"
    [LT]="Slapukų politika"
    [BG]="Политика за бисквитки"
    [SL]="Pravilnik o piškotkih"
    [HU]="Süti szabályzat"
    [FI]="Evästekäytäntö"
    [DA]="Cookiepolitik"
    [GR]="Πολιτική Cookies"
)

# --- PRIVACY POLICY ---
T_PRIVACY=(
    [EN]="Privacy Policy"
    [RU]="Политика конфиденциальности"
    [FR]="Confidentialité"
    [DE]="Datenschutz"
    [PL]="Polityka prywatności"
    [CZ]="Ochrana soukromí"
    [CS]="Ochrana soukromí"
    [PT]="Privacidade"
    [IT]="Informativa sulla privacy"
    [NL]="Privacybeleid"
    [ES]="Privacidad"
    [SK]="Ochrana súkromia"
    [ET]="Privaatsuspoliitika"
    [LV]="Privātuma politika"
    [RO]="Politică de confidențialitate"
    [SV]="Integritetspolicy"
    [LT]="Privatumo politika"
    [BG]="Политика за поверителност"
    [SL]="Pravilnik o zasebnosti"
    [HU]="Adatvédelmi irányelvek"
    [FI]="Tietosuojaseloste"
    [DA]="Privatlivspolitik"
    [GR]="Πολιτική Απορρήτου"
)

# --- LEGAL NOTICE ---
T_LEGAL=(
    [EN]="Legal Notice"
    [RU]="Юридическая информация"
    [FR]="Mentions légales"
    [DE]="Impressum"
    [PL]="Nota prawna"
    [CZ]="Právní doložka"
    [CS]="Právní doložka"
    [PT]="Aviso legal"
    [IT]="Informazioni Legali"
    [NL]="Juridische informatie"
    [ES]="Aviso legal"
    [SK]="Právne informácie"
    [ET]="Oikeudellinen ilmoitus"
    [LV]="Juridiskā informācija"
    [RO]="Mențiuni legale"
    [SV]="Rättslig information"
    [LT]="Teisinė informacija"
    [BG]="Правна информация"
    [SL]="Pravno obvestilo"
    [HU]="Jogi nyilatkozat"
    [FI]="Oikeudellinen huomautus"
    [DA]="Juridisk meddelelse"
    [GR]="Νομική Σημείωση"
)

# --- SITEMAP ---
T_SITEMAP=(
    [EN]="Sitemap"
    [RU]="Карта сайта"
    [FR]="Plan du site"
    [DE]="Sitemap"
    [PL]="Mapa strony"
    [CZ]="Mapa stránek"
    [CS]="Mapa stránek"
    [PT]="Mapa do site"
    [IT]="Mappa del sito"
    [NL]="Sitemap"
    [ES]="Mapa del sitio"
    [SK]="Mapa stránok"
    [ET]="Sisukaart"
    [LV]="Lapas karte"
    [RO]="Hartă site"
    [SV]="Sajtkarta"
    [LT]="Svetainės medis"
    [BG]="Карта на сайта"
    [SL]="Kazalo strani"
    [HU]="Oldaltérkép"
    [FI]="Sivukartta"
    [DA]="Sitemap"
    [GR]="Χάρτης Ιστότοπου"
)

echo "Creating categories..."
wp term create category "Utility Pages"
wp term create category "page+5"
wp term create category "page+30"

echo "Creating page structure..."

# Главная страница (Шаблон HOME)
HOME_TITLE="${T_HOME[$SITE_LANG]:-Home}"
HOME_ID=$(wp post create --post_type=page --post_title="$HOME_TITLE" --post_name="home" --post_status=publish --page_template="article.php" --post_author="$ADMIN_ID" --porcelain)
# делаем её главной в настройках чтения
wp option update show_on_front "page"
wp option update page_on_front $HOME_ID

# Articles (Шаблон ARTICLE, slug /articles/)
# --post_name задает slug
ARTICLES_TITLE="${T_ALL_POSTS[$SITE_LANG]:-Articles}"
ARTICLES_ID=$(wp post create --post_type=page --post_title="$ARTICLES_TITLE" --post_name="articles" --post_status=publish --page_template="article.php" --post_content="<!-- wp:html --><h3>[txt_kb_bets]</h3><!-- /wp:html --> <!-- wp:shortcode -->[articles_with_pagination]<!-- /wp:shortcode -->" --post_author="$ADMIN_ID" --porcelain)
wp post term set $ARTICLES_ID category "Utility Pages"
PARENT_ID=$ARTICLES_ID # PARENT_ID для дочерних статей
echo "Created 'Articles' page (ID: $PARENT_ID)"

# 5 дочерних articles страниц (Шаблон ARTICLE)
for i in {1..5}; do
   CHILD_ID=$(wp post create --post_type=page --post_title="cl$i" --post_status=publish --post_parent=$PARENT_ID --page_template="article.php" --post_author="$ADMIN_ID" --porcelain)
   wp post term set $CHILD_ID category "page+5"
done

ABOUT_US_ID=""
COOKIE_ID=""
PRIVACY_ID=""
LEGAL_ID=""

# 4 страницы Legal (Шаблон ARTICLE)
LEGAL_PAGES=("About Us" "Cookie Policy" "Privacy Policy" "Legal Notice")

for PAGE_KEY in "${LEGAL_PAGES[@]}"; do
    case "$PAGE_KEY" in
        "About Us")
            PAGE_TITLE="${T_ABOUT[$SITE_LANG]:-About Us}"
            PAGE_SLUG="about-us"
            ;;
        "Cookie Policy")
            PAGE_TITLE="${T_COOKIE[$SITE_LANG]:-Cookie Policy}"
            PAGE_SLUG="cookie-policy"
            ;;
        "Privacy Policy")
            PAGE_TITLE="${T_PRIVACY[$SITE_LANG]:-Privacy Policy}"
            PAGE_SLUG="privacy-policy"
            ;;
        "Legal Notice")
            PAGE_TITLE="${T_LEGAL[$SITE_LANG]:-Legal Notice}"
            PAGE_SLUG="legal-notice"
            ;;
    esac

    PAGE_ID=$(wp post create --post_type=page --post_title="$PAGE_TITLE" --post_name="$PAGE_SLUG" --post_status=publish --page_template="article.php" --post_author="$ADMIN_ID" --porcelain)
    
    # сохраняем ID в нужные глобальные переменные для использования в меню
    case "$PAGE_KEY" in
        "About Us")
            ABOUT_US_ID=$PAGE_ID
            ;;
        "Cookie Policy")
            COOKIE_ID=$PAGE_ID
            ;;
        "Privacy Policy")
            PRIVACY_ID=$PAGE_ID
            ;;
        "Legal Notice")
            LEGAL_ID=$PAGE_ID
            ;;
    esac
    # Назначаем категорию Utility Pages
    wp post term set $PAGE_ID category "Utility Pages"
    echo "Created '$PAGE_TITLE' page (ID: $PAGE_ID)"
done

# Sitemap (Шаблон ARTICLE, шорткод [rank_math_html_sitemap])
SITEMAP_TITLE="${T_SITEMAP[$SITE_LANG]:-Sitemap}"
SITEMAP_ID=$(wp post create --post_type=page --post_title="$SITEMAP_TITLE" --post_name="sitemap" --post_status=publish --page_template="article.php" --post_content="<!-- wp:shortcode -->[custom_html_sitemap]<!-- /wp:shortcode -->" --post_author="$ADMIN_ID" --porcelain)
wp post term set $SITEMAP_ID category "Utility Pages"
echo "Created '$SITEMAP_TITLE' page (ID: $SITEMAP_ID)"

# ===========================================================================
# 4. СОЗДАНИЕ КАТЕГОРИЙ



# ===========================================================================
# 5. СОЗДАНИЕ MainMenu и FooterMenu

MAIN_MENU_LOC="header-menu" 
FOOTER_MENU_LOC="footer-menu"

# создание MainMenu
MAIN_MENU_SLUG="MainMenu"
MAIN_MENU_ID=$(wp menu list --fields=term_id,name --format=csv | grep "^$MAIN_MENU_SLUG," | cut -d ',' -f 1 | head -n 1)

if [ -z "$MAIN_MENU_ID" ]; then
    # Если меню не найдено, создаем его (этот блок сработает только если авто-создания нет)
    MAIN_MENU_ID=$(wp menu create "$MAIN_MENU_SLUG" --porcelain)
    echo "Created $MAIN_MENU_SLUG (ID: $MAIN_MENU_ID)"
else
    echo "Found existing menu $MAIN_MENU_SLUG (ID: $MAIN_MENU_ID). Continuing."
fi

# добавление пунктов в MainMenu
CHILD_IDS=$(wp post list --post_type=page --post_parent=$ARTICLES_ID --field=ID --orderby=ID --order=ASC)
for C_ID in $CHILD_IDS; do
    wp menu item add-post $MAIN_MENU_ID $C_ID
done
wp menu item add-post $MAIN_MENU_ID $ARTICLES_ID
echo "Added child pages and Articles page to $MAIN_MENU_SLUG."

# назначение MainMenu токации темы
wp menu location assign $MAIN_MENU_SLUG $MAIN_MENU_LOC
echo "Assigned $MAIN_MENU_SLUG to location: $MAIN_MENU_LOC"


# создание FooterMenu
FOOTER_MENU_SLUG="FooterMenu"
FOOTER_MENU_ID=$(wp menu list --fields=term_id,name --format=csv | grep "^$FOOTER_MENU_SLUG," | cut -d ',' -f 1 | head -n 1)

if [ -z "$FOOTER_MENU_ID" ]; then
    FOOTER_MENU_ID=$(wp menu create "$FOOTER_MENU_SLUG" --porcelain)
    echo "Created $FOOTER_MENU_SLUG (ID: $FOOTER_MENU_ID)"
else
    echo "Found existing menu $FOOTER_MENU_SLUG (ID: $FOOTER_MENU_ID). Continuing."
fi
echo "Created $FOOTER_MENU_SLUG (ID: $FOOTER_MENU_ID)"

# добавление пуунктов в FooterMenu
wp menu item add-post $FOOTER_MENU_ID $ABOUT_US_ID
wp menu item add-post $FOOTER_MENU_ID $COOKIE_ID
wp menu item add-post $FOOTER_MENU_ID $PRIVACY_ID
wp menu item add-post $FOOTER_MENU_ID $LEGAL_ID
wp menu item add-post $FOOTER_MENU_ID $SITEMAP_ID
echo "Added 5 items to $FOOTER_MENU_SLUG."

# назначение FooterMenu локации темы
wp menu location assign $FOOTER_MENU_SLUG $FOOTER_MENU_LOC
echo "Assigned $FOOTER_MENU_SLUG to location: $FOOTER_MENU_LOC"

echo "--- Menu Setup Complete ---"


# ===========================================================================
# 6. УСТАНОВКА ПЛАГИНОВ
wp plugin delete hello dolly
wp plugin delete akismet
echo "Default plugins removed."
# echo "Installing plugins..."
# wp plugin install wpvivid-backuprestore
# echo "    - WPvivid Backup Plugin"
# wp plugin install seo-by-rank-math --activate
# echo "    - Rank Math SEO"
# wp plugin install clearfy --activate
# echo "    - Clearfy"

# Создание Application Password для autoposter
echo "Creating Application Password 'autoposter'..."
APP_PASS=$(wp user application-password create $ADMIN_USER "autoposter" --porcelain)

echo "--- Готово! Сайт настроен. ---"

echo ""
echo "========================================================="
echo "--- УЧЕТНЫЕ ДАННЫЕ АДМИНА (ОБЯЗАТЕЛЬНО СКОПИРУЙ!) ---"
echo "==================================================="
echo "  - Логин: $ADMIN_USER"
echo "  - Пароль: $ADMIN_PASS"
echo "  - Email: $ADMIN_EMAIL"
echo "  - App Password (autoposter): $APP_PASS"
echo "========================================================="
echo ""

# Сохранение данных в JSON файл
UPLOADS_DIR="wp-content/uploads"
mkdir -p "$UPLOADS_DIR"

JSON_FILE="$UPLOADS_DIR/temp_wp.json"

printf '{\n  "admin_user": "%s",\n  "admin_pass": "%s",\n  "admin_email": "%s",\n  "app_pass": "%s"\n}\n' \
  "$ADMIN_USER" \
  "$ADMIN_PASS" \
  "$ADMIN_EMAIL" \
  "$APP_PASS" > "$JSON_FILE"

echo "Учетные данные сохранены в $JSON_FILE"
echo ""