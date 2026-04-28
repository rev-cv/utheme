# Языковые массивы для технических страниц сайта.
# Портированы из core/setup_site.sh.

LANG_MAP = {
    "EN": "en_US", "RU": "ru_RU", "FR": "fr_FR", "DE": "de_DE",
    "PL": "pl_PL", "CZ": "cs_CZ", "CS": "cs_CZ", "PT": "pt_PT",
    "IT": "it_IT", "NL": "nl_NL", "ES": "es_ES", "SK": "sk_SK",
    "ET": "et_EE", "LV": "lv_LV", "RO": "ro_RO", "SV": "sv_SE",
    "LT": "lt_LT", "BG": "bg_BG", "SL": "sl_SI", "HU": "hu_HU",
    "FI": "fi_FI", "DA": "da_DK", "GR": "el",
    "HR": "hr_HR", "NO": "nb_NO", "LB": "lb_LU",
}

# slug → lang_code → title
PAGE_TITLES = {
    "index": {
        "EN": "Home",        "RU": "Главная",       "FR": "Accueil",
        "DE": "Startseite",  "PL": "Strona główna", "CZ": "Domů",
        "CS": "Domů",        "PT": "Início",        "IT": "Home",
        "NL": "Home",        "ES": "Inicio",        "SK": "Domov",
        "ET": "Avaleht",     "LV": "Sākums",        "RO": "Acasă",
        "SV": "Hem",         "LT": "Pradžia",       "BG": "Начало",
        "SL": "Domov",       "HU": "Főoldal",       "FI": "Koti",
        "DA": "Forside",     "GR": "Αρχική",
        "HR": "Početna",     "NO": "Hjem",          "LB": "Heem",
    },
    "articles": {
        "EN": "All Posts",          "RU": "Все статьи",          "FR": "Tous les articles",
        "DE": "Alle Artikel",       "PL": "Wszystkie artykuły",  "CZ": "Všechny články",
        "CS": "Všechny články",     "PT": "Todos os artigos",    "IT": "Tutti gli articoli",
        "NL": "Alle artikelen",     "ES": "Todos los artículos", "SK": "Všetky články",
        "ET": "Kõik postitused",    "LV": "Visi raksti",         "RO": "Toate articolele",
        "SV": "Alla inlägg",        "LT": "Visi įrašai",         "BG": "Всички статии",
        "SL": "Vsi prispevki",      "HU": "Összes bejegyzés",    "FI": "Kaikki kirjoitukset",
        "DA": "Alle indlæg",        "GR": "Όλα τα άρθρα",
        "HR": "Sve objave",         "NO": "Alle innlegg",        "LB": "All Artikelen",
    },
    "about-us": {
        "EN": "About Us",       "RU": "О нас",          "FR": "À propos",
        "DE": "Über uns",       "PL": "O nas",           "CZ": "O nás",
        "CS": "O nás",          "PT": "Sobre nós",       "IT": "Chi Siamo",
        "NL": "Over ons",       "ES": "Sobre nosotros",  "SK": "O nás",
        "ET": "Meist",          "LV": "Par mums",        "RO": "Despre noi",
        "SV": "Om oss",         "LT": "Apie mus",        "BG": "За нас",
        "SL": "O nas",          "HU": "Rólunk",          "FI": "Meistä",
        "DA": "Om os",          "GR": "Σχετικά με εμάς",
        "HR": "O nama",         "NO": "Om oss",              "LB": "Iwwer eis",
    },
    "cookie-policy": {
        "EN": "Cookie Policy",          "RU": "Политика Cookie",          "FR": "Politique cookies",
        "DE": "Cookie-Richtlinie",      "PL": "Polityka cookies",         "CZ": "Zásady cookies",
        "CS": "Zásady cookies",         "PT": "Política de cookies",      "IT": "Informativa sui Cookie",
        "NL": "Cookiebeleid",           "ES": "Política de cookies",      "SK": "Zásady cookies",
        "ET": "Küpsiste eeskirjad",     "LV": "Sīkdatņu politika",       "RO": "Politică de cookie",
        "SV": "Cookiepolicy",           "LT": "Slapukų politika",         "BG": "Политика за бисквитки",
        "SL": "Pravilnik o piškotkih",  "HU": "Süti szabályzat",          "FI": "Evästekäytäntö",
        "DA": "Cookiepolitik",          "GR": "Πολιτική Cookies",
        "HR": "Pravila o kolačićima",   "NO": "Informasjonskapselpolicy", "LB": "Cookie-Politik",
    },
    "privacy-policy": {
        "EN": "Privacy Policy",             "RU": "Политика конфиденциальности", "FR": "Confidentialité",
        "DE": "Datenschutz",                "PL": "Polityka prywatności",        "CZ": "Ochrana soukromí",
        "CS": "Ochrana soukromí",           "PT": "Privacidade",                 "IT": "Informativa sulla privacy",
        "NL": "Privacybeleid",              "ES": "Privacidad",                  "SK": "Ochrana súkromia",
        "ET": "Privaatsuspoliitika",        "LV": "Privātuma politika",          "RO": "Politică de confidențialitate",
        "SV": "Integritetspolicy",          "LT": "Privatumo politika",          "BG": "Политика за поверителност",
        "SL": "Pravilnik o zasebnosti",     "HU": "Adatvédelmi irányelvek",      "FI": "Tietosuojaseloste",
        "DA": "Privatlivspolitik",          "GR": "Πολιτική Απορρήτου",
        "HR": "Pravila privatnosti",        "NO": "Personvernerklæring",     "LB": "Dateschutzpolitik",
    },
    "legal-notice": {
        "EN": "Legal Notice",           "RU": "Юридическая информация",  "FR": "Mentions légales",
        "DE": "Impressum",              "PL": "Nota prawna",             "CZ": "Právní doložka",
        "CS": "Právní doložka",         "PT": "Aviso legal",             "IT": "Informazioni Legali",
        "NL": "Juridische informatie",  "ES": "Aviso legal",             "SK": "Právne informácie",
        "ET": "Oikeudellinen ilmoitus", "LV": "Juridiskā informācija",   "RO": "Mențiuni legale",
        "SV": "Rättslig information",   "LT": "Teisinė informacija",     "BG": "Правна информация",
        "SL": "Pravno obvestilo",       "HU": "Jogi nyilatkozat",        "FI": "Oikeudellinen huomautus",
        "DA": "Juridisk meddelelse",    "GR": "Νομική Σημείωση",
        "HR": "Pravna napomena",        "NO": "Juridisk merknad",        "LB": "Rechtleche Vermerk",
    },
    "sitemap": {
        "EN": "Sitemap",        "RU": "Карта сайта",    "FR": "Plan du site",
        "DE": "Sitemap",        "PL": "Mapa strony",    "CZ": "Mapa stránek",
        "CS": "Mapa stránek",   "PT": "Mapa do site",   "IT": "Mappa del sito",
        "NL": "Sitemap",        "ES": "Mapa del sitio", "SK": "Mapa stránok",
        "ET": "Sisukaart",      "LV": "Lapas karte",    "RO": "Hartă site",
        "SV": "Sajtkarta",      "LT": "Svetainės medis","BG": "Карта на сайта",
        "SL": "Kazalo strani",  "HU": "Oldaltérkép",    "FI": "Sivukartta",
        "DA": "Sitemap",        "GR": "Χάρτης Ιστότοπου",
        "HR": "Karta stranice", "NO": "Nettstedskart",           "LB": "Saiteplang",
    },
    "news": {
        "EN": "News",       "RU": "Новости",    "FR": "Actualités",
        "DE": "Nachrichten","PL": "Aktualności","CZ": "Novinky",
        "CS": "Novinky",    "PT": "Notícias",   "IT": "Notizie",
        "NL": "Nieuws",     "ES": "Noticias",   "SK": "Novinky",
        "ET": "Uudised",    "LV": "Jaunumi",    "RO": "Știri",
        "SV": "Nyheter",    "LT": "Naujienos",  "BG": "Новини",
        "SL": "Novice",     "HU": "Hírek",      "FI": "Uutiset",
        "DA": "Nyheder",    "GR": "Νέα",
        "HR": "Vijesti",    "NO": "Nyheter",    "LB": "Neiegkeeten",
    },
}

PAGE_DESCRIPTIONS = {
    "articles": {
        "EN": "All articles on «{site_title}» — expert guides, reviews and useful tips.",
        "RU": "Все статьи «{site_title}» — экспертные руководства, обзоры и полезные советы.",
        "FR": "Tous les articles de «{site_title}» — guides d'experts, avis et conseils pratiques.",
        "DE": "Alle Artikel von «{site_title}» — Expertenratgeber, Rezensionen und nützliche Tipps.",
        "PL": "Wszystkie artykuły «{site_title}» — poradniki ekspertów, recenzje i przydatne wskazówki.",
        "CZ": "Všechny články «{site_title}» — odborné průvodce, recenze a užitečné tipy.",
        "CS": "Všechny články «{site_title}» — odborné průvodce, recenze a užitečné tipy.",
        "PT": "Todos os artigos de «{site_title}» — guias especializados, análises e dicas úteis.",
        "IT": "Tutti gli articoli di «{site_title}» — guide di esperti, recensioni e consigli utili.",
        "NL": "Alle artikelen van «{site_title}» — expertgidsen, beoordelingen en nuttige tips.",
        "ES": "Todos los artículos de «{site_title}» — guías de expertos, reseñas y consejos útiles.",
        "SK": "Všetky články «{site_title}» — odborné príručky, recenzie a užitočné tipy.",
        "ET": "Kõik artiklid saidil «{site_title}» — eksperdijuhendid, arvustused ja kasulikud näpunäited.",
        "LV": "Visi raksti vietnē «{site_title}» — ekspertu ceļveži, atsauksmes un noderīgi padomi.",
        "RO": "Toate articolele de pe «{site_title}» — ghiduri de experți, recenzii și sfaturi utile.",
        "SV": "Alla artiklar på «{site_title}» — expertguider, recensioner och användbara tips.",
        "LT": "Visi straipsniai «{site_title}» — ekspertų vadovai, apžvalgos ir naudingi patarimai.",
        "BG": "Всички статии на «{site_title}» — експертни ръководства, ревюта и полезни съвети.",
        "SL": "Vsi članki na «{site_title}» — strokovni vodniki, ocene in koristni nasveti.",
        "HU": "Összes cikk a(z) «{site_title}» oldalon — szakértői útmutatók, vélemények és hasznos tippek.",
        "FI": "Kaikki artikkelit sivustolla «{site_title}» — asiantuntijaoppaat, arvostelut ja hyödylliset vinkit.",
        "DA": "Alle artikler på «{site_title}» — ekspertguider, anmeldelser og nyttige tips.",
        "GR": "Όλα τα άρθρα του «{site_title}» — οδηγοί ειδικών, κριτικές και χρήσιμες συμβουλές.",
        "HR": "Svi članci na «{site_title}» — stručni vodiči, recenzije i korisni savjeti.",
        "NO": "Alle artikler på «{site_title}» — ekspertguider, anmeldelser og nyttige tips.",
        "LB": "All Artikelen op «{site_title}» — Experteführeren, Rezensiounen a nëtzlech Tipps.",
    },
    "news": {
        "EN": "Latest news and updates from «{site_title}» — stay informed with the most recent publications.",
        "RU": "Последние новости «{site_title}» — актуальные события, обновления и анонсы.",
        "FR": "Dernières actualités de «{site_title}» — restez informé des publications les plus récentes.",
        "DE": "Neueste Nachrichten von «{site_title}» — bleiben Sie mit den aktuellen Meldungen auf dem Laufenden.",
        "PL": "Najnowsze wiadomości «{site_title}» — bądź na bieżąco z aktualnymi publikacjami.",
        "CZ": "Nejnovější zprávy «{site_title}» — zůstaňte informováni o nejnovějších příspěvcích.",
        "CS": "Nejnovější zprávy «{site_title}» — zůstaňte informováni o nejnovějších příspěvcích.",
        "PT": "Últimas notícias de «{site_title}» — fique informado com as publicações mais recentes.",
        "IT": "Ultime notizie da «{site_title}» — rimani aggiornato con le pubblicazioni più recenti.",
        "NL": "Laatste nieuws van «{site_title}» — blijf op de hoogte van de meest recente publicaties.",
        "ES": "Últimas noticias de «{site_title}» — mantente informado con las publicaciones más recientes.",
        "SK": "Najnovšie správy «{site_title}» — zostante informovaní o najnovších príspevkoch.",
        "ET": "Viimased uudised leheküljelt «{site_title}» — püsige kursis kõige värskema sisuga.",
        "LV": "Jaunākās ziņas no «{site_title}» — sekojiet līdzi jaunākajām publikācijām.",
        "RO": "Ultimele știri de pe «{site_title}» — rămâneți la curent cu cele mai recente publicații.",
        "SV": "Senaste nyheterna från «{site_title}» — håll dig uppdaterad med de senaste inläggen.",
        "LT": "Naujausios naujienos iš «{site_title}» — sekite naujausius įrašus ir atnaujinimus.",
        "BG": "Последни новини от «{site_title}» — бъдете в крак с актуалните публикации.",
        "SL": "Najnovejše novice z «{site_title}» — ostanite na tekočem z najsvežjimi objavami.",
        "HU": "Legújabb hírek a(z) «{site_title}» oldalról — maradj naprakész a legfrissebb publikációkkal.",
        "FI": "Uusimmat uutiset sivustolta «{site_title}» — pysy ajan tasalla tuoreimpien julkaisujen kanssa.",
        "DA": "Seneste nyheder fra «{site_title}» — hold dig opdateret med de nyeste indlæg.",
        "GR": "Τελευταία νέα από το «{site_title}» — μείνετε ενημερωμένοι με τις πιο πρόσφατες δημοσιεύσεις.",
        "HR": "Najnovije vijesti s «{site_title}» — ostanite informirani o najnovijim objavama.",
        "NO": "Siste nyheter fra «{site_title}» — hold deg oppdatert med de nyeste innleggene.",
        "LB": "Déi lescht Neiegkeeten vun «{site_title}» — bleift mat de neisten Artikelen um Lafenden.",
    },
    "sitemap": {
        "EN": "Sitemap «{site_title}»: complete list of all sections and pages on the site.",
        "RU": "Карта сайта «{site_title}»: полный список всех разделов и страниц сайта.",
        "FR": "Plan du site «{site_title}» : liste complète de toutes les sections et pages.",
        "DE": "Sitemap «{site_title}»: vollständige Liste aller Bereiche und Seiten der Website.",
        "PL": "Mapa strony «{site_title}»: pełna lista wszystkich sekcji i stron witryny.",
        "CZ": "Mapa stránek «{site_title}»: úplný seznam všech sekcí a stránek webu.",
        "CS": "Mapa stránek «{site_title}»: úplný seznam všech sekcí a stránek webu.",
        "PT": "Mapa do site «{site_title}»: lista completa de todas as secções e páginas.",
        "IT": "Mappa del sito «{site_title}»: elenco completo di tutte le sezioni e le pagine.",
        "NL": "Sitemap «{site_title}»: volledige lijst van alle secties en pagina's van de site.",
        "ES": "Mapa del sitio «{site_title}»: lista completa de todas las secciones y páginas.",
        "SK": "Mapa stránok «{site_title}»: úplný zoznam všetkých sekcií a stránok webu.",
        "ET": "Saidikaart «{site_title}»: täielik loend kõigist jaotistest ja lehtedest.",
        "LV": "Vietnes karte «{site_title}»: pilns visu sadaļu un lapu saraksts.",
        "RO": "Harta site-ului «{site_title}»: lista completă a tuturor secțiunilor și paginilor.",
        "SV": "Webbplatskarta «{site_title}»: fullständig lista över alla sektioner och sidor.",
        "LT": "Svetainės žemėlapis «{site_title}»: pilnas visų skyrių ir puslapių sąrašas.",
        "BG": "Карта на сайта «{site_title}»: пълен списък на всички раздели и страници.",
        "SL": "Zemljevid spletnega mesta «{site_title}»: celoten seznam vseh razdelkov in strani.",
        "HU": "Oldaltérkép «{site_title}»: a webhely összes szakaszának és oldalának teljes listája.",
        "FI": "Sivukartta «{site_title}»: täydellinen luettelo kaikista osioista ja sivuista.",
        "DA": "Sitemap «{site_title}»: komplet liste over alle sektioner og sider på sitet.",
        "GR": "Χάρτης ιστότοπου «{site_title}»: πλήρης λίστα όλων των ενοτήτων και σελίδων.",
        "HR": "Karta web mjesta «{site_title}»: cjelovit popis svih sekcija i stranica.",
        "NO": "Nettstedskart «{site_title}»: fullstendig liste over alle seksjoner og sider.",
        "LB": "Saiteplang «{site_title}»: vollständeg Lëscht vun alle Sektiounen a Säiten.",
    },
}


EDITORIAL = {
    "EN": "Editorial Team",  "RU": "Редакция",       "FR": "La Rédaction",
    "DE": "Redaktion",       "PL": "Redakcja",        "CZ": "Redakce",
    "CS": "Redakce",         "PT": "Redação",         "IT": "Redazione",
    "NL": "Redactie",        "ES": "Redacción",       "SK": "Redakcia",
    "ET": "Toimetus",        "LV": "Redakcija",       "RO": "Redacția",
    "SV": "Redaktionen",     "LT": "Redakcija",       "BG": "Редакция",
    "SL": "Uredništvo",      "HU": "Szerkesztőség",   "FI": "Toimitus",
    "DA": "Redaktion",       "GR": "Σύνταξη",
    "HR": "Uredništvo",      "NO": "Redaksjon",      "LB": "Redaktioun",
}


WELCOME_NEWS_TITLE = {
    "EN": "Welcome! We Have Launched!",
    "RU": "Добро пожаловать! Мы открылись!",
    "FR": "Bienvenue ! Nous avons lancé !",
    "DE": "Willkommen! Wir sind online!",
    "PL": "Witamy! Wystartowaliśmy!",
    "CZ": "Vítejte! Spustili jsme!",
    "CS": "Vítejte! Spustili jsme!",
    "PT": "Bem-vindo! Lançámos o nosso site!",
    "IT": "Benvenuti! Siamo online!",
    "NL": "Welkom! We zijn gelanceerd!",
    "ES": "¡Bienvenidos! ¡Hemos lanzado nuestro sitio!",
    "SK": "Vitajte! Spustili sme!",
    "ET": "Tere tulemast! Oleme avatud!",
    "LV": "Laipni lūdzam! Mēs esam atvērušies!",
    "RO": "Bun venit! Ne-am lansat!",
    "SV": "Välkommen! Vi har lanserat!",
    "LT": "Sveiki atvykę! Mes startavome!",
    "BG": "Добре дошли! Стартирахме!",
    "SL": "Dobrodošli! Začeli smo!",
    "HU": "Üdvözlünk! Elindultunk!",
    "FI": "Tervetuloa! Olemme avanneet!",
    "DA": "Velkommen! Vi er gået i luften!",
    "GR": "Καλώς ήρθατε! Ξεκινήσαμε!",
    "HR": "Dobrodošli! Pokrenuli smo se!",
    "NO": "Velkommen! Vi har lansert!",
    "LB": "Wëllkomm! Mir hunn gestart!",
}

WELCOME_NEWS_CONTENT = {
    "EN": "We are thrilled to announce the launch of our new website, «{site_title}»! This is the beginning of our journey, and we are excited to share it with you. Here you will find the latest news, interesting articles, and important updates. Stay tuned and thank you for being with us!",
    "RU": "Мы рады объявить о запуске нашего нового сайта, «{site_title}»! Это начало нашего пути, и мы рады разделить его с вами. Здесь вы найдёте последние новости, интересные статьи и важные обновления. Оставайтесь с нами и спасибо, что вы с нами!",
    "FR": "Nous sommes ravis d'annoncer le lancement de notre nouveau site, «{site_title}» ! C'est le début de notre aventure, et nous sommes heureux de la partager avec vous. Vous trouverez ici les dernières actualités, des articles intéressants et des mises à jour importantes. Restez à l'écoute et merci d'être avec nous !",
    "DE": "Wir freuen uns, den Start unserer neuen Website «{site_title}» bekannt zu geben! Dies ist der Beginn unserer Reise, und wir freuen uns, sie mit Ihnen zu teilen. Hier finden Sie die neuesten Nachrichten, interessante Artikel und wichtige Updates. Bleiben Sie dran und danke, dass Sie bei uns sind!",
    "PL": "Z radością ogłaszamy uruchomienie naszej nowej strony internetowej, «{site_title}»! To początek naszej podróży i cieszymy się, że możemy ją z Wami dzielić. Znajdziecie tu najnowsze wiadomości, ciekawe artykuły i ważne aktualizacje. Bądźcie z nami i dziękujemy, że jesteście!",
    "CZ": "S radostí oznamujeme spuštění našich nových webových stránek «{site_title}»! Toto je začátek naší cesty a jsme nadšeni, že ji můžeme sdílet s vámi. Zde najdete nejnovější zprávy, zajímavé články a důležité aktualizace. Zůstaňte s námi a děkujeme, že jste s námi!",
    "CS": "S radostí oznamujeme spuštění našich nových webových stránek «{site_title}»! Toto je začátek naší cesty a jsme nadšeni, že ji můžeme sdílet s vámi. Zde najdete nejnovější zprávy, zajímavé články a důležité aktualizace. Zůstaňte s námi a děkujeme, že jste s námi!",
    "PT": "Temos o prazer de anunciar o lançamento do nosso novo site, «{site_title}»! Este é o início da nossa jornada, e estamos entusiasmados por partilhá-la convosco. Aqui encontrará as últimas notícias, artigos interessantes e atualizações importantes. Fique atento e obrigado por estar connosco!",
    "IT": "Siamo entusiasti di annunciare il lancio del nostro nuovo sito web, «{site_title}»! Questo è l'inizio del nostro viaggio e siamo felici di condividerlo con voi. Qui troverete le ultime notizie, articoli interessanti e aggiornamenti importanti. Rimanete sintonizzati e grazie per essere con noi!",
    "NL": "We zijn verheugd de lancering van onze nieuwe website, «{site_title}», aan te kondigen! Dit is het begin van onze reis en we zijn enthousiast om deze met u te delen. Hier vindt u het laatste nieuws, interessante artikelen en belangrijke updates. Blijf op de hoogte en bedankt dat u bij ons bent!",
    "ES": "¡Estamos encantados de anunciar el lanzamiento de nuestro nuevo sitio web, «{site_title}»! Este es el comienzo de nuestro viaje y estamos emocionados de compartirlo con ustedes. Aquí encontrarán las últimas noticias, artículos interesantes y actualizaciones importantes. ¡Manténganse al tanto y gracias por estar con nosotros!",
    "SK": "S radosťou oznamujeme spustenie našej novej webovej stránky, «{site_title}»! Toto je začiatok našej cesty a sme nadšení, že ju môžeme s vami zdieľať. Tu nájdete najnovšie správy, zaujímavé články a dôležité aktualizácie. Zostaňte s nami a ďakujeme, že ste s nami!",
    "ET": "Meil on hea meel teatada meie uue veebisaidi «{site_title}» käivitamisest! See on meie teekonna algus ja meil on hea meel seda teiega jagada. Siit leiate viimaseid uudiseid, huvitavaid artikleid ja olulisi uuendusi. Püsige lainel ja aitäh, et olete meiega!",
    "LV": "Mēs ar prieku paziņojam par mūsu jaunās vietnes «{site_title}» atklāšanu! Šis ir mūsu ceļojuma sākums, un mēs esam priecīgi to dalīties ar jums. Šeit jūs atradīsiet jaunākās ziņas, interesantus rakstus un svarīgus atjauninājumus. Sekojiet līdzi un paldies, ka esat ar mums!",
    "RO": "Suntem încântați să anunțăm lansarea noului nostru site, «{site_title}»! Acesta este începutul călătoriei noastre și suntem bucuroși să o împărtășim cu dumneavoastră. Aici veți găsi cele mai recente știri, articole interesante și actualizări importante. Rămâneți pe față și vă mulțumim că sunteți alături de noi!",
    "SV": "Vi är glada att meddela lanseringen av vår nya webbplats, «{site_title}»! Detta är början på vår resa, och vi är glada att dela den med er. Här hittar du de senaste nyheterna, intressanta artiklar och viktiga uppdateringar. Håll utkik och tack för att du är med oss!",
    "LT": "Džiaugiamės galėdami pranešti apie mūsų naujos svetainės «{site_title}» paleidimą! Tai mūsų kelionės pradžia, ir mes džiaugiamės galėdami ja pasidalinti su jumis. Čia rasite naujausias naujienas, įdomius straipsnius ir svarbius atnaujinimus. Sekite naujienas ir ačiū, kad esate su mumis!",
    "BG": "Радваме се да обявим старта на нашия нов уебсайт, «{site_title}»! Това е началото на нашето пътуване и сме развълнувани да го споделим с вас. Тук ще намерите последните новини, интересни статии и важни актуализации. Останете на линия и ви благодарим, че сте с нас!",
    "SL": "Z veseljem naznanjamo zagon naše nove spletne strani, «{site_title}»! To je začetek naše poti in veseli nas, da jo lahko delimo z vami. Tukaj boste našli najnovejše novice, zanimive članke in pomembne posodobitve. Ostanite z nami in hvala, ker ste z nami!",
    "HU": "Örömmel jelentjük be új weboldalunk, a «{site_title}» indulását! Ez utazásunk kezdete, és izgatottan várjuk, hogy megosszuk veletek. Itt találjátok a legfrissebb híreket, érdekes cikkeket és fontos frissítéseket. Maradjatok velünk, és köszönjük, hogy velünk vagytok!",
    "FI": "Olemme iloisia voidessamme ilmoittaa uuden verkkosivustomme, «{site_title}», julkaisusta! Tämä on matkamme alku, ja olemme innoissamme voidessamme jakaa sen kanssanne. Täältä löydät uusimmat uutiset, mielenkiintoisia artikkeleita ja tärkeitä päivityksiä. Pysy kuulolla ja kiitos, että olet mukanamme!",
    "DA": "Vi er glade for at annoncere lanceringen af vores nye hjemmeside, «{site_title}»! Dette er begyndelsen på vores rejse, og vi er spændte på at dele den med jer. Her finder du de seneste nyheder, interessante artikler og vigtige opdateringer. Følg med og tak fordi du er med os!",
    "GR": "Είμαστε στην ευχάριστη θέση να ανακοινώσουμε την έναρξη του νέου μας ιστότοπου, «{site_title}»! Αυτή είναι η αρχή του ταξιδιού μας και είμαστε ενθουσιασμένοι που το μοιραζόμαστε μαζί σας. Εδώ θα βρείτε τα τελευταία νέα, ενδιαφέροντα άρθρα και σημαντικές ενημερώσεις. Μείνετε συντονισμένοι και σας ευχαριστούμε που είστε μαζί μας!",
    "HR": "Oduševljeni smo što možemo najaviti pokretanje naše nove web stranice, «{site_title}»! Ovo je početak našeg putovanja i radujemo se što ga možemo podijeliti s vama. Ovdje ćete pronaći najnovije vijesti, zanimljive članke i važne informacije. Pratite nas i hvala što ste s nama!",
    "NO": "Vi er begeistret for å kunngjøre lanseringen av vår nye nettside, «{site_title}»! Dette er begynnelsen på vår reise, og vi er glade for å dele den med deg. Her finner du de siste nyhetene, interessante artikler og viktige oppdateringer. Følg med og takk for at du er med oss!",
    "LB": "Mir si frou déi Lancéierung vun eisem neien Internetsite, «{site_title}», unzekënnegen! Dëst ass den Ufank vun eisem Wee an mir freeën eis dësen mat Iech ze deelen. Hei fannt Dir déi lescht Neiegkeeten, interessant Artikelen an wichteg Aktualiséierungen. Bleift dobäi a Merci datt Dir bei eis sidd!",
}


def get_welcome_post(lang: str, site_title: str) -> tuple[str, str]:
    """Returns (title, content) for the welcome news post, with site_title substituted."""
    title   = WELCOME_NEWS_TITLE.get(lang) or WELCOME_NEWS_TITLE["EN"]
    content = WELCOME_NEWS_CONTENT.get(lang) or WELCOME_NEWS_CONTENT["EN"]
    return title, content.format(site_title=site_title)


def get_page_title(slug: str, lang: str) -> str:
    """Возвращает локализованный заголовок технической страницы по slug и коду языка."""
    titles = PAGE_TITLES.get(slug, {})
    return titles.get(lang) or titles.get("EN") or slug


def get_page_description(slug: str, lang: str, site_title: str) -> str | None:
    """Возвращает SEO-описание технической страницы с подставленным названием сайта."""
    descs = PAGE_DESCRIPTIONS.get(slug, {})
    template = descs.get(lang) or descs.get("EN")
    if not template:
        return None
    return template.format(site_title=site_title)


def get_lang_code(lang: str) -> str:
    """Нормализует SITE_LANG к 2-буквенному uppercase-ключу для словарей.
    Принимает как 'FR', так и 'fr_BE' → 'FR'."""
    if "_" in lang:
        return lang[:2].upper()
    return lang.upper()


def get_wp_locale(lang: str) -> str:
    """Возвращает WP locale slug.
    Принимает как 'FR' (→ fr_FR через LANG_MAP), так и 'fr_BE' (→ fr_BE напрямую)."""
    if "_" in lang:
        return lang
    return LANG_MAP.get(lang.upper(), "en_US")


def get_editorial_name(lang: str, site_title: str) -> str:
    """Возвращает display_name автора вида 'Редакция «Название сайта»'."""
    first = EDITORIAL.get(lang) or EDITORIAL["EN"]
    return f"{first} «{site_title}»"
