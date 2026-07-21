<?php
/**
 * Shared official brand icon set for social sign-in buttons.
 */

if (!function_exists('uthenga_brand_icon_svg')) {
    function uthenga_brand_icon_svg(string $name): string {
        $icons = [
            'google' => '<path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9.1 3.2l6.8-6.8C35.7 2.2 30.2 0 24 0 14.7 0 6.7 5.5 2.9 13.4l7.9 6.1C12.5 13.1 17.8 9.5 24 9.5z"/><path fill="#4285F4" d="M46.5 24.5c0-1.6-.1-3.1-.4-4.5H24v8.5h12.7c-.6 3-2.3 5.5-4.9 7.2l7.6 5.9C43.7 37.5 46.5 31.4 46.5 24.5z"/><path fill="#FBBC05" d="M10.8 28.5A14.5 14.5 0 0 1 9.5 24c0-1.6.3-3.1.8-4.5L2.4 13.4A23.9 23.9 0 0 0 0 24c0 3.8.9 7.4 2.4 10.6l8.4-6.1z"/><path fill="#34A853" d="M24 48c6.2 0 11.4-2 15.2-5.5l-7.6-5.9c-2 1.4-4.6 2.2-7.6 2.2-6.2 0-11.5-4.2-13.4-9.8l-8.4 6.1C6.7 42.5 14.7 48 24 48z"/>',
            'facebook' => '<path d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047v-2.66c0-3.025 1.791-4.697 4.533-4.697 1.312 0 2.686.236 2.686.236v2.97h-1.513c-1.491 0-1.956.93-1.956 1.883v2.268h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z" fill="currentColor"/>',
            'microsoft' => '<rect x="1" y="1" width="9" height="9" fill="#f25022"/><rect x="12" y="1" width="9" height="9" fill="#7fba00"/><rect x="1" y="12" width="9" height="9" fill="#00a4ef"/><rect x="12" y="12" width="9" height="9" fill="#ffb900"/>',
        ];

        $path = $icons[$name] ?? $icons['google'];
        $viewBox = $name === 'microsoft' ? '0 0 23 23' : ($name === 'google' ? '0 0 48 48' : '0 0 24 24');
        return '<svg class="oauth-brand-icon oauth-brand-' . e($name) . '" viewBox="' . $viewBox . '" aria-hidden="true" focusable="false">' . $path . '</svg>';
    }
}
