/**
 * Bonsai.io (Elasticsearch) & MySQL Fallback Server-Side Pagination & Search JS Helper
 * Features: Live search, Autocomplete Dropdown / Autocorrect Suggestions, Active Filters, Page Size Limits, Pagination Controls.
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
        this.lastItems = [];

        // Autocomplete Dropdown State
        this.dropdownEl = null;
        this.selectedIndex = -1;

        this.init();
    }

    init() {
        const self = this;

        // Load URL Params if present
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('q')) this.query = urlParams.get('q');
        if (urlParams.has('page')) this.page = parseInt(urlParams.get('page'), 10) || 1;
        if (urlParams.has('limit')) this.limit = parseInt(urlParams.get('limit'), 10) || this.limit;

        // Inject Styles for Autocomplete Dropdown
        this.injectStyles();

        // Setup Autocomplete Container
        const searchInput = document.querySelector(this.searchInputSelector);
        if (searchInput) {
            this.setupAutocompleteDropdown(searchInput);

            searchInput.value = this.query;

            const triggerSuggest = () => {
                self.query = searchInput.value.trim();
                self.page = 1; // Reset to page 1 on new search

                const clearBtn = document.querySelector(self.clearBtnSelector);
                if (clearBtn) {
                    clearBtn.style.display = self.query.length > 0 ? 'block' : 'none';
                }

                clearTimeout(self.debounceTimer);
                self.debounceTimer = setTimeout(() => {
                    self.fetchData(true); // true = update autocomplete suggestions
                }, 150);
            };

            searchInput.addEventListener('input', triggerSuggest);
            searchInput.addEventListener('focus', function() {
                if (self.query.length > 0) {
                    if (self.lastItems && self.lastItems.length > 0) {
                        self.renderAutocompleteSuggestions(self.lastItems);
                    } else {
                        triggerSuggest();
                    }
                }
            });

            // Keyboard navigation in search input
            searchInput.addEventListener('keydown', function(e) {
                if (!self.dropdownEl || self.dropdownEl.style.display === 'none') return;
                const items = self.dropdownEl.querySelectorAll('.bonsai-suggest-item');
                if (items.length === 0) return;

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    self.selectedIndex = (self.selectedIndex + 1) % items.length;
                    self.updateHighlightedSuggestion(items);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    self.selectedIndex = (self.selectedIndex - 1 + items.length) % items.length;
                    self.updateHighlightedSuggestion(items);
                } else if (e.key === 'Enter') {
                    if (self.selectedIndex >= 0 && items[self.selectedIndex]) {
                        e.preventDefault();
                        items[self.selectedIndex].click();
                    } else {
                        self.hideAutocomplete();
                    }
                } else if (e.key === 'Escape') {
                    self.hideAutocomplete();
                }
            });

            // Hide dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!searchInput.contains(e.target) && self.dropdownEl && !self.dropdownEl.contains(e.target)) {
                    self.hideAutocomplete();
                }
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
                self.hideAutocomplete();
                self.fetchData();
            });
        }

        // Filter Selectors Listeners
        Object.keys(this.filterSelectors).forEach(key => {
            const el = document.querySelector(this.filterSelectors[key]);
            if (el) {
                // Read initial value from DOM (e.g., if set by PHP backend)
                this.filters[key] = el.value;

                if (urlParams.has(key)) {
                    el.value = urlParams.get(key);
                    this.filters[key] = el.value;
                } else if (key === 'status' && urlParams.has('filter')) {
                    // Alias for 'filter' parameter used in admin dashboard links
                    el.value = urlParams.get('filter');
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

    injectStyles() {
        if (document.getElementById('bonsai-autocomplete-styles')) return;
        const style = document.createElement('style');
        style.id = 'bonsai-autocomplete-styles';
        style.textContent = `
            .bonsai-autocomplete-dropdown {
                position: absolute;
                top: calc(100% + 4px);
                left: 0;
                right: 0;
                z-index: 999999 !important;
                background: #ffffff;
                border-radius: 10px;
                box-shadow: 0 12px 28px rgba(0, 0, 0, 0.18), 0 4px 10px rgba(0, 0, 0, 0.1);
                border: 1px solid #cbd5e1;
                max-height: 340px;
                overflow-y: auto;
                display: none;
                padding: 6px 0;
            }
            .bonsai-suggest-header {
                padding: 7px 14px;
                font-size: 11px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.6px;
                color: #64748b;
                background: #f8fafc;
                border-bottom: 1px solid #e2e8f0;
            }
            .bonsai-suggest-item {
                padding: 10px 14px;
                display: flex;
                align-items: center;
                gap: 12px;
                cursor: pointer;
                border-bottom: 1px solid #f1f5f9;
                transition: background 0.15s ease;
            }
            .bonsai-suggest-item:last-child {
                border-bottom: none;
            }
            .bonsai-suggest-item:hover, .bonsai-suggest-item.active {
                background-color: #f0f7ff;
            }
            .bonsai-suggest-icon {
                width: 32px;
                height: 32px;
                border-radius: 8px;
                background: #eef2ff;
                color: #4f46e5;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 13px;
                flex-shrink: 0;
            }
            .bonsai-suggest-content {
                flex: 1;
                min-width: 0;
            }
            .bonsai-suggest-title {
                font-weight: 600;
                font-size: 13.5px;
                color: #1e293b;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .bonsai-suggest-subtitle {
                font-size: 12px;
                color: #64748b;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                margin-top: 2px;
            }
            .bonsai-suggest-badge {
                font-size: 10.5px;
                padding: 3px 8px;
                border-radius: 12px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.3px;
                flex-shrink: 0;
            }
            .bonsai-badge-verified, .bonsai-badge-approved { background: #dcfce7; color: #166534; }
            .bonsai-badge-pending { background: #fef9c3; color: #854d0e; }
            .bonsai-badge-rejected { background: #fee2e2; color: #991b1b; }
            .bonsai-suggest-highlight { background: #fef08a; padding: 0 2px; border-radius: 2px; font-weight: 700; color: #000; }
        `;
        document.head.appendChild(style);
    }

    setupAutocompleteDropdown(searchInput) {
        const parent = searchInput.parentElement;
        if (parent) {
            parent.style.position = 'relative';
            parent.style.overflow = 'visible';
            if (parent.parentElement) parent.parentElement.style.overflow = 'visible';
        }

        this.dropdownEl = document.createElement('div');
        this.dropdownEl.className = 'bonsai-autocomplete-dropdown';
        if (parent) {
            parent.appendChild(this.dropdownEl);
        }
    }

    fetchData(fromInput = false) {
        const self = this;
        const tbody = document.querySelector(this.tbodySelector);

        if (tbody && tbody.children.length > 0) {
            tbody.style.opacity = '0.5';
        }

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
                    self.lastItems = data.items || [];
                    self.renderTable(self.lastItems);
                    self.renderPaginationControls();
                    self.renderInfo();

                    if (fromInput && self.query.length > 0) {
                        self.renderAutocompleteSuggestions(self.lastItems);
                    } else {
                        self.hideAutocomplete();
                    }
                } else {
                    console.error('BonsaiPagination API Error:', data.message);
                }
            })
            .catch(err => {
                if (tbody) tbody.style.opacity = '1';
                console.warn('BonsaiPagination Notice:', err.message);
            });
    }

    renderAutocompleteSuggestions(items) {
        if (!this.dropdownEl) return;
        this.dropdownEl.innerHTML = '';
        this.selectedIndex = -1;

        if (!items || items.length === 0 || this.query.length === 0) {
            this.hideAutocomplete();
            return;
        }

        const header = document.createElement('div');
        header.className = 'bonsai-suggest-header';
        header.innerHTML = `<i class="fas fa-magic"></i> Rekomendasi Pencarian (${Math.min(items.length, 6)})`;
        this.dropdownEl.appendChild(header);

        const self = this;
        const suggestions = items.slice(0, 6);

        suggestions.forEach((item, index) => {
            const div = document.createElement('div');
            div.className = 'bonsai-suggest-item';

            let titleText = '';
            let subtitleText = '';
            let status = (item.approval_status || item.verification_status || item.status || 'pending').toLowerCase();
            let iconClass = 'fa-user';

            if (self.target === 'employees') {
                titleText = item.full_name || item.employee_code || '';
                const code = item.employee_code ? `[${item.employee_code}] ` : '';
                const comp = item.competency_name || item.sub_competency || item.position || '';
                const company = item.contractor_company ? ` • ${item.contractor_company}` : '';
                subtitleText = `${code}${comp}${company}`;
                iconClass = 'fa-user-tie';
            } else {
                titleText = item.appointment_number || item.employee_name || '';
                const empName = item.employee_name ? `${item.employee_name}` : '';
                const company = item.contractor_company ? ` • ${item.contractor_company}` : '';
                subtitleText = `${empName}${company}`;
                iconClass = 'fa-file-signature';
            }

            const safeTitleText = escapeHtml(titleText);
            const safeSubtitleText = escapeHtml(subtitleText);
            const highlightedTitle = self.highlightQuery(safeTitleText, self.query);
            const badgeClass = 'bonsai-badge-' + status;

            div.innerHTML = `
                <div class="bonsai-suggest-icon"><i class="fas ${iconClass}"></i></div>
                <div class="bonsai-suggest-content">
                    <div class="bonsai-suggest-title">${highlightedTitle}</div>
                    <div class="bonsai-suggest-subtitle">${safeSubtitleText}</div>
                </div>
                <div class="bonsai-suggest-badge ${badgeClass}">${escapeHtml(status)}</div>
            `;

            div.addEventListener('click', function(e) {
                e.stopPropagation();
                const input = document.querySelector(self.searchInputSelector);
                if (input) {
                    input.value = titleText;
                    self.query = titleText;
                    self.page = 1;
                    const clearBtn = document.querySelector(self.clearBtnSelector);
                    if (clearBtn) clearBtn.style.display = 'block';
                }
                self.hideAutocomplete();
                self.fetchData();
            });

            self.dropdownEl.appendChild(div);
        });

        this.dropdownEl.style.display = 'block';
    }

    highlightQuery(text, query) {
        if (!query) return text;
        const escaped = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const regex = new RegExp(`(${escaped})`, 'gi');
        return text.replace(regex, '<span class="bonsai-suggest-highlight">$1</span>');
    }

    updateHighlightedSuggestion(items) {
        items.forEach((item, idx) => {
            if (idx === this.selectedIndex) {
                item.classList.add('active');
                item.scrollIntoView({ block: 'nearest' });
            } else {
                item.classList.remove('active');
            }
        });
    }

    hideAutocomplete() {
        if (this.dropdownEl) {
            this.dropdownEl.style.display = 'none';
        }
        this.selectedIndex = -1;
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
