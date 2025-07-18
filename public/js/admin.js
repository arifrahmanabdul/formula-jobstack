// Admin Dashboard JavaScript
class AdminDashboard {
  constructor() {
    this.sidebar = document.querySelector(".admin-sidebar");
    this.sidebarToggle = document.querySelector(".sidebar-toggle");
    this.overlay = null;
    this.navLinks = document.querySelectorAll(".nav-link");
    this.statsCards = document.querySelectorAll(".stat-card");
    this.actionCards = document.querySelectorAll(".action-card");
    this.mainContent = document.querySelector(".admin-main");
    this.resizeTimeout = null;
    this.currentTooltip = null;
    this.gtag = window.gtag; // Declare gtag variable
    this.isLoading = false;
    this.refreshInterval = null;

    this.init();
  }

  init() {
    this.createOverlay();
    this.bindEvents();
    this.loadDashboardData();
    this.initializeTooltips();
    this.handleResponsiveNavigation();
    this.setActiveNavigation();
    this.startAutoRefresh();
    this.initializeAnimations();
  }

  createOverlay() {
    this.overlay = document.createElement("div");
    this.overlay.className = "sidebar-overlay";
    document.body.appendChild(this.overlay);
  }

  bindEvents() {
    // Sidebar toggle
    if (this.sidebarToggle) {
      this.sidebarToggle.addEventListener("click", () => this.toggleSidebar());
    }

    // Overlay click to close sidebar
    if (this.overlay) {
      this.overlay.addEventListener("click", () => this.closeSidebar());
    }

    // Navigation link clicks
    this.navLinks.forEach((link) => {
      link.addEventListener("click", (e) => this.handleNavigation(e));
    });

    // Stats card clicks
    this.statsCards.forEach((card) => {
      card.addEventListener("click", () => this.handleStatCardClick(card));
    });

    // Action card hover effects
    this.actionCards.forEach((card) => {
      card.addEventListener("mouseenter", () => this.handleActionHover(card, true));
      card.addEventListener("mouseleave", () => this.handleActionHover(card, false));
    });

    // Keyboard navigation - FIXED: Remove Ctrl+R override
    document.addEventListener("keydown", (e) => this.handleKeyboardNavigation(e));

    // Window resize
    window.addEventListener("resize", () => this.handleResize());

    // Page visibility change
    document.addEventListener("visibilitychange", () => this.handleVisibilityChange());
  }

  toggleSidebar() {
    if (!this.sidebar) return;

    const isActive = this.sidebar.classList.contains("active");

    if (isActive) {
      this.closeSidebar();
    } else {
      this.openSidebar();
    }
  }

  openSidebar() {
    if (!this.sidebar) return;

    this.sidebar.classList.add("active");
    if (this.overlay) {
      this.overlay.classList.add("active");
    }
    document.body.style.overflow = "hidden";

    // Focus first navigation item for accessibility
    const firstNavLink = this.sidebar.querySelector(".nav-link");
    if (firstNavLink) {
      setTimeout(() => firstNavLink.focus(), 100);
    }
  }

  closeSidebar() {
    if (!this.sidebar) return;

    this.sidebar.classList.remove("active");
    if (this.overlay) {
      this.overlay.classList.remove("active");
    }
    document.body.style.overflow = "";
  }

  handleNavigation(e) {
    const link = e.currentTarget;
    const href = link.getAttribute("href");

    // Don't prevent default navigation for actual links
    if (href && href !== "#") {
      // Update active state
      this.navLinks.forEach((l) => l.classList.remove("active"));
      link.classList.add("active");

      // Close sidebar on mobile after navigation
      if (window.innerWidth <= 1024) {
        this.closeSidebar();
      }

      // Analytics tracking
      this.trackNavigation(href);

      // Let the browser handle the navigation
      return;
    }

    // Prevent default only for non-navigation links
    e.preventDefault();
  }

  handleStatCardClick(card) {
    const statType = card.dataset.stat;

    // Add click animation
    card.style.transform = "scale(0.98)";
    setTimeout(() => {
      card.style.transform = "";
    }, 150);

    // Navigate to relevant page
    const routes = {
      total_jobs: "/admin/jobs",
      total_companies: "/admin/companies",
      total_applications: "/admin/applications",
      total_users: "/admin/users",
    };

    if (routes[statType]) {
      window.location.href = routes[statType];
    }
  }

  handleActionHover(card, isHover) {
    const icon = card.querySelector(".action-icon");
    if (icon) {
      if (isHover) {
        icon.style.transform = "scale(1.1)";
      } else {
        icon.style.transform = "";
      }
    }
  }

  handleKeyboardNavigation(e) {
    // ESC key to close sidebar
    if (e.key === "Escape" && this.sidebar && this.sidebar.classList.contains("active")) {
      this.closeSidebar();
    }

    // Ctrl/Cmd + B to toggle sidebar
    if ((e.ctrlKey || e.metaKey) && e.key === "b") {
      e.preventDefault();
      this.toggleSidebar();
    }

    // REMOVED: Ctrl+R override - let browser handle normal refresh
    // F5 to refresh data (but don't prevent default)
    if (e.key === "F5") {
      // Don't prevent default - let browser refresh normally
      // Just track the event
      this.trackEvent("manual_refresh", { method: "F5" });
    }
  }

  handleResize() {
    // Close sidebar on desktop resize
    if (window.innerWidth > 1024) {
      this.closeSidebar();
    }

    // Debounce resize handling
    clearTimeout(this.resizeTimeout);
    this.resizeTimeout = setTimeout(() => {
      this.updateLayout();
    }, 250);
  }

  handleVisibilityChange() {
    if (!document.hidden) {
      // Refresh data when page becomes visible
      this.refreshDashboardData();
    }
  }

  updateLayout() {
    // Recalculate grid layouts if needed
    const statsGrid = document.querySelector(".stats-grid");

    if (statsGrid) {
      statsGrid.style.display = "none";
      statsGrid.offsetHeight; // Force reflow
      statsGrid.style.display = "";
    }
  }

  async loadDashboardData() {
    if (this.isLoading) return;

    this.isLoading = true;
    this.showLoadingState();

    try {
      // Load statistics and activity in parallel
      const [statsResponse, activityResponse] = await Promise.all([this.fetchStatistics(), this.fetchRecentActivity()]);

      console.log("Stats Response:", statsResponse);
      console.log("Activity Response:", activityResponse);

      if (statsResponse && statsResponse.success) {
        this.updateStatistics(statsResponse.data);
        this.updateHeaderStats(statsResponse.data);
      } else {
        console.error("Failed to load statistics:", statsResponse);
        this.showErrorNotification("Gagal memuat statistik");
      }

      if (activityResponse && activityResponse.success) {
        this.updateRecentActivity(activityResponse.data);
      } else {
        console.error("Failed to load activity:", activityResponse);
        this.showErrorState();
      }
    } catch (error) {
      console.error("Error loading dashboard data:", error);
      this.showErrorState();
      this.showErrorNotification("Terjadi kesalahan saat memuat data");
    } finally {
      this.isLoading = false;
      this.hideLoadingState();
    }
  }

  async fetchStatistics() {
    try {
      const response = await fetch("/api/admin/statistics", {
        method: "GET",
        headers: {
          "Content-Type": "application/json",
          "X-Requested-With": "XMLHttpRequest",
        },
        credentials: "same-origin",
      });

      console.log("Statistics response status:", response.status);

      if (!response.ok) {
        const errorText = await response.text();
        console.error("Statistics API error:", errorText);
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const data = await response.json();
      console.log("Statistics data received:", data);
      return data;
    } catch (error) {
      console.error("Failed to fetch statistics:", error);
      return { success: false, error: error.message };
    }
  }

  async fetchRecentActivity() {
    try {
      const response = await fetch("/api/admin/recent-activity", {
        method: "GET",
        headers: {
          "Content-Type": "application/json",
          "X-Requested-With": "XMLHttpRequest",
        },
        credentials: "same-origin",
      });

      console.log("Activity response status:", response.status);

      if (!response.ok) {
        const errorText = await response.text();
        console.error("Activity API error:", errorText);
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const data = await response.json();
      console.log("Activity data received:", data);
      return data;
    } catch (error) {
      console.error("Failed to fetch recent activity:", error);
      return { success: false, error: error.message };
    }
  }

  updateStatistics(data) {
    console.log("Updating statistics with data:", data);

    // Update total jobs
    this.updateStatCard("total_jobs", {
      value: data.total_jobs.count,
      change: data.total_jobs.growth_text,
      isPositive: data.total_jobs.growth >= 0,
    });

    // Update total companies
    this.updateStatCard("total_companies", {
      value: data.total_companies.count,
      change: data.total_companies.growth_text,
      isPositive: data.total_companies.growth >= 0,
    });

    // Update total applications
    this.updateStatCard("total_applications", {
      value: data.total_applications.count,
      change: data.total_applications.growth_text,
      isPositive: data.total_applications.growth >= 0,
    });

    // Update total users
    this.updateStatCard("total_users", {
      value: data.total_users.count,
      change: data.total_users.growth_text,
      isPositive: data.total_users.growth >= 0,
    });
  }

  updateStatCard(statType, data) {
    const card = document.querySelector(`[data-stat="${statType}"]`);
    if (!card) {
      console.warn(`Stat card not found for type: ${statType}`);
      return;
    }

    const valueElement = card.querySelector(".stat-value");
    const changeElement = card.querySelector(".stat-change");

    console.log(`Updating ${statType}:`, data);

    if (valueElement) {
      // Remove skeleton class first
      valueElement.classList.remove("skeleton");
      this.animateNumber(valueElement, data.value);
    }

    if (changeElement) {
      // Remove skeleton class first
      changeElement.classList.remove("skeleton");
      changeElement.textContent = data.change;
      changeElement.className = `stat-change ${data.isPositive ? "positive" : "negative"}`;

      // Add appropriate icon
      const icon = changeElement.querySelector("i") || document.createElement("i");
      icon.className = data.isPositive ? "fas fa-arrow-up" : "fas fa-arrow-down";
      if (!changeElement.querySelector("i")) {
        changeElement.prepend(icon);
      }
    }
  }

  updateHeaderStats(data) {
    console.log("Updating header stats with data:", data);

    // Update header statistics
    const headerStats = document.querySelectorAll(".stat-number");
    if (headerStats.length >= 3) {
      // Remove skeleton class and animate numbers
      headerStats[0].classList.remove("skeleton");
      headerStats[1].classList.remove("skeleton");
      headerStats[2].classList.remove("skeleton");

      this.animateNumber(headerStats[0], data.total_jobs.count);
      this.animateNumber(headerStats[1], data.total_companies.count);
      this.animateNumber(headerStats[2], data.total_applications.count);
    }
  }

  updateRecentActivity(activities) {
    const activityList = document.querySelector(".activity-list");
    if (!activityList) return;

    console.log("Updating recent activity with:", activities);

    if (!activities || activities.length === 0) {
      activityList.innerHTML = `
        <div class="activity-item">
          <div class="activity-icon info">
            <i class="fas fa-info-circle"></i>
          </div>
          <div class="activity-content">
            <h5>Tidak ada aktivitas terbaru</h5>
            <p>Belum ada aktivitas yang tercatat dalam sistem</p>
          </div>
          <div class="activity-time">-</div>
        </div>
      `;
      return;
    }

    const activitiesHTML = activities
      .map(
        (activity) => `
      <div class="activity-item">
        <div class="activity-icon ${activity.color}">
          <i class="${activity.icon}"></i>
        </div>
        <div class="activity-content">
          <h5>${this.escapeHtml(activity.title)}</h5>
          <p>${this.escapeHtml(activity.description)}</p>
        </div>
        <div class="activity-time">${this.escapeHtml(activity.time)}</div>
      </div>
    `
      )
      .join("");

    activityList.innerHTML = activitiesHTML;

    // Add entrance animation
    const items = activityList.querySelectorAll(".activity-item");
    items.forEach((item, index) => {
      item.style.opacity = "0";
      item.style.transform = "translateY(20px)";
      setTimeout(() => {
        item.style.transition = "all 0.3s ease";
        item.style.opacity = "1";
        item.style.transform = "translateY(0)";
      }, index * 100);
    });
  }

  animateNumber(element, targetNumber) {
    const startNumber = 0;
    const duration = 1500;
    const startTime = performance.now();

    const animate = (currentTime) => {
      const elapsed = currentTime - startTime;
      const progress = Math.min(elapsed / duration, 1);

      // Easing function (ease-out-cubic)
      const easeOutCubic = 1 - Math.pow(1 - progress, 3);
      const currentNumber = Math.floor(startNumber + (targetNumber - startNumber) * easeOutCubic);

      element.textContent = this.formatNumber(currentNumber);

      if (progress < 1) {
        requestAnimationFrame(animate);
      }
    };

    requestAnimationFrame(animate);
  }

  formatNumber(num) {
    return new Intl.NumberFormat("id-ID").format(num);
  }

  showLoadingState() {
    // Show skeleton loading for stat cards
    document.querySelectorAll(".stat-value").forEach((element) => {
      if (!element.classList.contains("skeleton")) {
        element.classList.add("skeleton");
        element.textContent = "";
      }
    });

    document.querySelectorAll(".stat-change").forEach((element) => {
      if (!element.classList.contains("skeleton")) {
        element.classList.add("skeleton");
        element.textContent = "";
      }
    });

    // Show skeleton for header stats
    document.querySelectorAll(".stat-number").forEach((element) => {
      if (!element.classList.contains("skeleton")) {
        element.classList.add("skeleton");
        element.textContent = "";
      }
    });

    // Show loading for activity list
    const activityList = document.querySelector(".activity-list");
    if (activityList) {
      activityList.innerHTML = `
        <div class="loading-container">
          <div class="loading-spinner"></div>
        </div>
      `;
    }
  }

  hideLoadingState() {
    document.querySelectorAll(".skeleton").forEach((element) => {
      element.classList.remove("skeleton");
    });
  }

  showErrorState() {
    const activityList = document.querySelector(".activity-list");
    if (activityList) {
      activityList.innerHTML = `
        <div class="activity-item">
          <div class="activity-icon warning">
            <i class="fas fa-exclamation-triangle"></i>
          </div>
          <div class="activity-content">
            <h5>Gagal memuat data</h5>
            <p>Terjadi kesalahan saat memuat aktivitas terbaru. <a href="#" onclick="window.adminDashboard.refreshData()">Coba lagi</a></p>
          </div>
          <div class="activity-time">-</div>
        </div>
      `;
    }
  }

  async refreshData() {
    console.log("Manually refreshing data...");
    await this.loadDashboardData();
    this.showNotification("Data berhasil diperbarui", "success");
  }

  async refreshDashboardData() {
    // Only refresh if page has been hidden for more than 5 minutes
    const now = Date.now();
    const lastRefresh = localStorage.getItem("lastDashboardRefresh");

    if (!lastRefresh || now - Number.parseInt(lastRefresh) > 300000) {
      console.log("Auto-refreshing dashboard data...");
      await this.loadDashboardData();
      localStorage.setItem("lastDashboardRefresh", now.toString());
    }
  }

  startAutoRefresh() {
    // Refresh data every 5 minutes
    this.refreshInterval = setInterval(() => {
      if (!document.hidden && !this.isLoading) {
        console.log("Auto-refresh triggered");
        this.refreshData();
      }
    }, 300000); // 5 minutes
  }

  initializeAnimations() {
    // Add entrance animations for cards
    const cards = document.querySelectorAll(".stat-card, .action-card");
    cards.forEach((card, index) => {
      card.style.opacity = "0";
      card.style.transform = "translateY(20px)";
      setTimeout(() => {
        card.style.transition = "all 0.5s ease";
        card.style.opacity = "1";
        card.style.transform = "translateY(0)";
      }, index * 100);
    });
  }

  showNotification(message, type = "info") {
    // Remove existing notifications
    document.querySelectorAll(".notification").forEach((n) => n.remove());

    const notification = document.createElement("div");
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
      <i class="fas fa-${type === "success" ? "check-circle" : type === "error" ? "exclamation-circle" : "info-circle"}"></i>
      <span>${message}</span>
      <button onclick="this.parentElement.remove()">
        <i class="fas fa-times"></i>
      </button>
    `;

    // Add notification styles
    const colors = {
      success: "var(--success-color)",
      error: "var(--danger-color)",
      warning: "var(--warning-color)",
      info: "var(--info-color)",
    };

    notification.style.cssText = `
      position: fixed;
      top: 20px;
      right: 20px;
      background: ${colors[type] || colors.info};
      color: white;
      padding: 1rem 1.5rem;
      border-radius: var(--border-radius);
      box-shadow: var(--shadow-lg);
      z-index: 10000;
      display: flex;
      align-items: center;
      gap: 0.75rem;
      font-weight: 500;
      animation: slideInRight 0.3s ease;
      max-width: 400px;
    `;

    document.body.appendChild(notification);

    // Auto remove after 5 seconds
    setTimeout(() => {
      if (notification.parentElement) {
        notification.style.animation = "slideOutRight 0.3s ease";
        setTimeout(() => notification.remove(), 300);
      }
    }, 5000);
  }

  showErrorNotification(message) {
    this.showNotification(message, "error");
  }

  trackNavigation(href) {
    // Analytics tracking
    if (typeof this.gtag !== "undefined") {
      this.gtag("event", "admin_navigation", {
        page_path: href,
        timestamp: new Date().toISOString(),
      });
    }
  }

  trackEvent(action, data = {}) {
    // Analytics tracking
    if (typeof window.gtag !== "undefined") {
      window.gtag("event", action, {
        event_category: "admin_dashboard",
        ...data,
      });
    }

    // Console log for development
    console.log("Event tracked:", action, data);
  }

  escapeHtml(text) {
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
  }

  setActiveNavigation() {
    const currentPath = window.location.pathname;
    this.navLinks.forEach((link) => {
      link.classList.remove("active");
      if (link.getAttribute("href") === currentPath) {
        link.classList.add("active");
      }
    });
  }

  handleResponsiveNavigation() {
    // Handle responsive navigation behavior
    const mediaQuery = window.matchMedia("(max-width: 1024px)");

    const handleMediaChange = (e) => {
      if (!e.matches) {
        // Desktop view
        this.closeSidebar();
      }
    };

    if (mediaQuery.addListener) {
      mediaQuery.addListener(handleMediaChange);
    } else {
      mediaQuery.addEventListener("change", handleMediaChange);
    }
    handleMediaChange(mediaQuery);
  }

  initializeTooltips() {
    // Initialize tooltips if needed
    document.querySelectorAll("[data-tooltip]").forEach((element) => {
      element.addEventListener("mouseenter", (e) => {
        this.showTooltip(e.target, e.target.dataset.tooltip);
      });

      element.addEventListener("mouseleave", () => {
        this.hideTooltip();
      });
    });
  }

  showTooltip(element, text) {
    this.hideTooltip(); // Remove existing tooltip

    const tooltip = document.createElement("div");
    tooltip.className = "tooltip";
    tooltip.textContent = text;
    tooltip.style.cssText = `
      position: absolute;
      background: var(--gray-800);
      color: white;
      padding: 0.5rem;
      border-radius: 4px;
      font-size: 0.875rem;
      z-index: 1000;
      pointer-events: none;
      white-space: nowrap;
    `;

    document.body.appendChild(tooltip);

    const rect = element.getBoundingClientRect();
    tooltip.style.top = rect.top - tooltip.offsetHeight - 8 + "px";
    tooltip.style.left = rect.left + rect.width / 2 - tooltip.offsetWidth / 2 + "px";

    this.currentTooltip = tooltip;
  }

  hideTooltip() {
    if (this.currentTooltip) {
      this.currentTooltip.remove();
      this.currentTooltip = null;
    }
  }

  destroy() {
    // Cleanup
    if (this.refreshInterval) {
      clearInterval(this.refreshInterval);
    }

    if (this.overlay && this.overlay.parentElement) {
      this.overlay.remove();
    }

    this.hideTooltip();
  }

  // Public methods for external access
  static getInstance() {
    if (!AdminDashboard.instance) {
      AdminDashboard.instance = new AdminDashboard();
    }
    return AdminDashboard.instance;
  }
}

// Initialize dashboard when DOM is loaded
document.addEventListener("DOMContentLoaded", () => {
  console.log("Initializing Admin Dashboard...");
  window.adminDashboard = AdminDashboard.getInstance();
});

// Cleanup on page unload
window.addEventListener("beforeunload", () => {
  if (window.adminDashboard) {
    window.adminDashboard.destroy();
  }
  localStorage.setItem("lastDashboardRefresh", Date.now().toString());
});

// Add notification styles to head
const notificationStyles = document.createElement("style");
notificationStyles.textContent = `
  @keyframes slideInRight {
    from {
      transform: translateX(100%);
      opacity: 0;
    }
    to {
      transform: translateX(0);
      opacity: 1;
    }
  }

  @keyframes slideOutRight {
    from {
      transform: translateX(0);
      opacity: 1;
    }
    to {
      transform: translateX(100%);
      opacity: 0;
    }
  }

  .notification button {
    background: none;
    border: none;
    color: white;
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 50%;
    transition: background-color 0.2s ease;
  }

  .notification button:hover {
    background-color: rgba(255, 255, 255, 0.2);
  }
`;
document.head.appendChild(notificationStyles);

// Skills Management Class with Pagination - FIXED VERSION
class SkillsManager {
  constructor() {
    this.currentSkillId = null;
    this.skills = [];
    this.currentPage = 1;
    this.totalPages = 1;
    this.itemsPerPage = 10;
    this.searchQuery = "";

    this.init();
  }

  init() {
    this.bindEvents();
    this.loadSkills();
  }

  bindEvents() {
    // Add skill button
    const addBtn = document.getElementById("addSkillBtn");
    if (addBtn) {
      addBtn.addEventListener("click", () => this.openAddModal());
    }

    // Search functionality
    const searchInput = document.getElementById("searchInput");
    if (searchInput) {
      searchInput.addEventListener("input", (e) => this.handleSearch(e.target.value));
    }

    // Modal events
    document.addEventListener("click", (e) => {
      if (e.target.classList.contains("modal")) {
        this.closeModal();
        this.closeDeleteModal();
      }
    });

    // Form submission
    const skillForm = document.getElementById("skillForm");
    if (skillForm) {
      skillForm.addEventListener("submit", (e) => this.handleFormSubmit(e));
    }

    // Delete confirmation
    const confirmDeleteBtn = document.getElementById("confirmDeleteBtn");
    if (confirmDeleteBtn) {
      confirmDeleteBtn.addEventListener("click", () => this.confirmDelete());
    }

    // Keyboard events
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") {
        this.closeModal();
        this.closeDeleteModal();
      }
    });
  }

  async loadSkills(page = 1, search = "") {
    try {
      this.showLoading();

      const params = new URLSearchParams({
        page: page.toString(),
        search: search,
      });

      const response = await fetch(`/admin/skills?${params}`, {
        method: "GET",
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
      });

      if (!response.ok) {
        throw new Error("Gagal memuat data skills");
      }

      const result = await response.json();

      if (result.success) {
        this.skills = result.data;
        this.currentPage = result.pagination.current_page;
        this.totalPages = result.pagination.total_pages;
        this.renderSkills();
        this.renderPagination(result.pagination);
      } else {
        throw new Error(result.error || "Gagal memuat data skills");
      }
    } catch (error) {
      console.error("Error loading skills:", error);
      this.showError("Gagal memuat data skills: " + error.message);
    } finally {
      this.hideLoading();
    }
  }

  renderSkills() {
    const tbody = document.getElementById("skillsTableBody");
    const emptyState = document.getElementById("emptyState");
    const tableContainer = document.querySelector(".table-container");

    if (!tbody) return;

    if (this.skills.length === 0) {
      if (tableContainer) tableContainer.style.display = "none";
      if (emptyState) emptyState.style.display = "block";
      return;
    }

    if (tableContainer) tableContainer.style.display = "block";
    if (emptyState) emptyState.style.display = "none";

    tbody.innerHTML = this.skills
      .map(
        (skill) => `
      <tr>
        <td>${skill.id}</td>
        <td>${this.escapeHtml(skill.name)}</td>
        <td>
          <div class="action-buttons">
            <a href="/admin/skills/edit/${skill.id}" class="btn btn-sm btn-primary">
              <i class="fas fa-edit"></i>
              Edit
            </a>
            <button class="btn btn-sm btn-danger" onclick="window.skillsManager.deleteSkill(${skill.id}, '${this.escapeHtml(skill.name).replace(/'/g, "\\'")}')">
              <i class="fas fa-trash"></i>
              Hapus
            </button>
          </div>
        </td>
      </tr>
    `
      )
      .join("");
  }

  renderPagination(pagination) {
    const paginationContainer = document.getElementById("paginationContainer");
    if (!paginationContainer) {
      // Create pagination container if it doesn't exist
      const tableContainer = document.querySelector(".table-container");
      if (tableContainer) {
        const paginationDiv = document.createElement("div");
        paginationDiv.id = "paginationContainer";
        paginationDiv.className = "pagination-container";
        tableContainer.appendChild(paginationDiv);
      }
    }

    const container = document.getElementById("paginationContainer");
    if (!container) return;

    if (pagination.total_pages <= 1) {
      container.innerHTML = "";
      return;
    }

    let paginationHTML = '<div class="pagination">';

    // Previous button
    if (pagination.current_page > 1) {
      paginationHTML += `<button class="pagination-btn" onclick="window.skillsManager.goToPage(${pagination.current_page - 1})">
        <i class="fas fa-chevron-left"></i>
      </button>`;
    }

    // Page numbers
    const startPage = Math.max(1, pagination.current_page - 2);
    const endPage = Math.min(pagination.total_pages, pagination.current_page + 2);

    if (startPage > 1) {
      paginationHTML += `<button class="pagination-btn" onclick="window.skillsManager.goToPage(1)">1</button>`;
      if (startPage > 2) {
        paginationHTML += '<span class="pagination-dots">...</span>';
      }
    }

    for (let i = startPage; i <= endPage; i++) {
      const activeClass = i === pagination.current_page ? "active" : "";
      paginationHTML += `<button class="pagination-btn ${activeClass}" onclick="window.skillsManager.goToPage(${i})">${i}</button>`;
    }

    if (endPage < pagination.total_pages) {
      if (endPage < pagination.total_pages - 1) {
        paginationHTML += '<span class="pagination-dots">...</span>';
      }
      paginationHTML += `<button class="pagination-btn" onclick="window.skillsManager.goToPage(${pagination.total_pages})">${pagination.total_pages}</button>`;
    }

    // Next button
    if (pagination.current_page < pagination.total_pages) {
      paginationHTML += `<button class="pagination-btn" onclick="window.skillsManager.goToPage(${pagination.current_page + 1})">
        <i class="fas fa-chevron-right"></i>
      </button>`;
    }

    paginationHTML += "</div>";

    // Add pagination info
    paginationHTML += `<div class="pagination-info">
      Menampilkan ${(pagination.current_page - 1) * pagination.items_per_page + 1} - ${Math.min(pagination.current_page * pagination.items_per_page, pagination.total_items)} dari ${pagination.total_items} data
    </div>`;

    container.innerHTML = paginationHTML;
  }

  goToPage(page) {
    if (page >= 1 && page <= this.totalPages && page !== this.currentPage) {
      this.loadSkills(page, this.searchQuery);
    }
  }

  handleSearch(query) {
    this.searchQuery = query.toLowerCase().trim();
    this.currentPage = 1;
    this.loadSkills(1, this.searchQuery);
  }

  openAddModal() {
    const modal = document.getElementById("skillModal");
    const modalTitle = document.getElementById("modalTitle");
    const form = document.getElementById("skillForm");
    const nameInput = document.getElementById("skillName");

    if (!modal) return;

    modalTitle.textContent = "Tambah Skill";
    form.reset();
    nameInput.focus();
    this.currentSkillId = null;

    this.clearErrors();
    modal.classList.add("show");
    modal.style.display = "flex";
  }

  async editSkill(id) {
    try {
      const response = await fetch(`/admin/skills/edit/${id}`, {
        method: "GET",
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
      });

      if (!response.ok) {
        throw new Error("Gagal memuat data skill");
      }

      const result = await response.json();

      if (result.success) {
        const modal = document.getElementById("skillModal");
        const modalTitle = document.getElementById("modalTitle");
        const nameInput = document.getElementById("skillName");

        modalTitle.textContent = "Edit Skill";
        nameInput.value = result.data.name;
        this.currentSkillId = id;

        this.clearErrors();
        modal.classList.add("show");
        modal.style.display = "flex";
        nameInput.focus();
      } else {
        throw new Error(result.error || "Gagal memuat data skill");
      }
    } catch (error) {
      this.showNotification("Error: " + error.message, "error");
    }
  }

  deleteSkill(id, name) {
    const modal = document.getElementById("deleteModal");
    const skillNameSpan = document.getElementById("deleteSkillName");

    if (!modal) return;

    skillNameSpan.textContent = name;
    this.currentSkillId = id;

    modal.classList.add("show");
    modal.style.display = "flex";
  }

  async handleFormSubmit(e) {
    e.preventDefault();

    const nameInput = document.getElementById("skillName");
    const submitBtn = document.getElementById("submitBtn");

    if (!nameInput || !submitBtn) return;

    this.clearErrors();

    if (!nameInput.value.trim()) {
      this.showFieldError("skillName", "Nama skill tidak boleh kosong");
      return;
    }

    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';

    try {
      const url = this.currentSkillId ? `/admin/skills/update/${this.currentSkillId}` : "/admin/skills";

      // Create FormData for proper form submission
      const formData = new FormData();
      formData.append("name", nameInput.value.trim());

      const response = await fetch(url, {
        method: "POST",
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
        body: formData,
      });

      // Check if response is ok
      if (!response.ok) {
        const errorText = await response.text();
        console.error("Server response error:", errorText);
        throw new Error(`Server error: ${response.status}`);
      }

      const result = await response.json();
      console.log("Form submit result:", result);

      if (result.success) {
        this.showNotification(result.message || "Skill berhasil disimpan!", "success");
        this.closeModal();
        await this.loadSkills(this.currentPage, this.searchQuery);
      } else {
        throw new Error(result.error || "Operasi gagal");
      }
    } catch (error) {
      console.error("Form submission error:", error);
      this.showNotification("Error: " + error.message, "error");
    } finally {
      submitBtn.disabled = false;
      submitBtn.innerHTML = originalText;
    }
  }

  async confirmDelete() {
    if (!this.currentSkillId) return;

    const confirmBtn = document.getElementById("confirmDeleteBtn");
    if (!confirmBtn) return;

    // Show loading
    const originalText = confirmBtn.innerHTML;
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menghapus...';

    try {
      const response = await fetch(`/admin/skills/delete/${this.currentSkillId}`, {
        method: "POST",
        headers: {
          "X-Requested-With": "XMLHttpRequest",
          "Content-Type": "application/json",
        },
      });

      console.log("Delete response status:", response.status);

      if (!response.ok) {
        const errorText = await response.text();
        console.error("Delete error response:", errorText);
        throw new Error("Gagal menghapus skill");
      }

      const result = await response.json();
      console.log("Delete result:", result);

      if (result.success) {
        this.showNotification(result.message || "Skill berhasil dihapus!", "success");
        this.closeDeleteModal();
        await this.loadSkills(this.currentPage, this.searchQuery);
      } else {
        throw new Error(result.error || "Gagal menghapus skill");
      }
    } catch (error) {
      console.error("Delete error:", error);
      this.showNotification("Error: " + error.message, "error");
    } finally {
      confirmBtn.disabled = false;
      confirmBtn.innerHTML = originalText;
    }
  }

  closeModal() {
    const modal = document.getElementById("skillModal");
    if (modal) {
      modal.classList.remove("show");
      modal.style.display = "none";
    }
    this.currentSkillId = null;
    this.clearErrors();
  }

  closeDeleteModal() {
    const modal = document.getElementById("deleteModal");
    if (modal) {
      modal.classList.remove("show");
      modal.style.display = "none";
    }
    this.currentSkillId = null;
  }

  showLoading() {
    const loadingState = document.getElementById("loadingState");
    const tableContainer = document.querySelector(".table-container");
    const emptyState = document.getElementById("emptyState");

    if (loadingState) loadingState.style.display = "block";
    if (tableContainer) tableContainer.style.display = "none";
    if (emptyState) emptyState.style.display = "none";
  }

  hideLoading() {
    const loadingState = document.getElementById("loadingState");
    if (loadingState) loadingState.style.display = "none";
  }

  showError(message) {
    const tbody = document.getElementById("skillsTableBody");
    if (tbody) {
      tbody.innerHTML = `
        <tr>
          <td colspan="3" class="text-center">
            <div class="empty-state">
              <i class="fas fa-exclamation-triangle"></i>
              <h3>Error</h3>
              <p>${message}</p>
              <button class="btn btn-primary" onclick="skillsManager.loadSkills()">
                <i class="fas fa-refresh"></i>
                Coba Lagi
              </button>
            </div>
          </td>
        </tr>
      `;
    }
  }

  clearErrors() {
    document.querySelectorAll(".error-message").forEach((el) => (el.textContent = ""));
    document.querySelectorAll(".error").forEach((el) => el.classList.remove("error"));
  }

  showFieldError(fieldId, message) {
    const field = document.getElementById(fieldId);
    const errorEl = document.getElementById(fieldId.replace("skill", "") + "Error");

    if (field) field.classList.add("error");
    if (errorEl) errorEl.textContent = message;
  }

  showNotification(message, type = "info") {
    // Remove existing notifications
    document.querySelectorAll(".notification").forEach((n) => n.remove());

    const notification = document.createElement("div");
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
      <i class="fas fa-${type === "success" ? "check-circle" : type === "error" ? "exclamation-circle" : "info-circle"}"></i>
      <span>${message}</span>
      <button onclick="this.parentElement.remove()">
        <i class="fas fa-times"></i>
      </button>
    `;

    // Add notification styles
    const colors = {
      success: "var(--success-color)",
      error: "var(--danger-color)",
      warning: "var(--warning-color)",
      info: "var(--info-color)",
    };

    notification.style.cssText = `
      position: fixed;
      top: 20px;
      right: 20px;
      background: ${colors[type] || colors.info};
      color: white;
      padding: 1rem 1.5rem;
      border-radius: var(--border-radius);
      box-shadow: var(--shadow-lg);
      z-index: 10000;
      display: flex;
      align-items: center;
      gap: 0.75rem;
      font-weight: 500;
      animation: slideInRight 0.3s ease;
      max-width: 400px;
    `;

    document.body.appendChild(notification);

    // Auto remove after 5 seconds
    setTimeout(() => {
      if (notification.parentElement) {
        notification.style.animation = "slideOutRight 0.3s ease";
        setTimeout(() => notification.remove(), 300);
      }
    }, 5000);
  }

  escapeHtml(text) {
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
  }
}

// Global functions for modal actions
function openAddModal() {
  if (window.skillsManager) {
    window.skillsManager.openAddModal();
  }
}

function closeModal() {
  if (window.skillsManager) {
    window.skillsManager.closeModal();
  }
}

function closeDeleteModal() {
  if (window.skillsManager) {
    window.skillsManager.closeDeleteModal();
  }
}

// Initialize SkillsManager when DOM is loaded
document.addEventListener("DOMContentLoaded", () => {
  if (document.getElementById("skillsTableBody")) {
    console.log("Initializing Skills Manager...");
    window.skillsManager = new SkillsManager();
  }
});

// education
class EducationManager {
      constructor() {
      
        this.tableBody = document.getElementById("educationTableBody");
        this.loading = document.getElementById("loadingState");
        this.empty = document.getElementById("emptyState");
        this.searchInput = document.getElementById("searchInput");

        this.educationData = [];
        this.init();
      }

      async init() {
        this.showLoading();
        try {
          const res = await fetch(this.apiURL);
          const data = await res.json();
          this.educationData = data;
          this.renderTable(data);
        } catch (err) {
          console.error("Gagal ambil data:", err);
          this.showEmpty("Gagal memuat data dari server.");
        } finally {
          this.hideLoading();
        }

        this.searchInput.addEventListener("input", () => {
          this.filterData(this.searchInput.value);
        });
      }

      renderTable(data) {
        this.tableBody.innerHTML = "";

        if (data.length === 0) {
          this.showEmpty("Belum ada data pendidikan.");
          return;
        }

        data.forEach(item => {
          const row = document.createElement("tr");
          row.innerHTML = `
            <td>${item.nama || '-'}</td>
            <td>${item.institution || '-'}</td>
            <td>${item.degree || '-'}</td>
            <td>${item.field_of_study || '-'}</td>
            <td>${item.start_date || '-'}</td>
            <td>${item.end_date || '-'}</td>
            <td>${item.gpa || '-'}</td>
            <td>${item.description || '-'}</td>
            <td>${item.certificate_url ? `<a href="${item.certificate_url}" target="_blank">Lihat</a>` : '-'}</td>
            <td>${item.create_time || '-'}</td>
            <td>${item.archived == 1 ? "Ya" : "Tidak"}</td>
          `;
          this.tableBody.appendChild(row);
        });

        this.empty.style.display = "none";
      }

      filterData(keyword) {
        const filtered = this.educationData.filter(item =>
          item.nama.toLowerCase().includes(keyword.toLowerCase())
        );
        this.renderTable(filtered);
      }

      showLoading() {
        this.loading.style.display = "block";
        this.empty.style.display = "none";
      }

      hideLoading() {
        this.loading.style.display = "none";
      }

      showEmpty(message) {
        this.empty.style.display = "block";
        this.empty.querySelector("p").textContent = message;
      }
    }

    document.addEventListener("DOMContentLoaded", function () {
      new EducationManager();
    });

    //location

    document.addEventListener("DOMContentLoaded", function () {
    const searchInput = document.getElementById("searchInput");
    const tableBody = document.getElementById("locationsTableBody");
    const loadingState = document.getElementById("loadingState");

    // Load data lokasi saat halaman dimuat
    loadLocations();

    // Pencarian realtime
    if (searchInput) {
        searchInput.addEventListener("input", function () {
            const query = this.value.toLowerCase();
            const rows = tableBody.querySelectorAll("tr");

            rows.forEach(row => {
                const rowText = row.textContent.toLowerCase();
                row.style.display = rowText.includes(query) ? "" : "none";
            });
        });
    }

    function loadLocations() {
        if (!tableBody) return;

        loadingState.style.display = "block";

        fetch("/admin/locations/data", {
            headers: {
                "X-Requested-With": "XMLHttpRequest"
            }
        })
            .then(res => res.json())
            .then(data => {
                loadingState.style.display = "none";
                tableBody.innerHTML = "";

                if (data.length === 0) {
                    tableBody.innerHTML = `<tr><td colspan="4" class="text-center text-muted">Belum ada data lokasi.</td></tr>`;
                    return;
                }

                data.forEach(loc => {
                    const row = document.createElement("tr");
                    row.innerHTML = `
                        <td>${loc.city}</td>
                        <td>${loc.province}</td>
                        <td>${loc.country}</td>
                        <td>
                            <a href="/admin/locations/edit/${loc.id}" class="btn btn-sm btn-warning">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <a href="/admin/locations/delete/${loc.id}" class="btn btn-sm btn-danger" onclick="return confirm('Yakin ingin menghapus lokasi ini?')">
                                <i class="fas fa-trash"></i> Hapus
                            </a>
                        </td>
                    `;
                    tableBody.appendChild(row);
                });
            })
            .catch(err => {
                loadingState.style.display = "none";
                tableBody.innerHTML = `<tr><td colspan="4" class="text-danger">Gagal memuat data lokasi.</td></tr>`;
                console.error("Error fetching lokasi:", err);
            });
    }
});
