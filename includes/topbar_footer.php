</div> <!-- End content-area -->

<script>
    // Mobile menu toggle
    const mobileToggle = document.getElementById('mobileToggleBtn');
    const mobileMenu = document.getElementById('mobileMenu');
    
    mobileToggle.addEventListener('click', function() {
        mobileMenu.classList.toggle('show');
    });
    
    // Close mobile menu when clicking outside
    document.addEventListener('click', function(event) {
        if (!mobileMenu.contains(event.target) && !mobileToggle.contains(event.target)) {
            mobileMenu.classList.remove('show');
        }
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>