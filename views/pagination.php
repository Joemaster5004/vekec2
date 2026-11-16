<?php
use function App\e;
/** @var App\Pagination $pagination */
$pageNumbers = [1, $pagination->pages];
for ($pageNumber = max(1, $pagination->page - 2); $pageNumber <= min($pagination->pages, $pagination->page + 2); $pageNumber++) {
    $pageNumbers[] = $pageNumber;
}
$pageNumbers = array_values(array_unique($pageNumbers));
sort($pageNumbers);
$hiddenFields = static function (string $name, mixed $value) use (&$hiddenFields): void {
    if (is_array($value)) {
        foreach ($value as $key => $item) $hiddenFields($name . '[' . $key . ']', $item);
    } elseif (is_scalar($value)) {
        echo '<input type="hidden" name="' . e($name) . '" value="' . e($value) . '">';
    }
};
?>
<nav class="pagination" aria-label="<?= e('แบ่งหน้ารายการ ' . $pagination->pageKey) ?>">
    <p class="pagination-summary">แสดง <?= e(number_format($pagination->start())) ?>–<?= e(number_format($pagination->end())) ?> จาก <?= e(number_format($pagination->total)) ?> รายการ</p>
    <div class="pagination-controls">
        <div class="pagination-pages">
            <?php if ($pagination->page > 1): ?>
                <a class="pagination-link" href="<?= e($pagination->url($pagination->page - 1)) ?>" rel="prev" aria-label="หน้าก่อนหน้า">ก่อนหน้า</a>
            <?php else: ?>
                <span class="pagination-link pagination-disabled" aria-disabled="true">ก่อนหน้า</span>
            <?php endif; ?>
            <?php $previousPage = 0; foreach ($pageNumbers as $pageNumber): ?>
                <?php if ($previousPage && $pageNumber > $previousPage + 1): ?><span class="pagination-ellipsis" aria-hidden="true">…</span><?php endif; ?>
                <?php if ($pageNumber === $pagination->page): ?>
                    <span class="pagination-link pagination-current" aria-current="page" aria-label="<?= e('หน้า ' . $pageNumber) ?>"><?= e(number_format($pageNumber)) ?></span>
                <?php else: ?>
                    <a class="pagination-link" href="<?= e($pagination->url($pageNumber)) ?>" aria-label="<?= e('หน้า ' . $pageNumber) ?>"><?= e(number_format($pageNumber)) ?></a>
                <?php endif; ?>
            <?php $previousPage = $pageNumber; endforeach; ?>
            <?php if ($pagination->page < $pagination->pages): ?>
                <a class="pagination-link" href="<?= e($pagination->url($pagination->page + 1)) ?>" rel="next" aria-label="หน้าถัดไป">ถัดไป</a>
            <?php else: ?>
                <span class="pagination-link pagination-disabled" aria-disabled="true">ถัดไป</span>
            <?php endif; ?>
        </div>
        <form class="pagination-size" method="get" action="<?= e($pagination->path()) ?>">
            <?php foreach ($pagination->query() as $key => $value): ?>
                <?php if ($key !== $pagination->pageKey && $key !== $pagination->sizeKey) $hiddenFields((string)$key, $value); ?>
            <?php endforeach; ?>
            <input type="hidden" name="<?= e($pagination->pageKey) ?>" value="1">
            <label for="<?= e('pagination-size-' . $pagination->pageKey) ?>">ต่อหน้า</label>
            <select id="<?= e('pagination-size-' . $pagination->pageKey) ?>" name="<?= e($pagination->sizeKey) ?>">
                <?php foreach ([10, 25, 50] as $size): ?><option value="<?= $size ?>" <?= $pagination->perPage === $size ? 'selected' : '' ?>><?= $size ?></option><?php endforeach; ?>
            </select>
            <button class="button outline small" type="submit">แสดง</button>
        </form>
    </div>
</nav>
