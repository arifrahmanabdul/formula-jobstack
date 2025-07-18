/**
 * File: public/js/recruiter-jobs.js
 * Deskripsi: Mengelola semua interaksi pada halaman "Kelola Lowongan" untuk Perekrut,
 * termasuk memuat data, render tabel, pencarian, paginasi, dan aksi hapus.
 */
class RecruiterJobsManager {
  constructor() {
    this.jobs = []
    this.currentPage = 1
    this.totalPages = 1
    this.searchQuery = ""
    this.currentJobId = null

    this.handleSearch = this.debounce(this.handleSearch.bind(this), 300)
    this.confirmDelete = this.confirmDelete.bind(this)
    this.closeDeleteModal = this.closeDeleteModal.bind(this)

    this.init()
  }

  init() {
    this.bindEvents()
    this.loadJobs()
  }

  bindEvents() {
    const searchInput = document.getElementById("searchInput")
    if (searchInput) searchInput.addEventListener("input", (e) => this.handleSearch(e.target.value))

    const confirmDeleteBtn = document.getElementById("confirmDeleteBtn")
    if (confirmDeleteBtn) confirmDeleteBtn.addEventListener("click", this.confirmDelete)

    const tableBody = document.getElementById("jobsTableBody")
    if (tableBody) {
      tableBody.addEventListener("click", (e) => {
        if (e.target && e.target.closest(".delete-btn")) {
          const button = e.target.closest(".delete-btn")
          this.openDeleteModal(button.dataset.id, button.dataset.title)
        }
      })
    }

    document.addEventListener("click", (e) => {
      if (e.target.matches(".modal, .modal-close")) this.closeDeleteModal()
    })
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") this.closeDeleteModal()
    })
  }

  async loadJobs(page = 1, search = "") {
    this.showLoading()
    try {
      const params = new URLSearchParams({ page, search })
      const response = await fetch(`/recruiter/jobs/data?${params}`, {
        headers: { "X-Requested-With": "XMLHttpRequest" },
      })
      const result = await response.json()
      if (!response.ok || !result.success) throw new Error(result.message || "Gagal memuat data.")

      this.jobs = result.data
      this.currentPage = result.pagination.current_page
      this.totalPages = result.pagination.total_pages
      this.renderTable()
      this.renderPagination(result.pagination)
    } catch (error) {
      console.error("Error loading jobs:", error)
      this.showError(error.message)
    } finally {
      this.hideLoading()
    }
  }

  renderTable() {
    const tbody = document.getElementById("jobsTableBody")
    const emptyState = document.getElementById("emptyState")
    const tableContainer = document.querySelector(".table-container")

    if (!tbody) return
    if (this.jobs.length === 0) {
      tableContainer.style.display = "none"
      emptyState.style.display = "block"
      return
    }
    tableContainer.style.display = "block"
    emptyState.style.display = "none"

    tbody.innerHTML = this.jobs
      .map((job) => {
        const jobImage = job.job_image ? `/uploads/jobs/${job.job_image}` : null
        const companyLogo = job.company_logo ? `/uploads/logos/${job.company_logo}` : "/img/default-logo.png"
        const displayImage = jobImage || companyLogo

        // Format status with proper badge styling
        const statusClass = String(job.status || "open").toLowerCase()
        const statusText = job.status || "open"

        return `
          <tr>
            <td>${job.id}</td>
            <td><img src="${displayImage}" alt="Gambar Lowongan" class="job-image-thumb" onerror="this.onerror=null;this.src='/img/default-logo.png';"></td>
            <td>${this.escapeHtml(job.title)}</td>
            <td>${this.escapeHtml(job.category_name)}</td>
            <td>${this.escapeHtml(job.location_name)}</td>
            <td><span class="badge badge-${statusClass}">${this.escapeHtml(statusText)}</span></td>
            <td>
              <div class="action-buttons">
                <a href="/recruiter/jobs/edit/${job.id}" class="btn btn-sm btn-primary">
                  <i class="fas fa-edit"></i> Edit
                </a>
                <button class="btn btn-sm btn-danger delete-btn" data-id="${job.id}" data-title="${this.escapeHtml(job.title)}">
                  <i class="fas fa-trash"></i> Hapus
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
    if (pagination.current_page > 1)
      html += `<button class="pagination-btn" onclick="window.recruiterJobsManager.goToPage(${pagination.current_page - 1})">&laquo;</button>`

    const totalPages = pagination.total_pages
    const currentPage = pagination.current_page
    let pages = []
    if (totalPages <= 5) {
      for (let i = 1; i <= totalPages; i++) pages.push(i)
    } else {
      pages.push(1)
      if (currentPage > 3) pages.push("...")
      if (currentPage > 2) pages.push(currentPage - 1)
      if (currentPage > 1 && currentPage < totalPages) pages.push(currentPage)
      if (currentPage < totalPages - 1) pages.push(currentPage + 1)
      if (currentPage < totalPages - 2) pages.push("...")
      pages.push(totalPages)
    }

    pages = [...new Set(pages)]

    pages.forEach((page) => {
      if (page === "...") {
        html += `<span class="pagination-dots">...</span>`
      } else {
        html += `<button class="pagination-btn ${page === currentPage ? "active" : ""}" onclick="window.recruiterJobsManager.goToPage(${page})">${page}</button>`
      }
    })

    if (pagination.current_page < pagination.total_pages)
      html += `<button class="pagination-btn" onclick="window.recruiterJobsManager.goToPage(${pagination.current_page + 1})">&raquo;</button>`
    html += "</div>"
    container.innerHTML = html
  }

  goToPage(page) {
    this.loadJobs(page, this.searchQuery)
  }

  handleSearch(query) {
    this.searchQuery = query.trim()
    this.loadJobs(1, this.searchQuery)
  }

  openDeleteModal(id, title) {
    this.currentJobId = id
    document.getElementById("deleteJobTitle").textContent = title
    document.getElementById("deleteModal").style.display = "flex"
  }

  closeDeleteModal() {
    document.getElementById("deleteModal").style.display = "none"
    this.currentJobId = null
  }

  async confirmDelete() {
    if (!this.currentJobId) return
    const btn = document.getElementById("confirmDeleteBtn")
    btn.disabled = true
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menghapus...'
    try {
      const response = await fetch(`/recruiter/jobs/delete/${this.currentJobId}`, {
        method: "POST",
        headers: { "X-Requested-With": "XMLHttpRequest" },
      })
      const result = await response.json()
      if (!response.ok || !result.success) throw new Error(result.message || "Gagal menghapus.")
      this.showNotification("Lowongan berhasil diarsipkan.", "success")
      this.closeDeleteModal()
      if (this.jobs.length === 1 && this.currentPage > 1) {
        this.loadJobs(this.currentPage - 1, this.searchQuery)
      } else {
        this.loadJobs(this.currentPage, this.searchQuery)
      }
    } catch (error) {
      this.showNotification(error.message, "error")
    } finally {
      btn.disabled = false
      btn.innerHTML = "Ya, Hapus"
    }
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
    if (text === null || typeof text === "undefined" || text === "") return "-"
    const map = { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;" }
    return String(text).replace(/[&<>"']/g, (m) => map[m])
  }

  showNotification(message, type) {
    const existingNotif = document.querySelector(".notification")
    if (existingNotif) existingNotif.remove()

    const notification = document.createElement("div")
    notification.className = `notification notification-${type}`
    notification.innerHTML = `<span>${message}</span><button onclick="this.parentElement.remove()" style="background:none;border:none;color:white;font-size:1.2rem;cursor:pointer;margin-left:1rem;">&times;</button>`

    Object.assign(notification.style, {
      position: "fixed",
      top: "20px",
      right: "20px",
      padding: "1rem",
      color: "white",
      borderRadius: "8px",
      zIndex: 1000,
      backgroundColor: type === "success" ? "#28a745" : "#dc3545",
      display: "flex",
      alignItems: "center",
    })

    document.body.appendChild(notification)
    setTimeout(() => notification.remove(), 5000)
  }

  debounce(func, delay) {
    let timeout
    return function (...args) {
      clearTimeout(timeout)
      timeout = setTimeout(() => func.apply(this, args), delay)
    }
  }
}

document.addEventListener("DOMContentLoaded", () => {
  if (document.querySelector("#jobsTableBody")) {
    window.recruiterJobsManager = new RecruiterJobsManager()
  }
})
