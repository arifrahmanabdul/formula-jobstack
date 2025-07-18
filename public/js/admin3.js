// Job Categories Management Class
class JobCategoriesManager {
  constructor() {
    this.currentCategoryId = null
    this.categories = []
    this.currentPage = 1
    this.totalPages = 1
    this.itemsPerPage = 10
    this.searchQuery = ""

    this.init()
  }

  init() {
    this.bindEvents()
    this.loadCategories()
  }

  bindEvents() {
    // Search functionality
    const searchInput = document.getElementById("searchInput")
    if (searchInput) {
      searchInput.addEventListener("input", (e) => this.handleSearch(e.target.value))
    }

    // Modal events
    document.addEventListener("click", (e) => {
      if (e.target.classList.contains("modal")) {
        this.closeModal()
      }
    })

    // Form submission
    const categoryForm = document.getElementById("categoryForm")
    if (categoryForm) {
      categoryForm.addEventListener("submit", (e) => this.handleFormSubmit(e))
    }

    // Auto-generate slug from name
    const nameInput = document.getElementById("categoryName")
    const slugInput = document.getElementById("categorySlug")
    if (nameInput && slugInput) {
      nameInput.addEventListener("input", (e) => {
        if (!this.currentCategoryId) {
          // Only auto-generate for new categories
          const slug = this.generateSlug(e.target.value)
          slugInput.value = slug
        }
      })
    }

    // Keyboard events
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") {
        this.closeModal()
      }
    })

    // Image preview functionality
    const imageInput = document.getElementById("icon_image") || document.getElementById("categoryIcon")
    if (imageInput) {
      imageInput.addEventListener("change", (e) => {
        this.handleImagePreview(e.target.files[0])
      })
    }
  }

  generateSlug(text) {
    return text
      .toLowerCase()
      .replace(/[^a-z0-9\s-]/g, "") // Remove special characters
      .replace(/\s+/g, "-") // Replace spaces with hyphens
      .replace(/-+/g, "-") // Replace multiple hyphens with single
      .trim("-") // Remove leading/trailing hyphens
  }

  async loadCategories(page = 1, search = "") {
    try {
      this.showLoading()

      const params = new URLSearchParams({
        page: page.toString(),
        search: search,
      })

      const response = await fetch(`/admin/job-categories?${params}`, {
        method: "GET",
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
      })

      if (!response.ok) {
        throw new Error("Gagal memuat data kategori")
      }

      const result = await response.json()

      if (result.success) {
        this.categories = result.data
        this.currentPage = result.pagination.current_page
        this.totalPages = result.pagination.total_pages
        this.renderCategories()
        this.renderPagination(result.pagination)
      } else {
        throw new Error(result.error || "Gagal memuat data kategori")
      }
    } catch (error) {
      console.error("Error loading categories:", error)
      this.showError("Gagal memuat data kategori: " + error.message)
    } finally {
      this.hideLoading()
    }
  }

  renderCategories() {
    const tbody = document.getElementById("categoriesTableBody")
    const emptyState = document.getElementById("emptyState")
    const tableContainer = document.querySelector(".table-container")

    if (!tbody) return

    if (this.categories.length === 0) {
      if (tableContainer) tableContainer.style.display = "none"
      if (emptyState) emptyState.style.display = "block"
      return
    }

    if (tableContainer) tableContainer.style.display = "block"
    if (emptyState) emptyState.style.display = "none"

    tbody.innerHTML = this.categories
      .map(
        (category) => `
            <tr>
                <td>${category.id}</td>
                <td>
                    <div class="category-icon-container">
                        ${
                          category.icon_image
                            ? `<img src="${this.getImageUrl(category.icon_image)}" 
                                   alt="${this.escapeHtml(category.name)}" 
                                   class="category-icon"
                                   onerror="this.style.display='none'; this.nextElementSibling.style.display='inline';">
                               <i class="fas fa-image category-icon-fallback"></i>`
                            : '<i class="fas fa-image text-muted" style="font-size: 24px; color: #999;"></i>'
                        }
                    </div>
                </td>
                <td>${this.escapeHtml(category.name)}</td>
                <td><code>${this.escapeHtml(category.slug)}</code></td>
                <td><span class="badge badge-info">${category.job_count || 0}</span></td>
                <td>
                    <div class="action-buttons">
                        <a href="/admin/job-categories/${category.id}/edit" class="btn btn-sm btn-primary">
                            <i class="fas fa-edit"></i>
                            Edit
                        </a>
                    </div>
                </td>
            </tr>
        `,
      )
      .join("")
  }

  getImageUrl(iconImage) {
    if (!iconImage) return ""

    // If already starts with /, return as is
    if (iconImage.startsWith("/")) {
      return iconImage
    }

    // If it's just a filename, prepend the uploads path
    return `/uploads/job_categories/${iconImage}`
  }

  renderPagination(pagination) {
    const paginationContainer = document.getElementById("paginationContainer")
    if (!paginationContainer) {
      // Create pagination container if it doesn't exist
      const tableContainer = document.querySelector(".table-container")
      if (tableContainer) {
        const paginationDiv = document.createElement("div")
        paginationDiv.id = "paginationContainer"
        paginationDiv.className = "pagination-container"
        tableContainer.appendChild(paginationDiv)
      }
    }

    const container = document.getElementById("paginationContainer")
    if (!container) return

    if (pagination.total_pages <= 1) {
      container.innerHTML = ""
      return
    }

    let paginationHTML = '<div class="pagination">'

    // Previous button
    if (pagination.current_page > 1) {
      paginationHTML += `<button class="pagination-btn" onclick="window.jobCategoriesManager.goToPage(${pagination.current_page - 1})">
                <i class="fas fa-chevron-left"></i>
            </button>`
    }

    // Page numbers
    const startPage = Math.max(1, pagination.current_page - 2)
    const endPage = Math.min(pagination.total_pages, pagination.current_page + 2)

    if (startPage > 1) {
      paginationHTML += `<button class="pagination-btn" onclick="window.jobCategoriesManager.goToPage(1)">1</button>`
      if (startPage > 2) {
        paginationHTML += '<span class="pagination-dots">...</span>'
      }
    }

    for (let i = startPage; i <= endPage; i++) {
      const activeClass = i === pagination.current_page ? "active" : ""
      paginationHTML += `<button class="pagination-btn ${activeClass}" onclick="window.jobCategoriesManager.goToPage(${i})">${i}</button>`
    }

    if (endPage < pagination.total_pages) {
      if (endPage < pagination.total_pages - 1) {
        paginationHTML += '<span class="pagination-dots">...</span>'
      }
      paginationHTML += `<button class="pagination-btn" onclick="window.jobCategoriesManager.goToPage(${pagination.total_pages})">${pagination.total_pages}</button>`
    }

    // Next button
    if (pagination.current_page < pagination.total_pages) {
      paginationHTML += `<button class="pagination-btn" onclick="window.jobCategoriesManager.goToPage(${pagination.current_page + 1})">
                <i class="fas fa-chevron-right"></i>
            </button>`
    }

    paginationHTML += "</div>"

    // Add pagination info
    paginationHTML += `<div class="pagination-info">
            Menampilkan ${(pagination.current_page - 1) * pagination.items_per_page + 1} - ${Math.min(pagination.current_page * pagination.items_per_page, pagination.total_items)} dari ${pagination.total_items} data
        </div>`

    container.innerHTML = paginationHTML
  }

  goToPage(page) {
    if (page >= 1 && page <= this.totalPages && page !== this.currentPage) {
      this.loadCategories(page, this.searchQuery)
    }
  }

  handleSearch(query) {
    this.searchQuery = query.toLowerCase().trim()
    this.currentPage = 1
    this.loadCategories(1, this.searchQuery)
  }

  openAddModal() {
    const modal = document.getElementById("categoryModal")
    const modalTitle = document.getElementById("modalTitle")
    const form = document.getElementById("categoryForm")
    const nameInput = document.getElementById("categoryName")

    if (!modal) return

    modalTitle.textContent = "Tambah Kategori Pekerjaan"
    form.reset()
    nameInput.focus()
    this.currentCategoryId = null

    this.clearErrors()
    modal.classList.add("show")
    modal.style.display = "flex"
  }

  async editCategory(id) {
    try {
      const response = await fetch(`/admin/job-categories/${id}/edit`, {
        method: "GET",
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
      })

      if (!response.ok) {
        throw new Error("Gagal memuat data kategori")
      }

      const result = await response.json()

      if (result.success) {
        const modal = document.getElementById("categoryModal")
        const modalTitle = document.getElementById("modalTitle")
        const nameInput = document.getElementById("categoryName")
        const slugInput = document.getElementById("categorySlug")
        const descriptionInput = document.getElementById("categoryDescription")

        modalTitle.textContent = "Edit Kategori Pekerjaan"
        nameInput.value = result.data.name
        slugInput.value = result.data.slug
        descriptionInput.value = result.data.description || ""
        this.currentCategoryId = id

        this.clearErrors()
        modal.classList.add("show")
        modal.style.display = "flex"
        nameInput.focus()
      } else {
        throw new Error(result.error || "Gagal memuat data kategori")
      }
    } catch (error) {
      this.showNotification("Error: " + error.message, "error")
    }
  }

  async handleFormSubmit(e) {
    e.preventDefault()

    const form = document.getElementById("categoryForm")
    const submitBtn = document.getElementById("submitBtn")

    if (!form || !submitBtn) return

    this.clearErrors()

    const formData = new FormData(form)

    // Basic validation
    if (!formData.get("name").trim()) {
      this.showFieldError("categoryName", "Nama kategori tidak boleh kosong")
      return
    }

    if (!formData.get("slug").trim()) {
      this.showFieldError("categorySlug", "Slug tidak boleh kosong")
      return
    }

    const originalText = submitBtn.innerHTML
    submitBtn.disabled = true
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...'

    try {
      const url = this.currentCategoryId ? `/admin/job-categories/${this.currentCategoryId}` : "/admin/job-categories"

      const response = await fetch(url, {
        method: "POST",
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
        body: formData,
      })

      if (!response.ok) {
        const errorText = await response.text()
        console.error("Server response error:", errorText)
        throw new Error(`Server error: ${response.status}`)
      }

      const result = await response.json()
      console.log("Form submit result:", result)

      if (result.success) {
        this.showNotification(result.message || "Kategori berhasil disimpan!", "success")
        this.closeModal()
        await this.loadCategories(this.currentPage, this.searchQuery)
      } else {
        if (result.errors) {
          Object.keys(result.errors).forEach((field) => {
            this.showFieldError("category" + field.charAt(0).toUpperCase() + field.slice(1), result.errors[field])
          })
        } else {
          throw new Error(result.message || "Operasi gagal")
        }
      }
    } catch (error) {
      console.error("Form submission error:", error)
      this.showNotification("Error: " + error.message, "error")
    } finally {
      submitBtn.disabled = false
      submitBtn.innerHTML = originalText
    }
  }

  closeModal() {
    const modal = document.getElementById("categoryModal")
    if (modal) {
      modal.classList.remove("show")
      modal.style.display = "none"
    }
    this.currentCategoryId = null
    this.clearErrors()
  }

  showLoading() {
    const loadingState = document.getElementById("loadingState")
    const tableContainer = document.querySelector(".table-container")
    const emptyState = document.getElementById("emptyState")

    if (loadingState) loadingState.style.display = "block"
    if (tableContainer) tableContainer.style.display = "none"
    if (emptyState) emptyState.style.display = "none"
  }

  hideLoading() {
    const loadingState = document.getElementById("loadingState")
    if (loadingState) loadingState.style.display = "none"
  }

  showError(message) {
    const tbody = document.getElementById("categoriesTableBody")
    if (tbody) {
      tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center">
                        <div class="empty-state">
                            <i class="fas fa-exclamation-triangle"></i>
                            <h3>Error</h3>
                            <p>${message}</p>
                            <button class="btn btn-primary" onclick="jobCategoriesManager.loadCategories()">
                                <i class="fas fa-refresh"></i>
                                Coba Lagi
                            </button>
                        </div>
                    </td>
                </tr>
            `
    }
  }

  clearErrors() {
    document.querySelectorAll(".error-message").forEach((el) => (el.textContent = ""))
    document.querySelectorAll(".error").forEach((el) => el.classList.remove("error"))
  }

  showFieldError(fieldId, message) {
    const field = document.getElementById(fieldId)
    const errorEl = document.getElementById(fieldId.replace("category", "").toLowerCase() + "Error")

    if (field) field.classList.add("error")
    if (errorEl) errorEl.textContent = message
  }

  showNotification(message, type = "info") {
    // Remove existing notifications
    document.querySelectorAll(".notification").forEach((n) => n.remove())

    const notification = document.createElement("div")
    notification.className = `notification notification-${type}`
    notification.innerHTML = `
            <i class="fas fa-${type === "success" ? "check-circle" : type === "error" ? "exclamation-circle" : "info-circle"}"></i>
            <span>${message}</span>
            <button onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        `

    const colors = {
      success: "var(--success-color)",
      error: "var(--danger-color)",
      warning: "var(--warning-color)",
      info: "var(--info-color)",
    }

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
        `

    document.body.appendChild(notification)

    setTimeout(() => {
      if (notification.parentElement) {
        notification.style.animation = "slideOutRight 0.3s ease"
        setTimeout(() => notification.remove(), 300)
      }
    }, 5000)
  }

  handleImagePreview(file) {
    const preview = document.getElementById("imagePreview")
    const previewImg = document.getElementById("previewImg")

    if (!file) {
      if (preview) preview.style.display = "none"
      return
    }

    // Validate file type
    const allowedTypes = ["image/jpeg", "image/jpg", "image/png", "image/gif", "image/svg+xml"]
    if (!allowedTypes.includes(file.type)) {
      this.showNotification("Format file tidak didukung. Gunakan JPG, PNG, GIF, atau SVG.", "error")
      return
    }

    // Validate file size (2MB)
    if (file.size > 2 * 1024 * 1024) {
      this.showNotification("Ukuran file terlalu besar. Maksimal 2MB.", "error")
      return
    }

    if (preview && previewImg) {
      const reader = new FileReader()
      reader.onload = (e) => {
        previewImg.src = e.target.result
        preview.style.display = "block"
      }
      reader.readAsDataURL(file)
    }
  }

  removeImagePreview() {
    const imageInput = document.getElementById("icon_image") || document.getElementById("categoryIcon")
    const preview = document.getElementById("imagePreview")

    if (imageInput) imageInput.value = ""
    if (preview) preview.style.display = "none"
  }

  escapeHtml(text) {
    const div = document.createElement("div")
    div.textContent = text
    return div.innerHTML
  }
}

// Form-specific functions for create/edit pages
class JobCategoryFormManager {
  constructor() {
    this.init()
  }

  init() {
    this.bindEvents()
  }

  bindEvents() {
    // Auto-generate slug from name
    const nameInput = document.getElementById("name")
    const slugInput = document.getElementById("slug")

    if (nameInput && slugInput) {
      nameInput.addEventListener(
        "input",
        function (e) {
          const slug = this.generateSlug(e.target.value)
          slugInput.value = slug
        }.bind(this),
      )
    }

    // Image preview functionality
    const imageInput = document.getElementById("icon_image")
    if (imageInput) {
      imageInput.addEventListener(
        "change",
        function (e) {
          this.handleImagePreview(e.target.files[0])
        }.bind(this),
      )
    }

    // Form submission with loading state
    const form = document.getElementById("categoryForm")
    const submitBtn = document.getElementById("submitBtn")

    if (form && submitBtn) {
      form.addEventListener("submit", (e) => {
        const originalText = submitBtn.innerHTML
        submitBtn.disabled = true
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...'

        // Re-enable button after a delay if form doesn't submit
        setTimeout(() => {
          if (submitBtn.disabled) {
            submitBtn.disabled = false
            submitBtn.innerHTML = originalText
          }
        }, 10000)
      })
    }
  }

  generateSlug(text) {
    return text
      .toLowerCase()
      .replace(/[^a-z0-9\s-]/g, "") // Remove special characters
      .replace(/\s+/g, "-") // Replace spaces with hyphens
      .replace(/-+/g, "-") // Replace multiple hyphens with single
      .trim("-") // Remove leading/trailing hyphens
  }

  handleImagePreview(file) {
    const preview = document.getElementById("imagePreview")
    const previewImg = document.getElementById("previewImg")

    if (!file) {
      if (preview) preview.style.display = "none"
      return
    }

    // Validate file type
    const allowedTypes = ["image/jpeg", "image/jpg", "image/png", "image/gif", "image/svg+xml"]
    if (!allowedTypes.includes(file.type)) {
      this.showNotification("Format file tidak didukung. Gunakan JPG, PNG, GIF, atau SVG.", "error")
      return
    }

    // Validate file size (2MB)
    if (file.size > 2 * 1024 * 1024) {
      this.showNotification("Ukuran file terlalu besar. Maksimal 2MB.", "error")
      return
    }

    if (preview && previewImg) {
      const reader = new FileReader()
      reader.onload = (e) => {
        previewImg.src = e.target.result
        preview.style.display = "block"
      }
      reader.readAsDataURL(file)
    }
  }

  removeImagePreview() {
    const imageInput = document.getElementById("icon_image")
    const preview = document.getElementById("imagePreview")

    if (imageInput) imageInput.value = ""
    if (preview) preview.style.display = "none"
  }

  showNotification(message, type = "info") {
    // Remove existing notifications
    document.querySelectorAll(".notification").forEach((n) => n.remove())

    const notification = document.createElement("div")
    notification.className = `notification notification-${type}`
    notification.innerHTML = `
            <i class="fas fa-${type === "success" ? "check-circle" : type === "error" ? "exclamation-circle" : "info-circle"}"></i>
            <span>${message}</span>
            <button onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        `

    const colors = {
      success: "var(--success-color)",
      error: "var(--danger-color)",
      warning: "var(--warning-color)",
      info: "var(--info-color)",
    }

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
        `

    document.body.appendChild(notification)

    setTimeout(() => {
      if (notification.parentElement) {
        notification.style.animation = "slideOutRight 0.3s ease"
        setTimeout(() => notification.remove(), 300)
      }
    }, 5000)
  }
}

// Global functions for modal actions
function openAddModal() {
  if (window.jobCategoriesManager) {
    window.jobCategoriesManager.openAddModal()
  }
}

function closeModal() {
  if (window.jobCategoriesManager) {
    window.jobCategoriesManager.closeModal()
  }
}

function removeImagePreview() {
  if (window.jobCategoryFormManager) {
    window.jobCategoryFormManager.removeImagePreview()
  }
}

// Initialize managers when DOM is loaded
document.addEventListener("DOMContentLoaded", () => {
  // Initialize for index page
  if (document.getElementById("categoriesTableBody")) {
    console.log("Initializing Job Categories Manager...")
    window.jobCategoriesManager = new JobCategoriesManager()
  }

  // Initialize for create/edit pages
  if (document.getElementById("categoryForm")) {
    console.log("Initializing Job Category Form Manager...")
    window.jobCategoryFormManager = new JobCategoryFormManager()
  }
})
