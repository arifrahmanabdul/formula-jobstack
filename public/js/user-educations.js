class UserEducationsManager {
    constructor() {
        this.educations = [];
        this.currentPage = 1;
        this.totalPages = 1;
        this.searchQuery = "";
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadEducations();
    }

    bindEvents() {
        const searchInput = document.getElementById("searchInput");
        if (searchInput) {
            searchInput.addEventListener("input", this.debounce(() => this.handleSearch(searchInput.value), 300));
        }
    }

    async loadEducations(page = 1, search = "") {
        this.showLoading();
        try {
            const params = new URLSearchParams({ page, search });
            const response = await fetch(`/admin/user-educations/data?${params}`, { headers: { "X-Requested-With": "XMLHttpRequest" } });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || "Gagal memuat data.");

            this.educations = result.data;
            this.currentPage = result.pagination.current_page;
            this.totalPages = result.pagination.total_pages;
            this.renderTable();
            this.renderPagination(result.pagination);
        } catch (error) {
            this.showError(error.message);
        } finally {
            this.hideLoading();
        }
    }

    renderTable() {
        const tbody = document.getElementById("educationsTableBody");
        const emptyState = document.getElementById("emptyState");
        const tableContainer = document.querySelector(".table-container");

        if (!tbody) return;
        if (!this.educations || this.educations.length === 0) {
            if (tableContainer) tableContainer.style.display = "none";
            if (emptyState) emptyState.style.display = "block";
            return;
        }
        if (tableContainer) tableContainer.style.display = "block";
        if (emptyState) emptyState.style.display = "none";

        tbody.innerHTML = this.educations.map(edu => `
            <tr>
                <td>${edu.id || '-'}</td>
                <td>${this.escapeHtml(edu.user_name)}</td>
                <td>${this.escapeHtml(edu.institution)}</td>
                <td>${this.escapeHtml(edu.degree)}</td>
                <td>${this.escapeHtml(edu.field_of_study)}</td>
                <td>${this.formatDate(edu.start_date)} - ${this.formatDate(edu.end_date)}</td>
                <td>${edu.gpa || '-'}</td>
            </tr>
        `).join("");
    }

    renderPagination(pagination) {
        const container = document.getElementById("paginationContainer");
        if (!container || pagination.total_pages <= 1) {
            if(container) container.innerHTML = "";
            return;
        }
        let html = '<div class="pagination">';
        if (pagination.current_page > 1) html += `<button class="pagination-btn" onclick="window.educationsManager.goToPage(${pagination.current_page - 1})">&laquo;</button>`;
        for (let i = 1; i <= pagination.total_pages; i++) {
            html += `<button class="pagination-btn ${i === pagination.current_page ? 'active' : ''}" onclick="window.educationsManager.goToPage(${i})">${i}</button>`;
        }
        if (pagination.current_page < pagination.total_pages) html += `<button class="pagination-btn" onclick="window.educationsManager.goToPage(${pagination.current_page + 1})">&raquo;</button>`;
        html += '</div>';
        container.innerHTML = html;
    }

    goToPage(page) { this.loadEducations(page, this.searchQuery); }
    handleSearch(query) { this.searchQuery = query.trim(); this.loadEducations(1, this.searchQuery); }
    
    showLoading() {
        const loadingState = document.getElementById('loadingState');
        const tableContainer = document.querySelector('.table-container');
        const emptyState = document.getElementById('emptyState');
        if (loadingState) loadingState.style.display = 'block';
        if (tableContainer) tableContainer.style.display = 'none';
        if (emptyState) emptyState.style.display = 'none';
    }
    hideLoading() {
        const loadingState = document.getElementById('loadingState');
        if (loadingState) loadingState.style.display = 'none';
    }
    showError(msg) {
        const emptyState = document.getElementById('emptyState');
        const p = emptyState.querySelector('p');
        if (p) p.textContent = msg;
        emptyState.style.display = 'block';
        document.querySelector('.table-container').style.display = 'none';
    }
    escapeHtml(text) {
        if (text === null || typeof text === 'undefined' || text === '') return '-';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }
    debounce(func, delay) {
        let timeout;
        return (...args) => {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), delay);
        };
    }
    formatDate(dateString) {
        if (!dateString) return 'Sekarang';
        const date = new Date(dateString);
        return date.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
    }
}

document.addEventListener("DOMContentLoaded", () => {
    if (document.getElementById('educationsTableBody')) {
        window.educationsManager = new UserEducationsManager();
    }
});
