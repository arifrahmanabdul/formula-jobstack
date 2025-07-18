class SeekerApplicationsManager {
  constructor() {
    this.applications = []
    this.currentPage = 1
    this.totalPages = 1
    this.searchQuery = ""
    this.statusFilter = ""
    this.currentAppId = null
    this.init()
  }

  init() {
    this.bindEvents()
    this.loadApplications()
  }

  bindEvents() {
    const searchInput = document.getElementById("searchInput")
    if (searchInput) {
      searchInput.addEventListener(
        "input",
        this.debounce(() => this.handleSearch(searchInput.value), 300),
      )
    }

    const statusFilter = document.getElementById("statusFilter")
    if (statusFilter) {
      statusFilter.addEventListener("change", () => this.handleStatusFilter(statusFilter.value))
    }
    
    const resetBtn = document.getElementById("resetBtn")
    if (resetBtn) {
        resetBtn.addEventListener("click", () => this.resetFilters())
    }

    const confirmDeleteBtn = document.getElementById("confirmDeleteBtn")
    if (confirmDeleteBtn) {
      confirmDeleteBtn.addEventListener("click", () => this.confirmDelete())
    }

    const tableBody = document.getElementById("applicationsTableBody")
    if (tableBody) {
      tableBody.addEventListener("click", (e) => {
        if (e.target.closest(".delete-btn")) {
          const button = e.target.closest(".delete-btn")
          this.openDeleteModal(button.dataset.id, button.dataset.info)
        }
      })
    }

    document.addEventListener("click", (e) => {
      if (e.target.matches(".modal, .modal-close")) {
        this.closeDeleteModal()
      }
    })

    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") {
        this.closeDeleteModal()
      }
    })
  }

  async loadApplications(page = 1, search = "", status = "") {
    this.showLoading()
    try {
      const params = new URLSearchParams({ page, search, status })
      const response = await fetch(`/seeker/applications/data?${params}`, {
        headers: { "X-Requested-With": "XMLHttpRequest" },
      })
      const result = await response.json()

      if (!response.ok || !result.success) {
        throw new Error(result.message || "Gagal memuat data.")
      }

      this.applications = result.data
      this.currentPage = result.pagination.current_page
      this.totalPages = result.pagination.total_pages
      this.renderTable()
      this.renderPagination(result.pagination)
    } catch (error) {
      this.showError(error.message)
    } finally {
      this.hideLoading()
    }
  }

  renderTable() {
    const tbody = document.getElementById("applicationsTableBody")
    const emptyState = document.getElementById("emptyState")
    const tableContainer = document.querySelector(".table-container")

    if (!tbody) return

    if (this.applications.length === 0) {
      tableContainer.style.display = "none"
      emptyState.style.display = "block"
      return
    }

    tableContainer.style.display = "block"
    emptyState.style.display = "none"

    tbody.innerHTML = this.applications
      .map((app) => {
        const statusClass = (app.status || "new").toLowerCase().replace("_", "-")
        const statusText = this.getStatusText(app.status)
        
        return `
                <tr>
                    <td>${app.id}</td>
                    <td>
                        <div class="job-info">
                            <strong>${this.escapeHtml(app.job_title)}</strong>
                        </div>
                    </td>
                    <td>${this.escapeHtml(app.company_name)}</td>
                    <td>${new Date(app.application_date).toLocaleDateString("id-ID", {
                      day: "2-digit",
                      month: "short",
                      year: "numeric",
                    })}</td>
                    <td><span class="badge badge-${statusClass}">${statusText}</span></td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn btn-sm btn-danger delete-btn" 
                                    data-id="${app.id}" 
                                    data-info="lamaran untuk posisi ${this.escapeHtml(app.job_title)}"
                                    title="Tarik Lamaran">
                                <i class="fas fa-trash"></i> Tarik
                            </button>
                        </div>
                    </td>
                </tr>
            `
      })
      .join("")
  }

  renderPagination(pagination) {
    const container = document.getElementById("paginationContainer")
    if (!container || pagination.total_pages <= 1) {
      if (container) container.innerHTML = ""
      return
    }

    let html = '<div class="pagination">'

    if (pagination.current_page > 1) {
      html += `<button class="pagination-btn" onclick="window.seekerApplicationsManager.goToPage(${pagination.current_page - 1})">&laquo;</button>`
    }

    for (let i = 1; i <= pagination.total_pages; i++) {
      html += `<button class="pagination-btn ${i === pagination.current_page ? "active" : ""}" onclick="window.seekerApplicationsManager.goToPage(${i})">${i}</button>`
    }

    if (pagination.current_page < pagination.total_pages) {
      html += `<button class="pagination-btn" onclick="window.seekerApplicationsManager.goToPage(${pagination.current_page + 1})">&raquo;</button>`
    }

    html += "</div>"
    container.innerHTML = html
  }
  
    resetFilters() {
        const searchInput = document.getElementById("searchInput");
        const statusFilter = document.getElementById("statusFilter");
        if(searchInput) searchInput.value = "";
        if(statusFilter) statusFilter.value = "";
        
        this.searchQuery = "";
        this.statusFilter = "";
        this.loadApplications(1, this.searchQuery, this.statusFilter);
    }

  goToPage(page) {
    this.loadApplications(page, this.searchQuery, this.statusFilter)
  }

  handleSearch(query) {
    this.searchQuery = query.trim()
    this.loadApplications(1, this.searchQuery, this.statusFilter)
  }

  handleStatusFilter(status) {
    this.statusFilter = status
    this.loadApplications(1, this.searchQuery, this.statusFilter)
  }

  openDeleteModal(id, info) {
    this.currentAppId = id
    document.getElementById("deleteItemInfo").textContent = info
    document.getElementById("deleteModal").style.display = "flex"
  }

  closeDeleteModal() {
    document.getElementById("deleteModal").style.display = "none"
    this.currentAppId = null
  }

  async confirmDelete() {
    if (!this.currentAppId) return

    const btn = document.getElementById("confirmDeleteBtn")
    btn.disabled = true
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menarik...'

    try {
      const response = await fetch(`/seeker/applications/delete/${this.currentAppId}`, {
        method: "POST", // Soft delete should use POST/DELETE, not GET
        headers: { "X-Requested-With": "XMLHttpRequest" },
      })
      const result = await response.json()

      if (!response.ok || !result.success) {
        throw new Error(result.message || "Gagal menarik lamaran.")
      }

      this.showNotification("Lamaran berhasil ditarik.", "success")
      this.closeDeleteModal()
      this.loadApplications(this.currentPage, this.searchQuery, this.statusFilter)
    } catch (error) {
      this.showNotification(error.message, "error")
    } finally {
      btn.disabled = false
      btn.innerHTML = "Ya, Tarik Lamaran"
    }
  }

  getStatusText(status) {
    const statusMap = {
      new: "Baru",
      viewed: "Dilihat",
      sent_to_client: "Dikirim ke Klien",
      accepted: "Diterima",
      rejected: "Ditolak",
    }
    return statusMap[status] || status
  }

  showLoading() {
    document.getElementById("loadingState").style.display = "block"
    document.querySelector(".table-container").style.display = "none"
    document.getElementById("emptyState").style.display = "none"
  }

  hideLoading() {
    document.getElementById("loadingState").style.display = "none"
  }

  showError(msg) {
    const empty = document.getElementById("emptyState")
    const p = empty.querySelector("p")
    if (p) p.textContent = msg
    empty.style.display = "block"
    document.querySelector(".table-container").style.display = "none"
  }

  escapeHtml(text) {
    if (text === null || typeof text === "undefined") return ""
    const map = { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;" }
    return text.toString().replace(/[&<>"']/g, (m) => map[m])
  }

  showNotification(message, type) {
    const notification = document.getElementById("notification")
    if(!notification) return; // Exit if notification element doesn't exist
    
    const messageEl = notification.querySelector(".notification-message")

    messageEl.textContent = message
    notification.className = `notification ${type}`
    notification.style.display = "block"

    setTimeout(() => {
      notification.style.display = "none"
    }, 5000)

    notification.querySelector(".notification-close").onclick = () => {
      notification.style.display = "none"
    }
  }

  debounce(func, delay) {
    let timeout
    return (...args) => {
      clearTimeout(timeout)
      timeout = setTimeout(() => func.apply(this, args), delay)
    }
  }
}

document.addEventListener("DOMContentLoaded", () => {
  if (document.getElementById("applicationsTableBody")) {
    window.seekerApplicationsManager = new SeekerApplicationsManager()
  }
})