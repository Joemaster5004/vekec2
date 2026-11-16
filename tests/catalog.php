<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
use App\{Catalog,DB};
use function App\env;

if (PHP_SAPI !== 'cli' || env('APP_ENV') !== 'test' || env('DB_DATABASE') !== 'vekec_test') {
    fwrite(STDERR, "Isolated test environment required.\n");
    exit(1);
}

$checks = 0;
$check = static function (bool $condition, string $label) use (&$checks): void {
    if (!$condition) throw new RuntimeException($label);
    $checks++;
    echo "PASS $label\n";
};
$ids = static fn(array $rows): array => array_map(static fn(array $row): int => (int)$row['id'], $rows);
$db = DB::connection();
$db->begin_transaction();
try {
    // Existing test courses are hidden only in this transaction; the final rollback restores them.
    DB::run("UPDATE courses SET status='draft' WHERE status='published'");
    $check(Catalog::featured() === ['latestCourses' => [], 'popularCourses' => []], 'empty published catalog returns two empty lists');

    $suffix = bin2hex(random_bytes(6));
    $teacherName = 'Catalog teacher ' . $suffix;
    DB::run("INSERT INTO users(name,email,role) VALUES(?,?,'teacher')", [$teacherName, 'catalog-teacher-' . $suffix . '@example.invalid']);
    $teacherId = DB::id();
    $category = 'Catalog category ' . $suffix;
    DB::run('INSERT INTO categories(name) VALUES(?)', [$category]);
    $course = static function (string $code, string $date, string $status = 'published') use ($teacherId, $category, $suffix): int {
        DB::run('INSERT INTO courses(teacher_id,title,code,description,category,status,created_at) VALUES(?,?,?,?,?,?,?)',
            [$teacherId, 'Catalog course ' . $code, 'CAT-' . $suffix . '-' . $code, 'Catalog test course', $category, $status, $date]);
        return DB::id();
    };
    $a = $course('A', '2030-01-01 10:00:00');
    $b = $course('B', '2030-01-02 10:00:00');
    foreach ([[$a,1],[$a,2],[$b,1]] as [$courseId,$position]) {
        DB::run("INSERT INTO lessons(course_id,title,kind,position) VALUES(?,?,'article',?)", [$courseId, 'Catalog lesson ' . $position, $position]);
    }
    $featured = Catalog::featured();
    $check($ids($featured['latestCourses']) === [$b,$a] && $ids($featured['popularCourses']) === [$b,$a], 'fewer than three published courses remain visible in both lists');
    $check(array_map(static fn(array $row): int => (int)$row['enrollment_count'], $featured['popularCourses']) === [0,0], 'popular courses include zero-enrollment courses');
    $check($featured['latestCourses'][0]['teacher_name'] === $teacherName && (int)$featured['latestCourses'][0]['lesson_count'] === 1 && (int)$featured['popularCourses'][1]['lesson_count'] === 2, 'featured rows provide teacher names and correct lesson counts');

    $c = $course('C', '2030-01-03 10:00:00');
    $d = $course('D', '2030-01-03 10:00:00');
    $draft = $course('DRAFT', '2031-01-01 10:00:00', 'draft');
    $archived = $course('ARCHIVED', '2031-01-02 10:00:00', 'archived');
    $students = [];
    for ($i = 0; $i < 5; $i++) {
        DB::run("INSERT INTO users(name,email,role) VALUES(?,?,'student')", ['Catalog student ' . $i, 'catalog-student-' . $suffix . '-' . $i . '@example.invalid']);
        $students[] = DB::id();
    }
    foreach ([$a=>4,$b=>2,$c=>2,$d=>2,$draft=>5,$archived=>5] as $courseId => $count) {
        foreach (array_slice($students, 0, $count) as $studentId) DB::run('INSERT INTO enrollments(user_id,course_id) VALUES(?,?)', [$studentId,$courseId]);
        if ($courseId === $a) {
            $mixed = Catalog::featured()['popularCourses'];
            $check($ids($mixed) === [$a,$d,$c] && (int)$mixed[1]['enrollment_count'] === 0 && (int)$mixed[2]['enrollment_count'] === 0, 'popular list fills remaining places with zero-enrollment published courses');
        }
    }
    $featured = Catalog::featured();
    $check(count($featured['latestCourses']) === 3 && count($featured['popularCourses']) === 3, 'featured lists contain at most three published courses');
    $check($ids($featured['latestCourses']) === [$d,$c,$b], 'latest courses sort by creation time then descending id');
    $check($ids($featured['popularCourses']) === [$a,$d,$c], 'popular courses sort by enrollment count with creation-time and id tie breaks');
    $check(array_map(static fn(array $row): int => (int)$row['enrollment_count'], $featured['popularCourses']) === [4,2,2], 'popular enrollment totals are accurate without lesson-join multiplication');
    $check(!array_intersect([$draft,$archived], array_merge($ids($featured['latestCourses']), $ids($featured['popularCourses']))), 'newer draft and archived courses with more enrollments are excluded');
    $check(count(array_filter(array_merge($featured['latestCourses'],$featured['popularCourses']), static fn(array $row): bool => $row['status'] !== 'published')) === 0, 'every featured course is published');

    DB::run('INSERT IGNORE INTO enrollments(user_id,course_id) VALUES(?,?)', [$students[0],$a]);
    $check((int)Catalog::featured()['popularCourses'][0]['enrollment_count'] === 4, 'duplicate enrollment attempts do not inflate popularity');
    DB::run('INSERT INTO enrollments(user_id,course_id) VALUES(?,?)', [$students[4],$a]);
    $updated = Catalog::featured();
    $check($ids($updated['latestCourses']) === $ids($featured['latestCourses']) && (int)$updated['popularCourses'][0]['enrollment_count'] === 5, 'new enrollments update popularity without changing latest-course order');
} finally {
    $db->rollback();
}
echo "All $checks catalog checks passed; fixtures rolled back.\n";
