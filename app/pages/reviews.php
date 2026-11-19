<?php
$productId = (int) ($_GET['id'] ?? $_POST['product_id'] ?? 0);
$product = find_product($productId);

if (!$product) {
    http_response_code(404);
    $pageTitle = 'Товар не найден — ВОЛЬТ';
    include APP_ROOT . '/app/views/header.php';
    ?>
    <div class="container section">
        <div class="empty-state empty-state--page">
            <h1>Товар не найден</h1>
            <p>Отзывы доступны только для товаров из каталога.</p>
            <a class="button" href="/catalog.php">Перейти в каталог</a>
        </div>
    </div>
    <?php
    include APP_ROOT . '/app/views/footer.php';
    return;
}

$user = current_user();
$errors = [];
$form = ['rating' => 0, 'pros' => '', 'cons' => '', 'comment' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'helpful') {
        // «Полезно»: один голос с одной сессии на один отзыв
        $reviewId = (int) ($_POST['review_id'] ?? 0);
        $voted = $_SESSION['review_votes'] ?? [];
        if ($reviewId > 0 && empty($voted[$reviewId])) {
            $stmt = db()->prepare('UPDATE reviews SET helpful = helpful + 1 WHERE id = ? AND product_id = ?');
            $stmt->execute([$reviewId, $productId]);
            $voted[$reviewId] = true;
            $_SESSION['review_votes'] = $voted;
        }
        redirect('/reviews.php?id=' . $productId . '#review-' . $reviewId);
    }

    if ($action === 'add') {
        require_login();
        $form['rating'] = (int) ($_POST['rating'] ?? 0);
        $form['pros'] = trim((string) ($_POST['pros'] ?? ''));
        $form['cons'] = trim((string) ($_POST['cons'] ?? ''));
        $form['comment'] = trim((string) ($_POST['comment'] ?? ''));

        if ($form['rating'] < 1 || $form['rating'] > 5) {
            $errors[] = 'Поставьте оценку от 1 до 5.';
        }
        if (mb_strlen($form['comment']) < 10) {
            $errors[] = 'Напишите комментарий не короче 10 символов.';
        }
        if (mb_strlen($form['comment']) > 1000 || mb_strlen($form['pros']) > 300 || mb_strlen($form['cons']) > 300) {
            $errors[] = 'Слишком длинный текст: комментарий до 1000 символов, достоинства и недостатки до 300.';
        }

        if (!$errors) {
            $parts = preg_split('/\s+/', trim((string) $user['full_name']));
            $author = $parts[0] . (isset($parts[1]) ? ' ' . mb_substr($parts[1], 0, 1) . '.' : '');
            $stmt = db()->prepare(
                'INSERT INTO reviews (product_id, user_id, author, rating, pros, cons, comment, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$productId, (int) $user['id'], $author, $form['rating'], $form['pros'], $form['cons'], $form['comment'], date('Y-m-d H:i:s')]);
            flash('success', 'Спасибо! Ваш отзыв опубликован.');
            redirect('/reviews.php?id=' . $productId . '&sort=new#list');
        }
    }
}

// Фильтры и сортировка берутся из адреса: ?photo=1&reply=1&stars=5&sort=new&limit=10
$filters = [
    'photo' => !empty($_GET['photo']),
    'reply' => !empty($_GET['reply']),
    'stars' => (int) ($_GET['stars'] ?? 0),
    'sort' => in_array($_GET['sort'] ?? '', ['helpful', 'new', 'high', 'low'], true) ? $_GET['sort'] : 'helpful',
];
$limit = max(5, min(100, (int) ($_GET['limit'] ?? 5)));
$summary = reviews_summary($productId);
$list = reviews_list($productId, $filters, $limit);
$price = (int) $product['price'];
$reviewImage = $product['image'];
$reviewUrl = static fn (array $over = []): string => '/reviews.php?' . query_string(array_merge(['id' => $productId, 'limit' => null], $over)) . '#list';

$pageTitle = 'Отзывы о ' . $product['name'] . ' — ВОЛЬТ';
$bodyClass = 'page-product page-pv page-reviews';
$extraCss = ['css/product-v2.css'];
include APP_ROOT . '/app/views/header.php';
?>
<div class="pv-minibar">
    <div class="container pv-minibar__row">
        <img src="<?= e(product_image($product['image'])) ?>" alt="" width="62" height="46">
        <div>
            <b><?= e($product['name']) ?></b>
            <small><?php if ($summary['count'] > 0): ?><?= stars_html((int) round($summary['avg'])) ?> <?= str_replace('.', ',', (string) $summary['avg']) ?> · <?= $summary['count'] ?> <?= plural_ru($summary['count'], 'отзыв', 'отзыва', 'отзывов') ?><?php else: ?>Отзывов пока нет<?php endif; ?></small>
        </div>
        <span class="pv-minibar__sp"></span>
        <strong><?= format_price($price) ?></strong>
        <?php if ((int) $product['stock_quantity'] > 0): ?>
            <form action="/cart.php" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                <input type="hidden" name="quantity" value="1">
                <button class="pv-btn pv-btn--sm" type="submit">В корзину</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="pv">
    <div class="container">
        <a class="pv-back" href="/product.php?id=<?= $productId ?>">← К карточке товара</a>
        <h1 class="pv-h1">Отзывы о <?= e($product['name']) ?>
            <small><?= $summary['count'] ?> <?= plural_ru($summary['count'], 'отзыв', 'отзыва', 'отзывов') ?><?= $summary['count'] > 0 ? ' · средняя оценка ' . str_replace('.', ',', (string) $summary['avg']) : '' ?></small>
        </h1>

        <div class="pv-cols">
            <aside class="pv-aside">
                <div class="pv-card">
                    <?php if ($summary['count'] > 0): ?>
                        <?php include APP_ROOT . '/app/views/review-summary.php'; ?>
                    <?php else: ?>
                        <h2>Отзывов пока нет</h2><p class="pv-muted">Расскажите о товаре первым.</p>
                    <?php endif; ?>
                </div>
                <div class="pv-card pv-filters">
                    <h2>Фильтры</h2>
                    <a class="pv-toggle<?= $filters['photo'] ? ' is-on' : '' ?>" href="<?= e($reviewUrl(['photo' => $filters['photo'] ? null : 1, 'page' => null])) ?>"><span>Только с фото · <?= $summary['photos'] ?></span><i></i></a>
                    <a class="pv-toggle<?= $filters['reply'] ? ' is-on' : '' ?>" href="<?= e($reviewUrl(['reply' => $filters['reply'] ? null : 1])) ?>"><span>С ответом магазина · <?= $summary['replies'] ?></span><i></i></a>
                    <div class="pv-filters__title">Оценка</div>
                    <div class="pv-chips">
                        <?php foreach ([5, 4, 3, 2, 1] as $s): ?>
                            <a class="pv-chip<?= $filters['stars'] === $s ? ' is-on' : '' ?>" href="<?= e($reviewUrl(['stars' => $filters['stars'] === $s ? null : $s])) ?>"><?= $s ?> ★</a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </aside>

            <section id="list">
                <div class="pv-sort">
                    <a class="pv-pill<?= !$filters['photo'] && !$filters['reply'] && !$filters['stars'] ? ' is-on' : '' ?>" href="<?= e($reviewUrl(['photo' => null, 'reply' => null, 'stars' => null])) ?>">Все · <?= $summary['count'] ?></a>
                    <a class="pv-pill<?= $filters['photo'] ? ' is-on' : '' ?>" href="<?= e($reviewUrl(['photo' => 1])) ?>">С фото · <?= $summary['photos'] ?></a>
                    <a class="pv-pill<?= $filters['reply'] ? ' is-on' : '' ?>" href="<?= e($reviewUrl(['reply' => 1])) ?>">С ответом · <?= $summary['replies'] ?></a>
                    <form class="pv-sort__form" action="/reviews.php" method="get">
                        <input type="hidden" name="id" value="<?= $productId ?>">
                        <?php if ($filters['photo']): ?><input type="hidden" name="photo" value="1"><?php endif; ?>
                        <?php if ($filters['reply']): ?><input type="hidden" name="reply" value="1"><?php endif; ?>
                        <?php if ($filters['stars']): ?><input type="hidden" name="stars" value="<?= (int) $filters['stars'] ?>"><?php endif; ?>
                        <select name="sort" data-auto-submit aria-label="Сортировка отзывов">
                            <option value="helpful" <?= $filters['sort'] === 'helpful' ? 'selected' : '' ?>>Сначала полезные</option>
                            <option value="new" <?= $filters['sort'] === 'new' ? 'selected' : '' ?>>Сначала новые</option>
                            <option value="high" <?= $filters['sort'] === 'high' ? 'selected' : '' ?>>Высокие оценки</option>
                            <option value="low" <?= $filters['sort'] === 'low' ? 'selected' : '' ?>>Низкие оценки</option>
                        </select>
                    </form>
                </div>

                <details class="pv-write" id="write" <?= $errors || ($_GET['write'] ?? '') !== '' ? 'open' : '' ?>>
                    <summary>Написать отзыв</summary>
                    <?php if (!$user): ?>
                        <p>Чтобы оставить отзыв, <a href="/login.php">войдите в аккаунт</a>.</p>
                    <?php else: ?>
                        <?php if ($errors): ?>
                            <div class="form-errors" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
                        <?php endif; ?>
                        <form action="/reviews.php?id=<?= $productId ?>#write" method="post" class="pv-write__form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="product_id" value="<?= $productId ?>">
                            <fieldset class="pv-rate">
                                <legend>Ваша оценка</legend>
                                <?php for ($s = 5; $s >= 1; $s--): ?>
                                    <input type="radio" id="rate<?= $s ?>" name="rating" value="<?= $s ?>" <?= $form['rating'] === $s ? 'checked' : '' ?>><label for="rate<?= $s ?>" title="<?= $s ?> из 5">★</label>
                                <?php endfor; ?>
                            </fieldset>
                            <label class="pv-field"><span>Достоинства</span><input type="text" name="pros" maxlength="300" value="<?= e($form['pros']) ?>"></label>
                            <label class="pv-field"><span>Недостатки</span><input type="text" name="cons" maxlength="300" value="<?= e($form['cons']) ?>"></label>
                            <label class="pv-field"><span>Комментарий</span><textarea name="comment" rows="4" maxlength="1000" required><?= e($form['comment']) ?></textarea></label>
                            <button class="pv-btn" type="submit">Опубликовать отзыв</button>
                        </form>
                    <?php endif; ?>
                </details>

                <?php if (!$list['items']): ?>
                    <div class="pv-card pv-empty">
                        <h2><?= $summary['count'] > 0 ? 'По выбранным фильтрам отзывов нет' : 'Отзывов пока нет' ?></h2>
                        <p class="pv-muted"><?= $summary['count'] > 0 ? 'Попробуйте снять часть фильтров.' : 'Станьте первым, кто расскажет об этом товаре.' ?></p>
                        <?php if ($summary['count'] > 0): ?><a class="pv-btn pv-btn--ghost" href="<?= e($reviewUrl(['photo' => null, 'reply' => null, 'stars' => null])) ?>">Сбросить фильтры</a><?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php foreach ($list['items'] as $review): ?>
                    <?php include APP_ROOT . '/app/views/review-card.php'; ?>
                <?php endforeach; ?>

                <?php if ($list['total'] > count($list['items'])): ?>
                    <div class="pv-more"><a class="pv-btn pv-btn--ghost" href="<?= e($reviewUrl(['limit' => $limit + 5])) ?>">Показать ещё <?= min(5, $list['total'] - count($list['items'])) ?> из <?= $list['total'] - count($list['items']) ?></a></div>
                <?php endif; ?>
            </section>
        </div>
    </div>
</div>
<?php include APP_ROOT . '/app/views/footer.php'; ?>
