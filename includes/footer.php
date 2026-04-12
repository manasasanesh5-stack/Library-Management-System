<?php // includes/footer.php ?>

<?php
// Compute project base path dynamically.
// Examples:
//   /admin/books.php                -> ''
//   /library_system/admin/books.php -> '/library_system'
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'], 2), '/\\');
if ($basePath === '/' || $basePath === '\\') {
    $basePath = '';
}

$jsPath = $basePath . '/public/js/main.js';
?>

</main><!-- /.main-content -->

<footer class="site-footer">
  <p>&copy; <?= date('Y') ?> <?= e(APP_NAME) ?> &mdash; Secure Library Management System</p>
</footer>

<script src="<?= e($jsPath) ?>"></script>
</body>
</html>