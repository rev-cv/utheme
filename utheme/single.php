<?php
/**
 * Template for single posts.
 * Supports two category types:
 *  - "news"  → H1 > featured image > article content
 *  - "event" → scoreboard-style layout with countdown timer
 */

get_header();

function _sp_lang() {
    $lang = substr(get_bloginfo('language'), 0, 2);
    $t = [
        'tomorrow'   => [
            'es'=>'Mañana','fr'=>'Demain','de'=>'Morgen','pl'=>'Jutro','pt'=>'Amanhã','nl'=>'Morgen',
            'sk'=>'Zajtra','it'=>'Domani','et'=>'Homme','lv'=>'Rīt','ro'=>'Mâine','sv'=>'Imorgon','lt'=>'Rytoj',
            'bg'=>'Утре','sl'=>'Jutri','hu'=>'Holnap','fi'=>'Huomenna','en'=>'Tomorrow','cs'=>'Zítra',
            'da'=>'I morgen','ru'=>'Завтра','el'=>'Αύριο','hr'=>'Sutra','no'=>'I morgen','lb'=>'Muer'
        ],
        'pre_match'  => [
            'es'=>'Pre-partido','fr'=>'Avant-match','de'=>'Vor dem Spiel','pl'=>'Przed meczem','pt'=>'Pré-jogo',
            'nl'=>'Voor de wedstrijd','sk'=>'Pred zápasom','it'=>'Pre-partita','et'=>'Enne mängu','lv'=>'Pirms spēles',
            'ro'=>'Pre-meci','sv'=>'Före match','lt'=>'Prieš rungtynes','bg'=>'Преди мач','sl'=>'Pred tekmo',
            'hu'=>'Mérkőzés előtt','fi'=>'Ennen ottelua','en'=>'Pre-Match','cs'=>'Před zápasem','da'=>'Før kamp',
            'ru'=>'До матча','el'=>'Πριν τον αγώνα','hr'=>'Prije utakmice','no'=>'Før kamp','lb'=>'Virun dem Match'
        ],
        'match_started' => [
            'es'=>'El partido ha empezado','fr'=>'Le match a commencé','de'=>'Spiel hat begonnen',
            'pl'=>'Mecz się rozpoczął','pt'=>'O jogo começou','nl'=>'Wedstrijd begonnen','sk'=>'Zápas sa začal',
            'it'=>'La partita è iniziata','et'=>'Mäng alanud','lv'=>'Spēle ir sākusies','ro'=>'Meciul a început',
            'sv'=>'Matchen har börjat','lt'=>'Rungtynės prasidėjo','bg'=>'Мачът започна','sl'=>'Tekma se je začela',
            'hu'=>'A mérkőzés elkezdődött','fi'=>'Ottelu on alkanut','en'=>'Match started','cs'=>'Zápas začal',
            'da'=>'Kampen er startet','ru'=>'Мач начался','el'=>'Ο αγώνας ξεκίνησε',
            'hr'=>'Utakmica je počela','no'=>'Kampen har startet','lb'=>'De Match huet ugefaangen'
        ],
        'home_team'  => [
            'es'=>'Local','fr'=>'Domicile','de'=>'Heim','pl'=>'Gospodarz','pt'=>'Casa','nl'=>'Thuis',
            'sk'=>'Domáci','it'=>'Casa','et'=>'Kodu','lv'=>'Mājas','ro'=>'Acasă','sv'=>'Hemmalag',
            'lt'=>'Namuose','bg'=>'Домашен','sl'=>'Domači','hu'=>'Hazai','fi'=>'Koti','en'=>'Home',
            'cs'=>'Domácí','da'=>'Hjemme','ru'=>'Домашняя','el'=>'Γηπεδούχος',
            'hr'=>'Domaćin','no'=>'Hjemmelag','lb'=>'Heem'
        ],
        'away_team'  => [
            'es'=>'Visitante','fr'=>'Extérieur','de'=>'Auswärts','pl'=>'Gość','pt'=>'Fora','nl'=>'Uit',
            'sk'=>'Hosť','it'=>'Trasferta','et'=>'Võõrsil','lv'=>'Izbraukums','ro'=>'Deplasare','sv'=>'Bortalag',
            'lt'=>'Svečiuose','bg'=>'Гост','sl'=>'Gostje','hu'=>'Vendég','fi'=>'Vieras','en'=>'Away',
            'cs'=>'Hosté','da'=>'Ude','ru'=>'Гостевая','el'=>'Φιλοξενούμενη',
            'hr'=>'Gost','no'=>'Bortelag','lb'=>'Auswäerts'
        ],
        'expert_analysis' => [
            'es'=>'Análisis estratégico de expertos','fr'=>"Analyse stratégique d'experts",'de'=>'Strategische Expertenanalyse',
            'pl'=>'Ekspercka analiza strategiczna','pt'=>'Análise estratégica de especialistas','nl'=>'Strategische analyse van experts',
            'sk'=>'Expertná strategická analýza','it'=>'Analisi strategica degli esperti','et'=>'Eksperdi strateegiline analüüs',
            'lv'=>'Ekspertu stratēģiskā analīze','ro'=>'Analiză strategică de specialitate','sv'=>'Expert strategisk analys',
            'lt'=>'Ekspertų strateginė analizė','bg'=>'Експертен стратегически анализ','sl'=>'Ekspertna strateška analiza',
            'hu'=>'Szakértő stratégiai elemzés','fi'=>'Asiantuntijan strateginen analyysi','en'=>'Expert Strategic Analysis',
            'cs'=>'Expertní strategická analýza','da'=>'Ekspert strategisk analyse','ru'=>'Экспертный стратегический анализ',
            'el'=>'Εμπειρογνώμονας στρατηγική ανάλυση','hr'=>'Stručna strateška analiza',
            'no'=>'Ekspert strategisk analyse','lb'=>'Fachleche strategesche Analyse'
        ],
    ];
    $out = [];
    foreach ($t as $k => $v) {
        $out[$k] = isset($v[$lang]) ? $v[$lang] : (isset($v['en']) ? $v['en'] : $k);
    }
    return $out;
}
$L = _sp_lang();
?>

<?php while (have_posts()) : the_post(); ?>

<?php
$is_event = has_category('event');
$is_news  = has_category('news');

$game_time     = get_post_meta(get_the_ID(), '_event_game_time', true);
$home_team     = get_post_meta(get_the_ID(), '_event_home_team', true);
$away_team     = get_post_meta(get_the_ID(), '_event_away_team', true);
$league        = get_post_meta(get_the_ID(), '_event_league', true);
$home_logo     = get_post_meta(get_the_ID(), '_home_team_logo', true);
$away_logo     = get_post_meta(get_the_ID(), '_away_team_logo', true);
$official_pick = get_post_meta(get_the_ID(), '_event_official_pick', true);

$game_ts      = $game_time ? strtotime($game_time) : 0;
$timer_active = $game_ts && $game_ts > time();

$ev_date_label = '';
$ev_time_label = '';
if ($game_ts) {
    $today_start    = mktime(0, 0, 0);
    $tomorrow_start = $today_start + DAY_IN_SECONDS;
    $tomorrow_end   = $tomorrow_start + DAY_IN_SECONDS;

    if ($game_ts >= $tomorrow_start && $game_ts < $tomorrow_end) {
        $ev_date_label = $L['tomorrow'];
    } else {
        $ev_date_label = date_i18n(get_option('date_format'), $game_ts);
    }
    $ev_time_label = date_i18n(get_option('time_format'), $game_ts);
}

$home_logo_url = $home_logo ? wp_get_attachment_image_url($home_logo, 'medium') : '';
$away_logo_url = $away_logo ? wp_get_attachment_image_url($away_logo, 'medium') : '';
$featured_url  = get_the_post_thumbnail_url(get_the_ID(), 'large');
?>

<?php if ($is_event) : ?>
<main class="post-event">

    <?php echo get_my_breadcrumbs(); ?>

    <div class="match-header">
        <h1><?php the_title(); ?></h1>

        <?php if ($timer_active) : ?>
        <div class="timer" id="sp-timer-box">
            <span class="dot"></span>
            <div class="inner">
                <span class="label"><?php echo esc_html($L['pre_match']); ?></span>
                <span class="value" id="sp-countdown">--:--:--</span>
            </div>
        </div>
        <?php elseif ($game_ts && $game_ts <= time()) : ?>
        <span class="expired"><?php echo esc_html($L['match_started']); ?></span>
        <?php endif; ?>
    </div>

    <?php if ($home_team || $away_team || $featured_url) : ?>
    <section class="scoreboard">
        <?php if ($featured_url) : ?>
        <div class="bg" style="background-image:url('<?php echo esc_url($featured_url); ?>')"></div>
        <?php endif; ?>

        <div class="inner">

            <?php if ($home_team || $home_logo_url) : ?>
            <div class="team home">
                <?php if ($home_logo_url) : ?>
                <img src="<?php echo esc_url($home_logo_url); ?>"
                     alt="<?php echo esc_attr($home_team ?: $L['home_team']); ?>"
                     class="logo"
                     loading="lazy">
                <?php endif; ?>
                <?php if ($home_team) : ?>
                <p class="name"><?php echo esc_html($home_team); ?></p>
                <?php endif; ?>
                <span class="role"><?php echo esc_html($L['home_team']); ?></span>
            </div>
            <?php endif; ?>

            <div class="versus">
                <?php if ($league) : ?>
                <span class="league"><?php echo esc_html($league); ?></span>
                <?php endif; ?>
                <p class="text">VS</p>
                <?php if ($ev_date_label) : ?>
                <span class="date"><?php echo esc_html($ev_date_label); ?></span>
                <span class="time"><?php echo esc_html($ev_time_label); ?></span>
                <?php endif; ?>
            </div>

            <?php if ($away_team || $away_logo_url) : ?>
            <div class="team away">
                <?php if ($away_logo_url) : ?>
                <img src="<?php echo esc_url($away_logo_url); ?>"
                     alt="<?php echo esc_attr($away_team ?: $L['away_team']); ?>"
                     class="logo"
                     loading="lazy">
                <?php endif; ?>
                <?php if ($away_team) : ?>
                <p class="name"><?php echo esc_html($away_team); ?></p>
                <?php endif; ?>
                <span class="role"><?php echo esc_html($L['away_team']); ?></span>
            </div>
            <?php endif; ?>

        </div>
    </section>
    <?php elseif ($featured_url) : ?>
    <figure>
        <img src="<?php echo esc_url($featured_url); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy">
    </figure>
    <?php endif; ?>

    <article class="analysis">
        <div class="head">
            <h2><?php echo esc_html($L['expert_analysis']); ?></h2>
        </div>
        <div class="body">
            <?php the_content(); ?>
        </div>
    </article>

</main>

<?php else : ?>
<main>
    <article class="article post-news">
        <?php echo get_my_breadcrumbs(); ?>
        <h1><?php the_title(); ?></h1>
        <?php if (has_post_thumbnail()) : ?>
        <figure><?php the_post_thumbnail('large', ['loading' => 'lazy']); ?></figure>
        <?php endif; ?>
        <?php the_content(); ?>
    </article>
</main>

<?php endif; ?>

<?php
$_utility = get_term_by('name', 'Utility Pages', 'category');
if (!$_utility || !has_term($_utility->term_id, 'category', get_the_ID())) {
    echo render_more_pages(5);
}
?>

<?php endwhile; ?>

<?php if ($is_event && $timer_active) : ?>
<script>
(function () {
    var target = <?php echo (int) $game_ts; ?> * 1000;
    var el = document.getElementById('sp-countdown');
    var box = document.getElementById('sp-timer-box');
    if (!el) return;

    function pad(n) { return n < 10 ? '0' + n : n; }

    function tick() {
        var diff = target - Date.now();
        if (diff <= 0) {
            box && (box.style.display = 'none');
            return;
        }
        var h = Math.floor(diff / 3600000);
        var m = Math.floor((diff % 3600000) / 60000);
        var s = Math.floor((diff % 60000) / 1000);
        el.textContent = pad(h) + ':' + pad(m) + ':' + pad(s);
        setTimeout(tick, 1000);
    }
    tick();
})();
</script>
<?php endif; ?>

<?php get_footer(); ?>
