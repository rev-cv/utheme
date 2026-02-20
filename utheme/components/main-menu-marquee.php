<header id="main-header">
    <div class="island island-left">
        <div class="site-logo"><?php the_custom_logo(); ?></div>
        <div class="site-name">
            <a href="<?php echo home_url(); ?>"><?php bloginfo('name'); ?></a>
        </div>
    </div>

    <div class="island island-center">
        <div class="ticker-wrapper">
            <div class="ticker-mover">
                <?php
                for ($i = 0; $i < 2; $i++) {
                    wp_nav_menu([
                        "theme_location" => "header-menu",
                        "container"      => "div",
                        "container_class" => "ticker-instance", // Каждая копия меню
                        "menu_class"     => "ticker-list",
                        "walker"         => new Island_Walker(),
                        "depth"          => 1,
                    ]);
                }
                ?>
            </div>
        </div>
    </div>

    <div class="island island-right">
        <a href="<?php echo get_permalink(5); ?>" class="animated-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                <path fill="currentColor" d="M5 21h1v-3H3v1q0 .825.588 1.413T5 21m3 0h3v-3H8zm5 0h3v-3h-3zm5 0h1q.825 0 1.413-.587T21 19v-1h-3zM3 6h3V3H5q-.825 0-1.412.588T3 5zm0 5h3V8H3zm0 5h3v-3H3zM8 6h3V3H8zm0 5h3V8H8zm0 5h3v-3H8zm5-10h3V3h-3zm0 5h3V8h-3zm0 5h3v-3h-3zm5-10h3V5q0-.825-.587-1.412T19 3h-1zm0 5h3V8h-3zm0 5h3v-3h-3z" />
            </svg>
        </a>
    </div>
</header>

<script>
    document.addEventListener('DOMContentLoaded', () => {
    const mover = document.querySelector('.ticker-mover');
    const container = document.querySelector('.island-center');

    if (!mover || !container) return;

    let currentX = 0;
    let speed = 0; 
    let targetSpeed = 0;
    const maxSpeed = 3; 

    function animate() {
        speed += (targetSpeed - speed) * 0.1;
        
        currentX -= speed;

        const instance = mover.querySelector('.ticker-instance');
        if (instance) {
            const width = instance.offsetWidth;
            
            if (currentX <= -width) {
                currentX += width;
            } else if (currentX >= 0) {
                currentX -= width;
            }
        }

        mover.style.transform = `translateX(${currentX}px)`;
        requestAnimationFrame(animate);
    }

    container.addEventListener('mousemove', (e) => {
        const rect = container.getBoundingClientRect();
        const centerX = rect.left + rect.width / 2;
        
        const relativeX = (e.clientX - centerX) / (rect.width / 2);
        targetSpeed = relativeX * maxSpeed;
    });

    container.addEventListener('mouseleave', () => {
        targetSpeed = 0; 
    });

    animate();
});
</script>