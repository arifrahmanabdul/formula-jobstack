// public/js/locations.js

class LocationsManager {
  constructor() {
    this.locations = [];
    this.currentPage = 1;
    this.totalPages = 1;
    this.searchQuery = "";
    this.currentLocationId = null;

    this.init();
  }

  init() {
    this.bindEvents();
    this.loadLocations();
  }

  bindEvents() {
    // Search
    const searchInput = document.getElementById("searchInput");
    if (searchInput) {
      searchInput.addEventListener("input", (e) => this.handleSearch(e.target.value));
    }

    // Delete confirmation
    const confirmDeleteBtn = document.getElementById("confirmDeleteBtn");
    if (confirmDeleteBtn) {
      confirmDeleteBtn.addEventListener("click", () => this.confirmDelete());
    }

    // Modal close events
    document.addEventListener("click", (e) => {
        if (e.target.classList.contains("modal") || e.target.classList.contains('modal-close')) {
            this.closeDeleteModal();
        }
    });

    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape") {
            this.closeDeleteModal();
        }
    });
  }

  async loadLocations(page = 1, search = "") {
    this.showLoading();
    try {
      const params = new URLSearchParams({ page, search });
      const response = await fetch(`/admin/locations/data?${params}`, {
        headers: { "X-Requested-With": "XMLHttpRequest" },
      });
      if (!response.ok) throw new Error("Gagal memuat data.");

      const result = await response.json();
      if (result.success) {
        this.locations = result.data;
        this.currentPage = result.pagination.current_page;
        this.totalPages = result.pagination.total_pages;
        this.renderTable();
        this.renderPagination(result.pagination);
      } else {
        throw new Error(result.message || "Gagal memuat data.");
      }
    } catch (error) {
      console.error("Error loading locations:", error);
      this.showError(error.message);
    } finally {
      this.hideLoading();
    }
  }

  renderTable() {
    const tbody = document.getElementById("locationsTableBody");
    const emptyState = document.getElementById("emptyState");
    const tableContainer = document.querySelector(".table-container");

    if (!tbody) return;

    if (this.locations.length === 0) {
      if(tableContainer) tableContainer.style.display = "none";
      if(emptyState) emptyState.style.display = "block";
      return;
    }

    if(tableContainer) tableContainer.style.display = "block";
    if(emptyState) emptyState.style.display = "none";

    tbody.innerHTML = this.locations
      .map(
        (loc) => `
        <tr>
          <td>${loc.id}</td>
          <td>${this.escapeHtml(loc.city)}</td>
          <td>${this.escapeHtml(loc.province)}</td>
          <td>${this.escapeHtml(loc.country)}</td>
          <td>
            <div class="action-buttons">
              <a href="/admin/locations/edit/${loc.id}" class="btn btn-sm btn-primary">
                <i class="fas fa-edit"></i> Edit
              </a>
              <button class="btn btn-sm btn-danger" onclick="window.locationsManager.openDeleteModal(${loc.id}, '${this.escapeHtml(loc.city)}')">
                <i class="fas fa-trash"></i> Hapus
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
    if (!paginationContainer) return;

    if (pagination.total_pages <= 1) {
      paginationContainer.innerHTML = "";
      return;
    }

    let paginationHTML = '<div class="pagination">';
    // Previous
    if (pagination.current_page > 1) {
      paginationHTML += `<button class="pagination-btn" onclick="window.locationsManager.goToPage(${pagination.current_page - 1})">&laquo;</button>`;
    }

    // Pages
    for (let i = 1; i <= pagination.total_pages; i++) {
      const activeClass = i === pagination.current_page ? "active" : "";
      paginationHTML += `<button class="pagination-btn ${activeClass}" onclick="window.locationsManager.goToPage(${i})">${i}</button>`;
    }

    // Next
    if (pagination.current_page < pagination.total_pages) {
      paginationHTML += `<button class="pagination-btn" onclick="window.locationsManager.goToPage(${pagination.current_page + 1})">&raquo;</button>`;
    }
    paginationHTML += "</div>";
    
    paginationContainer.innerHTML = paginationHTML;
  }

  goToPage(page) {
    this.loadLocations(page, this.searchQuery);
  }

  handleSearch(query) {
    this.searchQuery = query.trim();
    this.loadLocations(1, this.searchQuery);
  }

  openDeleteModal(id, name) {
    this.currentLocationId = id;
    const modal = document.getElementById("deleteModal");
    const locationNameSpan = document.getElementById("deleteLocationName");
    if (modal && locationNameSpan) {
      locationNameSpan.textContent = name;
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
    this.currentLocationId = null;
  }

  async confirmDelete() {
    if (!this.currentLocationId) return;

    const confirmBtn = document.getElementById("confirmDeleteBtn");
    const originalText = confirmBtn.innerHTML;
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menghapus...';

    try {
      const response = await fetch(`/admin/locations/delete/${this.currentLocationId}`, {
        method: "POST",
        headers: { "X-Requested-With": "XMLHttpRequest" },
      });

      const result = await response.json();

      if (response.ok && result.success) {
        this.showNotification(result.message || "Lokasi berhasil dihapus.", "success");
        this.closeDeleteModal();
        this.loadLocations(this.currentPage, this.searchQuery);
      } else {
        throw new Error(result.message || "Gagal menghapus lokasi.");
      }
    } catch (error) {
      this.showNotification(error.message, "error");
    } finally {
      confirmBtn.disabled = false;
      confirmBtn.innerHTML = originalText;
    }
  }

  showLoading() {
    const loadingState = document.getElementById("loadingState");
    const tableContainer = document.querySelector(".table-container");
    const emptyState = document.getElementById("emptyState");
    if(loadingState) loadingState.style.display = 'block';
    if(tableContainer) tableContainer.style.display = 'none';
    if(emptyState) emptyState.style.display = 'none';
  }

  hideLoading() {
    const loadingState = document.getElementById("loadingState");
    if(loadingState) loadingState.style.display = 'none';
  }

  showError(message) {
      // Sama seperti di JobCategoriesManager
  }

  escapeHtml(text) {
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
  }

  showNotification(message, type = 'info') {
      // Sama seperti di JobCategoriesManager
  }
}

document.addEventListener("DOMContentLoaded", () => {
    if(document.getElementById('locationsTableBody')) {
        window.locationsManager = new LocationsManager();
    }
});