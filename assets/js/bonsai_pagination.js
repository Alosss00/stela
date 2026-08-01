/**
 * Bonsai.io (Elasticsearch) & MySQL Fallback Server-Side Pagination & Search JS Helper
 * Handles debounced search, active filters, page size limits, and pagination controls.
 */

class BonsaiPagination {
    constructor(options) {
        this.apiUrl = options.apiUrl || '/api/search_elasticsearch.php';
        this.target = options.target || 'employees'; // 'employees' or 'appointments'
        this.tableSelector = options.tableSelector || '#employeesTable';
        this.tbodySelector = options.tbodySelector || (this.tableSelector + ' tbody');
        this.searchInputSelector = options.searchInputSelector || '#esSearchInput';
        this.clearBtnSelector = options.clearBtnSelector || '#esClearBtn';
        this.paginationContainerSelector = options.paginationContainerSelector || '#bonsaiPaginationContainer';
        this.infoContainerSelector = options.infoContainerSelector || '#bonsaiInfoContainer';
        this.filterSelectors = options.filterSelectors || {}; // e.g. { company: '#filterCompany', status: '#filterStatus' }
        this.limitSelector = options.limitSelector || '#bonsaiPageLimit';
        this.renderRow = options.renderRow || null; // Custom row renderer function

        // State
        this.page = 1;
        this.limit = options.defaultLimit || 10;
        this.query = '';
        this.filters = {};
        this.total = 0;
        this.totalPages = 1;
        this.source = 'elasticsearch';
        this.debounceTimer = null;

        this.init();
    }

    init() {
        const self = this;

        // Load URL Params if present
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('q')) this.query = urlParams.get('q');
        if (urlParams.has('page')) this.page = parseInt(urlParams.get('page'), 10) || 1;
        if (urlParams.has('limit')) this.limit = parseInt(urlParams.get('limit'), 10) || this.limit;

        // Search Input Listener
        const searchInput = document.querySelector(this.searchInputSelector);
        if (searchInput) {
            searchInput.value = this.query;
            searchInput.addEventListener('input', function() {
                self.query = this.value.trim();
                self.page = 1; // Reset to page 1 on new search

                const clearBtn = document.querySelector(self.clearBtnSelector);
                if (clearBtn) {
                    clearBtn.style.display = self.query.length > 0 ? 'block' : 'none';
                }

                clearTimeout(self.debounceTimer);
                self.debounceTimer = setTimeout(() => {
                    self.fetchData();
                }, 200);
            });
        }

        // Clear Button Listener
        const clearBtn = document.querySelector(this.clearBtnSelector);
        if (clearBtn) {
            if (this.query.length > 0) clearBtn.style.display = 'block';
            clearBtn.addEventListener('click', function() {
                if (searchInput) searchInput.value = '';
                self.query = '';
                self.page = 1;
                clearBtn.style.display = 'none';
                self.fetchData();
            });
        }

        // Filter Selectors Listeners
        Object.keys(this.filterSelectors).forEach(key => {
            const el = document.querySelector(this.filterSelectors[key]);
            if (el) {
                if (urlParams.has(key)) {
                    el.value = urlParams.get(key);
                    this.filters[key] = el.value;
                }
                el.addEventListener('change', function() {
                    self.filters[key] = this.value;
                    self.page = 1; // Reset to page 1 on filter change
                    self.fetchData();
                });
            }
        });

        // Limit / Page Size Selector
        const limitEl = document.querySelector(this.limitSelector);
        if (limitEl) {
            limitEl.value = this.limit;
            limitEl.addEventListener('change', function() {
                self.limit = parseInt(this.value, 10) || 10;
                self.page = 1;
                self.fetchData();
            });
        }

        // Initial Data Fetch
        this.fetchData();
    }

    fetchData() {
        const self = this;
        const tbody = document.querySelector(this.tbodySelector);
        
        // Show subtle loading state
        if (tbody && tbody.children.length > 0) {
            tbody.style.opacity = '0.5';
        }

        // Build Query Parameters
        const params = new URLSearchParams();
        params.append('target', this.target);
        params.append('q', this.query);
        params.append('page', this.page);
        params.append('limit', this.limit);

        Object.keys(this.filters).forEach(k => {
            if (this.filters[k]) {
                params.append(k, this.filters[k]);
            }
        });

        // Update URL Query String without full refresh
        const newUrl = window.location.pathname + '?' + params.toString();
        window.history.replaceState({ path: newUrl }, '', newUrl);

        fetch(this.apiUrl + '?' + params.toString())
            .then(res => {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(data => {
                if (tbody) tbody.style.opacity = '1';
                if (data && data.status === 'success') {
                    self.total = data.total || 0;
                    self.totalPages = data.total_pages || 1;
                    self.source = data.source || 'elasticsearch';
                    self.renderTable(data.items || []);
                    self.renderPaginationControls();
                    self.renderInfo();
                } else {
                    console.error('BonsaiPagination API Error:', data.message);
                }
            })
            .catch(err => {
                if (tbody) tbody.style.opacity = '1';
                console.warn('BonsaiPagination Notice:', err.message);
            });
    }

    renderTable(items) {
        const tbody = document.querySelector(this.tbodySelector);
        if (!tbody) return;

        tbody.innerHTML = '';

        if (items.length === 0) {
            const tr = document.createElement('tr');
            const colCount = document.querySelectorAll(this.tableSelector + ' th').length || 7;
            tr.innerHTML = `<td colspan="${colCount}" style="text-align: center; padding: 24px; color: #718096;">
                <i class="fas fa-search" style="font-size: 24px; margin-bottom: 8px; color: #a0aec0;"></i><br>
                Tidak ada data yang ditemukan.
            </td>`;
            tbody.appendChild(tr);
            return;
        }

        items.forEach((item, index) => {
            if (typeof this.renderRow === 'function') {
                const trHtml = this.renderRow(item, index, (this.page - 1) * this.limit + index + 1);
                if (typeof trHtml === 'string') {
                    const temp = document.createElement('tbody');
                    temp.innerHTML = trHtml.trim();
                    if (temp.firstElementChild) {
                        tbody.appendChild(temp.firstElementChild);
                    }
                } else if (trHtml instanceof HTMLElement) {
                    tbody.appendChild(trHtml);
                }
            }
        });
    }

    renderInfo() {
        const container = document.querySelector(this.infoContainerSelector);
        if (!container) return;

        const start = this.total === 0 ? 0 : (this.page - 1) * this.limit + 1;
        const end = Math.min(this.page * this.limit, this.total);
        const sourceLabel = this.source === 'elasticsearch' ? 
            '<span class="badge bg-success" style="font-size: 11px; padding: 4px 8px;"><i class="fas fa-bolt"></i> Bonsai.io</span>' : 
            '<span class="badge bg-secondary" style="font-size: 11px; padding: 4px 8px;"><i class="fas fa-database"></i> MySQL Fallback</span>';

        container.innerHTML = `Showing ${start} to ${end} of ${this.total} entries &nbsp; ${sourceLabel}`;
    }

    renderPaginationControls() {
        const container = document.querySelector(this.paginationContainerSelector);
        if (!container) return;

        container.innerHTML = '';
        if (this.totalPages <= 1) return;

        const nav = document.createElement('nav');
        const ul = document.createElement('ul');
        ul.className = 'pagination pagination-sm m-0';

        const self = this;

        // Previous Button
        const prevLi = document.createElement('li');
        prevLi.className = 'page-item ' + (this.page <= 1 ? 'disabled' : '');
        prevLi.innerHTML = `<a class="page-link" href="#" aria-label="Previous">&laquo; Prev</a>`;
        prevLi.addEventListener('click', function(e) {
            e.preventDefault();
            if (self.page > 1) {
                self.page--;
                self.fetchData();
            }
        });
        ul.appendChild(prevLi);

        // Page Numbers Window
        const maxPagesToShow = 5;
        let startPage = Math.max(1, this.page - 2);
        let endPage = Math.min(this.totalPages, startPage + maxPagesToShow - 1);

        if (endPage - startPage < maxPagesToShow - 1) {
            startPage = Math.max(1, endPage - maxPagesToShow + 1);
        }

        if (startPage > 1) {
            const firstLi = document.createElement('li');
            firstLi.className = 'page-item';
            firstLi.innerHTML = `<a class="page-link" href="#">1</a>`;
            firstLi.addEventListener('click', function(e) {
                e.preventDefault();
                self.page = 1;
                self.fetchData();
            });
            ul.appendChild(firstLi);

            if (startPage > 2) {
                const ellipsisLi = document.createElement('li');
                ellipsisLi.className = 'page-item disabled';
                ellipsisLi.innerHTML = `<span class="page-link">...</span>`;
                ul.appendChild(ellipsisLi);
            }
        }

        for (let p = startPage; p <= endPage; p++) {
            const pageLi = document.createElement('li');
            pageLi.className = 'page-item ' + (p === this.page ? 'active' : '');
            pageLi.innerHTML = `<a class="page-link" href="#">${p}</a>`;
            const targetPage = p;
            pageLi.addEventListener('click', function(e) {
                e.preventDefault();
                if (self.page !== targetPage) {
                    self.page = targetPage;
                    self.fetchData();
                }
            });
            ul.appendChild(pageLi);
        }

        if (endPage < this.totalPages) {
            if (endPage < this.totalPages - 1) {
                const ellipsisLi = document.createElement('li');
                ellipsisLi.className = 'page-item disabled';
                ellipsisLi.innerHTML = `<span class="page-link">...</span>`;
                ul.appendChild(ellipsisLi);
            }

            const lastLi = document.createElement('li');
            lastLi.className = 'page-item';
            lastLi.innerHTML = `<a class="page-link" href="#">${this.totalPages}</a>`;
            lastLi.addEventListener('click', function(e) {
                e.preventDefault();
                self.page = self.totalPages;
                self.fetchData();
            });
            ul.appendChild(lastLi);
        }

        // Next Button
        const nextLi = document.createElement('li');
        nextLi.className = 'page-item ' + (this.page >= this.totalPages ? 'disabled' : '');
        nextLi.innerHTML = `<a class="page-link" href="#" aria-label="Next">Next &raquo;</a>`;
        nextLi.addEventListener('click', function(e) {
            e.preventDefault();
            if (self.page < self.totalPages) {
                self.page++;
                self.fetchData();
            }
        });
        ul.appendChild(nextLi);

        nav.appendChild(ul);
        container.appendChild(nav);
    }
}
