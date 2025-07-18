// Seeker Dashboard JavaScript - Enhanced functionality
document.addEventListener("DOMContentLoaded", () => {
  // Initialize all seeker dashboard components
  initializeDashboard()
  initializeForms()
  initializeFileUploads()
  initializeDataTables()
  initializeNotifications()
  initializeModals()

  console.log("Seeker dashboard initialized successfully")
})

// Dashboard initialization
function initializeDashboard() {
  // Animate dashboard elements on load
  const animatedElements = document.querySelectorAll(".action-card, .info-card, .form-section")
  animatedElements.forEach((element, index) => {
    element.style.opacity = "0"
    element.style.transform = "translateY(20px)"

    setTimeout(() => {
      element.style.transition = "all 0.5s ease"
      element.style.opacity = "1"
      element.style.transform = "translateY(0)"
    }, index * 100)
  })

  // Update active navigation
  updateActiveNavigation()

  // Initialize dashboard stats counter animation
  animateCounters()

  // Auto-refresh dashboard data every 5 minutes
  setInterval(refreshDashboardData, 300000)
}

// Form enhancements
function initializeForms() {
  const forms = document.querySelectorAll("form")

  forms.forEach((form) => {
    // Add loading state on form submission
    form.addEventListener("submit", function (e) {
      const submitBtn = this.querySelector('button[type="submit"], input[type="submit"]')
      if (submitBtn) {
        submitBtn.classList.add("loading")
        submitBtn.disabled = true

        // Create spinner
        const spinner = document.createElement("div")
        spinner.className = "spinner"
        submitBtn.appendChild(spinner)
      }
    })

    // Real-time form validation
    const inputs = form.querySelectorAll("input, textarea, select")
    inputs.forEach((input) => {
      input.addEventListener("blur", validateField)
      input.addEventListener("input", clearFieldError)
    })
  })

  // Auto-save form data to localStorage
  initializeAutoSave()
}

// File upload enhancements
function initializeFileUploads() {
  const fileInputs = document.querySelectorAll('input[type="file"]')

  fileInputs.forEach((input) => {
    const wrapper = input.closest(".form-group")
    if (!wrapper) return

    // Create drag and drop area
    const dropArea = createDropArea(input)
    wrapper.appendChild(dropArea)

    // Handle file selection
    input.addEventListener("change", (e) => {
      handleFileSelection(e.target.files, input)
    })

    // Drag and drop functionality
    ;["dragenter", "dragover", "dragleave", "drop"].forEach((eventName) => {
      dropArea.addEventListener(eventName, preventDefaults, false)
    })
    ;["dragenter", "dragover"].forEach((eventName) => {
      dropArea.addEventListener(eventName, () => dropArea.classList.add("dragover"), false)
    })
    ;["dragleave", "drop"].forEach((eventName) => {
      dropArea.addEventListener(eventName, () => dropArea.classList.remove("dragover"), false)
    })

    dropArea.addEventListener("drop", (e) => {
      const files = e.dataTransfer.files
      handleFileSelection(files, input)
    })
  })
}

// Data table enhancements
function initializeDataTables() {
  const tables = document.querySelectorAll(".data-table")

  tables.forEach((table) => {
    // Add sorting functionality
    const headers = table.querySelectorAll("th[data-sort]")
    headers.forEach((header) => {
      header.style.cursor = "pointer"
      header.addEventListener("click", () => sortTable(table, header))
    })

    // Add search functionality if search input exists
    const searchInput = document.querySelector(`[data-table="${table.id}"]`)
    if (searchInput) {
      searchInput.addEventListener("input", (e) => filterTable(table, e.target.value))
    }

    // Add row hover effects
    const rows = table.querySelectorAll("tbody tr")
    rows.forEach((row) => {
      row.addEventListener("mouseenter", function () {
        this.style.transform = "scale(1.01)"
        this.style.transition = "transform 0.2s ease"
      })

      row.addEventListener("mouseleave", function () {
        this.style.transform = "scale(1)"
      })
    })
  })
}

// Notification system
function initializeNotifications() {
  // Auto-hide alerts after 5 seconds
  const alerts = document.querySelectorAll(".alert")
  alerts.forEach((alert) => {
    setTimeout(() => {
      alert.style.opacity = "0"
      alert.style.transform = "translateX(100%)"
      setTimeout(() => alert.remove(), 300)
    }, 5000)
  })

  // Add close buttons to alerts
  alerts.forEach((alert) => {
    const closeBtn = document.createElement("button")
    closeBtn.innerHTML = "&times;"
    closeBtn.className = "alert-close"
    closeBtn.style.cssText = `
            background: none;
            border: none;
            font-size: 1.2rem;
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
      alert.style.opacity = "0"
      alert.style.transform = "translateX(100%)"
      setTimeout(() => alert.remove(), 300)
    })

    alert.appendChild(closeBtn)
  })
}

// Modal functionality
function initializeModals() {
  // Create modal backdrop
  const backdrop = document.createElement("div")
  backdrop.className = "modal-backdrop"
  backdrop.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        display: none;
        align-items: center;
        justify-content: center;
    `
  document.body.appendChild(backdrop)

  // Handle modal triggers
  const modalTriggers = document.querySelectorAll("[data-modal]")
  modalTriggers.forEach((trigger) => {
    trigger.addEventListener("click", function (e) {
      e.preventDefault()
      const modalId = this.dataset.modal
      showModal(modalId)
    })
  })

  // Close modal on backdrop click
  backdrop.addEventListener("click", function (e) {
    if (e.target === this) {
      hideModal()
    }
  })

  // Close modal on escape key
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      hideModal()
    }
  })
}

// Utility functions
function validateField(e) {
  const field = e.target
  const value = field.value.trim()
  const isRequired = field.hasAttribute("required")

  // Clear previous errors
  clearFieldError(e)

  // Required field validation
  if (isRequired && !value) {
    showFieldError(field, "This field is required")
    return false
  }

  // Email validation
  if (field.type === "email" && value) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
    if (!emailRegex.test(value)) {
      showFieldError(field, "Please enter a valid email address")
      return false
    }
  }

  // Phone validation
  if (field.type === "tel" && value) {
    const phoneRegex = /^[+]?[0-9\s\-$$$$]{10,}$/
    if (!phoneRegex.test(value)) {
      showFieldError(field, "Please enter a valid phone number")
      return false
    }
  }

  // File size validation
  if (field.type === "file" && field.files.length > 0) {
    const maxSize = Number.parseInt(field.dataset.maxSize) || 5 * 1024 * 1024 // 5MB default
    const file = field.files[0]

    if (file.size > maxSize) {
      showFieldError(field, `File size must be less than ${formatFileSize(maxSize)}`)
      return false
    }
  }

  return true
}

function showFieldError(field, message) {
  field.classList.add("error")

  // Remove existing error message
  const existingError = field.parentNode.querySelector(".field-error")
  if (existingError) {
    existingError.remove()
  }

  // Add new error message
  const errorDiv = document.createElement("div")
  errorDiv.className = "field-error"
  errorDiv.style.cssText = `
        color: var(--error-color);
        font-size: 0.8rem;
        margin-top: 0.25rem;
    `
  errorDiv.textContent = message

  field.parentNode.appendChild(errorDiv)
}

function clearFieldError(e) {
  const field = e.target
  field.classList.remove("error")

  const errorDiv = field.parentNode.querySelector(".field-error")
  if (errorDiv) {
    errorDiv.remove()
  }
}

function createDropArea(input) {
  const dropArea = document.createElement("div")
  dropArea.className = "file-upload-area"
  dropArea.innerHTML = `
        <div class="upload-icon">
            <i class="fas fa-cloud-upload-alt"></i>
        </div>
        <div class="upload-text">
            Click to upload or drag and drop
        </div>
        <div class="upload-hint">
            ${input.accept || "All files"} • Max ${input.dataset.maxSize ? formatFileSize(Number.parseInt(input.dataset.maxSize)) : "5MB"}
        </div>
    `

  dropArea.addEventListener("click", () => input.click())

  return dropArea
}

function handleFileSelection(files, input) {
  if (files.length === 0) return

  const file = files[0]
  const maxSize = Number.parseInt(input.dataset.maxSize) || 5 * 1024 * 1024

  // Validate file size
  if (file.size > maxSize) {
    showNotification(`File size must be less than ${formatFileSize(maxSize)}`, "error")
    return
  }

  // Validate file type
  if (input.accept) {
    const acceptedTypes = input.accept.split(",").map((type) => type.trim())
    const fileExtension = "." + file.name.split(".").pop().toLowerCase()
    const mimeType = file.type

    const isValidType = acceptedTypes.some((type) => {
      if (type.startsWith(".")) {
        return type === fileExtension
      } else {
        return mimeType.startsWith(type.replace("*", ""))
      }
    })

    if (!isValidType) {
      showNotification("Invalid file type", "error")
      return
    }
  }

  // Update UI to show selected file
  const dropArea = input.parentNode.querySelector(".file-upload-area")
  if (dropArea) {
    dropArea.innerHTML = `
            <div class="upload-icon" style="color: var(--success-color);">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="upload-text">
                ${file.name}
            </div>
            <div class="upload-hint">
                ${formatFileSize(file.size)} • Click to change
            </div>
        `
  }

  // Trigger change event
  const event = new Event("change", { bubbles: true })
  input.dispatchEvent(event)
}

function sortTable(table, header) {
  const column = Array.from(header.parentNode.children).indexOf(header)
  const rows = Array.from(table.querySelectorAll("tbody tr"))
  const isAscending = header.classList.contains("sort-asc")

  // Remove existing sort classes
  table.querySelectorAll("th").forEach((th) => {
    th.classList.remove("sort-asc", "sort-desc")
  })

  // Add new sort class
  header.classList.add(isAscending ? "sort-desc" : "sort-asc")

  // Sort rows
  rows.sort((a, b) => {
    const aValue = a.children[column].textContent.trim()
    const bValue = b.children[column].textContent.trim()

    // Try to parse as numbers
    const aNum = Number.parseFloat(aValue)
    const bNum = Number.parseFloat(bValue)

    if (!isNaN(aNum) && !isNaN(bNum)) {
      return isAscending ? bNum - aNum : aNum - bNum
    } else {
      return isAscending ? bValue.localeCompare(aValue) : aValue.localeCompare(bValue)
    }
  })

  // Reorder table rows
  const tbody = table.querySelector("tbody")
  rows.forEach((row) => tbody.appendChild(row))
}

function filterTable(table, searchTerm) {
  const rows = table.querySelectorAll("tbody tr")
  const term = searchTerm.toLowerCase()

  rows.forEach((row) => {
    const text = row.textContent.toLowerCase()
    row.style.display = text.includes(term) ? "" : "none"
  })
}

function updateActiveNavigation() {
  const currentPath = window.location.pathname
  const navLinks = document.querySelectorAll(".sidebar-menu a")

  navLinks.forEach((link) => {
    link.classList.remove("active")
    if (link.getAttribute("href") === currentPath) {
      link.classList.add("active")
    }
  })
}

function animateCounters() {
  const counters = document.querySelectorAll(".stat-number")

  counters.forEach((counter) => {
    const target = Number.parseInt(counter.textContent)
    if (isNaN(target)) return

    let current = 0
    const increment = target / 50
    const timer = setInterval(() => {
      current += increment
      if (current >= target) {
        current = target
        clearInterval(timer)
      }
      counter.textContent = Math.floor(current)
    }, 20)
  })
}

function initializeAutoSave() {
  const forms = document.querySelectorAll("form[data-autosave]")

  forms.forEach((form) => {
    const formId = form.dataset.autosave
    const inputs = form.querySelectorAll("input, textarea, select")

    // Load saved data
    const savedData = localStorage.getItem(`form_${formId}`)
    if (savedData) {
      const data = JSON.parse(savedData)
      Object.keys(data).forEach((key) => {
        const input = form.querySelector(`[name="${key}"]`)
        if (input && input.type !== "file") {
          input.value = data[key]
        }
      })
    }

    // Save data on input
    inputs.forEach((input) => {
      input.addEventListener(
        "input",
        debounce(() => {
          const formData = new FormData(form)
          const data = {}
          for (const [key, value] of formData.entries()) {
            if (form.querySelector(`[name="${key}"]`).type !== "file") {
              data[key] = value
            }
          }
          localStorage.setItem(`form_${formId}`, JSON.stringify(data))
        }, 1000),
      )
    })

    // Clear saved data on successful submit
    form.addEventListener("submit", () => {
      localStorage.removeItem(`form_${formId}`)
    })
  })
}

function refreshDashboardData() {
  // Refresh dashboard statistics and notifications
  fetch("/seeker/dashboard/refresh", {
    method: "GET",
    headers: {
      "X-Requested-With": "XMLHttpRequest",
    },
  })
    .then((response) => response.json())
    .then((data) => {
      // Update dashboard stats
      if (data.stats) {
        Object.keys(data.stats).forEach((key) => {
          const element = document.querySelector(`[data-stat="${key}"]`)
          if (element) {
            element.textContent = data.stats[key]
          }
        })
      }

      // Show new notifications
      if (data.notifications && data.notifications.length > 0) {
        data.notifications.forEach((notification) => {
          showNotification(notification.message, notification.type)
        })
      }
    })
    .catch((error) => {
      console.error("Failed to refresh dashboard data:", error)
    })
}

function showModal(modalId) {
  const modal = document.getElementById(modalId)
  const backdrop = document.querySelector(".modal-backdrop")

  if (modal && backdrop) {
    backdrop.style.display = "flex"
    backdrop.appendChild(modal)
    modal.style.display = "block"

    // Animate modal
    setTimeout(() => {
      modal.style.transform = "scale(1)"
      modal.style.opacity = "1"
    }, 10)
  }
}

function hideModal() {
  const backdrop = document.querySelector(".modal-backdrop")
  const modal = backdrop.querySelector(".modal")

  if (modal) {
    modal.style.transform = "scale(0.9)"
    modal.style.opacity = "0"

    setTimeout(() => {
      backdrop.style.display = "none"
      modal.style.display = "none"
    }, 300)
  }
}

function showNotification(message, type = "info", duration = 5000) {
  // Remove existing notifications
  const existingNotifications = document.querySelectorAll(".toast-notification")
  existingNotifications.forEach((notification) => notification.remove())

  const notification = document.createElement("div")
  notification.className = `toast-notification toast-${type}`

  const icons = {
    success: "fas fa-check-circle",
    warning: "fas fa-exclamation-triangle",
    error: "fas fa-times-circle",
    info: "fas fa-info-circle",
  }

  const colors = {
    success: "#0BA02C",
    warning: "#FFAB00",
    error: "#E05151",
    info: "#0A65CC",
  }

  notification.innerHTML = `
        <i class="${icons[type]}"></i>
        <span>${message}</span>
        <button class="toast-close">&times;</button>
    `

  notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${colors[type]};
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
        word-wrap: break-word;
    `

  // Close button functionality
  const closeBtn = notification.querySelector(".toast-close")
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
    setTimeout(() => notification.remove(), 300)
  })

  document.body.appendChild(notification)

  // Auto remove after specified duration
  setTimeout(() => {
    if (notification.parentElement) {
      notification.style.animation = "slideOutRight 0.3s ease"
      setTimeout(() => notification.remove(), 300)
    }
  }, duration)
}

// Utility functions
function formatFileSize(bytes) {
  if (bytes === 0) return "0 Bytes"
  const k = 1024
  const sizes = ["Bytes", "KB", "MB", "GB"]
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return Number.parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i]
}

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

function preventDefaults(e) {
  e.preventDefault()
  e.stopPropagation()
}

// Global utilities for seeker dashboard
window.SeekerDashboard = {
  showNotification,
  showModal,
  hideModal,
  validateField,
  formatFileSize,
  refreshDashboardData,
}

// Add CSS animations if not already present
if (!document.querySelector("#seeker-animations")) {
  const style = document.createElement("style")
  style.id = "seeker-animations"
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
        .modal {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            transform: scale(0.9);
            opacity: 0;
            transition: all 0.3s ease;
        }
        .sort-asc::after {
            content: ' ↑';
            color: var(--primary-blue);
        }
        .sort-desc::after {
            content: ' ↓';
            color: var(--primary-blue);
        }
    `
  document.head.appendChild(style)
}
