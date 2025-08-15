<script src="https://unpkg.com/lucide@latest"></script>
<script>
    lucide.createIcons();
</script>
<?php if ($toast_message): ?>
    <div class="toast">
        <div class="alert alert-success">
            <span class="text-gray-100"><?php echo htmlspecialchars($toast_message); ?></span>
        </div>
    </div>

    <script>
        // Hide toast after 3 seconds
        setTimeout(() => {
            document.querySelector('.toast')?.remove();
        }, 3000);
    </script>
<?php endif; ?>
</body>

</html>