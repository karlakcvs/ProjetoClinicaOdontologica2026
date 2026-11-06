<?php
// Gera a imagem do código "não sou um robô" (SVG desenhado, com distorção e ruído).
require_once __DIR__ . '/include/config.php';

$form = preg_replace('/[^a-z_]/', '', $_GET['f'] ?? '');
if ($form === '' || strlen($form) > 30) { http_response_code(400); exit; }
$cod = captcha_novo($form);

header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$w = 170; $h = 56;
$cores = ['#0b3a75', '#0e8c89', '#06244b', '#3a5fb0', '#0b6f6d'];
$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $w . '" height="' . $h . '" viewBox="0 0 ' . $w . ' ' . $h . '">';
$svg .= '<rect width="100%" height="100%" rx="10" fill="#eef6f6"/>';
// ruído de fundo
for ($i = 0; $i < 22; $i++) {
    $svg .= sprintf('<circle cx="%d" cy="%d" r="%d" fill="%s" opacity=".18"/>', random_int(0, $w), random_int(0, $h), random_int(1, 4), $cores[array_rand($cores)]);
}
for ($i = 0; $i < 4; $i++) {
    $svg .= sprintf('<path d="M%d %d Q%d %d %d %d" stroke="%s" stroke-width="%.1f" fill="none" opacity=".45"/>',
        random_int(-10, 20), random_int(5, $h - 5), random_int(40, 130), random_int(-20, $h + 20), random_int($w - 20, $w + 10), random_int(5, $h - 5),
        $cores[array_rand($cores)], random_int(10, 22) / 10);
}
// caracteres, cada um com rotação, tamanho e deslocamento próprios
$fontes = ['Georgia, serif', 'Verdana, sans-serif', 'Courier New, monospace', 'Trebuchet MS, sans-serif'];
for ($i = 0; $i < strlen($cod); $i++) {
    $x = 18 + $i * 30 + random_int(-3, 3);
    $y = 38 + random_int(-6, 6);
    $svg .= sprintf('<text x="%d" y="%d" font-family="%s" font-size="%d" font-weight="bold" fill="%s" transform="rotate(%d %d %d) skewX(%d)">%s</text>',
        $x, $y, $fontes[array_rand($fontes)], random_int(26, 32), $cores[array_rand($cores)], random_int(-22, 22), $x, $y, random_int(-12, 12), $cod[$i]);
}
// linha cortando os caracteres
$svg .= sprintf('<path d="M5 %d C 50 %d, 110 %d, %d %d" stroke="#0b3a75" stroke-width="1.6" fill="none" opacity=".55"/>',
    random_int(20, 40), random_int(0, $h), random_int(0, $h), $w - 5, random_int(20, 40));
$svg .= '</svg>';
echo $svg;
