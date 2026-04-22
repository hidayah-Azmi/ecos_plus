    </div> <!-- End content-container -->
</div> <!-- End main-content -->

<script>
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('toggleSidebarBtn');
    const overlay = document.getElementById('sidebarOverlay');
    
    toggleBtn.addEventListener('click', function() {
        sidebar.classList.toggle('show');
        overlay.classList.toggle('active');
    });
    
    overlay.addEventListener('click', function() {
        sidebar.classList.remove('show');
        overlay.classList.remove('active');
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>