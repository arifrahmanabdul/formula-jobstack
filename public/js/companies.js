// public/js/companies.js

class CompaniesManager {
  constructor() {
    this.companies = [];
    this.currentPage = 1;
    this.totalPages = 1;
    this.searchQuery = "";
    this.currentCompanyId = null;

    this.init();
  }

  init() {
    this.bindEvents();
    this.loadCompanies();
  }

  bindEvents() {
    const searchInput = document.getElementById("searchInput");
    if (searchInput) {
      searchInput.addEventListener("input", (e) => this.handleSearch(e.target.value));
    }

    const confirmDeleteBtn = document.getElementById("confirmDeleteBtn");
    if (confirmDeleteBtn) {
      confirmDeleteBtn.addEventListener("click", () => this.confirmDelete());
    }

    document.addEventListener("click", (e) => {
      if (e.target.classList.contains("modal") || e.target.classList.contains('modal-close')) {
        this.closeDeleteModal();
      }
    });

    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") this.closeDeleteModal();
    });
  }

  async loadCompanies(page = 1, search = "") {
    this.showLoading();
    try {
      const params = new URLSearchParams({ page, search });
      const response = await fetch(`/admin/companies/data?${params}`, {
        headers: { "X-Requested-With": "XMLHttpRequest" },
      });
      if (!response.ok) throw new Error("Gagal memuat data perusahaan.");

      const result = await response.json();
      if (result.success) {
        this.companies = result.data;
        this.currentPage = result.pagination.current_page;
        this.totalPages = result.pagination.total_pages;
        this.renderTable();
        this.renderPagination(result.pagination);
      } else {
        throw new Error(result.message || "Gagal memuat data.");
      }
    } catch (error) {
      console.error("Error loading companies:", error);
      this.showError(error.message);
    } finally {
      this.hideLoading();
    }
  }

  renderTable() {
    const tbody = document.getElementById("companiesTableBody");
    const emptyState = document.getElementById("emptyState");
    const tableContainer = document.querySelector(".table-container");

    if (!tbody) return;

    if (this.companies.length === 0) {
      if (tableContainer) tableContainer.style.display = "none";
      if (emptyState) emptyState.style.display = "block";
      return;
    }

    if (tableContainer) tableContainer.style.display = "block";
    if (emptyState) emptyState.style.display = "none";

    tbody.innerHTML = this.companies
      .map((company) => {
        const logoUrl = company.logo_url ? `/uploads/logos/${company.logo_url}` : '/img/default-logo.png';
        return `
          <tr>
            <td>${company.id}</td>
            <td><img src="${logoUrl}" alt="Logo" class="company-logo-thumb"></td>
            <td>${this.escapeHtml(company.name)}</td>
            <td>${this.escapeHtml(company.industry_name) || '-'}</td>
            <td><a href="${this.escapeHtml(company.website)}" target="_blank">${this.escapeHtml(company.website)}</a></td>
            <td>
              <div class="action-buttons">
                <a href="/admin/companies/edit/${company.id}" class="btn btn-sm btn-primary">
                  <i class="fas fa-edit"></i> Edit
                </a>
                <button class="btn btn-sm btn-danger" onclick="window.companiesManager.openDeleteModal(${company.id}, '${this.escapeHtml(company.name)}')">
                  <i class="fas fa-trash"></i> Hapus
                </button>
              </div>
            </td>
          </tr>
        `;
      }).join("");
  }

  renderPagination(pagination) {
    const paginationContainer = document.getElementById("paginationContainer");
    if (!paginationContainer) return;
    if (pagination.total_pages <= 1) {
      paginationContainer.innerHTML = "";
      return;
    }
    // Implementasi render pagination sama seperti di locations.js
    let html = '<div class="pagination">';
    if (pagination.current_page > 1) html += `<button class="pagination-btn" onclick="window.companiesManager.goToPage(${pagination.current_page - 1})">&laquo;</button>`;
    for (let i = 1; i <= pagination.total_pages; i++) {
        html += `<button class="pagination-btn ${i === pagination.current_page ? 'active' : ''}" onclick="window.companiesManager.goToPage(${i})">${i}</button>`;
    }
    if (pagination.current_page < pagination.total_pages) html += `<button class="pagination-btn" onclick="window.companiesManager.goToPage(${pagination.current_page + 1})">&raquo;</button>`;
    html += '</div>';
    paginationContainer.innerHTML = html;
  }

  goToPage(page) {
    this.loadCompanies(page, this.searchQuery);
  }

  handleSearch(query) {
    this.searchQuery = query.trim();
    this.loadCompanies(1, this.searchQuery);
  }

  openDeleteModal(id, name) {
    this.currentCompanyId = id;
    const modal = document.getElementById("deleteModal");
    const companyNameSpan = document.getElementById("deleteCompanyName");
    if (modal && companyNameSpan) {
      companyNameSpan.textContent = name;
      modal.classList.add("show");
      modal.style.display = 'flex';
    }
  }

  closeDeleteModal() {
    const modal = document.getElementById("deleteModal");
    if (modal) {
      modal.classList.remove("show");
      modal.style.display = 'none';
    }
    this.currentCompanyId = null;
  }

  async confirmDelete() {
    if (!this.currentCompanyId) return;

    const confirmBtn = document.getElementById("confirmDeleteBtn");
    const originalText = confirmBtn.innerHTML;
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menghapus...';

    try {
      const response = await fetch(`/admin/companies/delete/${this.currentCompanyId}`, {
        method: "POST",
        headers: { "X-Requested-With": "XMLHttpRequest" },
      });
      const result = await response.json();
      if (response.ok && result.success) {
        this.showNotification("Perusahaan berhasil dihapus.", "success");
        this.closeDeleteModal();
        this.loadCompanies(this.currentPage, this.searchQuery);
      } else {
        throw new Error(result.message || "Gagal menghapus perusahaan.");
      }
    } catch (error) {
      this.showNotification(error.message, "error");
    } finally {
      confirmBtn.disabled = false;
      confirmBtn.innerHTML = originalText;
    }
  }
  
  // Fungsi showLoading, hideLoading, showError, escapeHtml, showNotification sama seperti di locations.js
  showLoading() {
      const loading = document.getElementById('loadingState');
      if (loading) loading.style.display = 'block';
      const container = document.querySelector('.table-container');
      if (container) container.style.display = 'none';
  }
  hideLoading() {
      const loading = document.getElementById('loadingState');
      if (loading) loading.style.display = 'none';
  }
  showError(msg) { /* ... */ }
  escapeHtml(text) {
      const div = document.createElement("div");
      div.textContent = text;
      return div.innerHTML;
  }
  showNotification(message, type) { /* ... */ }
}

document.addEventListener("DOMContentLoaded", () => {
    if (document.getElementById('companiesTableBody')) {
        window.companiesManager = new CompaniesManager();
    }
});