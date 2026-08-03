<?php
// footer.php
?>
<footer style="position:fixed;bottom:0;left:0;right:0;background:rgba(0,0,0,0.25);color:#fff;padding:8px;text-align:center;font-size:14px;">
  &copy; <?=date('Y')?> SYMOTECH
</footer>
<script>
    document.addEventListener('input', function (e) {
        if (e.target.tagName.toLowerCase() === 'input' && e.target.type === 'text' || e.target.tagName.toLowerCase() === 'textarea') {
            var start = e.target.selectionStart;
            var end = e.target.selectionEnd;
            e.target.value = e.target.value.toUpperCase();
            e.target.setSelectionRange(start, end);
        }
    });
</script>
