// Admin Industries JavaScript
class IndustriesManager {
  constructor() {
    this.currentIndustryId = null;
    this.industries = [];
    this.filteredIndustries = [];
    this.currentPage = 1;
    this.totalPages = 1;
    this.itemsPerPage = 10;
    this.searchQuery = "";

    this.init();
  }

  init() {
    this.bindEvents();
    this.loadIndustries();
  }

  bindEvents() {
    // Add industry button
    const addBtn = document.getElementById("addIndustryBtn");
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
    const industryForm = document.getElementById("industryForm");
    if (industryForm) {
      industryForm.addEventListener("submit", (e) => this.handleFormSubmit(e));
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

  async loadIndustries(page = 1, search = "") {
    try {
      this.showLoading();

      const params = new URLSearchParams({
        page: page.toString(),
        search: search,
      });

      const response = await fetch(`/admin/industries?${params}`, {
        method: "GET",
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
      });

      if (!response.ok) {
        throw new Error("Gagal memuat data industri");
      }

      const result = await response.json();

      if (result.success) {
        this.industries = result.data;
        this.currentPage = result.pagination.current_page;
        this.totalPages = result.pagination.total_pages;
        this.renderIndustries();
        this.renderPagination(result.pagination);
      } else {
        throw new Error(result.error || "Gagal memuat data industri");
      }
    } catch (error) {
      console.error("Error loading industries:", error);
      this.showError("Gagal memuat data industri: " + error.message);
    } finally {
      this.hideLoading();
    }
  }

  renderIndustries() {
    const tbody = document.getElementById("industriesTableBody");
    const emptyState = document.getElementById("emptyState");
    const tableContainer = document.querySelector(".table-container");

    if (!tbody) return;

    if (this.industries.length === 0) {
      if (tableContainer) tableContainer.style.display = "none";
      if (emptyState) emptyState.style.display = "block";
      return;
    }

    if (tableContainer) tableContainer.style.display = "block";
    if (emptyState) emptyState.style.display = "none";

    tbody.innerHTML = this.industries
      .map(
        (industry) => `
      <tr>
        <td>${industry.id}</td>
        <td>${this.escapeHtml(industry.name)}</td>
        <td>
          <div class="action-buttons">
            <a href="/admin/industries/edit/${industry.id}" class="btn btn-sm btn-primary">
              <i class="fas fa-edit"></i>
              Edit
            </a>
            <button class="btn btn-sm btn-danger" onclick="window.industriesManager.deleteIndustry(${industry.id}, '${this.escapeHtml(industry.name).replace(/'/g, "\\'")}')">
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
      paginationHTML += `<button class="pagination-btn" onclick="window.industriesManager.goToPage(${pagination.current_page - 1})">
        <i class="fas fa-chevron-left"></i>
      </button>`;
    }

    // Page numbers
    const startPage = Math.max(1, pagination.current_page - 2);
    const endPage = Math.min(pagination.total_pages, pagination.current_page + 2);

    if (startPage > 1) {
      paginationHTML += `<button class="pagination-btn" onclick="window.industriesManager.goToPage(1)">1</button>`;
      if (startPage > 2) {
        paginationHTML += '<span class="pagination-dots">...</span>';
      }
    }

    for (let i = startPage; i <= endPage; i++) {
      const activeClass = i === pagination.current_page ? "active" : "";
      paginationHTML += `<button class="pagination-btn ${activeClass}" onclick="window.industriesManager.goToPage(${i})">${i}</button>`;
    }

    if (endPage < pagination.total_pages) {
      if (endPage < pagination.total_pages - 1) {
        paginationHTML += '<span class="pagination-dots">...</span>';
      }
      paginationHTML += `<button class="pagination-btn" onclick="window.industriesManager.goToPage(${pagination.total_pages})">${pagination.total_pages}</button>`;
    }

    // Next button
    if (pagination.current_page < pagination.total_pages) {
      paginationHTML += `<button class="pagination-btn" onclick="window.industriesManager.goToPage(${pagination.current_page + 1})">
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
      this.loadIndustries(page, this.searchQuery);
    }
  }

  handleSearch(query) {
    this.searchQuery = query.toLowerCase().trim();
    this.currentPage = 1;
    this.loadIndustries(1, this.searchQuery);
  }

  openAddModal() {
    const modal = document.getElementById("industryModal");
    const modalTitle = document.getElementById("modalTitle");
    const form = document.getElementById("industryForm");
    const nameInput = document.getElementById("industryName");

    if (!modal) return;

    modalTitle.textContent = "Tambah Industri";
    form.reset();
    nameInput.focus();
    this.currentIndustryId = null;

    this.clearErrors();
    modal.classList.add("show");
    modal.style.display = "flex";
  }

  async editIndustry(id) {
    try {
      const response = await fetch(`/admin/industries/edit/${id}`, {
        method: "GET",
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
      });

      if (!response.ok) {
        throw new Error("Gagal memuat data industri");
      }

      const result = await response.json();

      if (result.success) {
        const modal = document.getElementById("industryModal");
        const modalTitle = document.getElementById("modalTitle");
        const nameInput = document.getElementById("industryName");
        const descriptionInput = document.getElementById("industryDescription");

        modalTitle.textContent = "Edit Industri";
        nameInput.value = result.data.name;
        if (descriptionInput) {
          descriptionInput.value = result.data.description || "";
        }
        this.currentIndustryId = id;

        this.clearErrors();
        modal.classList.add("show");
        modal.style.display = "flex";
        nameInput.focus();
      } else {
        throw new Error(result.error || "Gagal memuat data industri");
      }
    } catch (error) {
      this.showNotification("Error: " + error.message, "error");
    }
  }

  deleteIndustry(id, name) {
    const modal = document.getElementById("deleteModal");
    const industryNameSpan = document.getElementById("deleteIndustryName");

    if (!modal) return;

    industryNameSpan.textContent = name;
    this.currentIndustryId = id;

    modal.classList.add("show");
    modal.style.display = "flex";
  }

  async handleFormSubmit(e) {
    e.preventDefault();

    const nameInput = document.getElementById("industryName");
    const descriptionInput = document.getElementById("industryDescription");
    const submitBtn = document.getElementById("submitBtn");

    if (!nameInput || !submitBtn) return;

    // Clear previous errors
    this.clearErrors();

    // Validate
    if (!nameInput.value.trim()) {
      this.showFieldError("industryName", "Nama industri tidak boleh kosong");
      return;
    }

    // Show loading
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';

    try {
      const url = this.currentIndustryId ? `/admin/industries/update/${this.currentIndustryId}` : "/admin/industries";

      const formData = new FormData();
      formData.append("name", nameInput.value.trim());
      if (descriptionInput) {
        formData.append("description", descriptionInput.value.trim());
      }

      const response = await fetch(url, {
        method: "POST",
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
        body: formData,
      });

      if (!response.ok) {
        throw new Error("Gagal menyimpan industri");
      }

      const result = await response.json();

      if (result.success) {
        this.showNotification(result.message || "Industri berhasil disimpan!", "success");
        this.closeModal();
        await this.loadIndustries(this.currentPage, this.searchQuery); // Reload current page
      } else {
        if (result.errors) {
          Object.keys(result.errors).forEach((field) => {
            this.showFieldError(field === "name" ? "industryName" : field, result.errors[field]);
          });
        }
        throw new Error(result.message || "Gagal menyimpan industri");
      }
    } catch (error) {
      this.showNotification("Error: " + error.message, "error");
    } finally {
      submitBtn.disabled = false;
      submitBtn.innerHTML = originalText;
    }
  }

  async confirmDelete() {
    if (!this.currentIndustryId) {
      this.showNotification("ID industri tidak valid", "error");
      return;
    }

    const confirmBtn = document.getElementById("confirmDeleteBtn");
    if (!confirmBtn) return;

    // Show loading
    const originalText = confirmBtn.innerHTML;
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menghapus...';

    try {
      console.log("Attempting to delete industry ID:", this.currentIndustryId);

      const response = await fetch(`/admin/industries/delete/${this.currentIndustryId}`, {
        method: "POST",
        headers: {
          "X-Requested-With": "XMLHttpRequest",
          "Content-Type": "application/json",
        },
      });

      console.log("Response status:", response.status);
      console.log("Response headers:", Object.fromEntries(response.headers.entries()));

      let responseText = "";
      try {
        responseText = await response.text();
        console.log("Raw response:", responseText);
      } catch (textError) {
        console.error("Error reading response text:", textError);
        throw new Error("Tidak dapat membaca response dari server");
      }

      if (!responseText || responseText.trim() === "") {
        throw new Error("Server mengembalikan response kosong");
      }

      let result;
      try {
        result = JSON.parse(responseText);
        console.log("Parsed result:", result);
      } catch (parseError) {
        console.error("JSON parse error:", parseError);
        console.error("Response text:", responseText);
        throw new Error("Response dari server tidak valid (bukan JSON)");
      }

      if (response.ok) {
        if (result && result.success === true) {
          const message = result.message || "Industri berhasil dihapus!";
          this.showNotification(message, "success");
          this.closeDeleteModal();

          // Check if current page becomes empty after deletion
          if (this.industries.length === 1 && this.currentPage > 1) {
            this.currentPage -= 1;
          }

          await this.loadIndustries(this.currentPage, this.searchQuery);
        } else {
          const errorMsg = result && result.message ? result.message : "Gagal menghapus industri";
          throw new Error(errorMsg);
        }
      } else {
        const errorMsg = result && result.message ? result.message : `Server error: ${response.status}`;
        throw new Error(errorMsg);
      }
    } catch (error) {
      console.error("Delete error:", error);

      let errorMessage = "Terjadi kesalahan saat menghapus industri";

      if (error instanceof Error) {
        errorMessage = error.message;
      } else if (typeof error === "string") {
        errorMessage = error;
      } else if (error && typeof error.message === "string") {
        errorMessage = error.message;
      } else if (error && typeof error.toString === "function") {
        errorMessage = error.toString();
      }

      this.showNotification(errorMessage, "error");
    } finally {
      confirmBtn.disabled = false;
      confirmBtn.innerHTML = originalText;
    }
  }

  closeModal() {
    const modal = document.getElementById("industryModal");
    if (modal) {
      modal.classList.remove("show");
      modal.style.display = "none";
    }
    this.currentIndustryId = null;
    this.clearErrors();
  }

  closeDeleteModal() {
    const modal = document.getElementById("deleteModal");
    if (modal) {
      modal.classList.remove("show");
      modal.style.display = "none";
    }
    this.currentIndustryId = null;
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
    const tbody = document.getElementById("industriesTableBody");
    if (tbody) {
      tbody.innerHTML = `
        <tr>
          <td colspan="3" class="text-center">
            <div class="empty-state">
              <i class="fas fa-exclamation-triangle"></i>
              <h3>Error</h3>
              <p>${message}</p>
              <button class="btn btn-primary" onclick="window.industriesManager.loadIndustries()">
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
    const errorEl = document.getElementById(fieldId.replace("industry", "").toLowerCase() + "Error");

    if (field) field.classList.add("error");
    if (errorEl) errorEl.textContent = message;
  }

  showNotification(message, type = "info") {
    // Remove existing notifications
    document.querySelectorAll(".notification").forEach((n) => n.remove());

    // Convert message to string safely
    let displayMessage = "Terjadi kesalahan sistem";

    if (typeof message === "string") {
      displayMessage = message;
    } else if (message && typeof message.message === "string") {
      displayMessage = message.message;
    } else if (message && typeof message.error === "string") {
      displayMessage = message.error;
    } else if (message && typeof message.toString === "function") {
      displayMessage = message.toString();
    }

    console.log("Showing notification:", displayMessage, "Type:", type);

    const notification = document.createElement("div");
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
      <i class="fas fa-${type === "success" ? "check-circle" : type === "error" ? "exclamation-circle" : "info-circle"}"></i>
      <span>${displayMessage}</span>
      <button onclick="this.parentElement.remove()">
        <i class="fas fa-times"></i>
      </button>
    `;

    // Add notification styles
    const colors = {
      success: "#28a745",
      error: "#dc3545",
      warning: "#ffc107",
      info: "#17a2b8",
    };

    notification.style.cssText = `
      position: fixed;
      top: 20px;
      right: 20px;
      background: ${colors[type] || colors.info};
      color: white;
      padding: 1rem 1.5rem;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
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
  if (window.industriesManager) {
    window.industriesManager.openAddModal();
  }
}

function closeModal() {
  if (window.industriesManager) {
    window.industriesManager.closeModal();
  }
}

function closeDeleteModal() {
  if (window.industriesManager) {
    window.industriesManager.closeDeleteModal();
  }
}

// Initialize when document is loaded
document.addEventListener("DOMContentLoaded", () => {
  console.log("Initializing Industries Manager...");
  window.industriesManager = new IndustriesManager();
});

// Add notification and pagination styles to head
const styles = document.createElement("style");
styles.textContent = `
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

  .pagination-container {
    margin-top: 2rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
  }

  .pagination {
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }

  .pagination-btn {
    padding: 0.5rem 0.75rem;
    border: 1px solid #ddd;
    background: white;
    color: #333;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.875rem;
    min-width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .pagination-btn:hover {
    background: #f8f9fa;
    border-color: #007bff;
    color: #007bff;
  }

  .pagination-btn.active {
    background: #007bff;
    border-color: #007bff;
    color: white;
  }

  .pagination-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }

  .pagination-dots {
    padding: 0.5rem;
    color: #666;
  }

  .pagination-info {
    font-size: 0.875rem;
    color: #666;
    text-align: center;
  }

  .error {
    border-color: #dc3545 !important;
  }

  .error-message {
    color: #dc3545;
    font-size: 0.875rem;
    margin-top: 0.25rem;
    display: block;
  }
`;
document.head.appendChild(styles);
