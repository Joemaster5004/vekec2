<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
use App\Pagination;

$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) throw new RuntimeException($message);
    $checks++;
};
$_SERVER['REQUEST_URI'] = '/admin/categories';
$_GET = [];
$pagination = new Pagination(0);
$check([$pagination->page, $pagination->pages, $pagination->perPage, $pagination->offset, $pagination->start(), $pagination->end()] === [1, 1, 10, 0, 0, 0], 'Empty pagination bounds');
$check((new Pagination(-7))->total === 0, 'Negative totals are empty');
foreach ([1 => 1, 10 => 1, 11 => 2, 20 => 2, 21 => 3] as $total => $pages) {
    $check((new Pagination($total))->pages === $pages, 'Page count for total ' . $total);
}
$_GET = ['page' => '999', 'per_page' => '25'];
$pagination = new Pagination(56);
$check([$pagination->page, $pagination->pages, $pagination->offset, $pagination->start(), $pagination->end()] === [3, 3, 50, 51, 56], 'Out-of-range pages clamp to last page');
$check($pagination->url(0) === '/admin/categories?page=1&per_page=25', 'Generated page bounds clamp low');
$check($pagination->url(999) === '/admin/categories?page=3&per_page=25', 'Generated page bounds clamp high');
foreach (['0', '-2', 'x', '2.5', '1e2', '', ['2'], true, 2.5, str_repeat('9', 100)] as $invalid) {
    $_GET = ['page' => $invalid, 'per_page' => $invalid];
    $pagination = new Pagination(100);
    $check($pagination->page === 1 && $pagination->perPage === 10, 'Malformed page and size fall back safely: ' . json_encode($invalid));
}
foreach ([10, 25, 50] as $size) {
    $_GET = ['page' => '2', 'per_page' => (string)$size];
    $pagination = new Pagination(100);
    $check($pagination->perPage === $size && $pagination->offset === $size, 'Allowed size ' . $size);
}
$_GET = ['per_page' => '100'];
$check((new Pagination(100))->perPage === 10, 'Unsupported size falls back');
$_GET = [];
$check((new Pagination(100, 'page', 'per_page', 25))->perPage === 25, 'Supported default size');
$check((new Pagination(100, 'page', 'per_page', 500))->perPage === 10, 'Unsupported default size');
$_GET = ['page' => (string)PHP_INT_MAX, 'per_page' => '50'];
$pagination = new Pagination(PHP_INT_MAX);
$check($pagination->end() === PHP_INT_MAX && $pagination->offset <= PHP_INT_MAX, 'Large totals avoid integer overflow');
$_SERVER['REQUEST_URI'] = '/teacher/courses/12/students?ignored=original';
$_GET = ['q' => 'ช่าง & <script>"', 'filter' => ['levels' => ['voc1', 'voc2']], 'students_page' => '2', 'students_per_page' => '25', 'submissions_page' => '4', 'submissions_per_page' => '50'];
$pagination = new Pagination(200, 'students_page', 'students_per_page');
$url = $pagination->url(3);
parse_str((string)parse_url($url, PHP_URL_QUERY), $query);
$check(parse_url($url, PHP_URL_PATH) === '/teacher/courses/12/students', 'Current path is retained');
$expected = $_GET;
$expected['students_page'] = '3';
$check($query === $expected, 'Other table state and nested filters are retained');
$check(str_contains($url, '%26') && str_contains($url, '%3Cscript%3E%22') && !str_contains($url, ' '), 'Query values are RFC 3986 encoded');
ob_start();
require dirname(__DIR__) . '/views/pagination.php';
$html = ob_get_clean();
$check(str_contains($html, 'name="students_page" value="1"'), 'Size changes reset only the current page');
$check(str_contains($html, 'name="submissions_page" value="4"'), 'Size form retains another table page');
$check(str_contains($html, 'name="filter[levels][0]" value="voc1"'), 'Size form retains nested filters');
$check(str_contains($html, '&lt;script&gt;&quot;') && !str_contains($html, '<script>'), 'HTML query values are escaped');
$check(str_contains($html, 'aria-current="page"') && str_contains($html, 'aria-label="แบ่งหน้ารายการ students_page"'), 'Navigation has current-page and unique accessible labels');
$check(str_contains($html, 'pagination-ellipsis'), 'Distant pages collapse into an ellipsis');
$_GET = ['page' => '50000'];
$pagination = new Pagination(1000000);
ob_start();
require dirname(__DIR__) . '/views/pagination.php';
$html = ob_get_clean();
$check(substr_count($html, '<a ') <= 8, 'Large pagination renders a bounded page window');
foreach (['https://external.invalid/admin?x=1' => '/admin', '//external.invalid/admin' => '/admin', '/\\external.invalid/admin' => '/external.invalid/admin', '/' => '/', '' => '/'] as $requestUri => $path) {
    $_SERVER['REQUEST_URI'] = $requestUri;
    $pagination = new Pagination(100);
    $check($pagination->path() === $path && !str_starts_with($pagination->url(1), '//'), 'Generated links remain local: ' . $requestUri);
}
$_SERVER['REQUEST_URI'] = ['invalid'];
$check((new Pagination(100))->path() === '/', 'Invalid request URI type falls back safely');
echo "Pagination checks passed: $checks\n";
