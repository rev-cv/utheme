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

# перевод слова "Редакция" (Editorial Team / Redacción / и т.д.)
declare -A T_EDITORIAL
T_EDITORIAL=(
    [EN]="Editorial Team"
    [RU]="Редакция"
    [FR]="La Rédaction"
    [DE]="Redaktion"
    [PL]="Redakcja"
    [CZ]="Redakce"
    [CS]="Redakce"
    [PT]="Redação"
    [IT]="Redazione"
    [NL]="Redactie"
    [ES]="Redacción"
    [SK]="Redakcia"
    [ET]="Toimetus"
    [LV]="Redakcija"
    [RO]="Redacția"
    [SV]="Redaktionen"
    [LT]="Redakcija"
    [BG]="Редакция"
    [SL]="Uredništvo"
    [HU]="Szerkesztőség"
    [FI]="Toimitus"
    [DA]="Redaktion"
    [GR]="Σύνταξη"
)
# Получаем перевод. Если языка нет в массиве, используем английский по умолчанию.
EDITORIAL_TEXT=${T_EDITORIAL[$SITE_LANG]}
if [ -z "$EDITORIAL_TEXT" ]; then
    EDITORIAL_TEXT=${T_EDITORIAL[EN]}
fi

# Формируем Имя и Фамилию для WordPress
# Имя: Редакция, Фамилия: "Название сайта"
FIRST_NAME="$EDITORIAL_TEXT"
LAST_NAME="«$SITE_TITLE»"
SELECTED_NAME="$FIRST_NAME $LAST_NAME"

echo "Selected Author Name: $SELECTED_NAME"

# --- Далее ваш стандартный блок создания/обновления юзера ---

if wp user get "$ADMIN_USER" --allow-root > /dev/null 2>&1; then
    echo "Updating existing admin..."
    wp user update "$ADMIN_USER" \
        --user_pass="$ADMIN_PASS" \
        --role=administrator \
        --display_name="$SELECTED_NAME" \
        --first_name="$FIRST_NAME" \
        --last_name="$LAST_NAME" \
        --allow-root
else
    echo "Creating new admin..."
    wp user create "$ADMIN_USER" "$ADMIN_EMAIL" \
        --user_pass="$ADMIN_PASS" \
        --role=administrator \
        --display_name="$SELECTED_NAME" \
        --first_name="$FIRST_NAME" \
        --last_name="$LAST_NAME" \
        --allow-root
fi

ADMIN_ID=$(wp user get "$ADMIN_USER" --field=ID --allow-root)

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

# Объявляем ассоциативные массивы
declare -A T_HOME T_ALL_POSTS T_ABOUT T_COOKIE T_PRIVACY T_LEGAL T_SITEMAP T_NEWS T_WELCOME_NEWS_TITLE T_WELCOME_NEWS_CONTENT

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

# --- NEWS ---
T_NEWS=(
    [EN]="News"
    [RU]="Новости"
    [FR]="Actualités"
    [DE]="Nachrichten"
    [PL]="Aktualności"
    [CZ]="Novinky"
    [CS]="Novinky"
    [PT]="Notícias"
    [IT]="Notizie"
    [NL]="Nieuws"
    [ES]="Noticias"
    [SK]="Novinky"
    [ET]="Uudised"
    [LV]="Jaunumi"
    [RO]="Știri"
    [SV]="Nyheter"
    [LT]="Naujienos"
    [BG]="Новини"
    [SL]="Novice"
    [HU]="Hírek"
    [FI]="Uutiset"
    [DA]="Nyheder"
    [GR]="Νέα"
)

# --- WELCOME NEWS POST ---
T_WELCOME_NEWS_TITLE=(
    [EN]="Welcome! We Have Launched!"
    [RU]="Добро пожаловать! Мы открылись!"
    [FR]="Bienvenue ! Nous avons lancé !"
    [DE]="Willkommen! Wir sind online!"
    [PL]="Witamy! Wystartowaliśmy!"
    [CZ]="Vítejte! Spustili jsme!"
    [CS]="Vítejte! Spustili jsme!"
    [PT]="Bem-vindo! Lançámos o nosso site!"
    [IT]="Benvenuti! Siamo online!"
    [NL]="Welkom! We zijn gelanceerd!"
    [ES]="¡Bienvenidos! ¡Hemos lanzado nuestro sitio!"
    [SK]="Vitajte! Spustili sme!"
    [ET]="Tere tulemast! Oleme avatud!"
    [LV]="Laipni lūdzam! Mēs esam atvērušies!"
    [RO]="Bun venit! Ne-am lansat!"
    [SV]="Välkommen! Vi har lanserat!"
    [LT]="Sveiki atvykę! Mes startavome!"
    [BG]="Добре дошли! Стартирахме!"
    [SL]="Dobrodošli! Začeli smo!"
    [HU]="Üdvözlünk! Elindultunk!"
    [FI]="Tervetuloa! Olemme avanneet!"
    [DA]="Velkommen! Vi er gået i luften!"
    [GR]="Καλώς ήρθατε! Ξεκινήσαμε!"
)

T_WELCOME_NEWS_CONTENT=(
    [EN]="We are thrilled to announce the launch of our new website, «${SITE_TITLE}»! This is the beginning of our journey, and we are excited to share it with you. Here you will find the latest news, interesting articles, and important updates. Stay tuned and thank you for being with us!"
    [RU]="Мы рады объявить о запуске нашего нового сайта, «${SITE_TITLE}»! Это начало нашего пути, и мы рады разделить его с вами. Здесь вы найдете последние новости, интересные статьи и важные обновления. Оставайтесь с нами и спасибо, что вы с нами!"
    [FR]="Nous sommes ravis d'annoncer le lancement de notre nouveau site, «${SITE_TITLE}» ! C'est le début de notre aventure, et nous sommes heureux de la partager avec vous. Vous trouverez ici les dernières actualités, des articles intéressants et des mises à jour importantes. Restez à l'écoute et merci d'être avec nous !"
    [DE]="Wir freuen uns, den Start unserer neuen Website „${SITE_TITLE}“ bekannt zu geben! Dies ist der Beginn unserer Reise, und wir freuen uns, sie mit Ihnen zu teilen. Hier finden Sie die neuesten Nachrichten, interessante Artikel und wichtige Updates. Bleiben Sie dran und danke, dass Sie bei uns sind!"
    [PL]="Z radością ogłaszamy uruchomienie naszej nowej strony internetowej, „${SITE_TITLE}”! To początek naszej podróży i cieszymy się, że możemy ją z Wami dzielić. Znajdziecie tu najnowsze wiadomości, ciekawe artykuły i ważne aktualizacje. Bądźcie z nami i dziękujemy, że jesteście!"
    [CZ]="S radostí oznamujeme spuštění našich nových webových stránek „${SITE_TITLE}“! Toto je začátek naší cesty a jsme nadšeni, že ji můžeme sdílet s vámi. Zde najdete nejnovější zprávy, zajímavé články a důležité aktualizace. Zůstaňte s námi a děkujeme, že jste s námi!"
    [CS]="S radostí oznamujeme spuštění našich nových webových stránek „${SITE_TITLE}“! Toto je začátek naší cesty a jsme nadšeni, že ji můžeme sdílet s vámi. Zde najdete nejnovější zprávy, zajímavé články a důležité aktualizace. Zůstaňte s námi a děkujeme, že jste s námi!"
    [PT]="Temos o prazer de anunciar o lançamento do nosso novo site, «${SITE_TITLE}»! Este é o início da nossa jornada, e estamos entusiasmados por partilhá-la convosco. Aqui encontrará as últimas notícias, artigos interessantes e atualizações importantes. Fique atento e obrigado por estar connosco!"
    [IT]="Siamo entusiasti di annunciare il lancio del nostro nuovo sito web, «${SITE_TITLE}»! Questo è l'inizio del nostro viaggio e siamo felici di condividerlo con voi. Qui troverete le ultime notizie, articoli interessanti e aggiornamenti importanti. Rimanete sintonizzati e grazie per essere con noi!"
    [NL]="We zijn verheugd de lancering van onze nieuwe website, '${SITE_TITLE}', aan te kondigen! Dit is het begin van onze reis en we zijn enthousiast om deze met u te delen. Hier vindt u het laatste nieuws, interessante artikelen en belangrijke updates. Blijf op de hoogte en bedankt dat u bij ons bent!"
    [ES]="¡Estamos encantados de anunciar el lanzamiento de nuestro nuevo sitio web, «${SITE_TITLE}»! Este es el comienzo de nuestro viaje y estamos emocionados de compartirlo con ustedes. Aquí encontrarán las últimas noticias, artículos interesantes y actualizaciones importantes. ¡Manténganse al tanto y gracias por estar con nosotros!"
    [SK]="S radosťou oznamujeme spustenie našej novej webovej stránky, „${SITE_TITLE}“! Toto je začiatok našej cesty a sme nadšení, že ju môžeme s vami zdieľať. Tu nájdete najnovšie správy, zaujímavé články a dôležité aktualizácie. Zostaňte s nami a ďakujeme, že ste s nami!"
    [ET]="Meil on hea meel teatada meie uue veebisaidi „${SITE_TITLE}“ käivitamisest! See on meie teekonna algus ja meil on hea meel seda teiega jagada. Siit leiate viimaseid uudiseid, huvitavaid artikleid ja olulisi uuendusi. Püsige lainel ja aitäh, et olete meiega!"
    [LV]="Mēs ar prieku paziņojam par mūsu jaunās vietnes „${SITE_TITLE}“ atklāšanu! Šis ir mūsu ceļojuma sākums, un mēs esam priecīgi to dalīties ar jums. Šeit jūs atradīsiet jaunākās ziņas, interesantus rakstus un svarīgus atjauninājumus. Sekojiet līdzi un paldies, ka esat ar mums!"
    [RO]="Suntem încântați să anunțăm lansarea noului nostru site, «${SITE_TITLE}»! Acesta este începutul călătoriei noastre și suntem bucuroși să o împărtășim cu dumneavoastră. Aici veți găsi cele mai recente știri, articole interesante și actualizări importante. Rămâneți pe fază și vă mulțumim că sunteți alături de noi!"
    [SV]="Vi är glada att meddela lanseringen av vår nya webbplats, '${SITE_TITLE}'! Detta är början på vår resa, och vi är glada att dela den med er. Här hittar du de senaste nyheterna, intressanta artiklar och viktiga uppdateringar. Håll utkik och tack för att du är med oss!"
    [LT]="Džiaugiamės galėdami pranešti apie mūsų naujos svetainės „${SITE_TITLE}“ paleidimą! Tai mūsų kelionės pradžia, ir mes džiaugiamės galėdami ja pasidalinti su jumis. Čia rasite naujausias naujienas, įdomius straipsnius ir svarbius atnaujinimus. Sekite naujienas ir ačiū, kad esate su mumis!"
    [BG]="Радваме се да обявим старта на нашия нов уебсайт, „${SITE_TITLE}“! Това е началото на нашето пътуване и сме развълнувани да го споделим с вас. Тук ще намерите последните новини, интересни статии и важни актуализации. Останете на линия и ви благодарим, че сте с нас!"
    [SL]="Z veseljem naznanjamo zagon naše nove spletne strani, '${SITE_TITLE}'! To je začetek naše poti in veseli nas, da jo lahko delimo z vami. Tukaj boste našli najnovejše novice, zanimive članke in pomembne posodobitve. Ostanite z nami in hvala, ker ste z nami!"
    [HU]="Örömmel jelentjük be új weboldalunk, a '${SITE_TITLE}' indulását! Ez utazásunk kezdete, és izgatottan várjuk, hogy megosszuk veletek. Itt találjátok a legfrissebb híreket, érdekes cikkeket és fontos frissítéseket. Maradjatok velünk, és köszönjük, hogy velünk vagytok!"
    [FI]="Olemme iloisia voidessamme ilmoittaa uuden verkkosivustomme, '${SITE_TITLE}', julkaisusta! Tämä on matkamme alku, ja olemme innoissamme voidessamme jakaa sen kanssanne. Täältä löydät uusimmat uutiset, mielenkiintoisia artikkeleita ja tärkeitä päivityksiä. Pysy kuulolla ja kiitos, että olet mukanamme!"
    [DA]="Vi er glade for at annoncere lanceringen af vores nye hjemmeside, '${SITE_TITLE}'! Dette er begyndelsen på vores rejse, og vi er spændte på at dele den med jer. Her finder du de seneste nyheder, interessante artikler og vigtige opdateringer. Følg med og tak fordi du er med os!"
    [GR]="Είμαστε στην ευχάριστη θέση να ανακοινώσουμε την έναρξη του νέου μας ιστότοπου, «${SITE_TITLE}»! Αυτή είναι η αρχή του ταξιδιού μας και είμαστε ενθουσιασμένοι που το μοιραζόμαστε μαζί σας. Εδώ θα βρείτε τα τελευταία νέα, ενδιαφέρονта άρθρα και σημαντικές ενημερώσεις. Μείνετε συντονισμένοι και σας ευχαριστούμε που είστε μαζί μας!"
)

echo "Creating categories..."
wp term create category "Utility Pages"
wp term create category "page+5"
wp term create category "page+30"
wp term create category "news"

echo "Creating page structure..."

# Главная страница (Шаблон HOME)
HOME_TITLE="${T_HOME[$SITE_LANG]:-Home}"
HOME_ID=$(wp post create \
    --post_type=page \
    --post_title="$HOME_TITLE" \
    --post_name="home" \
    --post_status=publish \
    --page_template="page.php" \
    --post_author="$ADMIN_ID" \
    --porcelain \
)
# делаем её главной в настройках чтения
wp option update show_on_front "page"
wp option update page_on_front $HOME_ID

# Articles (Шаблон ARTICLE, slug /articles/)
# --post_name задает slug
ARTICLES_TITLE="${T_ALL_POSTS[$SITE_LANG]:-Articles}"
ARTICLES_ID=$(wp post create \
    --post_type=page \
    --post_title="$ARTICLES_TITLE" \
    --post_name="articles" \
    --post_status=publish \
    --page_template="page-list.php" \
    --post_author="$ADMIN_ID" \
    --porcelain \
)
wp post term set $ARTICLES_ID category "Utility Pages"
PARENT_ID=$ARTICLES_ID # PARENT_ID для дочерних статей
echo "Created 'Articles' page (ID: $PARENT_ID)"

# 5 дочерних articles страниц (Шаблон ARTICLE)
for i in {1..5}; do
    CHILD_ID=$(wp post create \
        --post_type=page \
        --post_title="cl$i" \
        --post_status=publish \
        --post_parent=$PARENT_ID \
        --page_template="page.php" \
        --post_author="$ADMIN_ID" \
        --porcelain \
    )
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

    PAGE_ID=$(wp post create \
        --post_type=page \
        --post_title="$PAGE_TITLE" \
        --post_name="$PAGE_SLUG" \
        --post_status=publish \
        --page_template="page.php" \
        --post_author="$ADMIN_ID" \
        --porcelain \
    )
    
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
SITEMAP_ID=$(wp post create \
    --post_type=page \
    --post_title="$SITEMAP_TITLE" \
    --post_name="sitemap" \
    --post_status=publish \
    --page_template="page-list.php" \
    --post_author="$ADMIN_ID" \
    --porcelain \
)
wp post term set $SITEMAP_ID category "Utility Pages"
echo "Created '$SITEMAP_TITLE' page (ID: $SITEMAP_ID)"

# News page
NEWS_TITLE="${T_NEWS[$SITE_LANG]:-News}"
NEWS_ID=$(wp post create \
    --post_type=page \
    --post_title="$NEWS_TITLE" \
    --post_name="news" \
    --post_status=publish \
    --page_template="page-sitemap.php" \
    --post_author="$ADMIN_ID" \
    --porcelain \
)
wp post term set $NEWS_ID category "Utility Pages"
echo "Created '$NEWS_TITLE' page (ID: $NEWS_ID)"

# Create first news post
WELCOME_TITLE="${T_WELCOME_NEWS_TITLE[$SITE_LANG]:-Welcome! We Have Launched!}"
WELCOME_CONTENT="${T_WELCOME_NEWS_CONTENT[$SITE_LANG]:-Welcome to our new site!}"
WELCOME_POST_ID=$(wp post create \
    --post_type=post \
    --post_title="$WELCOME_TITLE" \
    --post_content="$WELCOME_CONTENT" \
    --post_status=publish \
    --post_author="$ADMIN_ID" \
    --porcelain \
)
wp post term set $WELCOME_POST_ID category "news"
echo "Created welcome news post '$WELCOME_TITLE' (ID: $WELCOME_POST_ID)"

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
wp menu item add-post $MAIN_MENU_ID $NEWS_ID
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