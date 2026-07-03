<?php
/**
 * includes/theme.php
 * Preset warna tema per brand (gold/silver/bronze) + override custom.
 */

const THEME_PRESETS = [
    'gold'   => ['primary' => '#C9A84C', 'charcoal' => '#1A1A1A', 'soft' => '#E8D5A3'],
    'silver' => ['primary' => '#B8C2CC', 'charcoal' => '#1A1A1A', 'soft' => '#E4E9ED'],
    'bronze' => ['primary' => '#A9682F', 'charcoal' => '#1A1A1A', 'soft' => '#D9A876'],
];

/** Hasilkan CSS custom-properties (:root{...}) untuk tema brand yang aktif. */
function get_theme_css_vars(array $brand): string {
    if ($brand['theme_preset'] === 'custom') {
        $primary  = $brand['theme_primary'] ?: THEME_PRESETS['gold']['primary'];
        $charcoal = $brand['theme_charcoal'] ?: THEME_PRESETS['gold']['charcoal'];
        $soft     = $brand['theme_soft'] ?: THEME_PRESETS['gold']['soft'];
    } else {
        $preset = THEME_PRESETS[$brand['theme_preset']] ?? THEME_PRESETS['gold'];
        [$primary, $charcoal, $soft] = [$preset['primary'], $preset['charcoal'], $preset['soft']];
    }

    return ":root{--brand-primary:{$primary};--brand-charcoal:{$charcoal};--brand-soft:{$soft};}";
}
