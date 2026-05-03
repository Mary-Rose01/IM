
<script>
// Generic confirm-delete
document.querySelectorAll('.confirm-delete').forEach(btn => {
    btn.addEventListener('click', function(e) {
        if (!confirm('Are you sure you want to delete this record? This action cannot be undone.')) {
            e.preventDefault();
        }
    });
});

// Auto-dismiss alerts
setTimeout(() => {
    document.querySelectorAll('.alert.auto-dismiss').forEach(a => {
        a.style.opacity = '0';
        a.style.transition = 'opacity 0.4s';
        setTimeout(() => a.remove(), 400);
    });
}, 3500);

// Modal toggles
document.querySelectorAll('[data-modal]').forEach(trigger => {
    trigger.addEventListener('click', () => {
        const id = trigger.getAttribute('data-modal');
        document.getElementById(id)?.classList.toggle('open');
    });
});
document.querySelectorAll('.modal-close').forEach(btn => {
    btn.addEventListener('click', () => {
        btn.closest('.modal-overlay')?.classList.remove('open');
    });
});
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) overlay.classList.remove('open');
    });
});
</script>
</body>
</html>
