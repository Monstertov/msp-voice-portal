<?php
// Colours from config.php ('colors', plus the older 'primary_color' / 'primary_hover' keys),
// turned into the CSS variables style.css uses. Shared by the portal and the admin page.

function theme_colors($config) {
    $defaults = [
        'primary'       => '#7289da', // buttons, links, accents
        'primary_hover' => '#5b6eae',
        'background'    => '#141414', // page background
        'card'          => '#2d2d2d', // cards and panels
        'field'         => '#1a1a1a', // input fields and dark areas
        'text'          => '#f8f9fa',
        'heading'       => '#ffffff',
        'muted'         => '#b9bbbe', // labels and secondary text
        'border'        => '#404040',
        'focus'         => '#0d6efd', // outline of the text field being typed in
        'danger'        => '#ed4245', // stop/delete buttons and errors
        'danger_hover'  => '#c03537',
    ];
    $legacy = array_filter([
        'primary' => $config['primary_color'] ?? null,
        'primary_hover' => $config['primary_hover'] ?? null,
    ]);
    $colors = array_intersect_key(($config['colors'] ?? []) + $legacy + $defaults, $defaults);
    // Only plain colour values end up in the page (#hex, a colour name, rgb()/hsl())
    foreach ($colors as $name => $value) {
        if (!is_string($value) || !preg_match('/^(#[0-9a-fA-F]{3,8}|[a-zA-Z]+|(rgb|hsl)a?\([0-9.,%\s\/a-z]+\))$/', $value)) {
            $colors[$name] = $defaults[$name];
        }
    }
    return $colors;
}

// Value for a style="" attribute on <body>
function theme_css($config) {
    $c = theme_colors($config);
    $vars = [
        '--primary-color' => $c['primary'],
        '--primary-hover' => $c['primary_hover'],
        '--bs-darker'     => $c['background'],
        '--bg-card'       => $c['card'],
        '--bg-dark'       => $c['field'],
        '--bs-dark'       => $c['field'],
        '--bs-light'      => $c['text'],
        '--text-primary'  => $c['heading'],
        '--text-secondary' => $c['muted'],
        '--border-color'  => $c['border'],
        '--bs-primary'    => $c['focus'],
        '--danger-color'  => $c['danger'],
        '--danger-hover'  => $c['danger_hover'],
    ];
    $css = '';
    foreach ($vars as $var => $value) {
        $css .= "$var: $value; ";
    }
    return htmlspecialchars(trim($css), ENT_QUOTES, 'UTF-8');
}
