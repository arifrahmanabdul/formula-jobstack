// public/js/recruiter.js

document.addEventListener('DOMContentLoaded', function() {
    console.log("Recruiter dashboard script loaded.");

    /**
     * Sets the active class on the correct sidebar navigation link based on the current URL.
     */
    function setActiveNavigation() {
        const currentPath = window.location.pathname;
        const navLinks = document.querySelectorAll('.nav-link');

        navLinks.forEach(link => {
            const linkPath = link.getAttribute('href');
            // Remove active class from all links first
            link.classList.remove('active');

            // Check if the link's href is a prefix of the current path
            if (linkPath && currentPath.startsWith(linkPath)) {
                // To avoid marking parent links as active, check for exact match or match with trailing slash
                if (currentPath === linkPath || currentPath.startsWith(linkPath + '/')) {
                     link.classList.add('active');
                }
            }
        });
        
        // Ensure the main dashboard link is active only on its specific page
        const dashboardLink = document.querySelector('a[href$="/dashboard/recruiter"]');
        if (dashboardLink && currentPath !== dashboardLink.getAttribute('href')) {
             dashboardLink.classList.remove('active');
        }
    }

    /**
     * Animates a number from 0 to a target value with a count-up effect.
     * @param {HTMLElement} element The element whose textContent will be animated.
     * @param {number} targetNumber The final number to animate to.
     */
    function animateNumber(element, targetNumber) {
        if (!element) return;
        const duration = 1500; // Animation duration in milliseconds
        const startTime = performance.now();

        const animate = (currentTime) => {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            // Ease-out-cubic easing function for a smoother effect
            const easeOutCubic = 1 - Math.pow(1 - progress, 3);
            const currentNumber = Math.floor(easeOutCubic * targetNumber);

            // Format number with Indonesian locale for thousands separators
            element.textContent = currentNumber.toLocaleString('id-ID');

            if (progress < 1) {
                requestAnimationFrame(animate);
            } else {
                // Ensure the final number is exactly the target
                element.textContent = targetNumber.toLocaleString('id-ID');
            }
        };

        requestAnimationFrame(animate);
    }

    /**
     * Initializes all dashboard functionalities.
     */
    function initializeDashboard() {
        // Animate stat cards for a nice visual entry
        const statCards = document.querySelectorAll('.stat-card, .action-card');
        statCards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            setTimeout(() => {
                card.style.transition = 'all 0.5s ease-out';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });
        
        // Find all stat value elements and trigger the animation
        const statValues = document.querySelectorAll('.stat-value');
        statValues.forEach(statValue => {
            const finalValue = parseInt(statValue.textContent.trim(), 10);
            if (!isNaN(finalValue)) {
                animateNumber(statValue, finalValue);
            }
        });

        // Set the active navigation link
        setActiveNavigation();
    }

    // Run the initialization
    initializeDashboard();
});
