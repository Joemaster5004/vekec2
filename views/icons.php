<?php
function icon(string $name,string $class=''): string {
    $paths=[
      'school'=>'<path d="m3 9 9-6 9 6-9 6-9-6Z"/><path d="M7 12v6c3 2 7 2 10 0v-6M21 9v7"/>',
      'compass'=>'<circle cx="12" cy="12" r="9"/><path d="m16 8-3 5-5 3 3-5 5-3Z"/>',
      'book'=>'<path d="M12 6c-3-2-6-2-10-1v14c4-1 7-1 10 1 3-2 6-2 10-1V5c-4-1-7-1-10 1v14"/>',
      'award'=>'<circle cx="12" cy="8" r="5"/><path d="m8 12-2 9 6-3 6 3-2-9"/>',
      'layers'=>'<path d="m3 7 9-5 9 5-9 5-9-5ZM3 12l9 5 9-5M3 17l9 5 9-5"/>',
      'user'=>'<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>',
      'users'=>'<path d="M16 21v-2a6 6 0 0 0-12 0v2M20 21v-2a6 6 0 0 0-3-5"/><circle cx="10" cy="7" r="4"/><path d="M17 3a4 4 0 0 1 0 8"/>',
      'settings'=>'<circle cx="12" cy="12" r="3"/><path d="m9 3-1 3-3 1v3l-2 2 2 2v3l3 1 1 3h6l1-3 3-1v-3l2-2-2-2V7l-3-1-1-3H9Z"/>',
      'spark'=>'<path d="m12 3 2.5 6.5L21 12l-6.5 2.5L12 21l-2.5-6.5L3 12l6.5-2.5L12 3Z"/>',
      'arrow'=>'<path d="M4 12h16m-6-6 6 6-6 6"/>',
      'back'=>'<path d="M20 12H4m6 6-6-6 6-6"/>',
      'search'=>'<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>',
      'clock'=>'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
      'play'=>'<path d="m9 5 11 7-11 7V5Z"/>',
      'check'=>'<path d="m5 12 4 4L19 6"/>',
      'lock'=>'<rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
      'plus'=>'<path d="M12 5v14M5 12h14"/>',
      'menu'=>'<path d="M4 6h16M4 12h16M4 18h16"/>',
      'logout'=>'<path d="M9 3H4v18h5m6-14 5 5-5 5M9 12h11"/>',
      'file'=>'<path d="M14 2H5v20h14V7l-5-5v5h5M8 12h8M8 16h8"/>',
      'code'=>'<path d="m8 6-6 6 6 6m8-12 6 6-6 6m-3-15-2 18"/>',
      'download'=>'<path d="M12 3v12m-5-5 5 5 5-5M4 16v5h16v-5"/>',
      'shield'=>'<path d="m12 2 9 4v6c0 5-9 10-9 10S3 17 3 12V6l9-4Z"/><path d="m8 12 3 3 5-6"/>',
      'chart'=>'<path d="M3 3v18h18M7 16v-4m5 4V7m5 9v-6"/>',
      'chevron'=>'<path d="m9 5 7 7-7 7"/>',
      'mail'=>'<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 6 9 7 9-7"/>'
    ];
    return '<svg class="icon '.htmlspecialchars($class,ENT_QUOTES).'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name]??$paths['book']).'</svg>';
}
