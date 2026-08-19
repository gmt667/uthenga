<?php
/**
 * Uthenga — Ticket Templates (Phase: Customer Tickets)
 *
 * Shared renderer for the modern Uthenga ticket designs. Each template
 * carries the real Uthenga logo in the top-left corner and a unique,
 * per-ticket QR code (rendered client-side from the data-qr payload).
 *
 * Usage:
 *   require_once __DIR__ . '/TicketTemplates.php';
 *   echo uthenga_ticket_render([
 *       'template'    => 'vip',            // vip|early_bird|general|group|season
 *       'event_title' => 'MALAWI BUSINESS SUMMIT 2026',
 *       'tagline'     => 'Connect. Innovate. Grow.',
 *       'date'        => '18 AUG 2026',
 *       'time'        => '08:00 AM - 06:00 PM',
 *       'venue'       => 'SUNBIRD CAPITAL HOTEL',
 *       'city'        => 'LILONGWE, MALAWI',
 *       'ticket_name' => 'VIP PASS',
 *       'ticket_id'   => 'UTH-VIP-004821',
 *       'holder'      => 'Chimwemwe Banda',
 *       'row'         => 'A',
 *       'seat'        => '01',
 *       'qr_payload'  => 'UTH-VIP-004821',   // unique per ticket
 *       'perks'       => ['VIP LOUNGE','FRONT ROW SEATING','NETWORKING ACCESS','WELCOME DRINK'],
 *       'badge'       => 'ADMIT ONE',
 *       'extra'       => null,               // e.g. 'ADMIT 5 PEOPLE' sub-label
 *       'valid_from'  => '01 JAN 2026',
 *       'valid_to'    => '31 DEC 2026',
 *       'notes'       => ['All Music Events','All Workshops','All Conferences','Priority Booking'],
 *       'status'      => 'ACTIVE',
 *       'status_cls'  => 'active',
 *       'logo'        => null,               // defaults to theme-aware logo
 *   ]);
 */

function uthenga_ticket_esc(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function uthenga_ticket_icon(string $name, int $size = 12): string
{
    $sizeAttr = 'width="' . $size . '" height="' . $size . '"';
    $paths = [
        // calendar (stroke)
        'cal' => '<rect x="3" y="4" width="18" height="17" rx="2" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M16 2v4M8 2v4M3 10h18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>',
        // clock (stroke)
        'clk' => '<circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M12 7v5l3.2 2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>',
        // map pin (stroke)
        'pin' => '<path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="10" r="3" fill="none" stroke="currentColor" stroke-width="1.8"/>',
        // gear / settings (fill, Material)
        'gear' => '<path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58a.49.49 0 0 0 .12-.61l-1.92-3.32a.488.488 0 0 0-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54a.484.484 0 0 0-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58a.49.49 0 0 0-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/>',
        // microphone (fill, Material)
        'mic' => '<path d="M12 14a3 3 0 0 0 3-3V5a3 3 0 0 0-6 0v6a3 3 0 0 0 3 3zm5-3a5 5 0 0 1-10 0H5a7 7 0 0 0 6 6.9V21h2v-3.1A7 7 0 0 0 19 11h-2z"/>',
        // car (stroke)
        'car' => '<path d="M4 16v-4l2-4.5A2 2 0 0 1 7.8 6h8.4a2 2 0 0 1 1.8 1.5L20 12v4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M3 12h18v4h-2.2a2.8 2.8 0 0 1-5.6 0H10.8a2.8 2.8 0 0 1-5.6 0H3z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><circle cx="8" cy="16.5" r="1.2" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="16" cy="16.5" r="1.2" fill="none" stroke="currentColor" stroke-width="1.8"/>',
        // utensils (fill, Material restaurant)
        'utensils' => '<path d="M11 9H9V2H7v7H5V2H3v7c0 2.12 1.66 3.84 3.75 3.97V22h2.5v-9.03C11.34 12.84 13 11.12 13 9V2h-2v7zm5-3v8h2.5v8H21V2c-2.76 0-5 2.24-5 4z"/>',
        // group / meet & greet (fill, Material)
        'group' => '<path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>',
        // crown (fill, FontAwesome style)
        'crown' => '<path d="M5 16L3 5l5.5 5L12 4l3.5 6L21 5l-2 11H5z"/><path d="M4.5 17.5h15v2A1.5 1.5 0 0 1 18 21H6a1.5 1.5 0 0 1-1.5-1.5z"/>',
        // star (fill)
        'star' => '<path d="M12 2l2.9 6.3 6.9.8-5.2 4.6 1.5 6.8-6.1-3.4-6.1 3.4 1.5-6.8L2.2 9.1l6.9-.8z"/>',
    ];
    $body = $paths[$name] ?? $paths['star'];
    return '<svg viewBox="0 0 24 24" ' . $sizeAttr . ' aria-hidden="true" focusable="false" class="uth-tk-ic">' . $body . '</svg>';
}

function uthenga_ticket_logo_url(bool $darkTheme): string
{
    global $ticketLogoUrl;
    if (!empty($ticketLogoUrl)) return (string) $ticketLogoUrl;
    $base = defined('BASE_URL') ? BASE_URL : '/uthenga/';
    return $base . ($darkTheme ? 'assets/images/logo-light.png' : 'assets/images/logo-dark.png');
}

function uthenga_ticket_render(array $t): string
{
    $template = (string) ($t['template'] ?? 'general');
    $t = array_merge([
        'template' => 'general', 'event_title' => 'EVENT TITLE', 'tagline' => '',
        'date' => 'TBC', 'time' => 'TBC', 'venue' => 'VENUE', 'city' => 'MALAWI',
        'ticket_name' => 'TICKET', 'ticket_id' => 'UTH-000000', 'holder' => '',
        'row' => '', 'seat' => '', 'qr_payload' => '', 'perks' => [], 'perk_icons' => [], 'badge' => 'ADMIT ONE',
        'extra' => null, 'valid_from' => '', 'valid_to' => '', 'notes' => [],
        'status' => '', 'status_cls' => '', 'logo' => null, 'preview' => false,
    ], $t);

    $e = 'uthenga_ticket_esc';
    $preview = !empty($t['preview']);
    $pv = function (string $key, ?string $value) use ($preview, $e): string {
        $value = (string) $value;
        return $preview ? '<span class="ew-pv" data-pv="' . $key . '">' . $e($value) . '</span>' : $e($value);
    };
    $logoUrl = $e($t['logo'] ?: uthenga_ticket_logo_url(in_array($template, ['vip', 'vvip', 'season'], true)));

    $brandBlock = '<div class="uth-tk-brand"><img src="' . $logoUrl . '" alt="Uthenga" class="uth-tk-logo"><span class="uth-tk-brand-line">Uthenga <em>Events</em></span></div>';

    $qrBlock = '<div class="uth-tk-qr" data-qr="' . $e($t['qr_payload']) . '"><div class="uth-tk-qr-inner"></div></div>';

    $metaRow = '<div class="uth-tk-meta">'
        . '<span>' . uthenga_ticket_icon('cal') . $pv('date', $t['date']) . '</span>'
        . '<span>' . uthenga_ticket_icon('clk') . $pv('time', $t['time']) . '</span>'
        . '<span>' . uthenga_ticket_icon('pin') . $pv('venue', $t['venue']) . '<br><b>' . $pv('city', $t['city']) . '</b></span>'
        . '</div>';

    $perkRow = '';
    $perkIcons = (array) ($t['perk_icons'] ?? []);
    if (!empty($t['perks'])) {
        $perkList = array_slice($t['perks'], 0, 4);
        $rows = [];
        foreach ($perkList as $perkIndex => $perkText) {
            $rows[] = '<span>' . uthenga_ticket_icon((string) ($perkIcons[$perkIndex] ?? 'star')) . $e($perkText) . '</span>';
        }
        $perkRow = '<div class="uth-tk-perks">' . implode('', $rows) . '</div>';
    }

    $idBlock = '<p class="uth-tk-id-label">Ticket ID</p><p class="uth-tk-id">' . $e($t['ticket_id']) . '</p>';

    $notches = '<span class="uth-tk-notch top"></span><span class="uth-tk-notch bottom"></span>';

    if ($template === 'vip') {
        $h = '<article class="ticket-legacy">';
        $h .= '<div class="leg-main vip-bg" style="padding-right:150px;">';
        $h .= '<div class="leg-logo"><i class="fas fa-certificate"></i><span>Uthenga<b>Events</b></span></div>';
        $h .= '<h2 class="leg-title">' . $pv('event_title', $t['event_title']) . '</h2>';
        $h .= '<p class="leg-tagline" style="color:#eab308;">' . $pv('tagline', $t['tagline']) . '</p>';
        $h .= '<div class="leg-meta" style="color:#cbd5e1;">';
        $h .= '<div class="leg-flex"><span class="leg-ic"><i class="far fa-calendar-alt"></i></span><span>' . $pv('date', $t['date']) . '</span></div>';
        $h .= '<div class="leg-flex"><span class="leg-ic"><i class="far fa-clock"></i></span><span>' . $pv('time', $t['time']) . '</span></div>';
        $h .= '<div class="leg-flex"><span class="leg-ic"><i class="fas fa-map-marker-alt"></i></span><span>' . $pv('venue', $t['venue']) . '</span></div>';
        $h .= ($t['city'] !== '' ? '<div class="leg-flex" style="padding-left:20px;"><span>' . $pv('city', $t['city']) . '</span></div>' : '');
        $h .= '</div>';
        $h .= '<div class="leg-perks" style="color:#eab308;">';
        foreach (array_slice($t['perks'] ?: ['VIP LOUNGE', 'FRONT ROW SEATING', 'NETWORKING ACCESS', 'WELCOME DRINK'], 0, 4) as $pk => $pvText) {
            $h .= '<div><i class="' . ($pk === 0 ? 'fas fa-couch' : ($pk === 1 ? 'fas fa-chair' : ($pk === 2 ? 'fas fa-network-wired' : 'fas fa-glass-cheers'))) . '"></i>' . $e($pvText) . '</div>';
        }
        $h .= '</div>';
        $h .= '<div class="leg-hex"><div><i class="fas fa-crown"></i><b>VIP</b><span>PASS</span></div></div>';
        $h .= '</div>';
        $h .= '<div class="leg-stub ticket-stub-border" style="background:#3d2787;color:#fff;">';
        $h .= '<span class="notch-left notch-top"></span><span class="notch-left notch-bottom"></span>';
        $h .= '<div><h3>' . $pv('ticket_name', $t['ticket_name']) . '</h3>';
        $h .= '<p class="leg-id-lbl">Ticket ID</p><p class="leg-id">' . $e($t['ticket_id']) . '</p>';
        $h .= '<div class="leg-rowseat"><div><i>ROW</i><b>' . $pv('row', $t['row']) . '</b></div><div><i>SEAT</i><b>' . $pv('seat', $t['seat']) . '</b></div></div>';
        $h .= '</div>';
        $h .= '<div class="leg-qr">' . $qrBlock . '</div>';
        $h .= '<p class="leg-admit">' . $pv('badge', $t['badge']) . '</p>';
        $h .= '</div>';
        $h .= '<span class="scalloped-edge"></span>';
        $h .= '</article>';
        return $h;
    }

    if ($template === 'vvip') {
        $h = '<div class="uth-tk vvip">';
        $h .= '<div class="uth-tk-main vvip-main">' . $brandBlock;
        $h .= '<div class="uth-tk-event"><h2>' . $pv('event_title', $t['event_title']) . '</h2><p class="uth-tk-tagline">' . $pv('tagline', $t['tagline']) . '</p></div>';
        $h .= $metaRow . $perkRow;
        $h .= '<p class="uth-tk-holder">' . $pv('holder', $t['holder']) . '</p>';
        $h .= '</div>';
        $h .= '<div class="uth-tk-stub vvip-stub">' . $notches;
        $h .= '<h3>' . $pv('ticket_name', $t['ticket_name']) . '</h3>' . $idBlock;
        $h .= '<div class="uth-tk-rowseat"><span><i>ROW</i><b>' . $pv('row', $t['row']) . '</b></span><span><i>SEAT</i><b>' . $pv('seat', $t['seat']) . '</b></span></div>';
        $h .= $qrBlock . '<p class="uth-tk-admit">' . $pv('badge', $t['badge']) . '</p>';
        $h .= '</div></div>';
        return $h;
    }

    if ($template === 'early_bird') {
        $h = '<article class="ticket-legacy">';
        $h .= '<div class="leg-main" style="padding-right:150px;">';
        $h .= '<div class="leg-logo" style="color:#0b3846;"><i class="fas fa-certificate"></i><span>Uthenga<b>Events</b></span></div>';
        $h .= '<h2 class="leg-title" style="color:#15803d;">Early Bird</h2>';
        $h .= '<h3 class="leg-sub-title">' . $pv('event_title', $t['event_title']) . '</h3>';
        $h .= '<p class="leg-tagline" style="color:#16a34a;font-style:italic;font-weight:600;">' . $pv('tagline', $t['tagline']) . '</p>';
        $h .= '<div class="leg-meta" style="color:#4b5563;font-weight:500;">';
        $h .= '<div class="leg-flex"><span class="leg-ic"><i class="far fa-calendar-alt" style="color:#16a34a;"></i></span><span>' . $pv('date', $t['date']) . '</span></div>';
        $h .= '<div class="leg-flex"><span class="leg-ic"><i class="far fa-clock" style="color:#16a34a;"></i></span><span>' . $pv('time', $t['time']) . '</span></div>';
        $h .= '<div class="leg-flex" style="align-items:flex-start;"><span class="leg-ic"><i class="fas fa-map-marker-alt" style="color:#16a34a;"></i></span><span>' . $pv('venue', $t['venue']) . ($t['city'] !== '' ? '<div>' . $pv('city', $t['city']) . '</div>' : '') . '</span></div>';
        $h .= '</div>';
        $h .= '</div>';
        $h .= '<span class="early-bird-bg leg-deco"></span>';
        $h .= '<div class="leg-disc"><span>Discount</span><b>30%</b><span>Off</span></div>';
        $h .= '<div class="leg-stub ticket-stub-border-dark" style="background:#fff;color:#1f2937;">';
        $h .= '<span class="notch-left notch-top"></span><span class="notch-left notch-bottom"></span>';
        $h .= '<div><h3 style="color:#047857;">' . $pv('ticket_name', $t['ticket_name']) . '</h3>';
        $h .= '<p class="leg-id-lbl" style="color:#6b7280;">Ticket ID</p><p class="leg-id" style="color:#1f2937;">' . $e($t['ticket_id']) . '</p></div>';
        $h .= '<div class="leg-qr">' . $qrBlock . '</div>';
        $h .= '<p class="leg-admit" style="color:#1f2937;">' . $pv('badge', $t['badge']) . '</p>';
        $h .= '</div>';
        $h .= '<span class="scalloped-edge"></span>';
        $h .= '</article>';
        return $h;
    }

    if ($template === 'group') {
        $h = '<article class="ticket-legacy">';
        $h .= '<div class="leg-main" style="padding-right:120px;">';
        $h .= '<div class="leg-logo" style="color:#0b3846;"><i class="fas fa-certificate"></i><span>Uthenga<b>Events</b></span></div>';
        $h .= '<h2 class="leg-title" style="color:#7F00FF;">Group Pass</h2>';
        $h .= '<h3 class="leg-sub-title">' . $pv('event_title', $t['event_title']) . '</h3>';
        $h .= '<div class="leg-meta" style="color:#4b5563;font-weight:500;">';
        $h .= '<div class="leg-flex"><span class="leg-ic"><i class="far fa-calendar-alt" style="color:#7F00FF;"></i></span><span>' . $pv('date', $t['date']) . '</span></div>';
        $h .= '<div class="leg-flex"><span class="leg-ic"><i class="far fa-clock" style="color:#7F00FF;"></i></span><span>' . $pv('time', $t['time']) . '</span></div>';
        $h .= '<div class="leg-flex" style="align-items:flex-start;"><span class="leg-ic"><i class="fas fa-map-marker-alt" style="color:#7F00FF;"></i></span><span>' . $pv('venue', $t['venue']) . ($t['city'] !== '' ? '<div>' . $pv('city', $t['city']) . '</div>' : '') . '</span></div>';
        $h .= '<div class="leg-flex" style="font-weight:800;color:#7F00FF;"><span class="leg-ic"><i class="fas fa-users"></i></span><span>ADMIT ' . $pv('extra', $t['extra'] ?: '5') . ' PEOPLE</span></div>';
        $h .= '</div>';
        $h .= '</div>';
        $h .= '<span class="group-bg leg-deco"></span>';
        $h .= '<div class="leg-stub ticket-stub-border" style="background:#7F00FF;color:#fff;">';
        $h .= '<span class="notch-left notch-top"></span><span class="notch-left notch-bottom"></span>';
        $h .= '<div><h3>GROUP PASS</h3><p class="leg-id-lbl">(' . $pv('extra', $t['extra'] ?: '5') . ' PEOPLE)</p>';
        $h .= '<p class="leg-id-lbl">Ticket ID</p><p class="leg-id">' . $e($t['ticket_id']) . '</p></div>';
        $h .= '<div class="leg-qr">' . $qrBlock . '</div>';
        $h .= '<p class="leg-admit">ADMIT ' . $pv('extra', $t['extra'] ?: '5') . ' PEOPLE</p>';
        $h .= '</div>';
        $h .= '<span class="scalloped-edge"></span>';
        $h .= '</article>';
        return $h;
    }

    if ($template === 'season') {
        $notes = '';
        if (!empty($t['notes'])) {
            foreach ($t['notes'] as $n) {
                $notes .= '<span><i class="fas fa-check" style="color:#c0392b;"></i>' . $e($n) . '</span>';
            }
        }
        $h = '<article class="ticket-legacy">';
        $h .= '<div class="leg-main" style="justify-content:center;">';
        $h .= '<div class="leg-logo" style="color:#0b3846;"><i class="fas fa-certificate"></i><span>Uthenga<b>Events</b></span></div>';
        $h .= '<h2 class="leg-title" style="font-size:18px;color:#c0392b;">' . $pv('event_title', $t['event_title']) . '</h2>';
        $h .= '<h3 class="leg-sub-title" style="margin-bottom:10px;">' . $pv('tagline', $t['tagline']) . '</h3>';
        $h .= '<div class="leg-checks">' . $notes . '</div>';
        $h .= '</div>';
        $h .= '<div class="leg-stub ticket-stub-border" style="background:#c0392b;color:#fff;justify-content:center;">';
        $h .= '<span class="notch-left notch-top"></span><span class="notch-left notch-bottom"></span>';
        $h .= '<p class="leg-id-lbl" style="margin-bottom:2px;">VALID FROM</p><p class="leg-valid">' . $pv('valid_from', $t['valid_from']) . '</p>';
        $h .= '<p class="leg-id-lbl" style="margin-bottom:2px;">TO</p><p class="leg-valid" style="margin-bottom:12px;">' . $pv('valid_to', $t['valid_to']) . '</p>';
        $h .= '<p class="leg-id-lbl">TICKET ID</p><p class="leg-id">' . $e($t['ticket_id']) . '</p>';
        $h .= '</div>';
        $h .= '<div class="leg-stub ticket-stub-border" style="background:#922b21;color:#fff;">';
        $h .= '<span class="notch-left notch-top"></span><span class="notch-left notch-bottom"></span>';
        $h .= '<div class="leg-qr">' . $qrBlock . '</div>';
        $h .= '<p class="leg-admit">' . $pv('badge', $t['badge']) . '</p>';
        $h .= '</div>';
        $h .= '<span class="scalloped-edge"></span>';
        $h .= '</article>';
        return $h;
    }

    // general (default)
    $h = '<article class="ticket-legacy">';
    $h .= '<div class="leg-main" style="padding-right:120px;">';
    $h .= '<div class="leg-logo" style="color:#0b3846;"><i class="fas fa-certificate"></i><span>Uthenga<b>Events</b></span></div>';
    $h .= '<h2 class="leg-title" style="color:#0052D4;">General<br>Admission</h2>';
    $h .= '<h3 class="leg-sub-title">' . $pv('event_title', $t['event_title']) . '</h3>';
    $h .= '<div class="leg-meta" style="color:#4b5563;font-weight:500;">';
    $h .= '<div class="leg-flex"><span class="leg-ic"><i class="far fa-calendar-alt" style="color:#0052D4;"></i></span><span>' . $pv('date', $t['date']) . '</span></div>';
    $h .= '<div class="leg-flex"><span class="leg-ic"><i class="far fa-clock" style="color:#0052D4;"></i></span><span>' . $pv('time', $t['time']) . '</span></div>';
    $h .= '<div class="leg-flex" style="align-items:flex-start;"><span class="leg-ic"><i class="fas fa-map-marker-alt" style="color:#0052D4;"></i></span><span>' . $pv('venue', $t['venue']) . ($t['city'] !== '' ? '<div>' . $pv('city', $t['city']) . '</div>' : '') . '</span></div>';
    $h .= '</div>';
    $h .= '</div>';
    $h .= '<span class="ga-bg leg-deco"></span>';
    $h .= '<div class="leg-stub ticket-stub-border" style="background:#0052D4;color:#fff;">';
    $h .= '<span class="notch-left notch-top"></span><span class="notch-left notch-bottom"></span>';
    $h .= '<div><h3>GENERAL<br>ADMISSION</h3>';
    $h .= '<p class="leg-id-lbl">Ticket ID</p><p class="leg-id">' . $e($t['ticket_id']) . '</p></div>';
    $h .= '<div class="leg-qr">' . $qrBlock . '</div>';
    $h .= '<p class="leg-admit">' . $pv('badge', $t['badge']) . '</p>';
    $h .= '</div>';
    $h .= '<span class="scalloped-edge"></span>';
    $h .= '</article>';
    return $h;
}

function uthenga_ticket_render_css(): string
{
    return <<<'CSS'
.ticket-legacy{--tk-notch-bg:#f5f5f5;display:flex;width:100%;max-width:620px;margin:0 auto;box-sizing:border-box;position:relative;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 18px 40px rgba(15,23,42,.16);color:#111827;min-height:208px;font-family:'Inter',-apple-system,'Segoe UI',sans-serif;-webkit-font-smoothing:antialiased}
.ticket-legacy *{box-sizing:border-box}
.ticket-legacy .leg-main{flex:1;min-width:0;padding:16px;position:relative;z-index:1;display:flex;flex-direction:column;color:#fff}
.ticket-legacy .leg-logo{display:flex;align-items:center;gap:8px;margin-bottom:12px}
.ticket-legacy .leg-logo i{font-size:14px}
.ticket-legacy .leg-logo span{font-size:12px;font-weight:700;letter-spacing:1px;text-transform:uppercase;line-height:1.2}
.ticket-legacy .leg-logo b{font-weight:400;font-size:8px;letter-spacing:2px;display:block}
.ticket-legacy .leg-title{font-size:20px;font-weight:900;line-height:1.12;margin:0 0 4px;text-transform:uppercase}
.ticket-legacy .leg-sub-title{font-size:13px;font-weight:700;color:#111827;margin:0 0 4px;text-transform:uppercase}
.ticket-legacy .leg-tagline{font-size:12px;font-weight:500;margin:0 0 14px}
.ticket-legacy .leg-meta{display:flex;flex-direction:column;gap:4px;font-size:10px;font-weight:500;line-height:1.4}
.ticket-legacy .leg-flex{display:flex;align-items:center;gap:8px}
.ticket-legacy .leg-ic{width:14px;text-align:center;margin-right:2px}
.ticket-legacy .leg-perks{display:flex;gap:14px;flex-wrap:wrap;margin-top:auto;padding-top:8px;font-size:8px;font-weight:700;letter-spacing:.4px;border-top:1px solid rgba(255,255,255,.2)}
.ticket-legacy .leg-perks div{display:flex;align-items:center;gap:4px}
.ticket-legacy .leg-hex{position:absolute;top:50%;right:32px;transform:translateY(-50%);width:80px;height:96px;z-index:2;display:flex;flex-direction:column;align-items:center;justify-content:center;background:linear-gradient(135deg,#facc15,#eab308);clip-path:polygon(50% 0%,100% 25%,100% 75%,50% 100%,0% 75%,0% 25%);box-shadow:0 8px 22px rgba(0,0,0,.35)}
.ticket-legacy .leg-hex>div{background:#1a153a;width:90%;height:90%;clip-path:inherit;display:flex;flex-direction:column;align-items:center;justify-content:center}
.ticket-legacy .leg-hex i{color:#eab308;font-size:17px;margin-bottom:3px}
.ticket-legacy .leg-hex b{color:#eab308;font-size:19px;font-weight:900;line-height:1}
.ticket-legacy .leg-hex span{color:#eab308;font-size:11px;font-weight:600;letter-spacing:2px}
.ticket-legacy .leg-deco{position:absolute;top:0;bottom:0;width:50%;right:0;z-index:0;opacity:.9}
.ticket-legacy .leg-disc{position:absolute;top:50%;right:5.5rem;transform:translateY(-50%);z-index:2;width:56px;height:56px;border-radius:50%;background:#fff;border:2px solid #22c55e;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:2px;box-shadow:0 6px 16px rgba(0,0,0,.12)}
.ticket-legacy .leg-disc span{font-size:7px;font-weight:800;color:#14532d;text-transform:uppercase;letter-spacing:.5px}
.ticket-legacy .leg-disc b{font-size:16px;font-weight:900;color:#16a34a;line-height:1.1}
.ticket-legacy .leg-checks{display:grid;grid-template-columns:1fr 1fr;gap:7px 16px;font-size:10px;font-weight:700;color:#374151}
.ticket-legacy .leg-checks span{display:flex;align-items:center;gap:6px}
.ticket-legacy .leg-stub{width:96px;flex:none;display:flex;flex-direction:column;justify-content:space-between;align-items:center;text-align:center;padding:13px 10px;position:relative;border-radius:0 12px 12px 0}
.ticket-legacy .leg-stub h3{font-size:12px;font-weight:700;letter-spacing:.3px;line-height:1.3;margin:0 0 8px;text-transform:uppercase}
.ticket-legacy .leg-stub .leg-id-lbl{font-size:7px;text-transform:uppercase;letter-spacing:1px;opacity:.78;margin:3px 0 2px;font-weight:700}
.ticket-legacy .leg-stub .leg-id{font-size:9px;font-family:'JetBrains Mono',ui-monospace,Menlo,monospace;font-weight:700;margin:0 0 6px;word-break:break-all}
.ticket-legacy .leg-stub .leg-rowseat{display:flex;justify-content:space-between;gap:8px;font-size:10px;margin:0 0 8px;padding:0 2px;width:100%}
.ticket-legacy .leg-stub .leg-rowseat i{font-style:normal;display:block;font-size:8px;opacity:.75;letter-spacing:1px}
.ticket-legacy .leg-stub .leg-rowseat b{display:block;font-weight:800}
.ticket-legacy .leg-stub .leg-valid{font-size:11px;font-weight:800;margin:0 0 2px;line-height:1.3}
.ticket-legacy .leg-qr{background:#fff;border-radius:4px;padding:3px;width:62px;height:62px;margin:0 auto 6px;flex:none;display:flex;align-items:center;justify-content:center}
.ticket-legacy .leg-qr .qr-placeholder{width:100%;height:100%;border-radius:2px}
.ticket-legacy .leg-qr .uth-tk-qr{width:100%;height:100%;padding:0;background:transparent;border-radius:0;margin:0}
.ticket-legacy .leg-qr .uth-tk-qr .uth-tk-qr-inner{width:100%;height:100%;display:flex;align-items:center;justify-content:center}
.ticket-legacy .leg-admit{font-size:9px;font-weight:800;letter-spacing:1px;text-transform:uppercase;margin:0}
.ticket-legacy .ticket-stub-border{border-left:2px dashed rgba(255,255,255,.4)}
.ticket-legacy .ticket-stub-border-dark{border-left:2px dashed rgba(0,0,0,.2)}
.ticket-legacy .notch-left{position:absolute;width:20px;height:20px;border-radius:50%;z-index:10;background:var(--tk-notch-bg)}
.ticket-legacy .notch-top{top:-10px;left:-10px}
.ticket-legacy .notch-bottom{bottom:-10px;left:-10px}
.ticket-legacy .scalloped-edge{position:absolute;right:0;top:0;bottom:0;width:8px;z-index:11;background-image:radial-gradient(circle at 8px 10px,var(--tk-notch-bg) 8px,transparent 8.5px);background-size:16px 20px;background-repeat:repeat-y}
.ticket-legacy .vip-bg{background:linear-gradient(135deg,#0f0c29 0%,#302b63 50%,#24243e 100%);color:#fff}
.ticket-legacy .early-bird-bg{background:linear-gradient(135deg,#11998e 0%,#38ef7d 100%);clip-path:polygon(0 0,100% 0,100% 100%,20% 100%)}
.ticket-legacy .ga-bg{background:linear-gradient(135deg,#0052D4 0%,#4364F7 50%,#6FB1FC 100%);clip-path:polygon(0 0,100% 0,100% 100%,30% 100%)}
.ticket-legacy .group-bg{background:linear-gradient(135deg,#7F00FF 0%,#E100FF 100%);clip-path:polygon(10% 0,100% 0,100% 100%,0% 100%)}
.ticket-legacy .season-bg{background:#c0392b;color:#fff}
.ticket-legacy .qr-placeholder{background:repeating-linear-gradient(45deg,#000,#000 2px,#fff 2px,#fff 4px)}
.uth-tk{position:relative;display:flex;width:100%;max-width:520px;min-height:228px;border-radius:16px;overflow:hidden;background:#fff;box-shadow:0 18px 40px rgba(15,23,42,.16);font-family:'Inter',-apple-system,'Segoe UI',sans-serif;-webkit-font-smoothing:antialiased;margin:0 auto}
.uth-tk *{box-sizing:border-box}
.uth-tk-main{flex:1;min-width:0;padding:1.15rem 1.25rem;position:relative;z-index:1;display:flex;flex-direction:column}
.uth-tk-brand{display:flex;align-items:center;gap:.55rem;margin-bottom:.7rem;color:inherit}
.uth-tk-logo{height:22px;width:auto;max-width:110px;object-fit:contain}
.uth-tk-brand-line{font-size:.68rem;font-weight:800;letter-spacing:.14em;text-transform:uppercase;opacity:.92}
.uth-tk-brand-line em{font-style:normal;font-weight:500;opacity:.75;display:block;font-size:.55rem;letter-spacing:.3em}
.uth-tk-event h2{font-size:1.15rem;font-weight:900;line-height:1.12;letter-spacing:-.01em;margin:0 0 .15rem;text-transform:uppercase}
.uth-tk-tagline{font-size:.72rem;font-weight:600;margin:0 0 .6rem}
.uth-tk-meta{display:flex;flex-wrap:wrap;gap:.5rem 1.1rem;font-size:.62rem;line-height:1.35;font-weight:600;opacity:.92}
.uth-tk-meta span{display:inline-flex;align-items:flex-start;gap:.32rem}
.uth-tk-meta b{font-weight:800}
.uth-tk-perks{display:flex;flex-wrap:wrap;gap:.35rem .8rem;margin-top:.6rem;font-size:.55rem;font-weight:800;letter-spacing:.08em}
.uth-tk-perks span{display:inline-flex;align-items:center;gap:.28rem}
.uth-tk-holder{margin-top:auto;padding-top:.55rem;font-size:.72rem;font-weight:700;letter-spacing:.02em;opacity:.95}
.uth-tk-stub{width:118px;flex-shrink:0;position:relative;border-left:2px dashed rgba(255,255,255,.45);display:flex;flex-direction:column;align-items:center;justify-content:space-between;padding:.8rem .65rem;text-align:center;z-index:1;color:#fff}
.uth-tk-stub h3{font-size:.62rem;font-weight:900;letter-spacing:.12em;margin:0 0 .3rem;text-transform:uppercase}
.uth-tk-stub-sub{font-size:.55rem;opacity:.85;font-weight:700}
.uth-tk-id-label{font-size:.52rem;text-transform:uppercase;letter-spacing:.16em;opacity:.75;margin:.25rem 0 0;font-weight:700}
.uth-tk-id{font-family:'JetBrains Mono',ui-monospace,Menlo,monospace;font-size:.58rem;font-weight:700;margin:0;word-break:break-all}
.uth-tk-rowseat{display:flex;justify-content:space-between;gap:.4rem;margin:.45rem 0 .3rem;font-size:.6rem}
.uth-tk-rowseat span{text-align:center}
.uth-tk-rowseat i{display:block;font-style:normal;opacity:.7;font-size:.5rem;letter-spacing:.14em}
.uth-tk-rowseat b{font-size:.8rem}
.uth-tk-qr{background:#fff;border-radius:6px;padding:.3rem;margin:.4rem auto;width:74px;height:74px;display:flex;align-items:center;justify-content:center;overflow:hidden}
.uth-tk-qr svg{width:100%;height:100%;display:block}
.uth-tk-qr-inner{display:flex;align-items:center;justify-content:center;width:100%;height:100%;font-size:.5rem;color:#9ca3af;font-weight:700;text-align:center}
.uth-tk-admit{font-size:.56rem;font-weight:900;letter-spacing:.1em;margin:0}
.uth-tk-notch{position:absolute;width:18px;height:18px;background:transparent;border-radius:50%;left:-11px;z-index:3;box-shadow:0 0 0 18px transparent}
.uth-tk-notch.top{top:-9px;box-shadow:0 0 0 18px #f5f5f5}
.uth-tk-notch.bottom{bottom:-9px;box-shadow:0 0 0 18px #f5f5f5}
.uth-tk-bg{position:absolute;top:0;bottom:0;width:48%;right:0;z-index:0}
.uth-tk-badge{position:absolute;z-index:2;border-radius:50%;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:.3rem;line-height:1.05;box-shadow:0 6px 16px rgba(0,0,0,.14)}
.vip-bg{background:linear-gradient(135deg,#0f0c29 0%,#302b63 50%,#24243e 100%)}
.vip-bg .uth-tk-tagline{color:#eab308}
.vip-bg .uth-tk-brand-line,.vip-bg .uth-tk-meta{color:rgba(255,255,255,.92)}
.vip-bg .uth-tk-perks{color:#eab308;border-top:1px solid rgba(255,255,255,.2);padding-top:.55rem}
.vip-bg .uth-tk-holder{color:rgba(255,255,255,.85)}
.vip-stub{background:#3d2787}
.uth-tk-hex{position:absolute;top:50%;right:1.9rem;transform:translateY(-50%);width:74px;height:88px;background:linear-gradient(135deg,#facc15,#eab308);clip-path:polygon(50% 0%,100% 25%,100% 75%,50% 100%,0% 75%,0% 25%);display:flex;flex-direction:column;align-items:center;justify-content:center;box-shadow:0 8px 20px rgba(0,0,0,.35)}
.uth-tk-hex::after{content:'';position:absolute;inset:4px;background:#1a153a;clip-path:polygon(50% 0%,100% 25%,100% 75%,50% 100%,0% 75%,0% 25%)}
.uth-tk-hex>*{position:relative;z-index:1}
.uth-tk-hex strong{color:#eab308;font-size:1.05rem;font-weight:900;line-height:1}
.uth-tk-hex span{color:#eab308;font-size:.5rem;font-weight:800;letter-spacing:.18em}
.uth-tk-hex .uth-ic-crown{color:#eab308;font-size:.85rem;margin-bottom:.15rem}
.eb-bg{background:linear-gradient(135deg,#11998e 0%,#38ef7d 100%);clip-path:polygon(0 0,100% 0,100% 100%,20% 100%)}
.eb-main .uth-tk-tagline{color:#059669}
.eb-main .uth-tk-meta{color:#374151}
.eb-main .uth-tk-perks{color:#059669;font-size:.55rem}
.eb-main .uth-tk-holder{color:#1f2937}
.eb-badge{width:52px;height:52px;background:#fff;border:2px solid #10b981;top:50%;right:6.4rem;transform:translateY(-50%);color:#065f46}
.eb-badge small{font-size:.42rem;font-weight:900;text-transform:uppercase}
.eb-badge b{color:#16a34a;font-size:.85rem}
.eb-stub{background:#fff;color:#1f2937;border-left:2px dashed rgba(0,0,0,.2)}
.eb-stub h3{color:#047857}
.eb-stub .uth-tk-id-label{color:#6b7280}
.ga-bg{background:linear-gradient(135deg,#0052D4 0%,#4364F7 50%,#6FB1FC 100%);clip-path:polygon(0 0,100% 0,100% 100%,30% 100%)}
.ga-main .uth-tk-tagline{color:#2563eb}
.ga-main .uth-tk-meta{color:#374151}
.ga-main .uth-tk-perks{color:#2563eb}
.ga-main .uth-tk-holder{color:#1f2937}
.ga-stub{background:#0052d4}
.grp-bg{background:linear-gradient(135deg,#7F00FF 0%,#E100FF 100%);clip-path:polygon(10% 0,100% 0,100% 100%,0 100%)}
.grp-main .uth-tk-tagline{color:#7c3aed}
.grp-main .uth-tk-meta{color:#374151}
.grp-main .uth-tk-perks{color:#7c3aed}
.grp-main .uth-tk-group-note{font-size:.6rem;font-weight:900;color:#7c3aed;margin:.45rem 0 0;letter-spacing:.08em}
.grp-main .uth-tk-holder{color:#1f2937}
.grp-stub{background:#7f00ff}
.sn-main{background:#c0392b;color:#fff;justify-content:center}
.sn-main .uth-tk-tagline{color:rgba(255,255,255,.85)}
.sn-main .uth-tk-meta{color:rgba(255,255,255,.9)}
.sn-main .uth-tk-holder{color:rgba(255,255,255,.9)}
.sn-mid{background:#c0392b;width:104px}
.sn-mid .uth-tk-vline{font-size:.5rem;opacity:.8;letter-spacing:.16em;font-weight:700}
.sn-mid b{font-size:.62rem;margin:.1rem 0 .4rem;font-weight:800}
.sn-stub{background:#922b21;width:104px}
.uth-tk-notes{display:grid;grid-template-columns:1fr 1fr;gap:.3rem .9rem;margin-top:.5rem;font-size:.6rem;font-weight:800}
.uth-tk-notes span{display:flex;align-items:center;gap:.3rem}
.uth-tk-ic{display:inline-block;flex:none;vertical-align:-2px}
.uth-tk-meta svg{width:11px;height:11px;vertical-align:-2px;margin-top:1px}
.uth-tk-perks svg{width:10px;height:10px;vertical-align:-2px}
.vvip-main{background:linear-gradient(160deg,#17130a,#0d0b06 60%,#151007);color:#fff}
.vvip-main::after{content:'';position:absolute;inset:0;z-index:0;background:repeating-linear-gradient(115deg,transparent 0 26px,rgba(232,182,76,.05) 26px 27px);pointer-events:none}
.vvip-main>*{position:relative;z-index:1}
.vvip-main .uth-tk-brand-line,.vvip-main .uth-tk-meta{color:#e5e7eb}
.vvip-main .uth-tk-meta svg,.vvip-main .uth-tk-perks svg{color:#e8b64c}
.vvip-main .uth-tk-event h2{color:transparent;background:linear-gradient(90deg,#f5deab,#e8b64c);-webkit-background-clip:text;background-clip:text}
.vvip-main .uth-tk-tagline{color:#f3f4f6;font-weight:600;opacity:.92}
.vvip-main .uth-tk-perks{color:#e8c877;border-top:1px solid rgba(232,182,76,.28);padding-top:.55rem}
.vvip-main .uth-tk-holder{color:rgba(255,255,255,.85)}
.vvip-stub{background:#0f0d08;border-left:2px dashed rgba(232,182,76,.35)}
.vvip-stub h3{color:#e8b64c}
.vvip-stub .uth-tk-id-label{color:rgba(232,195,119,.72)}
.vvip-stub .uth-tk-rowseat i{opacity:.7;color:rgba(232,195,119,.8)}
@media (max-width:560px){
.uth-tk{max-width:100%;min-height:208px;border-radius:14px}
.uth-tk-main{padding:1rem}
.uth-tk-event h2{font-size:1rem}
.uth-tk-hex{width:60px;height:72px;right:1.2rem}
.uth-tk-qr{width:64px;height:64px}
.uth-tk-stub{width:100px}
.uth-tk-badge{right:5.2rem}
}
CSS;
}
