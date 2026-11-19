<?php
$productId = (int) ($_GET['id'] ?? 0);
$product = find_product($productId);

if (!$product) {
    http_response_code(404);
    $pageTitle = 'Товар не найден — ВОЛЬТ';
    include APP_ROOT . '/app/views/header.php';
    ?>
    <div class="container section">
        <div class="empty-state empty-state--page">
            <span class="empty-state__icon"><svg viewBox="0 0 24 24" fill="none"><path d="M4 7h16v13H4V7Zm4 0V5h8v2" stroke="currentColor" stroke-width="1.6"/><path d="m9 12 6 6m0-6-6 6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg></span>
            <h1>Товар не найден</h1>
            <p>Возможно, он больше не продаётся. Посмотрите другие модели в каталоге.</p>
            <a class="button" href="/catalog.php">Перейти в каталог</a>
        </div>
    </div>
    <?php
    include APP_ROOT . '/app/views/footer.php';
    return;
}

$specs = product_specs($product);
$stock = (int) $product['stock_quantity'];
$price = (int) $product['price'];
$oldPrice = (int) $product['old_price'];
$discount = $oldPrice > $price ? (int) round((1 - $price / $oldPrice) * 100) : 0;
$saving = $oldPrice > $price ? $oldPrice - $price : 0;

// Отзывы берутся из базы: рейтинг и число отзывов считаются по настоящим данным
$summary = reviews_summary($productId);
$latest = reviews_list($productId, ['sort' => 'helpful'], 2)['items'];
$deliveryOptions = product_delivery_options($price);
$reviewImage = $product['image'];

$pageTitle = $product['name'] . ' — купить в ВОЛЬТ';
$metaDescription = mb_substr($product['description'], 0, 160);
$bodyClass = 'page-product page-pv';
$extraCss = ['css/product-v2.css'];

// Похожие товары: текущий товар в список не попадает
$relatedStmt = db()->prepare(
    'SELECT p.*, c.name AS category_name, c.slug AS category_slug
     FROM products p
     JOIN categories c ON c.id = p.category_id
     WHERE p.category_id = ? AND p.is_active = 1 AND p.id <> ?
     ORDER BY p.id ASC
     LIMIT 4'
);
$relatedStmt->execute([(int) $product['category_id'], $productId]);
$related = $relatedStmt->fetchAll();

include APP_ROOT . '/app/views/header.php';
?>
<div class="pv">
    <div class="container">
        <nav class="pv-crumbs" aria-label="Хлебные крошки">
            <a href="/">Главная</a><span>/</span>
            <a href="/catalog.php">Каталог</a><span>/</span>
            <a href="/catalog.php?category=<?= e($product['category_slug']) ?>"><?= e($product['category_name']) ?></a><span>/</span>
            <span><?= e($product['name']) ?></span>
        </nav>

        <div class="pv-top">
            <div class="pv-bigimg">
                <div class="pv-tags">
                    <?php if ((int) $product['is_featured']): ?><span class="pv-tag">Хит</span><?php endif; ?>
                    <?php if ($discount > 0): ?><span class="pv-tag pv-tag--red">−<?= $discount ?>%</span><?php endif; ?>
                    <?php if ($stock > 0 && $stock <= 3): ?><span class="pv-tag pv-tag--warn">Осталось <?= $stock ?></span><?php endif; ?>
                </div>
                <img src="<?= e(product_image($product['image'])) ?>" alt="<?= e($product['name']) ?>" width="800" height="600">
            </div>

            <div class="pv-below">
                <div class="pv-head">
                    <div class="pv-meta"><a href="/catalog.php?category=<?= e($product['category_slug']) ?>"><?= e($product['category_name']) ?></a> · Артикул <?= str_pad((string) $product['id'], 5, '0', STR_PAD_LEFT) ?></div>
                    <h1><?= e($product['name']) ?></h1>

                    <div class="pv-rating">
                        <?php if ($summary['count'] > 0): ?>
                            <?= stars_html((int) round($summary['avg'])) ?>
                            <b><?= str_replace('.', ',', (string) $summary['avg']) ?></b>
                            <a href="/reviews.php?id=<?= $productId ?>"><?= $summary['count'] ?> <?= plural_ru($summary['count'], 'отзыв', 'отзыва', 'отзывов') ?></a>
                            <span class="pv-ok"><?= $summary['recommend'] ?>% рекомендуют</span>
                        <?php else: ?>
                            <span class="pv-muted">Отзывов пока нет</span> · <a href="/reviews.php?id=<?= $productId ?>#write">Оставить первый отзыв</a>
                        <?php endif; ?>
                    </div>

                    <p class="pv-lead"><?= e($product['description']) ?></p>

                    <?php if ($specs): ?>
                        <div class="pv-specs">
                            <?php foreach (array_slice($specs, 0, 4, true) as $label => $value): ?>
                                <div><span><?= e((string) $label) ?></span><b><?= e((string) $value) ?></b></div>
                            <?php endforeach; ?>
                        </div>
                        <a class="pv-link" href="#specs">Все характеристики</a>
                    <?php endif; ?>
                </div>

                <div class="pv-box">
                    <div class="pv-price">
                        <strong><?= format_price($price) ?></strong>
                        <?php if ($saving > 0): ?><s><?= format_price($oldPrice) ?></s><em>−<?= format_price($saving) ?></em><?php endif; ?>
                    </div>
                    <div class="pv-stock<?= $stock === 0 ? ' is-empty' : '' ?>">
                        <?php if ($stock === 0): ?>Нет в наличии<?php elseif ($stock <= 3): ?>Осталось <?= $stock ?> шт. — успейте заказать<?php else: ?>В наличии <?= $stock ?> шт.<?php endif; ?>
                    </div>

                    <div class="pv-delivery-title">Получение</div>
                    <div class="pv-chips">
                        <?php foreach ($deliveryOptions as $opt): ?>
                            <span class="pv-chip<?= $opt['on'] ? ' is-on' : '' ?>"><?= e($opt['label']) ?> · <?= $opt['cost'] === 0 ? 'бесплатно' : format_price($opt['cost']) ?></span>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($stock > 0): ?>
                        <form class="pv-buy" action="/cart.php" method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                            <div class="pv-qty" data-quantity>
                                <button type="button" data-qty-minus aria-label="Уменьшить количество">−</button>
                                <input type="number" name="quantity" value="1" min="1" max="<?= min(99, $stock) ?>" aria-label="Количество">
                                <button type="button" data-qty-plus aria-label="Увеличить количество">+</button>
                            </div>
                            <button class="pv-btn" type="submit">В корзину · <?= format_price($price) ?></button>
                        </form>
                    <?php else: ?>
                        <button class="pv-btn pv-btn--off" type="button" disabled>Нет в наличии</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="pv-tabs" role="tablist" aria-label="Разделы карточки">
            <a href="#about" role="tab" data-tab="about" class="is-on" aria-selected="true">О товаре</a>
            <?php if ($specs): ?><a href="#specs" role="tab" data-tab="specs" aria-selected="false">Характеристики</a><?php endif; ?>
            <a href="#reviews" role="tab" data-tab="reviews" aria-selected="false">Отзывы <em><?= $summary['count'] ?></em></a>
            <a href="#delivery" role="tab" data-tab="delivery" aria-selected="false">Доставка и возврат</a>
        </div>

        <section class="pv-card pv-panel" id="about" data-panel="about" role="tabpanel">
            <h2>О товаре</h2>
            <p><?= e($product['description']) ?></p>
        </section>

        <?php if ($specs): ?>
            <section class="pv-card pv-panel" id="specs" data-panel="specs" role="tabpanel">
                <h2>Характеристики</h2>
                <div class="pv-spec-table">
                    <?php foreach ($specs as $label => $value): ?>
                        <div><span><?= e((string) $label) ?></span><b><?= e((string) $value) ?></b></div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <section class="pv-reviews pv-panel" id="reviews" data-panel="reviews" role="tabpanel">
            <aside class="pv-card pv-summary">
                <h2>Отзывы покупателей</h2>
                <?php if ($summary['count'] > 0): ?>
                    <?php include APP_ROOT . '/app/views/review-summary.php'; ?>
                <?php else: ?>
                    <p class="pv-muted">Пока никто не оставил отзыв. Станьте первым.</p>
                <?php endif; ?>
                <a class="pv-btn pv-btn--ghost" href="/reviews.php?id=<?= $productId ?>#write">Написать отзыв</a>
            </aside>
            <div class="pv-list">
                <?php foreach ($latest as $review): ?>
                    <?php include APP_ROOT . '/app/views/review-card.php'; ?>
                <?php endforeach; ?>
                <?php if ($summary['count'] > 0): ?>
                    <div class="pv-more"><a class="pv-btn" href="/reviews.php?id=<?= $productId ?>">Все отзывы · <?= $summary['count'] ?> →</a></div>
                <?php endif; ?>
            </div>
        </section>

        <section class="pv-card pv-panel" id="delivery" data-panel="delivery" role="tabpanel">
            <h2>Доставка и возврат</h2>
            <div class="pv-info-grid">
                <div><b>Курьером</b><span>1–2 дня по Новосибирску: бесплатно от <?= format_price(FREE_SHIPPING_THRESHOLD) ?>, иначе 590 ₽</span></div>
                <div><b>Экспресс</b><span>В течение дня при заказе до 13:00, 990 ₽</span></div>
                <div><b>Самовывоз</b><span>Красный проспект, 25, бесплатно</span></div>
                <div><b>Оплата</b><span>Картой онлайн, картой или наличными при получении</span></div>
            </div>
            <a class="pv-link" href="/info.php?page=delivery">Все условия доставки</a>
        </section>

        <?php if ($related): ?>
            <section class="pv-related">
                <h2>Похожие товары</h2>
                <div class="product-grid product-grid--four">
                    <?php $current = $product; foreach ($related as $product): include APP_ROOT . '/app/views/product-card.php'; endforeach; $product = $current; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>
</div>
<script>
// Вкладки: показываем одну «страницу» за раз. Без JavaScript видны все разделы подряд.
(function () {
    var tabs = document.querySelectorAll('.pv-tabs [data-tab]');
    var panels = document.querySelectorAll('[data-panel]');
    if (!tabs.length || !panels.length) { return; }
    function show(name, scroll) {
        var found = false;
        panels.forEach(function (p) { var on = p.dataset.panel === name; p.hidden = !on; if (on) { found = true; } });
        if (!found) { return; }
        tabs.forEach(function (t) {
            var on = t.dataset.tab === name;
            t.classList.toggle('is-on', on);
            t.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        if (scroll) { document.querySelector('.pv-tabs').scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    }
    tabs.forEach(function (t) {
        t.addEventListener('click', function (e) {
            e.preventDefault();
            show(t.dataset.tab, false);
            if (window.history && history.replaceState) { history.replaceState(null, '', '#' + t.dataset.tab); }
        });
    });
    // обычные ссылки на раздел (например, «Все характеристики») тоже открывают нужную вкладку
    document.querySelectorAll('a[href^="#"]:not([data-tab])').forEach(function (a) {
        var name = a.getAttribute('href').slice(1);
        if (document.querySelector('[data-panel="' + name + '"]')) {
            a.addEventListener('click', function (e) { e.preventDefault(); show(name, true); });
        }
    });
    var initial = location.hash.slice(1);
    show(document.querySelector('[data-panel="' + initial + '"]') ? initial : 'about', false);
    // переход по ссылке с #раздел на уже открытой странице
    window.addEventListener('hashchange', function () {
        var name = location.hash.slice(1);
        if (document.querySelector('[data-panel="' + name + '"]')) { show(name, false); }
    });
})();
</script>
<?php include APP_ROOT . '/app/views/footer.php'; ?>
