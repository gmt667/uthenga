<?php
/**
 * Shared SVG icon set for admin dashboards.
 */

if (!function_exists('admin_icon_svg')) {
    function admin_icon_svg(string $name): string {
        $icons = [
            'brand' => '<path d="M12 2 3.5 6.5v11L12 22l8.5-4.5v-11z" fill="currentColor"/><path d="M12 6.2 7.5 8.6v5l4.5 2.4 4.5-2.4v-5z" fill="currentColor" opacity="0.35"/>',
            'menu' => '<path d="M4 7h16v2H4zm0 4.5h16v2H4zm0 4.5h16v2H4z" fill="currentColor"/>',
            'search' => '<path d="M10.5 4a6.5 6.5 0 1 0 4.1 11.5l4.7 4.7 1.4-1.4-4.7-4.7A6.5 6.5 0 0 0 10.5 4Zm0 2a4.5 4.5 0 1 1 0 9 4.5 4.5 0 0 1 0-9Z" fill="currentColor"/>',
            'bell' => '<path d="M12 22a2.5 2.5 0 0 0 2.45-2h-4.9A2.5 2.5 0 0 0 12 22Zm7-6V11a7 7 0 1 0-14 0v5l-2 2v1h18v-1l-2-2Z" fill="currentColor"/>',
            'sun' => '<path d="M12 4V2m0 20v-2m8-8h2M2 12h2m14.25-6.25 1.4-1.4M4.35 19.65l1.4-1.4m0-12.5-1.4-1.4m15.3 15.3-1.4-1.4M12 8a4 4 0 1 1 0 8 4 4 0 0 1 0-8Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
            'moon' => '<path d="M20 14.6A8.5 8.5 0 0 1 9.4 4a8.5 8.5 0 1 0 10.6 10.6Z" fill="currentColor"/>',
            'user' => '<path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm0 2c-4 0-7 2.2-7 5v1h14v-1c0-2.8-3-5-7-5Z" fill="currentColor"/>',
            'users' => '<path d="M9 11a4 4 0 1 1 0-8 4 4 0 0 1 0 8Zm8 1a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM3 21v-2a5 5 0 0 1 5-5h1a7 7 0 0 0 7 0h1a5 5 0 0 1 5 5v2Z" fill="currentColor"/>',
            'store' => '<path d="M3 7h18l-1 4H4L3 7Zm2 6h14v7H5z" fill="currentColor"/>',
            'calendar' => '<path d="M7 2v2H5a2 2 0 0 0-2 2v2h18V6a2 2 0 0 0-2-2h-2V2h-2v2H9V2zM3 12v8a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-8Z" fill="currentColor"/>',
            'chart' => '<path d="M5 19V9h3v10H5Zm5 0V5h3v14h-3Zm5 0v-7h3v7h-3Z" fill="currentColor"/>',
            'wallet' => '<path d="M4 6a3 3 0 0 1 3-3h12v3H7a1 1 0 0 0 0 2h13v10H7a3 3 0 0 1-3-3zm14 6a1 1 0 1 0 0 2 1 1 0 0 0 0-2z" fill="currentColor"/>',
            'shield' => '<path d="M12 2 4 5v6c0 5 3.5 8.8 8 11 4.5-2.2 8-6 8-11V5z" fill="currentColor"/>',
            'file' => '<path d="M7 2h7l5 5v15a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Zm6 1.7V8h4.3Z" fill="currentColor"/>',
            'link' => '<path d="M10.5 13.5a1 1 0 0 1 0-1.4l2.6-2.6a4 4 0 0 1 5.7 5.7l-1.7 1.7a4 4 0 0 1-5.7 0 1 1 0 1 1 1.4-1.4 2 2 0 0 0 2.8 0l1.7-1.7a2 2 0 1 0-2.8-2.8l-2.6 2.6a1 1 0 0 1-1.4 0Zm-1 1a1 1 0 0 1 0 1.4l-2.6 2.6a4 4 0 1 1-5.7-5.7l1.7-1.7a4 4 0 0 1 5.7 0 1 1 0 0 1-1.4 1.4 2 2 0 0 0-2.8 0L2.7 14.2a2 2 0 1 0 2.8 2.8l2.6-2.6a1 1 0 0 1 1.4 0Z" fill="currentColor"/>',
            'logout' => '<path d="M10 17v-2h4v-2h-4v-2l-4 3zm6-13H6a2 2 0 0 0-2 2v4h2V6h10v12H6v-4H4v4a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2z" fill="currentColor"/>',
            'settings' => '<path d="M19.4 13.5a7.8 7.8 0 0 0 .1-1.5 7.8 7.8 0 0 0-.1-1.5l2-1.5-2-3.5-2.4 1a7.7 7.7 0 0 0-2.6-1.5l-.4-2.6H9l-.4 2.6a7.7 7.7 0 0 0-2.6 1.5l-2.4-1-2 3.5 2 1.5a7.8 7.8 0 0 0-.1 1.5 7.8 7.8 0 0 0 .1 1.5l-2 1.5 2 3.5 2.4-1a7.7 7.7 0 0 0 2.6 1.5l.4 2.6h4.8l.4-2.6a7.7 7.7 0 0 0 2.6-1.5l2.4 1 2-3.5zM12 15.5A3.5 3.5 0 1 1 12 8.5a3.5 3.5 0 0 1 0 7z" fill="currentColor"/>',
            'support' => '<path d="M4 4h16v11H7l-3 3V4Zm4 4h8v2H8Zm0 4h5v2H8Z" fill="currentColor"/>',
            'report' => '<path d="M6 2h9l5 5v15a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Zm8 1.7V8h4.3ZM8 12h8v2H8Zm0 4h8v2H8Z" fill="currentColor"/>',
            'megaphone' => '<path d="M4 13v-2a2 2 0 0 1 2-2h2l8-4v14l-8-4H6a2 2 0 0 1-2-2Zm14-5.5a4.5 4.5 0 0 1 0 9v-2a2.5 2.5 0 0 0 0-5z" fill="currentColor"/>',
            'database' => '<path d="M12 2C7 2 3 3.8 3 6v12c0 2.2 4 4 9 4s9-1.8 9-4V6c0-2.2-4-4-9-4Zm0 2c4.4 0 7 .9 7 2s-2.6 2-7 2-7-.9-7-2 2.6-2 7-2Zm0 14c-4.4 0-7-.9-7-2v-3c1.4 1 4 1.5 7 1.5s5.6-.5 7-1.5v3c0 1.1-2.6 2-7 2Zm0-6c-4.4 0-7-.9-7-2V9c1.4 1 4 1.5 7 1.5s5.6-.5 7-1.5v1c0 1.1-2.6 2-7 2Z" fill="currentColor"/>',
            'notification' => '<path d="M12 22a2.5 2.5 0 0 0 2.45-2h-4.9A2.5 2.5 0 0 0 12 22Zm7-6V11a7 7 0 1 0-14 0v5l-2 2v1h18v-1l-2-2Z" fill="currentColor"/>',
            'clock' => '<path d="M12 3a9 9 0 1 0 9 9 9 9 0 0 0-9-9Zm1 4v5.2l4 2.4-1 1.6-5-3V7Z" fill="currentColor"/>',
            'check' => '<path d="M9.2 16.2 5.8 12.8l-1.4 1.4 4.8 4.8L19.6 9.6l-1.4-1.4z" fill="currentColor"/>',
            'close' => '<path d="M6.4 5 5 6.4 10.6 12 5 17.6 6.4 19 12 13.4 17.6 19 19 17.6 13.4 12 19 6.4 17.6 5 12 10.6z" fill="currentColor"/>',
            'bank' => '<path d="M12 2 3 7v2h18V7ZM5 11h2v8H5Zm4 0h2v8H9Zm4 0h2v8h-2Zm4 0h2v8h-2ZM3 21h18v2H3z" fill="currentColor"/>',
            'cash' => '<path d="M4 6h16v12H4z" fill="currentColor" opacity=".18"/><path d="M6 8h12v8H6zm6 1.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5Z" fill="currentColor"/>',
            'activity' => '<path d="M4 13h4l2-6 4 12 2-6h4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
            'announcement' => '<path d="M4 13v-2a2 2 0 0 1 2-2h2l8-4v14l-8-4H6a2 2 0 0 1-2-2Zm14-5.5a4.5 4.5 0 0 1 0 9v-2a2.5 2.5 0 0 0 0-5z" fill="currentColor"/>',
            'grid' => '<path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z" fill="currentColor"/>',
            'truck' => '<path d="M3 6h11v9H3zM14 9h3l3 3v3h-6zM7 18a2 2 0 1 0 0 4 2 2 0 0 0 0-4Zm10 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z" fill="currentColor"/>',
            'plus' => '<path d="M11 5h2v14h-2zM5 11h14v2H5z" fill="currentColor"/>',
            'download' => '<path d="M12 3v10m0 0 4-4m-4 4-4-4M4 19h16v2H4z" fill="currentColor"/>',
            'filter' => '<path d="M4 5h16l-6 7v5l-4 2v-7z" fill="currentColor"/>',
            'trash' => '<path d="M8 5V3h8v2h4v2H4V5Zm1 4h2v9H9Zm4 0h2v9h-2ZM6 9h12l-1 13H7L6 9Z" fill="currentColor"/>',
            'pencil' => '<path d="M4 16.5V20h3.5L18.9 8.6l-3.5-3.5L4 16.5Zm14.9-8.4 1.6-1.6a1.5 1.5 0 0 0 0-2.1l-1-1a1.5 1.5 0 0 0-2.1 0L15.8 4.9Z" fill="currentColor"/>',
            'toggle' => '<path d="M7 8a4 4 0 1 0 0 8h10a4 4 0 0 0 0-8Zm0 6a2 2 0 1 1 0-4 2 2 0 0 1 0 4Z" fill="currentColor"/>',
            'lock' => '<path d="M8 10V8a4 4 0 0 1 8 0v2h1a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2Zm2 0h4V8a2 2 0 0 0-4 0Z" fill="currentColor"/>',
            'home' => '<path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z" fill="currentColor"/>',
            'map' => '<path d="M15 3l-6 2.2L3 3v16l6 2.2L15 19l6 2V5zm-2 14.4-4-1.5V6.2l4 1.5v9.7z" fill="currentColor"/>',
            'transport' => '<path d="M4 16c0 .88.39 1.67 1 2.22V20a1 1 0 0 0 1 1h1a1 1 0 0 0 1-1v-1h8v1a1 1 0 0 0 1 1h1a1 1 0 0 0 1-1v-1.78A3 3 0 0 0 20 16V6c0-3.5-3.58-4-8-4S4 2.5 4 6v10zm3.5 1a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm9 0a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zM6 10V6h12v4H6z" fill="currentColor"/>',
            'credit-card' => '<path d="M20 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2zM4 11h16v3H4v-3z" fill="currentColor"/>',
            'help' => '<path d="M12 2a10 10 0 1 0 0 20A10 10 0 0 0 12 2zm1 16h-2v-2h2v2zm0-4h-2c0-3 3-2.8 3-5a3 3 0 0 0-6 0H6a5 5 0 0 1 10 0c0 3-3 2.8-3 5z" fill="currentColor"/>',
        ];

        $path = $icons[$name] ?? $icons['brand'];
        return '<svg class="admin-icon admin-icon-' . e($name) . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $path . '</svg>';
    }
}
