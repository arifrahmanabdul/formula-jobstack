class RecruiterApplicationsManager {
  constructor() {
    this.currentPage = 1
    this.searchTerm = ""
    this.statusFilter = ""
    this.deleteApplicationId = null
    this.init()
  }

  init() {
    this.bindEvents()
    this.loadApplications()
  }

  bindEvents() {
    // Search functionality
    const searchBtn = document.getElementById("searchBtn") // searchBtn tidak ada di HTML, tapi kita biarkan untuk fleksibilitas
    const searchInput = document.getElementById("searchInput")
    const statusFilter = document.getElementById("statusFilter")
    const resetBtn = document.getElementById("resetBtn")

    if (searchBtn) {
      searchBtn.addEventListener("click", () => this.handleSearch())
    }

    if (searchInput) {
      searchInput.addEventListener("keypress", (e) => {
        if (e.key === "Enter") this.handleSearch()
      })
      // Tambahkan event 'input' untuk pencarian real-time setelah user berhenti mengetik
      searchInput.addEventListener("input", this.debounce(() => this.handleSearch(), 500));
    }

    if (statusFilter) {
      statusFilter.addEventListener("change", () => this.handleSearch())
    }

    if (resetBtn) {
      resetBtn.addEventListener("click", () => this.resetFilters())
    }

    // Modal events
    const confirmDeleteBtn = document.getElementById("confirmDeleteBtn")
    if (confirmDeleteBtn) {
      confirmDeleteBtn.addEventListener("click", () => this.confirmDelete())
    }

    // Modal close events
    const modalCloseButtons = document.querySelectorAll(".modal-close")
    modalCloseButtons.forEach((btn) => {
      btn.addEventListener("click", () => this.hideModal())
    })

    const modal = document.getElementById("deleteModal")
    if (modal) {
      modal.addEventListener("click", (e) => {
        if (e.target === modal) this.hideModal()
      })
    }
  }

  handleSearch() {
    const searchInput = document.getElementById("searchInput")
    const statusFilter = document.getElementById("statusFilter")

    this.searchTerm = searchInput ? searchInput.value.trim() : ""
    this.statusFilter = statusFilter ? statusFilter.value : ""
    this.currentPage = 1
    this.loadApplications()
  }

  resetFilters() {
    const searchInput = document.getElementById("searchInput")
    const statusFilter = document.getElementById("statusFilter")

    if (searchInput) searchInput.value = ""
    if (statusFilter) statusFilter.value = ""

    this.searchTerm = ""
    this.statusFilter = ""
    this.currentPage = 1
    this.loadApplications()
  }

  async loadApplications() {
    try {
      this.showLoading(true)

      const params = new URLSearchParams({
        page: this.currentPage,
        search: this.searchTerm,
        status: this.statusFilter,
      })

      // PASTIKAN URL INI BENAR
      const response = await fetch(`/recruiter/applications/data?${params.toString()}`)
      const data = await response.json()

      if (data.success) {
        this.renderTable(data.data)
        this.renderPagination(data.pagination)
        this.showTableContainer(data.data.length > 0)
      } else {
        this.showError("Gagal memuat data lamaran: " + (data.message || "Unknown error"))
        this.showTableContainer(false)
      }
    } catch (error) {
      console.error("Error loading applications:", error)
      this.showError("Terjadi kesalahan saat memuat data lamaran")
      this.showTableContainer(false)
    } finally {
      this.showLoading(false)
    }
  }

  renderTable(applications) {
    const tbody = document.getElementById("applicationsTableBody")
    if (!tbody) return

    if (!applications || applications.length === 0) {
      tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center">Tidak ada data lamaran</td>
                </tr>
            `
      return
    }

    tbody.innerHTML = applications
      .map(
        (app) => `
            <tr>
                <td>${this.escapeHtml(app.id)}</td>
                <td>${this.escapeHtml(app.user_name || "-")}</td>
                <td>${this.escapeHtml(app.job_title || "-")}</td>
                <td>
                    <span class="badge badge-${app.status ? app.status.replace('_', '-') : 'secondary'}">${this.getStatusLabel(app.status)}</span>
                </td>
                <td>${app.application_date ? this.formatDate(app.application_date) : "-"}</td>
                <td class="action-buttons">
                    <a href="/recruiter/applications/show/${app.id}" class="btn btn-info btn-sm" title="Lihat Detail">
                        <i class="fas fa-eye"></i>
                    </a>
                    <a href="/recruiter/applications/edit/${app.id}" class="btn btn-warning btn-sm" title="Edit Status">
                        <i class="fas fa-edit"></i>
                    </a>
                    <button type="button" class="btn btn-danger btn-sm" onclick="window.applicationsManager.showDeleteModal(${app.id})" title="Hapus">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `,
      )
      .join("")
  }

  renderPagination(pagination) {
    const paginationContainer = document.getElementById("paginationContainer");
    if (!paginationContainer) return;

    if (!pagination || pagination.total_pages <= 1) {
      paginationContainer.innerHTML = "";
      return;
    }

    let paginationHTML = '<div class="pagination">';
    if (pagination.current_page > 1) {
        paginationHTML += `<a href="#" class="pagination-btn" onclick="event.preventDefault(); window.applicationsManager.goToPage(${pagination.current_page - 1})">&laquo; Previous</a>`;
    }
    for (let i = 1; i <= pagination.total_pages; i++) {
        paginationHTML += `<a href="#" class="pagination-btn ${i === pagination.current_page ? 'active' : ''}" onclick="event.preventDefault(); window.applicationsManager.goToPage(${i})">${i}</a>`;
    }
    if (pagination.current_page < pagination.total_pages) {
        paginationHTML += `<a href="#" class="pagination-btn" onclick="event.preventDefault(); window.applicationsManager.goToPage(${pagination.current_page + 1})">Next &raquo;</a>`;
    }
    paginationHTML += '</div>';
    paginationContainer.innerHTML = paginationHTML;
  }

  goToPage(page) {
    this.currentPage = page
    this.loadApplications()
  }

  showDeleteModal(applicationId) {
    this.deleteApplicationId = applicationId
    const modal = document.getElementById("deleteModal")
    if (modal) {
      modal.style.display = "flex"
    }
  }

  hideModal() {
    const modal = document.getElementById("deleteModal")
    if (modal) {
      modal.style.display = "none"
    }
    this.deleteApplicationId = null
  }

  async confirmDelete() {
    if (!this.deleteApplicationId) return

    try {
      const response = await fetch(`/recruiter/applications/${this.deleteApplicationId}/delete`, {
        method: "DELETE", // Menggunakan method DELETE
        headers: {
          "Content-Type": "application/json",
          "X-Requested-With": "XMLHttpRequest"
        },
      })

      const data = await response.json()

      if (data.success) {
        this.hideModal()
        this.showSuccess("Lamaran berhasil dihapus")
        this.loadApplications() // Muat ulang data
      } else {
        this.showError("Gagal menghapus lamaran: " + (data.message || "Unknown error"))
      }
    } catch (error) {
      console.error("Error deleting application:", error)
      this.showError("Terjadi kesalahan saat menghapus lamaran")
    }
  }

  getStatusLabel(status) {
    const statusLabels = {
      new: "Baru",
      viewed: "Dilihat",
      sent_to_client: "Dikirim ke Klien",
      accepted: "Diterima",
      rejected: "Ditolak",
    }
    return statusLabels[status] || status || "Unknown"
  }

  formatDate(dateString) {
    try {
      const date = new Date(dateString)
      return date.toLocaleDateString("id-ID", {
        day: "2-digit",
        month: "long",
        year: "numeric",
      })
    } catch (error) {
      return dateString
    }
  }

  showTableContainer(hasData) {
    const tableContainer = document.querySelector(".table-container")
    const emptyState = document.getElementById("emptyState")

    if (tableContainer) {
      tableContainer.style.display = hasData ? "block" : "none"
    }
    if (emptyState) {
      emptyState.style.display = hasData ? "none" : "block"
    }
  }

  showLoading(show) {
    const loadingState = document.getElementById("loadingState")
    const tableContainer = document.querySelector(".table-container")

    if (loadingState) {
      loadingState.style.display = show ? "flex" : "none"
    }
    if (tableContainer) {
      tableContainer.style.opacity = show ? "0.5" : "1"
    }
  }

  // Implementasi notifikasi yang lebih baik jika diinginkan
  showSuccess(message) { alert(message) }
  showError(message) { alert(message) }

  escapeHtml(text) {
    if (typeof text !== "string") return text === null || text === undefined ? '' : text;
    const map = { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;" };
    return text.replace(/[&<>"']/g, (m) => map[m]);
  }
  
  debounce(func, delay) {
    let timeout;
    return (...args) => {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), delay);
    };
  }
}

// Inisialisasi manager ketika halaman dimuat
document.addEventListener("DOMContentLoaded", () => {
  window.applicationsManager = new RecruiterApplicationsManager()
})