document.addEventListener('DOMContentLoaded', () => {
    // 1. Dark Mode Toggle Logic
    const htmlElement = document.documentElement;
    const themeToggle = document.getElementById('theme-toggle');
    
    // Check local storage or system preference
    const savedTheme = localStorage.getItem('theme');
    const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    
    if (savedTheme === 'dark' || (!savedTheme && systemPrefersDark)) {
        htmlElement.setAttribute('data-bs-theme', 'dark');
        if (themeToggle) themeToggle.checked = true;
    } else {
        htmlElement.setAttribute('data-bs-theme', 'light');
        if (themeToggle) themeToggle.checked = false;
    }
    
    if (themeToggle) {
        themeToggle.addEventListener('change', () => {
            if (themeToggle.checked) {
                htmlElement.setAttribute('data-bs-theme', 'dark');
                localStorage.setItem('theme', 'dark');
            } else {
                htmlElement.setAttribute('data-bs-theme', 'light');
                localStorage.setItem('theme', 'light');
            }
        });
    }

    // 2. Sidebar Toggle for Mobile Devices
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            sidebar.classList.toggle('show');
        });
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', (e) => {
            if (sidebar.classList.contains('show') && !sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('show');
            }
        });
    }

    // 3. Auto-dismiss Bootstrap Alerts after 4 seconds
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getInstance(alert);
            if (bsAlert) {
                bsAlert.close();
            }
        }, 4000);
    });

    // 4. Live Clock (Hari, Tanggal, Jam hingga Detik)
    const clockDisplay = document.getElementById('clock-display');
    if (clockDisplay) {
        const formatDigits = (val) => String(val).padStart(2, '0');
        const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        
        const updateClock = () => {
            const now = new Date();
            const dayName = days[now.getDay()];
            const date = now.getDate();
            const monthName = months[now.getMonth()];
            const year = now.getFullYear();
            const hours = formatDigits(now.getHours());
            const minutes = formatDigits(now.getMinutes());
            const seconds = formatDigits(now.getSeconds());
            
            clockDisplay.textContent = `${dayName}, ${date} ${monthName} ${year} - ${hours}:${minutes}:${seconds}`;
        };
        
        updateClock();
        setInterval(updateClock, 1000);
    }
});

// Confirmation helper
function confirmDelete(message = 'Apakah Anda yakin ingin menghapus data ini?') {
    return confirm(message);
}
