<?php
$flash = getFlash();
?>
<div id="toast-container"></div>
<?php if ($flash): ?>
<div id="flash-message"
     data-message="<?= htmlspecialchars($flash['message']) ?>"
     data-type="<?= htmlspecialchars($flash['type']) ?>"
     style="display:none"></div>
<?php endif; ?>
<script src="<?= defined('BASE_URL') ? BASE_URL : '/campusvents' ?>/assets/js/main.js"></script>
</body>
</html>
