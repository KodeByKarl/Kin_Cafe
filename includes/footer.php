    </div>
    <?php $jsVer = isset($assetVer) ? (int) $assetVer : ((int) (@filemtime(__DIR__ . '/../assets/js/script.js') ?: time())); ?>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="assets/js/alert-modal.js?v=<?php echo $jsVer; ?>"></script>
    <script src="assets/js/password-toggle.js?v=<?php echo $jsVer; ?>"></script>
    <script src="assets/js/live-search.js?v=<?php echo $jsVer; ?>"></script>
    <script src="assets/js/cash-change.js?v=<?php echo $jsVer; ?>"></script>
    <script src="assets/js/form-autosave.js?v=<?php echo $jsVer; ?>"></script>
    <script src="assets/js/script.js?v=<?php echo $jsVer; ?>"></script>
</body>
</html>
