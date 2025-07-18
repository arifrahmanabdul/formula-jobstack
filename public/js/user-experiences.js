class UserExperiencesManager {
    constructor() {
        this.experiences = [];
        this.currentPage = 1;
        this.totalPages = 1;
        this.searchQuery = "";
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadExperiences();
    }

    bindEvents() {
        const searchInput = document.getElementById("searchInput");
        if (searchInput) {
            searchInput.addEventListener("input", this.debounce(() => this.handleSearch(searchInput.value), 300));
        }
    }

    async loadExperiences(page = 1, search = "") {
        this.showLoading();
        try {
            const params = new URLSearchParams({ page, search });
            const response = await fetch(`/admin/user-experiences/data?${params}`, { headers: { "X-Requested-With": "XMLHttpRequest" } });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || "Gagal memuat data.");

            this.experiences = result.data;
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
        const tbody = document.getElementById("experiencesTableBody");
        const emptyState = document.getElementById("emptyState");
        const tableContainer = document.querySelector(".table-container");

        if (!tbody) return;
        if (this.experiences.length === 0) {
            tableContainer.style.display = "none";
            emptyState.style.display = "block";
            return;
        }
        tableContainer.style.display = "block";
        emptyState.style.display = "none";

        tbody.innerHTML = this.experiences.map(exp => {
            // FIX: Menggunakan nama properti yang benar dari controller: user_name, position, company_name
            return `
                <tr>
                    <td>${exp.id}</td>
                    <td>${this.escapeHtml(exp.user_name)}</td>
                    <td>${this.escapeHtml(exp.position)}</td>
                    <td>${this.escapeHtml(exp.company_name)}</td>
                    <td>${this.formatDate(exp.start_date)} - ${exp.end_date ? this.formatDate(exp.end_date) : 'Sekarang'}</td>
                </tr>
            `;
        }).join("");
    }

    renderPagination(pagination) {
        const container = document.getElementById("paginationContainer");
        if (!container || pagination.total_pages <= 1) {
            if(container) container.innerHTML = "";
            return;
        }
        let html = '<div class="pagination">';
        if (pagination.current_page > 1) html += `<button class="pagination-btn" onclick="window.experiencesManager.goToPage(${pagination.current_page - 1})">&laquo;</button>`;
        for (let i = 1; i <= pagination.total_pages; i++) {
            html += `<button class="pagination-btn ${i === pagination.current_page ? 'active' : ''}" onclick="window.experiencesManager.goToPage(${i})">${i}</button>`;
        }
        if (pagination.current_page < pagination.total_pages) html += `<button class="pagination-btn" onclick="window.experiencesManager.goToPage(${pagination.current_page + 1})">&raquo;</button>`;
        html += '</div>';
        container.innerHTML = html;
    }

    goToPage(page) { this.loadExperiences(page, this.searchQuery); }
    handleSearch(query) { this.searchQuery = query.trim(); this.loadExperiences(1, this.searchQuery); }
    
    // Fungsi hapus tidak lagi diperlukan di frontend admin
    
    showLoading() {
        document.getElementById('loadingState').style.display = 'block';
        document.querySelector('.table-container').style.display = 'none';
        document.getElementById('emptyState').style.display = 'none';
    }
    hideLoading() {
        document.getElementById('loadingState').style.display = 'none';
    }
    showError(msg) {
        const empty = document.getElementById('emptyState');
        const p = empty.querySelector('p');
        if (p) p.textContent = msg;
        empty.style.display = 'block';
        document.querySelector('.table-container').style.display = 'none';
    }
    escapeHtml(text) {
        if (text === null || typeof text === 'undefined') return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return text.toString().replace(/[&<>"']/g, m => map[m]);
    }
    debounce(func, delay) {
        let timeout;
        return (...args) => {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), delay);
        };
    }
    formatDate(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        return date.toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });
    }
}

document.addEventListener("DOMContentLoaded", () => {
    if (document.getElementById('experiencesTableBody')) {
        window.experiencesManager = new UserExperiencesManager();
    }
});
