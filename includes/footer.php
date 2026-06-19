        <!-- Footer -->
        <footer class="mt-5 py-3 border-top text-center text-muted small no-print">
            <p class="m-0">&copy; <?php echo date('Y'); ?> Master Data Sekolah. Semua Hak Cipta Dilindungi.</p>
            <p class="m-0 text-muted" style="font-size: 10px;">Dibuat untuk Performa Tinggi pada Sistem Warisan (RAM 4GB)</p>
        </footer>
    </div>
</div>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom global JS -->
<script src="<?php echo $prefix; ?>assets/js/main.js"></script>

<?php if (isset($extra_js)): ?>
    <?php echo $extra_js; ?>
<?php endif; ?>

</body>
</html>
