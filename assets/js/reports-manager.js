/**
 * StelaReportManager - Frontend State Engine & UI Controller for STELA Reports
 */
class StelaReportManager {
    constructor(config = {}) {
        this.apiEndpoint = config.apiEndpoint || '/resources/views/api/reports_data.php';
        this.userRole = config.userRole || 'user';
        this.currentReport = 'accepted_requests';
        this.searchQuery = '';
        this.page = 1;
        this.perPage = 10;
        this.sortCol = 'id';
        this.sortDir = 'desc';
        this.filters = {
            company: '',
            department: '',
            scope: '',
            supervision_area: ''
        };
        this.debounceTimer = null;
        this.init();
    }

    init() {
        this.loadSummaryCounts();
        this.bindEvents();
        this.loadReportData();
    }

    bindEvents() {
        const self = this;

        // Metric Cards click events
        document.querySelectorAll('.report-metric-card').forEach(card => {
            card.addEventListener('click', function() {
                const reportType = this.getAttribute('data-report');
                if (reportType) {
                    self.switchReport(reportType);
                }
            });
        });

        // Search Input Debounce
        const searchInput = document.getElementById('reportSearchInput');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                clearTimeout(self.debounceTimer);
                self.searchQuery = this.value.trim();
                self.debounceTimer = setTimeout(() => {
                    self.page = 1;
                    self.loadReportData();
                }, 350);
            });
        }

        // Filter Selects
        const scopeFilter = document.getElementById('reportScopeFilter');
        if (scopeFilter) {
            scopeFilter.addEventListener('change', function() {
                self.filters.scope = this.value;
                self.page = 1;
                self.loadReportData();
            });
        }

        const compFilter = document.getElementById('reportCompanyFilter');
        if (compFilter) {
            compFilter.addEventListener('change', function() {
                self.filters.company = this.value;
                self.page = 1;
                self.loadReportData();
            });
        }

        const deptFilter = document.getElementById('reportDepartmentFilter');
        if (deptFilter) {
            deptFilter.addEventListener('change', function() {
                self.filters.department = this.value;
                self.page = 1;
                self.loadReportData();
            });
        }

        // Items per page select
        const perPageSelect = document.getElementById('reportPerPageSelect');
        if (perPageSelect) {
            perPageSelect.addEventListener('change', function() {
                self.perPage = parseInt(this.value, 10);
                self.page = 1;
                self.loadReportData();
            });
        }

        // Refresh Button
        const refreshBtn = document.getElementById('btnRefreshReport');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', function() {
                self.resetFilters();
                self.loadReportData();
                self.loadSummaryCounts();
            });
        }

        // Export Excel Button
        const exportExcelBtn = document.getElementById('btnExportExcel');
        if (exportExcelBtn) {
            exportExcelBtn.addEventListener('click', function() {
                self.exportData('export_excel');
            });
        }

        // Export PDF Button
        const exportPdfBtn = document.getElementById('btnExportPdf');
        if (exportPdfBtn) {
            exportPdfBtn.addEventListener('click', function() {
                self.exportData('export_pdf');
            });
        }
    }

    switchReport(reportType) {
        this.currentReport = reportType;
        this.page = 1;

        // Active state in metric cards
        document.querySelectorAll('.report-metric-card').forEach(card => {
            if (card.getAttribute('data-report') === reportType) {
                card.classList.add('active');
            } else {
                card.classList.remove('active');
            }
        });

        // Update Section Header Title
        const titles = {
            'accepted_requests': 'Detail Accepted Request (Verified by Admin)',
            'rejected_requests': 'Detail Rejected Request (Rejected by Admin)',
            'waiting_requests': 'Detail Waiting Request (Pending Admin Verification)',
            'accepted_assign_letters': 'Detail Accepted Assign Letter (Approved by KTT)',
            'rejected_assign_letters': 'Detail Rejected Assign Letter (Rejected by KTT)',
            'expired_certificates': 'Detail Certificate Expired (<= 60 Days / Expired)'
        };
        const titleEl = document.getElementById('activeReportTitle');
        if (titleEl) {
            titleEl.textContent = titles[reportType] || 'Report Detail';
        }

        this.loadReportData();
    }

    resetFilters() {
        this.searchQuery = '';
        this.page = 1;
        this.filters = { company: '', department: '', scope: '', supervision_area: '' };
        
        const searchInput = document.getElementById('reportSearchInput');
        if (searchInput) searchInput.value = '';
        const scopeFilter = document.getElementById('reportScopeFilter');
        if (scopeFilter) scopeFilter.value = '';
        const compFilter = document.getElementById('reportCompanyFilter');
        if (compFilter) compFilter.value = '';
        const deptFilter = document.getElementById('reportDepartmentFilter');
        if (deptFilter) deptFilter.value = '';
    }

    loadSummaryCounts() {
        fetch(`${this.apiEndpoint}?action=get_counts`)
            .then(res => res.json())
            .then(res => {
                if (res.success && res.counts) {
                    for (const [key, count] of Object.entries(res.counts)) {
                        const el = document.getElementById(`count_${key}`);
                        if (el) {
                            el.textContent = count;
                        }
                    }
                }
            })
            .catch(err => console.error('Error fetching summary counts:', err));
    }

    loadReportData() {
        const tableContainer = document.getElementById('reportTableContainer');
        const loadingIndicator = document.getElementById('reportLoadingSpinner');

        if (loadingIndicator) loadingIndicator.style.display = 'block';
        if (tableContainer) tableContainer.style.opacity = '0.5';

        const params = new URLSearchParams({
            action: 'get_report_data',
            report_type: this.currentReport,
            search: this.searchQuery,
            page: this.page,
            per_page: this.perPage,
            sort_col: this.sortCol,
            sort_dir: this.sortDir,
            company: this.filters.company,
            department: this.filters.department,
            scope: this.filters.scope,
            supervision_area: this.filters.supervision_area
        });

        fetch(`${this.apiEndpoint}?${params.toString()}`)
            .then(res => res.json())
            .then(res => {
                if (loadingIndicator) loadingIndicator.style.display = 'none';
                if (tableContainer) tableContainer.style.opacity = '1';

                if (res.success && res.data) {
                    this.renderTable(res.data);
                    this.renderPagination(res.data);
                    this.updateTotalBadge(res.data.total, res.data.source);
                } else {
                    this.renderEmptyState('Failed to load report data');
                }
            })
            .catch(err => {
                if (loadingIndicator) loadingIndicator.style.display = 'none';
                if (tableContainer) tableContainer.style.opacity = '1';
                console.error('Error loading report data:', err);
                this.renderEmptyState('Network or server error while fetching data.');
            });
    }

    renderTable(data) {
        const container = document.getElementById('reportTableContainer');
        if (!container) return;

        const items = data.items || [];
        if (items.length === 0) {
            this.renderEmptyState();
            return;
        }

        let html = `<div class="table-responsive">
            <table class="table table-hover table-striped align-middle stela-custom-table" style="min-width: 950px;">
                <thead class="table-dark">
                    <tr>${this.getTableHeaders()}</tr>
                </thead>
                <tbody>`;

        items.forEach(item => {
            html += `<tr>${this.getTableRow(item)}</tr>`;
        });

        html += `</tbody></table></div>`;
        container.innerHTML = html;

        // Bind sorting headers
        const self = this;
        container.querySelectorAll('th[data-sort]').forEach(th => {
            th.style.cursor = 'pointer';
            th.addEventListener('click', function() {
                const col = this.getAttribute('data-sort');
                if (self.sortCol === col) {
                    self.sortDir = self.sortDir === 'asc' ? 'desc' : 'asc';
                } else {
                    self.sortCol = col;
                    self.sortDir = 'asc';
                }
                self.loadReportData();
            });
        });
    }

    getTableHeaders() {
        const arrow = (col) => {
            if (this.sortCol !== col) return ' <i class="fas fa-sort text-muted opacity-50"></i>';
            return this.sortDir === 'asc' ? ' <i class="fas fa-sort-up text-warning"></i>' : ' <i class="fas fa-sort-down text-warning"></i>';
        };

        switch (this.currentReport) {
            case 'accepted_requests':
            case 'rejected_requests':
            case 'waiting_requests':
                return `
                    <th data-sort="full_name">Employee Name${arrow('full_name')}</th>
                    <th data-sort="employee_code">ID Badge${arrow('employee_code')}</th>
                    <th data-sort="company">Company${arrow('company')}</th>
                    <th>Department</th>
                    <th>Position</th>
                    <th>Competency Type</th>
                    <th data-sort="date">Request Date${arrow('date')}</th>
                    <th>Status</th>
                    <th>Actions / Notes</th>
                `;
            case 'accepted_assign_letters':
            case 'rejected_assign_letters':
                return `
                    <th data-sort="appointment_number">Assign Letter No.${arrow('appointment_number')}</th>
                    <th data-sort="employee_name">Employee Name${arrow('employee_name')}</th>
                    <th data-sort="company">Company${arrow('company')}</th>
                    <th>Department</th>
                    <th>Position</th>
                    <th>Scope</th>
                    <th data-sort="approved_date">Effective/Decision Date${arrow('approved_date')}</th>
                    <th>Status</th>
                    <th>Approval Info</th>
                `;
            case 'expired_certificates':
                return `
                    <th data-sort="employee_name">Employee Name${arrow('employee_name')}</th>
                    <th>ID Badge</th>
                    <th>Company</th>
                    <th data-sort="cert_name">Certificate Name${arrow('cert_name')}</th>
                    <th>Certificate No.</th>
                    <th data-sort="expiry_date">Expiry Date${arrow('expiry_date')}</th>
                    <th data-sort="days_left">Remaining Days${arrow('days_left')}</th>
                    <th>Status</th>
                `;
            default:
                return '';
        }
    }

    getTableRow(item) {
        switch (this.currentReport) {
            case 'accepted_requests':
            case 'rejected_requests':
            case 'waiting_requests':
                const isVerified = item.verification_status === 'verified';
                const isRejected = item.verification_status === 'rejected';
                const badgeClass = isVerified ? 'bg-success' : (isRejected ? 'bg-danger' : 'bg-warning text-dark');
                const statusLabel = isVerified ? 'Accepted' : (isRejected ? 'Rejected' : 'Waiting Admin');
                
                return `
                    <td><strong>${this.escapeHtml(item.full_name || '')}</strong></td>
                    <td><span class="badge bg-light text-dark font-monospace">${this.escapeHtml(item.employee_code || '')}</span></td>
                    <td>${this.escapeHtml(item.contractor_company || '-')}</td>
                    <td>${this.escapeHtml(item.department || '-')}</td>
                    <td>${this.escapeHtml(item.position || '-')}</td>
                    <td><span class="badge bg-info text-dark">${this.escapeHtml(item.competency_type || 'N/A')}</span></td>
                    <td>${item.request_date ? new Date(item.request_date).toLocaleDateString('id-ID') : 'N/A'}</td>
                    <td><span class="badge ${badgeClass}"><i class="fas fa-circle me-1"></i>${statusLabel}</span></td>
                    <td>${item.rejection_notes ? `<span class="badge bg-secondary" title="${this.escapeHtml(item.rejection_notes)}"><i class="fas fa-comment"></i> Notes</span>` : '-'}</td>
                `;

            case 'accepted_assign_letters':
            case 'rejected_assign_letters':
                const isApproved = item.status === 'approved';
                const apptBadge = isApproved ? 'bg-success' : 'bg-danger';
                return `
                    <td><strong class="text-primary">${this.escapeHtml(item.appointment_number || '')}</strong></td>
                    <td><strong>${this.escapeHtml(item.employee_name || '')}</strong><br><small class="text-muted">${this.escapeHtml(item.employee_code || '')}</small></td>
                    <td>${this.escapeHtml(item.contractor_company || '-')}</td>
                    <td>${this.escapeHtml(item.department || '-')}</td>
                    <td>${this.escapeHtml(item.position_name || '-')}</td>
                    <td><span class="badge bg-secondary">${this.escapeHtml(item.ruang_lingkup || 'N/A')}</span></td>
                    <td>${item.effective_date || item.approved_date || 'N/A'}</td>
                    <td><span class="badge ${apptBadge}">${isApproved ? 'Approved (KTT)' : 'Rejected (KTT)'}</span></td>
                    <td><small>${this.escapeHtml(item.approved_by_name || item.rejected_by_name || 'KTT')}</small></td>
                `;

            case 'expired_certificates':
                const days = parseInt(item.days_left || 0, 10);
                let daysBadge = 'bg-warning text-dark';
                let statusText = 'Expiring Soon';
                if (days <= 0) {
                    daysBadge = 'bg-danger';
                    statusText = 'Expired';
                } else if (days <= 30) {
                    daysBadge = 'bg-danger';
                    statusText = 'Critical';
                }

                return `
                    <td><strong>${this.escapeHtml(item.employee_name || '')}</strong></td>
                    <td><span class="badge bg-light text-dark">${this.escapeHtml(item.employee_code || '')}</span></td>
                    <td>${this.escapeHtml(item.contractor_company || '-')}</td>
                    <td><strong>${this.escapeHtml(item.cert_name || '-')}</strong></td>
                    <td><code>${this.escapeHtml(item.cert_number || '-')}</code></td>
                    <td>${item.expiry_date || 'N/A'}</td>
                    <td><span class="badge ${daysBadge}">${days} days</span></td>
                    <td><span class="badge ${daysBadge}">${statusText}</span></td>
                `;
            default:
                return '';
        }
    }

    renderPagination(data) {
        const container = document.getElementById('reportPaginationContainer');
        if (!container) return;

        const totalPages = data.total_pages || 1;
        if (totalPages <= 1) {
            container.innerHTML = '';
            return;
        }

        let html = `<ul class="pagination pagination-sm m-0 justify-content-end">`;
        
        // Prev button
        html += `<li class="page-item ${this.page === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" data-page="${this.page - 1}">&laquo; Prev</a>
        </li>`;

        const startPage = Math.max(1, this.page - 2);
        const endPage = Math.min(totalPages, this.page + 2);

        for (let i = startPage; i <= endPage; i++) {
            html += `<li class="page-item ${i === this.page ? 'active' : ''}">
                <a class="page-link" href="#" data-page="${i}">${i}</a>
            </li>`;
        }

        // Next button
        html += `<li class="page-item ${this.page === totalPages ? 'disabled' : ''}">
            <a class="page-link" href="#" data-page="${this.page + 1}">Next &raquo;</a>
        </li>`;

        html += `</ul>`;
        container.innerHTML = html;

        const self = this;
        container.querySelectorAll('a[data-page]').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const p = parseInt(this.getAttribute('data-page'), 10);
                if (p >= 1 && p <= totalPages && p !== self.page) {
                    self.page = p;
                    self.loadReportData();
                }
            });
        });
    }

    updateTotalBadge(total, source = 'mysql') {
        const totalEl = document.getElementById('reportTotalBadge');
        if (totalEl) {
            totalEl.textContent = `${total} Records (${source === 'elasticsearch' ? 'Search via Bonsai.io' : 'MySQL Database'})`;
        }
    }

    renderEmptyState(message = 'No records found for this report and filter criteria.') {
        const container = document.getElementById('reportTableContainer');
        const paginationContainer = document.getElementById('reportPaginationContainer');
        if (paginationContainer) paginationContainer.innerHTML = '';

        if (container) {
            container.innerHTML = `
                <div class="text-center py-5 my-3">
                    <i class="fas fa-folder-open fa-3x text-muted mb-3 opacity-50"></i>
                    <h5 class="text-muted fw-normal">${message}</h5>
                    <p class="small text-muted">Try adjusting your search keywords or clearing filters.</p>
                </div>
            `;
        }
    }

    exportData(action) {
        const params = new URLSearchParams({
            action: action,
            report_type: this.currentReport,
            search: this.searchQuery,
            sort_col: this.sortCol,
            sort_dir: this.sortDir,
            company: this.filters.company,
            department: this.filters.department,
            scope: this.filters.scope,
            supervision_area: this.filters.supervision_area
        });

        window.open(`${this.apiEndpoint}?${params.toString()}`, '_blank');
    }

    escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
}
