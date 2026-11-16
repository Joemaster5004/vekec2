<?php
declare(strict_types=1);
namespace App;

final class Catalog {
    public static function featured(): array {
        $latestCourses = DB::all("SELECT c.*,u.name AS teacher_name,
            (SELECT COUNT(*) FROM lessons l WHERE l.course_id=c.id) AS lesson_count
            FROM courses c JOIN users u ON u.id=c.teacher_id
            WHERE c.status='published'
            ORDER BY c.created_at DESC,c.id DESC LIMIT 3");
        $popularCourses = DB::all("SELECT c.*,u.name AS teacher_name,
            (SELECT COUNT(*) FROM lessons l WHERE l.course_id=c.id) AS lesson_count,
            COALESCE(e.enrollment_count,0) AS enrollment_count
            FROM courses c JOIN users u ON u.id=c.teacher_id
            LEFT JOIN (SELECT course_id,COUNT(*) AS enrollment_count FROM enrollments GROUP BY course_id) e ON e.course_id=c.id
            WHERE c.status='published'
            ORDER BY enrollment_count DESC,c.created_at DESC,c.id DESC LIMIT 3");
        return ['latestCourses' => $latestCourses, 'popularCourses' => $popularCourses];
    }
}
