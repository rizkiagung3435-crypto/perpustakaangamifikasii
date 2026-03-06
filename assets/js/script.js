// Dark Mode Toggle
document.addEventListener('DOMContentLoaded', function() {
    // Check for saved theme preference
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.body.classList.add('dark-mode');
    }

    // Theme toggle button
    const themeToggle = document.getElementById('theme-toggle');
    if (themeToggle) {
        themeToggle.addEventListener('click', function() {
            document.body.classList.toggle('dark-mode');
            const theme = document.body.classList.contains('dark-mode') ? 'dark' : 'light';
            localStorage.setItem('theme', theme);
        });
    }

    // Animate progress bars on scroll
    const progressBars = document.querySelectorAll('.progress-bar');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const width = entry.target.style.width;
                entry.target.style.width = '0';
                setTimeout(() => {
                    entry.target.style.width = width;
                }, 100);
            }
        });
    });

    progressBars.forEach(bar => observer.observe(bar));

    // Live search for books
    const searchInput = document.getElementById('search-books');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(function() {
            const searchTerm = this.value.toLowerCase();
            const bookCards = document.querySelectorAll('.book-card');
            
            bookCards.forEach(card => {
                const title = card.querySelector('.book-title').textContent.toLowerCase();
                const author = card.querySelector('.book-author').textContent.toLowerCase();
                
                if (title.includes(searchTerm) || author.includes(searchTerm)) {
                    card.style.display = 'block';
                    card.classList.add('fade-in');
                } else {
                    card.style.display = 'none';
                }
            });
        }, 300));
    }

    // Achievement popup
    function showAchievementPopup(badgeName, icon) {
        const popup = document.createElement('div');
        popup.className = 'achievement-popup glass-card';
        popup.innerHTML = `
            <div class="badge-icon">${icon}</div>
            <div class="badge-info">
                <h3>Achievement Unlocked!</h3>
                <p>${badgeName}</p>
            </div>
        `;
        
        document.body.appendChild(popup);
        
        setTimeout(() => {
            popup.classList.add('show');
        }, 100);
        
        setTimeout(() => {
            popup.classList.remove('show');
            setTimeout(() => {
                popup.remove();
            }, 300);
        }, 3000);
    }

    // Book borrow confirmation
    const borrowButtons = document.querySelectorAll('.btn-borrow');
    borrowButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const bookTitle = this.dataset.bookTitle;
            
            if (confirm(`Apakah Anda yakin ingin meminjam buku "${bookTitle}"?`)) {
                showNotification('success', `Buku "${bookTitle}" berhasil dipinjam! +20 XP`);
            }
        });
    });

    // Notification system
    function showNotification(type, message) {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type} glass-card`;
        notification.innerHTML = message;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.classList.add('show');
        }, 100);
        
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => {
                notification.remove();
            }, 300);
        }, 3000);
    }

    // Leaderboard animation
    const leaderboardItems = document.querySelectorAll('.leaderboard-item');
    leaderboardItems.forEach((item, index) => {
        item.style.animation = `slideIn 0.5s ease ${index * 0.1}s both`;
    });

    // Counter animation for stats
    const statValues = document.querySelectorAll('.stat-value');
    statValues.forEach(stat => {
        const target = parseInt(stat.textContent);
        let current = 0;
        const increment = target / 50;
        
        const updateCounter = () => {
            if (current < target) {
                current += increment;
                stat.textContent = Math.ceil(current);
                requestAnimationFrame(updateCounter);
            } else {
                stat.textContent = target;
            }
        };
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    updateCounter();
                    observer.unobserve(entry.target);
                }
            });
        });
        
        observer.observe(stat);
    });

    // Book rating system
    const ratingStars = document.querySelectorAll('.rating-star');
    ratingStars.forEach(star => {
        star.addEventListener('click', function() {
            const rating = this.dataset.rating;
            const bookId = this.dataset.bookId;
            
            // Remove active class from all stars
            this.parentElement.querySelectorAll('.rating-star').forEach(s => {
                s.classList.remove('active');
            });
            
            // Add active class up to selected star
            this.parentElement.querySelectorAll('.rating-star').forEach((s, index) => {
                if (index < rating) {
                    s.classList.add('active');
                }
            });
            
            // Submit rating via AJAX
            submitRating(bookId, rating);
        });
    });

    // AJAX function for rating
    function submitRating(bookId, rating) {
        fetch('ajax/submit_rating.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `book_id=${bookId}&rating=${rating}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('success', 'Rating berhasil dikirim! +30 XP');
            }
        })
        .catch(error => console.error('Error:', error));
    }

    // Debounce function for search
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func.apply(this, args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Book filter by category
    const categoryFilters = document.querySelectorAll('.category-filter');
    categoryFilters.forEach(filter => {
        filter.addEventListener('click', function() {
            const category = this.dataset.category;
            const bookCards = document.querySelectorAll('.book-card');
            
            categoryFilters.forEach(f => f.classList.remove('active'));
            this.classList.add('active');
            
            bookCards.forEach(card => {
                if (category === 'all' || card.dataset.category === category) {
                    card.style.display = 'block';
                    card.classList.add('fade-in');
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });
});

// XP Progress Animation
function updateXP(currentXP, targetXP) {
    const progressBar = document.querySelector('.xp-progress');
    const percentage = (currentXP / targetXP) * 100;
    
    progressBar.style.width = percentage + '%';
    progressBar.innerHTML = `${currentXP}/${targetXP} XP`;
    
    if (percentage >= 100) {
        showLevelUpNotification();
    }
}

// Level Up Notification
function showLevelUpNotification() {
    const levelUp = document.createElement('div');
    levelUp.className = 'level-up glass-card';
    levelUp.innerHTML = `
        <h2>🎉 LEVEL UP! 🎉</h2>
        <p>Selamat! Anda naik level!</p>
    `;
    
    document.body.appendChild(levelUp);
    
    setTimeout(() => {
        levelUp.classList.add('show');
    }, 100);
    
    setTimeout(() => {
        levelUp.classList.remove('show');
        setTimeout(() => {
            levelUp.remove();
        }, 300);
    }, 3000);
}