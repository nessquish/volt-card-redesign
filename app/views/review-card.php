<?php
/** Карточка одного отзыва: ожидает $review, $productId, $reviewImage. */
$letter = mb_strtoupper(mb_substr(trim((string) $review['author']), 0, 1));
$hasReply = trim((string) ($review['store_reply'] ?? '')) !== '';
?>
<article class="pv-review" id="review-<?= (int) $review['id'] ?>">
    <div class="pv-review__head">
        <span class="pv-avatar pv-avatar--<?= ((int) $review['id']) % 4 ?>"><?= e($letter) ?></span>
        <div><b><?= e($review['author']) ?></b><small><?= e(ru_date($review['created_at'])) ?></small></div>
    </div>
    <?= stars_html((int) $review['rating']) ?>
    <?php if ($review['pros'] !== ''): ?><p class="pv-f"><span>Достоинства:</span> <?= e($review['pros']) ?></p><?php endif; ?>
    <?php if ($review['cons'] !== ''): ?><p class="pv-f"><span>Недостатки:</span> <?= e($review['cons']) ?></p><?php endif; ?>
    <?php if ($review['comment'] !== ''): ?><p class="pv-f"><span>Комментарий:</span> <?= e($review['comment']) ?></p><?php endif; ?>
    <?php if ((int) $review['photos'] > 0): ?>
        <div class="pv-photos">
            <?php for ($i = 0; $i < (int) $review['photos']; $i++): ?>
                <span><img src="<?= e(product_image($reviewImage)) ?>" alt="Фото к отзыву" loading="lazy" width="84" height="84"></span>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
    <form class="pv-review__foot" action="/reviews.php?id=<?= (int) $productId ?>#review-<?= (int) $review['id'] ?>" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="helpful">
        <input type="hidden" name="product_id" value="<?= (int) $productId ?>">
        <input type="hidden" name="review_id" value="<?= (int) $review['id'] ?>">
        <button type="submit" class="pv-like">👍 Полезно · <?= (int) $review['helpful'] ?></button>
    </form>
    <?php if ($hasReply): ?>
        <div class="pv-reply"><b>Ответ магазина «ВОЛЬТ»</b><?= e($review['store_reply']) ?></div>
    <?php endif; ?>
</article>
