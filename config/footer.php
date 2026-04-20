<?php require_once __DIR__ . '/_chat_widget.php'; ?>
<?php require_once __DIR__ . '/_card_anim.php'; ?>
<?php require_once __DIR__ . '/_confirm_modal.php'; ?>
</div><!-- end container -->
<!-- 全域頭像彈出選單 -->
<div id="avd-popup">
    <div class="avd-pop-item" id="avd-go-profile"><i class="bi bi-person"></i> 個人頁面</div>
    <div class="avd-pop-item" id="avd-go-chat"><i class="bi bi-chat"></i> 聊天</div>
</div>
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/js/main.js?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'].'/js/main.js') ?>"></script>
<script src="/js/fab-drag.js?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'].'/js/fab-drag.js') ?>"></script>
</body>
</html>
