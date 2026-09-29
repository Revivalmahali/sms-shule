<?php
/**
 * includes/images.php
 * RAMANI YA PICHA ZA MFUMO (assets/img/)
 *
 * Majina ya faili yana nafasi na alama maalum (#, é, ') — hayafai kwenye URL
 * moja kwa moja. Helper img_url() inaya-encode salama.
 *
 * >> KUBADILISHA PICHA: badilisha jina la faili hapa chini TU —
 *    pages zote (landing, login, register, dashibodi) zitafuata. <<
 */
require_once __DIR__ . '/../config/db.php';   // db.php iko kwenye config/, si includes/

function sms_images(): array {
    static $map = null;
    if ($map === null) {
        $map = [
            /* Picha kubwa ya nyuma kwenye landing page (hero) */
            'hero' => 'The beauty of Education! #gracelandschoolsenugu….jpg',

            /* Picha za kando kwenye login na register (split-screen) */
            'login_side'    => '334955291057143405.jpg',
            'register_side' => '20 Adorable School Protective Styles for Black Girls to Rock.jpg',

            /* Picha ndogo ya mviringo kwenye hero + welcome banner ya dashibodi */
            'cutout' => 'Smiling African American Student With Books And Backpack On A Transparent Background.jpg',
            'banner' => '337418197093768015.jpg',

            /* Gallery ya "Maisha Shuleni" kwenye landing page */
            'gallery' => [
                ['src' => '20 Adorable School Protective Styles for Black Girls to Rock.jpg', 'caption' => 'Furaha ya kuwa mwanafunzi'],
                ['src' => '48202658506624573.jpg',  'caption' => 'Shughuli za shule'],
                ['src' => 'After Study abroad _ Abroad101 Blog.jpg', 'caption' => 'Elimu na mafanikio'],
                ['src' => 'Mixité sociale à l’école _ les établissements privés signent un protocole avec le ministère.jpg', 'caption' => 'Jamii ya shule'],
                ['src' => 'Top 10 South African youth policy proposals that could spark change.jpg', 'caption' => 'Nguvu ya vijana'],
                ['src' => '334955291057143405.jpg', 'caption' => 'Siku za masomo'],
            ],
        ];
    }
    return $map;
}

/** URL salama ya faili lolote ndani ya assets/img/ */
function img_file_url(string $filename): string {
    return url('/assets/img/' . rawurlencode($filename));
}

/** URL ya picha kwa jina la key (mf: img_url('hero')) */
function img_url(string $key): string {
    $file = sms_images()[$key] ?? null;
    return is_string($file) ? img_file_url($file) : '';
}
