<?php
/**
 * Compatibility shim for the admin reset-password URL.
 *
 * The real one-time reset script lives at:
 *   /reset_admin_password.php
 *
 * This wrapper keeps the older /admin/reset_admin_password.php URL working
 * for any existing deployment bookmarks or instructions.
 */

require_once __DIR__ . '/../reset_admin_password.php';
