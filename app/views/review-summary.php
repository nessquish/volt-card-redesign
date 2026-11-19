<?php
/** Сводка по отзывам: ожидает $summary (см. reviews_summary()). */
$maxCount = max(1, max($summary['by']));
?>
<div class="pv-big">
    <strong><?= str_replace('.', ',', (string) $summary['avg']) ?></strong>
    <div><?= stars_html((int) round($summary['avg'])) ?><span><?= $summary['count'] ?> <?= plural_ru($summary['count'], 'отзыв', 'отзыва', 'отзывов') ?></span></div>
</div>
<div class="pv-bars">
    <?php foreach ([5, 4, 3, 2, 1] as $stars): ?>
        <?php $c = (int) $summary['by'][$stars]; ?>
        <div><span><?= $stars ?> ★</span><i><u style="width: <?= (int) round($c / $summary['count'] * 100) ?>%"></u></i><b><?= $c ?></b></div>
    <?php endforeach; ?>
</div>
<div class="pv-rec"><?= $summary['recommend'] ?>% покупателей рекомендуют</div>
