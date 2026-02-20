<?php

function custom_toc_copy_shortcode()
{
    // Уникальный ID для места вставки, чтобы избежать конфликтов
    $target_id = 'toc-placeholder-' . uniqid();

    ob_start();
?>
    <div id="<?php echo $target_id; ?>" class="toc-copy-container"></div>

    <script>
        (function() {
            document.addEventListener("DOMContentLoaded", function() {
                // Ищем исходный элемент .toc
                var sourceToc = document.querySelector('.toc');
                // Ищем наш целевой контейнер
                var targetContainer = document.getElementById('<?php echo $target_id; ?>');

                if (sourceToc && targetContainer) {
                    // Копируем содержимое (глубокое копирование со всеми дочерними элементами)
                    var tocClone = sourceToc.cloneNode(true);

                    // Опционально: удаляем id у клона, чтобы не было дублей в DOM
                    if (tocClone.id) tocClone.removeAttribute('id');

                    targetContainer.appendChild(tocClone);
                }
            });
        })();
    </script>
<?php
    return ob_get_clean();
}
add_shortcode('duplicate_toc', 'custom_toc_copy_shortcode');
