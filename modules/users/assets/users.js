/**
 * WorkSafety.io - Users Module JavaScript
 * פונקציונליות מתקדמת למודול ניהול משתמשים
 */

class UsersManager {
    constructor() {
        this.currentView = 'table';
        this.selectedUsers = new Set();
        this.filteredUsers = [];
        this.init();
    }
    
    init() {
        this.setupEventListeners();
        this.setupFilters();
        this.setupViewSwitcher();
        this.setupBulkActions();
        this.setupDropdowns();
        this.initializeData();
        
        console.log('✅ Users Manager initialized');
    }
    
    setupEventListeners() {
        // Search functionality
        const searchInput = document.getElementById('searchUsers');
        if (searchInput) {
            searchInput.addEventListener('input', debounce((e) => {
                this.filterUsers();
            }, 300));
        }
        
        // Filter dropdowns
        ['filterStatus', 'filterRole', 'filterCompany'].forEach(filterId => {
            const filter = document.getElementById(filterId);
            if (filter) {
                filter.addEventListener('change', () => this.filterUsers());
            }
        });
        
        // Select all checkbox
        const selectAll = document.getElementById('selectAll');
        if (selectAll) {
            selectAll.addEventListener('change', (e) => {
                this.toggleSelectAll(e.target.checked);
            });
        }
        
        // Individual checkboxes
        document.addEventListener('change', (e) => {
            if (e.target.classList.contains('user-checkbox')) {
                this.toggleUserSelection(e.target.value, e.target.checked);
            }
        });
    }
    
    setupFilters() {
        this.filters = {
            search: '',
            status: '',
            role: '',
            company: ''
        };
    }
    
    setupViewSwitcher() {
        const viewButtons = document.querySelectorAll('[data-view]');
        viewButtons.forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.switchView(e.target.getAttribute('data-view'));
            });
        });
    }
    
    setupBulkActions() {
        // Bulk action buttons
        document.addEventListener('click', (e) => {
            if (e.target.closest('[onclick*="bulkAction"]')) {
                e.preventDefault();
                const action = e.target.closest('button').getAttribute('onclick').match(/bulkAction\('(.+?)'\)/)[1];
                this.executeBulkAction(action);
            }
        });
    }
    
    setupDropdowns() {
        document.addEventListener('click', (e) => {
            // Close all dropdowns when clicking outside
            if (!e.target.closest('.dropdown')) {
                document.querySelectorAll('.dropdown').forEach(dropdown => {
                    dropdown.classList.remove('active');
                });
            }
            
            // Handle dropdown toggle
            if (e.target.closest('.dropdown-toggle')) {
                e.preventDefault();
                const dropdown = e.target.closest('.dropdown');
                const isActive = dropdown.classList.contains('active');
                
                // Close all other dropdowns
                document.querySelectorAll('.dropdown').forEach(d => d.classList.remove('active'));
                
                // Toggle current dropdown
                if (!isActive) {
                    dropdown.classList.add('active');
                }
            }
        });
    }
    
    initializeData() {
        // Get all user rows
        const userRows = document.querySelectorAll('#usersTable tbody tr');
        this.filteredUsers = Array.from(userRows).map(row => {
            return {
                id: row.getAttribute('data-user-id'),
                element: row,
                data: this.extractUserData(row)
            };
        });
        
        this.updateResultsCount();
    }
    
    extractUserData(row) {
        const cells = row.querySelectorAll('td');
        return {
            name: cells[1]?.querySelector('.user-name')?.textContent.trim() || '',
            email: cells[1]?.querySelector('.user-email')?.textContent.trim() || '',
            company: cells[2]?.textContent.trim() || '',
            role: cells[3]?.querySelector('.role-badge')?.className.match(/role-(.+?)(?:\s|$)/)?.[1] || '',
            status: cells[4]?.querySelector('.status-badge')?.className.match(/status-(.+?)(?:\s|$)/)?.[1] || ''
        };
    }
    
    filterUsers() {
        const searchTerm = document.getElementById('searchUsers')?.value.toLowerCase() || '';
        const statusFilter = document.getElementById('filterStatus')?.value || '';
        const roleFilter = document.getElementById('filterRole')?.value || '';
        const companyFilter = document.getElementById('filterCompany')?.value || '';
        
        this.filteredUsers.forEach(user => {
            let visible = true;
            
            // Search filter
            if (searchTerm) {
                const searchableText = `${user.data.name} ${user.data.email} ${user.data.company}`.toLowerCase();
                visible = visible && searchableText.includes(searchTerm);
            }
            
            // Status filter
            if (statusFilter) {
                visible = visible && user.data.status === statusFilter;
            }
            
            // Role filter
            if (roleFilter) {
                visible = visible && user.data.role === roleFilter;
            }
            
            // Company filter (for super admin)
            if (companyFilter) {
                const companyId = user.element.querySelector('.user-checkbox')?.getAttribute('data-company-id');
                visible = visible && companyId === companyFilter;
            }
            
            // Show/hide row
            user.element.style.display = visible ? '' : 'none';
        });
        
        this.updateResultsCount();
        this.updateSelectAllState();
    }
    
    switchView(view) {
        const tableView = document.getElementById('tableView');
        const gridView = document.getElementById('gridView');
        const viewButtons = document.querySelectorAll('[data-view]');
        
        // Update buttons
        viewButtons.forEach(btn => {
            btn.classList.toggle('active', btn.getAttribute('data-view') === view);
        });
        
        // Switch views
        if (view === 'table') {
            tableView.style.display = 'block';
            gridView.style.display = 'none';
        } else {
            tableView.style.display = 'none';
            gridView.style.display = 'grid';
        }
        
        this.currentView = view;
    }
    
    toggleSelectAll(checked) {
        const visibleCheckboxes = document.querySelectorAll('#usersTable tbody tr:not([style*="display: none"]) .user-checkbox');
        
        visibleCheckboxes.forEach(checkbox => {
            checkbox.checked = checked;
            this.toggleUserSelection(checkbox.value, checked);
        });
    }
    
    toggleUserSelection(userId, selected) {
        if (selected) {
            this.selectedUsers.add(userId);
        } else {
            this.selectedUsers.delete(userId);
        }
        
        this.updateBulkActionsVisibility();
        this.updateSelectAllState();
    }
    
    updateSelectAllState() {
        const selectAllCheckbox = document.getElementById('selectAll');
        const visibleCheckboxes = document.querySelectorAll('#usersTable tbody tr:not([style*="display: none"]) .user-checkbox');
        const checkedVisible = Array.from(visibleCheckboxes).filter(cb => cb.checked);
        
        if (selectAllCheckbox) {
            selectAllCheckbox.indeterminate = checkedVisible.length > 0 && checkedVisible.length < visibleCheckboxes.length;
            selectAllCheckbox.checked = visibleCheckboxes.length > 0 && checkedVisible.length === visibleCheckboxes.length;
        }
    }
    
    updateBulkActionsVisibility() {
        const bulkActions = document.getElementById('bulkActions');
        const selectedCount = document.getElementById('selectedCount');
        
        if (this.selectedUsers.size > 0) {
            bulkActions.style.display = 'block';
            selectedCount.textContent = this.selectedUsers.size;
        } else {
            bulkActions.style.display = 'none';
        }
    }
    
    updateResultsCount() {
        const resultsCount = document.querySelector('.results-count');
        const visibleRows = document.querySelectorAll('#usersTable tbody tr:not([style*="display: none"])');
        
        if (resultsCount) {
            resultsCount.textContent = `מציג ${visibleRows.length} משתמשים`;
        }
    }
    
    clearSelection() {
        this.selectedUsers.clear();
        document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = false);
        document.getElementById('selectAll').checked = false;
        this.updateBulkActionsVisibility();
    }
    
    async executeBulkAction(action) {
        if (this.selectedUsers.size === 0) {
            showNotification('לא נבחרו משתמשים', 'warning');
            return;
        }
        
        const userIds = Array.from(this.selectedUsers);
        let confirmMessage = '';
        
        switch (action) {
            case 'activate':
                confirmMessage = `האם אתה בטוח שברצונך להפעיל ${userIds.length} משתמשים?`;
                break;
            case 'deactivate':
                confirmMessage = `האם אתה בטוח שברצונך להשהות ${userIds.length} משתמשים?`;
                break;
            case 'delete':
                confirmMessage = `האם אתה בטוח שברצונך למחוק ${userIds.length} משתמשים? פעולה זו אינה ניתנת לביטול!`;
                break;
            default:
                return;
        }
        
        if (!confirm(confirmMessage)) {
            return;
        }
        
        try {
            const response = await fetch('/modules/users/bulk_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: action,
                    user_ids: userIds,
                    csrf_token: window.WorkSafetyConfig?.csrfToken || ''
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                showNotification(result.message, 'success');
                this.clearSelection();
                // Reload page to see changes
                setTimeout(() => location.reload(), 1500);
            } else {
                showNotification(result.message || 'שגיאה בביצוע הפעולה', 'error');
            }
        } catch (error) {
            console.error('Bulk action error:', error);
            showNotification('שגיאה בביצוע הפעולה', 'error');
        }
    }
    
    async deleteUser(userId) {
        if (!confirm('האם אתה בטוח שברצונך למחוק משתמש זה? פעולה זו אינה ניתנת לביטול!')) {
            return;
        }
        
        try {
            const response = await fetch('/modules/users/delete.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    user_id: userId,
                    csrf_token: window.WorkSafetyConfig?.csrfToken || ''
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                showNotification('המשתמש נמחק בהצלחה', 'success');
                // Remove row from table
                const row = document.querySelector(`tr[data-user-id="${userId}"]`);
                if (row) {
                    row.style.animation = 'fadeOut 0.3s ease';
                    setTimeout(() => {
                        row.remove();
                        this.updateResultsCount();
                    }, 300);
                }
            } else {
                showNotification(result.message || 'שגיאה במחיקת המשתמש', 'error');
            }
        } catch (error) {
            console.error('Delete user error:', error);
            showNotification('שגיאה במחיקת המשתמש', 'error');
        }
    }
    
    exportUsers() {
        const visibleRows = document.querySelectorAll('#usersTable tbody tr:not([style*="display: none"])');
        const data = [];
        
        // Headers
        data.push(['שם', 'אימייל', 'חברה', 'תפקיד', 'סטטוס', 'התחברות אחרונה', 'תאריך הצטרפות']);
        
        // Data rows
        visibleRows.forEach(row => {
            const cells = row.querySelectorAll('td');
            const rowData = [
                cells[1]?.querySelector('.user-name')?.textContent.trim() || '',
                cells[1]?.querySelector('.user-email')?.textContent.trim() || '',
                cells[2]?.textContent.trim() || '',
                cells[3]?.textContent.trim() || '',
                cells[4]?.textContent.trim() || '',
                cells[5]?.querySelector('.login-date')?.textContent.trim() || cells[5]?.textContent.trim() || '',
                cells[6]?.textContent.trim() || ''
            ];
            data.push(rowData);
        });
        
        // Create CSV
        const csvContent = data.map(row => 
            row.map(field => `"${field.replace(/"/g, '""')}"`).join(',')
        ).join('\n');
        
        // Download
        const blob = new Blob(['\ufeff' + csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', `users_export_${new Date().toISOString().split('T')[0]}.csv`);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        showNotification('הקובץ יוצא בהצלחה', 'success');
    }
    
    resetFilters() {
        // Clear all filter inputs
        document.getElementById('searchUsers').value = '';
        document.getElementById('filterStatus').value = '';
        document.getElementById('filterRole').value = '';
        const companyFilter = document.getElementById('filterCompany');
        if (companyFilter) companyFilter.value = '';
        
        // Clear selection
        this.clearSelection();
        
        // Re-filter (show all)
        this.filterUsers();
        
        showNotification('הפילטרים נוקו', 'info');
    }
}

// Global functions for onclick handlers
function deleteUser(userId) {
    if (window.usersManager) {
        window.usersManager.deleteUser(userId);
    }
}

function bulkAction(action) {
    if (window.usersManager) {
        window.usersManager.executeBulkAction(action);
    }
}

function clearSelection() {
    if (window.usersManager) {
        window.usersManager.clearSelection();
    }
}

function exportUsers() {
    if (window.usersManager) {
        window.usersManager.exportUsers();
    }
}

function resetFilters() {
    if (window.usersManager) {
        window.usersManager.resetFilters();
    }
}

// Utility functions
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Fade out animation for deleted rows
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeOut {
        from { opacity: 1; transform: scale(1); }
        to { opacity: 0; transform: scale(0.95); }
    }
`;
document.head.appendChild(style);

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    window.usersManager = new UsersManager();
});

console.log('✅ Users Module JavaScript loaded');
