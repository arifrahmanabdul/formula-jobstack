class FindJobSeekerManager {
  constructor() {
    this.jobs = []
    this.currentPage = 1
    this.totalPages = 1
    this.searchQuery = ""
    this.init()
  }

  init() {
    this.bindEvents()
    this.loadJobs()
  }

  bindEvents() {
    const searchInput = document.getElementById("searchInput")
    if (searchInput) {
      searchInput.addEventListener(
        "input",
        this.debounce((e) => this.handleSearch(e.target.value), 300),
      )
    }
  }

  async loadJobs(page = 1, search = "") {
    this.showLoading()
    try {
      const params = new URLSearchParams({ page, search, limit: 6 })
      // Gunakan endpoint khusus untuk seeker
      const response = await fetch(`/seeker/jobs/data?${params}`, {
        headers: { "X-Requested-With": "XMLHttpRequest" },
      })
      if (!response.ok) throw new Error("Gagal memuat data lowongan.")

      const result = await response.json()
      if (result.success) {
        this.jobs = result.data
        this.currentPage = result.pagination.current_page
        this.totalPages = result.pagination.total_pages
        this.renderCards()
        this.renderPagination(result.pagination)
      } else {
        throw new Error(result.message || "Gagal memuat data.")
      }
    } catch (error) {
      console.error("Error loading jobs:", error)
      this.showError(error.message)
    } finally {
      this.hideLoading()
    }
  }

  renderCards() {
    const container = document.getElementById("jobsContainer")
    const emptyState = document.getElementById("emptyState")

    if (!container || !emptyState) return

    if (this.jobs.length === 0) {
      container.style.display = "none"
      emptyState.style.display = "block"
      return
    }
    container.style.display = "grid"
    emptyState.style.display = "none"

    container.innerHTML = this.jobs
      .map((job) => {
        const logoUrl = job.company_logo ? `/uploads/logos/${job.company_logo}` : "/img/default-logo.png"
        const salary =
          job.salary_min && job.salary_max
            ? `Rp ${this.formatNumber(job.salary_min)} - ${this.formatNumber(job.salary_max)}`
            : "Gaji Kompetitif"
        const jobTypeClass = String(job.job_type || "Full-time").toLowerCase()

        return `
                <div class="job-card">
                    <div class="job-card-header">
                        <img src="${logoUrl}" alt="Logo ${this.escapeHtml(job.company_name)}" class="job-card-logo" onerror="this.src='/img/default-logo.png'">
                        <div class="job-card-company-info">
                            <h4 class="job-card-title">${this.escapeHtml(job.title)}</h4>
                            <p class="job-card-company-name">${this.escapeHtml(job.company_name)}</p>
                        </div>
                    </div>
                    <div class="job-card-meta">
                        <span><i class="fas fa-map-marker-alt"></i> ${this.escapeHtml(job.location_name)}</span>
                        <span class="badge-job-type ${jobTypeClass}"><i class="fas fa-briefcase"></i> ${this.escapeHtml(job.job_type)}</span>
                    </div>
                     <div class="job-card-salary">
                        <span><i class="fas fa-money-bill-wave"></i> ${salary}</span>
                    </div>
                    <div class="job-card-footer">
                        <a href="/seeker/find-job/detail/${job.id}" class="btn btn-secondary btn-sm">
                            <i class="fas fa-eye"></i> Detail
                        </a>
                        <a href="/seeker/applications/create/${job.id}" class="btn btn-primary btn-sm">
                            <i class="fas fa-paper-plane"></i> Lamar Sekarang
                        </a>
                    </div>
                </div>
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
      html += `<button class="pagination-btn" onclick="window.jobManager.goToPage(${pagination.current_page - 1})">&laquo;</button>`
    }
    for (let i = 1; i <= pagination.total_pages; i++) {
      html += `<button class="pagination-btn ${i === pagination.current_page ? "active" : ""}" onclick="window.jobManager.goToPage(${i})">${i}</button>`
    }
    if (pagination.current_page < pagination.total_pages) {
      html += `<button class="pagination-btn" onclick="window.jobManager.goToPage(${pagination.current_page + 1})">&raquo;</button>`
    }
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

  debounce(func, delay) {
    let timeout
    return (...args) => {
      clearTimeout(timeout)
      timeout = setTimeout(() => func.apply(this, args), delay)
    }
  }

  showLoading() {
    document.getElementById("loadingState").style.display = "flex"
    document.getElementById("jobsContainer").style.display = "none"
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
    document.getElementById("jobsContainer").style.display = "none"
  }

  escapeHtml(text) {
    if (text === null || typeof text === "undefined") return ""
    const map = { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;" }
    return String(text).replace(/[&<>"']/g, (m) => map[m])
  }

  formatNumber(num) {
    if (num === null || typeof num === "undefined") return ""
    return new Intl.NumberFormat("id-ID").format(num)
  }
}

document.addEventListener("DOMContentLoaded", () => {
  if (document.getElementById("jobsContainer")) {
    window.jobManager = new FindJobSeekerManager()
  }
})
