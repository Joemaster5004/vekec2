<?php
declare(strict_types=1);
namespace App;

final class Homepage {
    public const SECTIONS = [
        'department_videos'=>'วิดีโอแนะนำแผนกวิชา',
        'latest_courses'=>'รายวิชาใหม่ล่าสุด',
        'popular_courses'=>'รายวิชาที่มีผู้สนใจเรียนมากที่สุด',
        'course_catalog'=>'ค้นพบรายวิชาที่ใช่สำหรับคุณ',
    ];

    public static function sections(): array {
        $saved=json_decode(setting('homepage_sections','{}'),true);
        $sections=[];
        foreach(self::SECTIONS as $key=>$label)$sections[$key]=is_array($saved)&&is_bool($saved[$key]??null)?$saved[$key]:true;
        return $sections;
    }

    public static function save(): void {
        Auth::require(['admin']);
        $sections=[];
        foreach(self::SECTIONS as $key=>$label){
            if(isset($_POST[$key])&&$_POST[$key]!=='1')abort(422,'กรุณาตรวจสอบการตั้งค่าการแสดงผล');
            $sections[$key]=isset($_POST[$key]);
        }
        DB::transaction(function()use($sections){
            DB::run('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',['homepage_sections',json_encode($sections,JSON_THROW_ON_ERROR)]);
            audit('homepage.sections_saved',json_encode($sections,JSON_THROW_ON_ERROR));
        });
    }
}
