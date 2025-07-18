// Jobstack Job Portal - Main JavaScript
document.addEventListener("DOMContentLoaded", () => {
  // Initialize all components
  initializeSearch()
  initializeAnimations()
  initializeTestimonials()
  initializeCompanyCarousel()
  initializeMobileMenu()
  initializeScrollEffects()
  initializeFindJobPage()
  initializeJobFilters()
  initializeDynamicContent()

  // Initialize job detail page if on job detail page
  if (document.querySelector(".job-detail-page")) {
    initJobDetailPage()
  }

  // Mobile menu toggle
  const mobileMenuBtn = document.querySelector(".mobile-menu-btn")
  const mobileMenu = document.querySelector(".mobile-menu")
  const mobileMenuClose = document.querySelector(".mobile-menu-close")

  if (mobileMenuBtn) {
    mobileMenuBtn.addEventListener("click", () => {
      mobileMenu.classList.add("active")
    })
  }

  if (mobileMenuClose) {
    mobileMenuClose.addEventListener("click", () => {
      mobileMenu.classList.remove("active")
    })
  }

  if (mobileMenu) {
    mobileMenu.addEventListener("click", (e) => {
      if (e.target === mobileMenu) {
        mobileMenu.classList.remove("active")
      }
    })
  }

  // Navbar search functionality
  const navbarSearchForm = document.querySelector(".navbar-search form")
  if (navbarSearchForm) {
    navbarSearchForm.addEventListener("submit", function (e) {
      e.preventDefault()

      const keyword = this.querySelector(".search-input").value

      let searchUrl = "/find-job"
      if (keyword.trim()) {
        searchUrl += "?keyword=" + encodeURIComponent(keyword.trim())
      }

      window.location.href = searchUrl
    })
  }

  // Suggestion tags functionality
  const suggestionTags = document.querySelectorAll(".suggestion-tag")
  suggestionTags.forEach((tag) => {
    tag.addEventListener("click", function (e) {
      e.preventDefault()
      const keyword = this.textContent.trim()
      window.location.href = "/find-job?keyword=" + encodeURIComponent(keyword)
    })
  })

  // Auto-hide alerts/notifications
  const alerts = document.querySelectorAll(".alert")
  alerts.forEach((alert) => {
    setTimeout(() => {
      alert.style.opacity = "0"
      setTimeout(() => {
        alert.remove()
      }, 300)
    }, 5000)
  })

  // Form validation helpers
  const forms = document.querySelectorAll("form")
  forms.forEach((form) => {
    form.addEventListener("submit", function (e) {
      const requiredFields = this.querySelectorAll("[required]")
      let isValid = true

      requiredFields.forEach((field) => {
        if (!field.value.trim()) {
          field.classList.add("error")
          isValid = false
        } else {
          field.classList.remove("error")
        }
      })

      if (!isValid) {
        e.preventDefault()
        console.log("Form validation failed")
      }
    })
  })

  console.log("Jobstack main.js loaded successfully")
})

// Search functionality with debouncing
function initializeSearch() {
  const searchForm = document.querySelector(".hero-search, .search-form")
  const headerSearch = document.querySelector(".search-input")

  if (headerSearch) {
    const debouncedSearch = debounce((query) => {
      if (query.length > 2) {
        // Implement live search suggestions here
        console.log("Searching for:", query)
      }
    }, 300)

    headerSearch.addEventListener("input", function () {
      debouncedSearch(this.value)
    })

    headerSearch.addEventListener("keypress", function (e) {
      if (e.key === "Enter") {
        const query = this.value.trim()
        if (query) {
          window.location.href = `/find-job?keyword=${encodeURIComponent(query)}`
        }
      }
    })
  }
}

// Find Job Page Enhanced functionality
function initializeFindJobPage() {
  // Job card click handlers
  const jobCards = document.querySelectorAll(".job-card-modern")
  jobCards.forEach((card) => {
    card.addEventListener("click", function (e) {
      const jobId = this.dataset.jobId
      if (jobId) {
        window.location.href = `/find-job/${jobId}`
      }
    })
  })

  // View toggle functionality
  const viewButtons = document.querySelectorAll(".view-btn")
  const jobsContainer = document.querySelector(".jobs-grid")

  viewButtons.forEach((btn) => {
    btn.addEventListener("click", function () {
      viewButtons.forEach((b) => b.classList.remove("active"))
      this.classList.add("active")

      const view = this.dataset.view
      if (view === "list") {
        jobsContainer.classList.add("jobs-list-view")
      } else {
        jobsContainer.classList.remove("jobs-list-view")
      }
    })
  })
}

// Enhanced job filters functionality
function initializeJobFilters() {
  // Filter tag removal
  const filterTags = document.querySelectorAll(".filter-tag .remove")
  filterTags.forEach((tag) => {
    tag.addEventListener("click", function () {
      const filterTag = this.parentElement
      const filterType = filterTag.dataset.filter
      const filterValue = filterTag.dataset.value

      // Remove from URL and reload
      const url = new URL(window.location)
      url.searchParams.delete(filterType)
      window.location.href = url.toString()
    })
  })

  // Sort dropdown
  const sortDropdown = document.querySelector(".sort-dropdown")
  if (sortDropdown) {
    sortDropdown.addEventListener("change", function () {
      const url = new URL(window.location)
      url.searchParams.set("sort", this.value)
      window.location.href = url.toString()
    })
  }

  // Pagination
  const pageButtons = document.querySelectorAll(".page-btn")
  pageButtons.forEach((btn) => {
    btn.addEventListener("click", function (e) {
      if (this.classList.contains("active") || this.classList.contains("disabled")) {
        e.preventDefault()
        return
      }

      const page = this.dataset.page
      if (page) {
        const url = new URL(window.location)
        url.searchParams.set("page", page)
        window.location.href = url.toString()
      }
    })
  })
}

// Testimonials functionality
function initializeTestimonials() {
  const testimonialCards = document.querySelectorAll(".testimonial-card")
  const paginationDots = document.querySelectorAll(".pagination-dot")
  let currentTestimonial = 1 // Start with second testimonial active

  function showTestimonial(index) {
    paginationDots.forEach((dot, i) => {
      dot.classList.toggle("active", i === index)
    })
  }

  // Initialize
  showTestimonial(currentTestimonial)

  // Auto-rotate testimonials
  setInterval(() => {
    currentTestimonial = (currentTestimonial + 1) % paginationDots.length
    showTestimonial(currentTestimonial)
  }, 5000)

  // Add click handlers for dots
  paginationDots.forEach((dot, index) => {
    dot.addEventListener("click", () => {
      currentTestimonial = index
      showTestimonial(currentTestimonial)
    })
  })
}

// Company carousel
function initializeCompanyCarousel() {
  const prevBtn = document.querySelector(".nav-prev")
  const nextBtn = document.querySelector(".nav-next")
  const companyGrids = document.querySelectorAll(".companies-grid")
  let currentSlide = 0

  if (prevBtn && nextBtn && companyGrids.length > 1) {
    function showSlide(index) {
      companyGrids.forEach((grid, i) => {
        grid.style.display = i === index ? "grid" : "none"
      })
    }

    prevBtn.addEventListener("click", () => {
      currentSlide = currentSlide > 0 ? currentSlide - 1 : companyGrids.length - 1
      showSlide(currentSlide)
    })

    nextBtn.addEventListener("click", () => {
      currentSlide = (currentSlide + 1) % companyGrids.length
      showSlide(currentSlide)
    })

    // Initialize
    showSlide(currentSlide)
  }
}

// Mobile menu
function initializeMobileMenu() {
  const mobileMenuToggle = document.querySelector(".mobile-menu-toggle")
  const mainNav = document.querySelector(".main-nav")

  if (mobileMenuToggle) {
    mobileMenuToggle.addEventListener("click", function () {
      mainNav.classList.toggle("active")
      this.classList.toggle("active")
    })
  }

  // Close mobile menu when clicking outside
  document.addEventListener("click", (e) => {
    if (!e.target.closest(".main-nav") && !e.target.closest(".mobile-menu-toggle")) {
      mainNav?.classList.remove("active")
      mobileMenuToggle?.classList.remove("active")
    }
  })
}

// Scroll effects
function initializeScrollEffects() {
  // Back to top button
  const backToTopButton = document.createElement("button")
  backToTopButton.innerHTML = '<i class="fas fa-arrow-up"></i>'
  backToTopButton.className = "btn btn-primary back-to-top"
  backToTopButton.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: none;
        z-index: 1000;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    `

  document.body.appendChild(backToTopButton)

  window.addEventListener("scroll", () => {
    if (window.scrollY > 300) {
      backToTopButton.style.display = "block"
    } else {
      backToTopButton.style.display = "none"
    }
  })

  backToTopButton.addEventListener("click", () => {
    window.scrollTo({
      top: 0,
      behavior: "smooth",
    })
  })

  // Header scroll effect
  const header = document.querySelector(".main-header")
  if (header) {
    window.addEventListener("scroll", () => {
      if (window.scrollY > 50) {
        header.style.boxShadow = "0 2px 10px rgba(0,0,0,0.1)"
      } else {
        header.style.boxShadow = "none"
      }
    })
  }
}

// Animations
function initializeAnimations() {
  // Animate stats on scroll
  const statNumbers = document.querySelectorAll(".stat-number")
  const observerOptions = {
    threshold: 0.5,
    rootMargin: "0px 0px -100px 0px",
  }

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        const target = entry.target
        const finalNumber = Number.parseInt(target.textContent.replace(/,/g, ""))
        animateCounter(target, Number.parseInt(finalNumber))
        observer.unobserve(target)
      }
    })
  }, observerOptions)

  statNumbers.forEach((stat) => {
    observer.observe(stat)
  })

  // Animate cards on hover
  const cards = document.querySelectorAll(".category-card, .company-card, .job-card, .job-card-modern")
  cards.forEach((card) => {
    card.addEventListener("mouseenter", function () {
      if (!this.classList.contains("job-card-modern")) {
        this.style.transform = "translateY(-4px)"
      }
    })

    card.addEventListener("mouseleave", function () {
      if (!this.classList.contains("job-card-modern")) {
        this.style.transform = "translateY(0)"
      }
    })
  })
}

function animateCounter(element, target) {
  let current = 0
  const increment = target / 100
  const timer = setInterval(() => {
    current += increment
    if (current >= target) {
      current = target
      clearInterval(timer)
    }
    element.textContent = Math.floor(current).toLocaleString()
  }, 20)
}

// Utility functions
function debounce(func, wait) {
  let timeout
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout)
      func(...args)
    }
    clearTimeout(timeout)
    timeout = setTimeout(later, wait)
  }
}

// Enhanced notification system
function showNotification(message, type = "info", duration = 3000) {
  // Remove existing notifications
  const existingNotifications = document.querySelectorAll(".notification")
  existingNotifications.forEach((notification) => notification.remove())

  const notification = document.createElement("div")
  notification.className = `notification notification-${type}`

  const colors = {
    success: "#0BA02C",
    warning: "#FFAB00",
    error: "#E05151",
    info: "#0A65CC",
  }

  const icons = {
    success: "fas fa-check-circle",
    warning: "fas fa-exclamation-triangle",
    error: "fas fa-times-circle",
    info: "fas fa-info-circle",
  }

  notification.innerHTML = `
    <i class="${icons[type]}"></i>
    <span>${message}</span>
    <button class="notification-close">&times;</button>
  `

  notification.style.cssText = `
    position: fixed;
    top: 20px;
    right: 20px;
    background: ${colors[type] || colors.info};
    color: white;
    padding: 12px 20px;
    border-radius: 8px;
    font-weight: 500;
    z-index: 10000;
    animation: slideInRight 0.3s ease;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    display: flex;
    align-items: center;
    gap: 8px;
    max-width: 400px;
  `

  // Close button functionality
  const closeBtn = notification.querySelector(".notification-close")
  closeBtn.style.cssText = `
    background: none;
    border: none;
    color: white;
    font-size: 18px;
    cursor: pointer;
    margin-left: auto;
    padding: 0;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
  `

  closeBtn.addEventListener("click", () => {
    notification.style.animation = "slideOutRight 0.3s ease"
    setTimeout(() => {
      if (notification.parentElement) {
        notification.remove()
      }
    }, 300)
  })

  document.body.appendChild(notification)

  // Auto remove after specified duration
  setTimeout(() => {
    if (notification.parentElement) {
      notification.style.animation = "slideOutRight 0.3s ease"
      setTimeout(() => {
        if (notification.parentElement) {
          notification.remove()
        }
      }, 300)
    }
  }, duration)
}

// Global utility functions
window.JobPortal = {
  formatSalary: (min, max) => {
    const formatter = new Intl.NumberFormat("id-ID", {
      style: "currency",
      currency: "IDR",
      minimumFractionDigits: 0,
    })

    if (min && max) {
      return `${formatter.format(min)} - ${formatter.format(max)}`
    } else if (min) {
      return `Mulai dari ${formatter.format(min)}`
    } else if (max) {
      return `Hingga ${formatter.format(max)}`
    }
    return "Gaji dapat dinegosiasi"
  },

  timeAgo: (date) => {
    const now = new Date()
    const posted = new Date(date)
    const diffTime = Math.abs(now - posted)
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))

    if (diffDays === 1) {
      return "1 hari yang lalu"
    } else if (diffDays < 7) {
      return `${diffDays} hari yang lalu`
    } else if (diffDays < 30) {
      const weeks = Math.floor(diffDays / 7)
      return `${weeks} minggu yang lalu`
    } else {
      const months = Math.floor(diffDays / 30)
      return `${months} bulan yang lalu`
    }
  },
}

// Job Detail Page Enhanced functionality
function initJobDetailPage() {
  // Apply button
  const applyBtn = document.querySelector(".apply-btn")
  if (applyBtn) {
    applyBtn.addEventListener("click", () => {
      window.location.href = "/auth/login?role=seeker"
    })
  }

  // Related jobs carousel
  initRelatedJobsCarousel()
}

// Enhanced related jobs carousel
function initRelatedJobsCarousel() {
  const carousel = document.querySelector(".related-jobs-grid")
  if (!carousel) return

  const prevBtn = document.querySelector(".carousel-prev")
  const nextBtn = document.querySelector(".carousel-next")

  if (prevBtn && nextBtn) {
    let currentIndex = 0
    const itemsPerView = window.innerWidth > 768 ? 3 : window.innerWidth > 480 ? 2 : 1
    const totalItems = carousel.children.length
    const maxIndex = Math.max(0, totalItems - itemsPerView)

    prevBtn.addEventListener("click", () => {
      if (currentIndex > 0) {
        currentIndex--
        updateCarousel()
      }
    })

    nextBtn.addEventListener("click", () => {
      if (currentIndex < maxIndex) {
        currentIndex++
        updateCarousel()
      }
    })

    function updateCarousel() {
      const translateX = -(currentIndex * (100 / itemsPerView))
      carousel.style.transform = `translateX(${translateX}%)`

      prevBtn.disabled = currentIndex === 0
      nextBtn.disabled = currentIndex === maxIndex

      // Update button styles
      prevBtn.classList.toggle("disabled", currentIndex === 0)
      nextBtn.classList.toggle("disabled", currentIndex === maxIndex)
    }

    // Initialize
    updateCarousel()

    // Handle window resize
    window.addEventListener("resize", () => {
      const newItemsPerView = window.innerWidth > 768 ? 3 : window.innerWidth > 480 ? 2 : 1
      if (newItemsPerView !== itemsPerView) {
        location.reload() // Simple solution for responsive carousel
      }
    })
  }
}

// Enhanced dynamic content functionality
function initializeDynamicContent() {
  // Format numbers with animation
  const statNumbers = document.querySelectorAll(".stat-number")
  statNumbers.forEach((stat) => {
    const finalNumber = Number.parseInt(stat.textContent.replace(/,/g, ""))
    if (finalNumber > 0) {
      animateCounter(stat, finalNumber)
    }
  })

  // Enhanced job card interactions
  const jobCards = document.querySelectorAll(".job-card")
  jobCards.forEach((card) => {
    card.addEventListener("mouseenter", function () {
      this.style.transform = "translateY(-5px)"
      this.style.boxShadow = "0 15px 35px rgba(0, 0, 0, 0.1)"
    })

    card.addEventListener("mouseleave", function () {
      this.style.transform = "translateY(0)"
      this.style.boxShadow = "0 4px 20px rgba(0, 0, 0, 0.08)"
    })
  })

  // Enhanced company card interactions
  const companyCards = document.querySelectorAll(".company-card")
  companyCards.forEach((card) => {
    card.addEventListener("mouseenter", function () {
      this.style.transform = "translateY(-8px)"
      this.style.boxShadow = "0 20px 40px rgba(10, 101, 204, 0.15)"
    })

    card.addEventListener("mouseleave", function () {
      this.style.transform = "translateY(0)"
      this.style.boxShadow = "0 4px 20px rgba(10, 101, 204, 0.1)"
    })
  })

  // Lazy loading for images
  const images = document.querySelectorAll("img[data-src]")
  const imageObserver = new IntersectionObserver((entries, observer) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        const img = entry.target
        img.src = img.dataset.src
        img.classList.remove("loading-skeleton")
        observer.unobserve(img)
      }
    })
  })

  images.forEach((img) => imageObserver.observe(img))

  // Enhanced CTA animations
  const ctaCards = document.querySelectorAll(".cta-card")
  ctaCards.forEach((card) => {
    card.addEventListener("mouseenter", function () {
      const icon = this.querySelector(".cta-icon")
      if (icon) {
        icon.style.transform = "scale(1.1) rotate(5deg)"
      }
    })

    card.addEventListener("mouseleave", function () {
      const icon = this.querySelector(".cta-icon")
      if (icon) {
        icon.style.transform = "scale(1) rotate(0deg)"
      }
    })
  })
}

// Salary formatter utility
function formatSalary(min, max) {
  const formatter = new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  })

  if (min && max) {
    return `${formatter.format(min)} - ${formatter.format(max)}`
  } else if (min) {
    return `Mulai dari ${formatter.format(min)}`
  } else if (max) {
    return `Hingga ${formatter.format(max)}`
  }
  return "Gaji dapat dinegosiasi"
}

// Time ago formatter
function timeAgo(dateString) {
  const now = new Date()
  const posted = new Date(dateString)
  const diffTime = Math.abs(now - posted)
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))

  if (diffDays === 1) {
    return "1 hari yang lalu"
  } else if (diffDays < 7) {
    return `${diffDays} hari yang lalu`
  } else if (diffDays < 30) {
    const weeks = Math.floor(diffDays / 7)
    return `${weeks} minggu yang lalu`
  } else {
    const months = Math.floor(diffDays / 30)
    return `${months} bulan yang lalu`
  }
}

// Export for use in other scripts
window.JobstackUtils = {
  formatNumber: (num) => new Intl.NumberFormat().format(num),
  formatCurrency: (amount) =>
    new Intl.NumberFormat("id-ID", {
      style: "currency",
      currency: "IDR",
      minimumFractionDigits: 0,
    }).format(amount),
  timeAgo: timeAgo,
}

// Add CSS animation keyframes if not already present
if (!document.querySelector("#notification-styles")) {
  const style = document.createElement("style")
  style.id = "notification-styles"
  style.textContent = `
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
    .jobs-list-view {
      display: flex !important;
      flex-direction: column !important;
    }
    .jobs-list-view .job-card-modern {
      display: flex;
      align-items: center;
      padding: 1rem 1.5rem;
    }
    .jobs-list-view .job-card-header {
      margin-bottom: 0;
      margin-right: 1rem;
    }
    .jobs-list-view .job-info {
      flex: 1;
      margin-bottom: 0;
      margin-right: 1rem;
    }
    .jobs-list-view .job-meta-modern {
      margin-bottom: 0;
      margin-right: 1rem;
    }
    .jobs-list-view .job-card-footer {
      flex-direction: column;
      align-items: flex-end;
      gap: 0.5rem;
    }
  `
  document.head.appendChild(style)
}
