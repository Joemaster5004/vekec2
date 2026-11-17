<?php
declare(strict_types=1);
namespace App;

final class Category {
    public static function all(?Pagination $pagination = null): array {
        $sql = 'SELECT c.id,c.name,
            (SELECT COUNT(*) FROM courses WHERE category=c.name) AS course_count
            FROM categories c ORDER BY c.name,c.id';
        return $pagination
            ? DB::all($sql.' LIMIT ? OFFSET ?', [$pagination->perPage, $pagination->offset])
            : DB::all($sql);
    }

    public static function options(): array {
        return DB::all('SELECT id,name FROM categories ORDER BY name,id');
    }

    public static function selectedName(string $name): string {
        // Keep the selected category available until the enclosing course transaction commits.
        $category = DB::one('SELECT name FROM categories WHERE name=? LOCK IN SHARE MODE', [$name]);
        if (!$category) abort(422, 'กรุณาเลือกหมวดหมู่ที่มีอยู่ในระบบ หากรายการเปลี่ยนไปให้โหลดหน้าใหม่');
        return $category['name'];
    }

    public static function save(?int $id = null): int {
        Auth::require(['admin']);
        $name = preg_replace('/^[\p{Z}\s]+|[\p{Z}\s]+$/u', '', input('name', 400));
        if ($name === '' || preg_match_all('/./us', $name) > 100) {
            abort(422, 'กรุณากรอกชื่อหมวดหมู่ 1–100 ตัวอักษร');
        }
        try {
            return DB::transaction(function () use ($id, $name): int {
                if ($id !== null) {
                    if (!DB::one('SELECT id FROM categories WHERE id=? FOR UPDATE', [$id])) abort(404, 'ไม่พบหมวดหมู่');
                    // The FK cascades a rename to all draft, published and archived courses.
                    DB::run('UPDATE categories SET name=? WHERE id=?', [$name, $id]);
                    audit('category.updated', (string)$id);
                } else {
                    DB::run('INSERT INTO categories(name) VALUES(?)', [$name]);
                    $id = DB::id();
                    audit('category.created', (string)$id);
                }
                return $id;
            });
        } catch (\mysqli_sql_exception $error) {
            if ($error->getCode() === 1062) abort(422, 'มีหมวดหมู่ชื่อนี้อยู่แล้ว กรุณาใช้ชื่ออื่น');
            throw $error;
        }
    }

    public static function delete(int $id, int $replacementId = 0): int {
        Auth::require(['admin']);
        if ($replacementId === $id) abort(422, 'กรุณาเลือกหมวดหมู่ปลายทางที่ต่างจากหมวดหมู่ที่จะลบ');
        return DB::transaction(function () use ($id, $replacementId): int {
            // Lock both parents in a consistent order before moving any course.
            $rows = DB::all('SELECT id,name FROM categories WHERE id IN (?,?) ORDER BY id FOR UPDATE', [$id, $replacementId]);
            $categories = array_column($rows, null, 'id');
            $source = $categories[$id] ?? abort(404, 'ไม่พบหมวดหมู่');
            if ($replacementId && !isset($categories[$replacementId])) abort(422, 'ไม่พบหมวดหมู่ปลายทาง กรุณาเลือกรายการใหม่');
            if(DB::one('SELECT id FROM department_videos WHERE category_id=? LIMIT 1',[$id])) abort(409, 'กรุณาย้ายวิดีโอแนะนำไปแผนกอื่นก่อนลบแผนกวิชานี้');
            $count = (int)DB::one('SELECT COUNT(*) AS total FROM courses WHERE category=?', [$source['name']])['total'];
            if ($count && !$replacementId) abort(409, 'หมวดหมู่นี้มีรายวิชาอยู่ กรุณาเลือกหมวดหมู่ปลายทางก่อนลบ');
            if ($count) {
                DB::run('UPDATE courses SET category=? WHERE category=?', [$categories[$replacementId]['name'], $source['name']]);
            }
            DB::run('DELETE FROM categories WHERE id=?', [$id]);
            audit('category.deleted', $id.':moved='.$count.':to='.$replacementId);
            return $count;
        });
    }
}
