<?php
/**
 * Uthenga - Admin Dashboard Footer
 */
?>
        </div>
      </main>
    </div>
    <footer class="dashboard-footer">
      <div>UTHENGA &copy; <?= date('Y') ?></div>
      <div>Version <?= e(APP_VERSION) ?> | <a href="<?= BASE_URL ?>admin/support.php">Support</a> | All rights reserved.</div>
    </footer>
  </div>
  <script src="<?= BASE_URL ?>assets/js/main.js?v=<?= rawurlencode(APP_VERSION) ?>"></script>
</body>
</html>
