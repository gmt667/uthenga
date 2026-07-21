<?php
/**
 * Shared SVG icon set for public-facing pages.
 */

if (!function_exists('uthenga_public_icon_svg')) {
    function uthenga_public_icon_svg(string $name): string {
        $icons = [
            'home' => '<path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z" fill="currentColor"/>',
            'search' => '<path d="M10.5 4a6.5 6.5 0 1 0 4.1 11.5l4.7 4.7 1.4-1.4-4.7-4.7A6.5 6.5 0 0 0 10.5 4Zm0 2a4.5 4.5 0 1 1 0 9 4.5 4.5 0 0 1 0-9Z" fill="currentColor"/>',
            'map' => '<path d="M15 3l-6 2.2L3 3v16l6 2.2L15 19l6 2V5zm-2 14.4-4-1.5V6.2l4 1.5v9.7z" fill="currentColor"/>',
            'pin' => '<path d="M12 22s6-5.3 6-11a6 6 0 1 0-12 0c0 5.7 6 11 6 11zm0-8.2A2.8 2.8 0 1 1 12 8.2a2.8 2.8 0 0 1 0 5.6z" fill="currentColor"/>',
            'calendar' => '<path d="M7 2v2H5a2 2 0 0 0-2 2v2h18V6a2 2 0 0 0-2-2h-2V2h-2v2H9V2zM3 12v8a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-8Z" fill="currentColor"/>',
            'ticket' => '<path d="M3 7a2 2 0 0 1 2-2h14v3a2 2 0 1 0 0 4v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-3a2 2 0 1 0 0-4z" fill="currentColor"/>',
            'wallet' => '<path d="M4 6a3 3 0 0 1 3-3h12v3H7a1 1 0 0 0 0 2h13v10H7a3 3 0 0 1-3-3zm14 6a1 1 0 1 0 0 2 1 1 0 0 0 0-2z" fill="currentColor"/>',
            'hotel' => '<path d="M4 21V3h10v18h-3v-3H7v3zm3-5h4v-2H7zm0-4h4v-2H7zm0-4h4V6H7zm12-2h-4v18h4V8h1V6a2 2 0 0 0-2-2z" fill="currentColor"/>',
            'bus' => '<path d="M4 16c0 .88.39 1.67 1 2.22V20a1 1 0 0 0 1 1h1a1 1 0 0 0 1-1v-1h8v1a1 1 0 0 0 1 1h1a1 1 0 0 0 1-1v-1.78A3 3 0 0 0 20 16V6c0-3.5-3.58-4-8-4S4 2.5 4 6v10zm3.5 1a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm9 0a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zM6 10V6h12v4H6z" fill="currentColor"/>',
            'car' => '<path d="M3 12.5 5 8h14l2 4.5V18h-2a2 2 0 1 1-4 0H9a2 2 0 1 1-4 0H3zM7 10 6 12h12l-1-2z" fill="currentColor"/>',
            'plane' => '<path d="M2 12l20-7-7 20-4-8-9-5z" fill="currentColor"/>',
            'restaurant' => '<path d="M8 2h2v20H8v-7H6V2h2zm8 0h2v20h-2v-8h-2V8a6 6 0 0 1 2-6z" fill="currentColor"/>',
            'camera' => '<path d="M9 4 7.5 6H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-3.5L15 4zM12 18a4 4 0 1 1 0-8 4 4 0 0 1 0 8z" fill="currentColor"/>',
            'shop' => '<path d="M3 7h18l-1 4H4L3 7zm2 6h14v7H5z" fill="currentColor"/>',
            'tour' => '<path d="M12 2 3.5 6.5v11L12 22l8.5-4.5v-11z" fill="currentColor"/><path d="M12 6.2 7.5 8.6v5l4.5 2.4 4.5-2.4v-5z" fill="currentColor" opacity="0.35"/>',
            'spa' => '<path d="M12 2c3.3 0 6 2.7 6 6 0 4-4 6.4-6 14-2-7.6-6-10-6-14 0-3.3 2.7-6 6-6zm0 8a2 2 0 1 0 0-4 2 2 0 0 0 0 4z" fill="currentColor"/>',
            'heart' => '<path d="M12 21s-7.5-4.5-9.5-9A5.7 5.7 0 0 1 12 5.5 5.7 5.7 0 0 1 21.5 12c-2 4.5-9.5 9-9.5 9z" fill="currentColor"/>',
            'share' => '<path d="M18 16a3 3 0 0 0-2.4 1.2L8.9 13.7a3.2 3.2 0 0 0 0-3.4l6.7-3.5a3 3 0 1 0-.9-1.8L8 8.5a3 3 0 1 0 0 7l6.7 3.5a3 3 0 1 0 3.3-3z" fill="currentColor"/>',
            'check' => '<path d="m9.2 16.2-4.1-4.1 1.4-1.4 2.7 2.7L17.5 5l1.4 1.4z" fill="currentColor"/>',
            'x' => '<path d="M6 6 18 18M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
            'megaphone' => '<path d="M4 13v-2a2 2 0 0 1 2-2h2l8-4v14l-8-4H6a2 2 0 0 1-2-2Zm14-5.5a4.5 4.5 0 0 1 0 9v-2a2.5 2.5 0 0 0 0-5z" fill="currentColor"/>',
            'warning' => '<path d="M12 3 1.8 20h20.4L12 3zm0 5.7 1 5.7h-2zM12 16.6a1.3 1.3 0 1 1 0 2.6 1.3 1.3 0 0 1 0-2.6z" fill="currentColor"/>',
            'globe' => '<path d="M12 2a10 10 0 1 0 0 20A10 10 0 0 0 12 2zm6.9 8h-3.2a16 16 0 0 0-1.2-4A8 8 0 0 1 18.9 10zM12 4c.7 1 1.5 2.7 2 6H10c.5-3.3 1.3-5 2-6zM5.1 14h3.2a16 16 0 0 0 1.2 4A8 8 0 0 1 5.1 14zm3.2-4H5.1a8 8 0 0 1 4.4-4c-.5 1.2-.9 2.5-1.2 4zm3.7 8c-.7-1-1.5-2.7-2-6h4c-.5 3.3-1.3 5-2 6zm3.7-4h3.2a8 8 0 0 1-4.4 4c.5-1.2.9-2.5 1.2-4z" fill="currentColor"/>',
            'mail' => '<path d="M4 6h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1zm0 2v.3l8 5.2 8-5.2V8z" fill="currentColor"/>',
            'phone' => '<path d="M6 3h4l2 5-2 2c1.4 2.8 3.5 4.9 6.3 6.3l2-2 5 2v4c0 1.1-.9 2-2 2C10 22 2 14 2 5c0-1.1.9-2 2-2z" fill="currentColor"/>',
            'lock' => '<path d="M8 10V8a4 4 0 0 1 8 0v2h1a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2Zm2 0h4V8a2 2 0 0 0-4 0Z" fill="currentColor"/>',
            'star' => '<path d="m12 2.5 2.9 5.9 6.5.9-4.7 4.6 1.1 6.5L12 17.2 6.2 20.4l1.1-6.5L2.6 9.3l6.5-.9z" fill="currentColor"/>',
            'sparkles' => '<path d="M12 2 13.8 8.2 20 10l-6.2 1.8L12 18l-1.8-6.2L4 10l6.2-1.8z" fill="currentColor"/>',
            'chevron-down' => '<path d="M6.7 9.2 12 14.5l5.3-5.3 1.4 1.4L12 17.3 5.3 10.6z" fill="currentColor"/>',
            'chevron-left' => '<path d="M14.5 6.7 9.2 12l5.3 5.3-1.4 1.4L6.4 12l6.7-6.7z" fill="currentColor"/>',
            'chevron-right' => '<path d="M9.5 6.7 14.8 12l-5.3 5.3 1.4 1.4 6.7-6.7-6.7-6.7z" fill="currentColor"/>',
            'info' => '<path d="M12 2a10 10 0 1 0 0 20A10 10 0 0 0 12 2zm0 4a1.3 1.3 0 1 1 0 2.6A1.3 1.3 0 0 1 12 6zm-1.1 4h2.2v8h-2.2z" fill="currentColor"/>',
            'news' => '<path d="M6 4h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2zm1 4h10V6H7zm0 4h10v-2H7zm0 4h7v-2H7z" fill="currentColor"/><circle cx="15.5" cy="10" r="1" fill="currentColor"/>',
        ];

        $path = $icons[$name] ?? $icons['info'];
        return '<svg class="public-icon public-icon-' . e($name) . '" viewBox="0 0 24 24" width="1em" height="1em" aria-hidden="true" focusable="false" style="display:inline-block;vertical-align:-0.125em;flex:none;">' . $path . '</svg>';
    }
}
