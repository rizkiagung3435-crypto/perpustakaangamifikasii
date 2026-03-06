<?php
/**
 * Navigation Bar Component
 */
?>
<nav class="navbar">
    <div class="nav-container">
        <div class="logo">
            <a href="dashboard.php">
                <h2 class="logo-text">
                    <span class="logo-icon">📚</span>
                    <span class="logo-title">Perpustakaan Digital</span>
                </h2>
            </a>
        </div>
        
        <button class="mobile-menu-toggle" id="mobileMenuToggle">
            <span></span>
            <span></span>
            <span></span>
        </button>
        
        <ul class="nav-links" id="navLinks">
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                    <span class="nav-icon">🏠</span>
                    <span class="nav-text">Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="siswa/katalog.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'katalog.php') !== false ? 'active' : ''; ?>">
                    <span class="nav-icon">📚</span>
                    <span class="nav-text">Katalog</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="siswa/leaderboard.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'leaderboard.php') !== false ? 'active' : ''; ?>">
                    <span class="nav-icon">🥇</span>
                    <span class="nav-text">Leaderboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="siswa/misi.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'misi.php') !== false ? 'active' : ''; ?>">
                    <span class="nav-icon">🎯</span>
                    <span class="nav-text">Misi</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="siswa/profil.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'profil.php') !== false ? 'active' : ''; ?>">
                    <span class="nav-icon">👤</span>
                    <span class="nav-text">Profil</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="logout.php" class="nav-link logout-link">
                    <span class="nav-icon">🚪</span>
                    <span class="nav-text">Logout</span>
                </a>
            </li>
            <li class="nav-item theme-toggle-container">
                <button id="theme-toggle" class="theme-toggle-btn" title="Ganti Tema">
                    <span class="theme-icon">🌓</span>
                </button>
            </li>
        </ul>
    </div>
</nav>

<!-- Mobile Menu Overlay -->
<div class="mobile-menu-overlay" id="mobileMenuOverlay"></div>

<script>
// Mobile menu toggle
document.getElementById('mobileMenuToggle')?.addEventListener('click', function() {
    document.getElementById('navLinks').classList.toggle('active');
    document.getElementById('mobileMenuOverlay').classList.toggle('active');
    this.classList.toggle('active');
});

document.getElementById('mobileMenuOverlay')?.addEventListener('click', function() {
    document.getElementById('navLinks').classList.remove('active');
    document.getElementById('mobileMenuToggle').classList.remove('active');
    this.classList.remove('active');
});
</script>