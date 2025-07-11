// Admin Analytics Module for Pet Care Management System
// This module provides comprehensive analytics and reporting for administrators

class AdminAnalytics {
    constructor() {
        this.charts = {};
        this.refreshInterval = null;
        this.currentPeriod = 'month'; // month, week, year
    }

    // Initialize admin analytics
    async init() {
        console.log('Initializing Admin Analytics...');
        
        // Load initial data
        await this.loadDashboardStats();
        await this.loadCharts();
        await this.loadSystemAlerts();
        await this.loadRecentActivity();
        
        // Set up auto-refresh
        this.startAutoRefresh();
        
        // Set up event listeners
        this.setupEventListeners();
    }

    // Load dashboard statistics
    async loadDashboardStats() {
        try {
            const adminId = localStorage.getItem('petCareUserId');
            const formData = new FormData();
            formData.append('action', 'get_admin_dashboard_stats');
            formData.append('admin_id', adminId);
            formData.append('period', this.currentPeriod);

            const response = await fetch('backend/api.php', { method: 'POST', body: formData });
            const data = await response.json();

            if (data.success) {
                this.updateStatsDisplay(data.data);
            } else {
                console.error('Failed to load dashboard stats:', data.error);
            }
        } catch (error) {
            console.error('Error loading dashboard stats:', error);
        }
    }

    // Update stats display
    updateStatsDisplay(stats) {
        const statsContainer = document.getElementById('admin-stats');
        if (!statsContainer) return;

        statsContainer.innerHTML = `
            <div class="stat-card">
                <i class="fas fa-users"></i>
                <h3>${stats.totalUsers}</h3>
                <p>Total Users</p>
                <small class="text-muted">+${stats.newUsersThisPeriod} this ${this.currentPeriod}</small>
            </div>
            <div class="stat-card">
                <i class="fas fa-paw"></i>
                <h3>${stats.totalPets}</h3>
                <p>Registered Pets</p>
                <small class="text-muted">+${stats.newPetsThisPeriod} this ${this.currentPeriod}</small>
            </div>
            <div class="stat-card">
                <i class="fas fa-calendar"></i>
                <h3>${stats.todayAppointments}</h3>
                <p>Today's Appointments</p>
                <small class="text-muted">${stats.completedToday} completed</small>
            </div>
            <div class="stat-card">
                <i class="fas fa-money-bill"></i>
                <h3>${stats.monthlyRevenue}</h3>
                <p>Monthly Revenue</p>
                <small class="text-muted">${stats.revenueGrowth > 0 ? '+' : ''}${stats.revenueGrowth}% vs last month</small>
            </div>
            <div class="stat-card">
                <i class="fas fa-stethoscope"></i>
                <h3>${stats.activeVets}</h3>
                <p>Active Veterinarians</p>
                <small class="text-muted">${stats.vetUtilization}% utilization</small>
            </div>
            <div class="stat-card">
                <i class="fas fa-chart-line"></i>
                <h3>${stats.systemHealth}</h3>
                <p>System Health</p>
                <small class="text-muted">${stats.uptime}% uptime</small>
            </div>
        `;
    }

    // Load and render charts
    async loadCharts() {
        await this.loadAppointmentsChart();
        await this.loadUserDistributionChart();
        await this.loadRevenueChart();
        await this.loadPetTypesChart();
        await this.loadSystemPerformanceChart();
    }

    // Appointments chart
    async loadAppointmentsChart() {
        try {
            const adminId = localStorage.getItem('petCareUserId');
            const formData = new FormData();
            formData.append('action', 'get_appointments_analytics');
            formData.append('admin_id', adminId);
            formData.append('period', this.currentPeriod);

            const response = await fetch('backend/api.php', { method: 'POST', body: formData });
            const data = await response.json();

            if (data.success) {
                this.renderAppointmentsChart(data.data);
            }
        } catch (error) {
            console.error('Error loading appointments chart:', error);
        }
    }

    // Render appointments chart
    renderAppointmentsChart(data) {
        const ctx = document.getElementById('appointmentsChart');
        if (!ctx) return;

        // Destroy existing chart if it exists
        if (this.charts.appointments) {
            this.charts.appointments.destroy();
        }

        this.charts.appointments = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.map(item => item.period),
                datasets: [{
                    label: 'Appointments',
                    data: data.map(item => item.count),
                    borderColor: '#1976d2',
                    backgroundColor: 'rgba(25, 118, 210, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    title: {
                        display: true,
                        text: 'Appointments Trend'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }

    // User distribution chart
    async loadUserDistributionChart() {
        try {
            const adminId = localStorage.getItem('petCareUserId');
            const formData = new FormData();
            formData.append('action', 'get_user_distribution');
            formData.append('admin_id', adminId);

            const response = await fetch('backend/api.php', { method: 'POST', body: formData });
            const data = await response.json();

            if (data.success) {
                this.renderUserDistributionChart(data.data);
            }
        } catch (error) {
            console.error('Error loading user distribution chart:', error);
        }
    }

    // Render user distribution chart
    renderUserDistributionChart(data) {
        const ctx = document.getElementById('usersChart');
        if (!ctx) return;

        if (this.charts.userDistribution) {
            this.charts.userDistribution.destroy();
        }

        const colors = ['#1976d2', '#43a047', '#ffa000', '#e53935', '#9c27b0'];

        this.charts.userDistribution = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: data.map(item => item.role),
                datasets: [{
                    data: data.map(item => item.count),
                    backgroundColor: colors.slice(0, data.length),
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    title: {
                        display: true,
                        text: 'User Distribution'
                    }
                }
            }
        });
    }

    // Revenue chart
    async loadRevenueChart() {
        try {
            const adminId = localStorage.getItem('petCareUserId');
            const formData = new FormData();
            formData.append('action', 'get_revenue_analytics');
            formData.append('admin_id', adminId);
            formData.append('period', this.currentPeriod);

            const response = await fetch('backend/api.php', { method: 'POST', body: formData });
            const data = await response.json();

            if (data.success) {
                this.renderRevenueChart(data.data);
            }
        } catch (error) {
            console.error('Error loading revenue chart:', error);
        }
    }

    // Render revenue chart
    renderRevenueChart(data) {
        const ctx = document.getElementById('revenueChart');
        if (!ctx) return;

        if (this.charts.revenue) {
            this.charts.revenue.destroy();
        }

        this.charts.revenue = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.map(item => item.period),
                datasets: [{
                    label: 'Revenue',
                    data: data.map(item => item.amount),
                    backgroundColor: '#43a047',
                    borderColor: '#2e7d32',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    title: {
                        display: true,
                        text: 'Revenue Trend'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'KSh ' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }

    // Pet types chart
    async loadPetTypesChart() {
        try {
            const adminId = localStorage.getItem('petCareUserId');
            const formData = new FormData();
            formData.append('action', 'get_pet_types_analytics');
            formData.append('admin_id', adminId);

            const response = await fetch('backend/api.php', { method: 'POST', body: formData });
            const data = await response.json();

            if (data.success) {
                this.renderPetTypesChart(data.data);
            }
        } catch (error) {
            console.error('Error loading pet types chart:', error);
        }
    }

    // Render pet types chart
    renderPetTypesChart(data) {
        const ctx = document.getElementById('petTypesChart');
        if (!ctx) return;

        if (this.charts.petTypes) {
            this.charts.petTypes.destroy();
        }

        const colors = ['#ff9800', '#2196f3', '#4caf50', '#f44336', '#9c27b0'];

        this.charts.petTypes = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: data.map(item => item.type),
                datasets: [{
                    data: data.map(item => item.count),
                    backgroundColor: colors.slice(0, data.length),
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    title: {
                        display: true,
                        text: 'Pet Types Distribution'
                    }
                }
            }
        });
    }

    // System performance chart
    async loadSystemPerformanceChart() {
        try {
            const adminId = localStorage.getItem('petCareUserId');
            const formData = new FormData();
            formData.append('action', 'get_system_performance');
            formData.append('admin_id', adminId);

            const response = await fetch('backend/api.php', { method: 'POST', body: formData });
            const data = await response.json();

            if (data.success) {
                this.renderSystemPerformanceChart(data.data);
            }
        } catch (error) {
            console.error('Error loading system performance chart:', error);
        }
    }

    // Render system performance chart
    renderSystemPerformanceChart(data) {
        const ctx = document.getElementById('systemPerformanceChart');
        if (!ctx) return;

        if (this.charts.systemPerformance) {
            this.charts.systemPerformance.destroy();
        }

        this.charts.systemPerformance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.map(item => item.time),
                datasets: [{
                    label: 'CPU Usage',
                    data: data.map(item => item.cpu),
                    borderColor: '#e53935',
                    backgroundColor: 'rgba(229, 57, 53, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Memory Usage',
                    data: data.map(item => item.memory),
                    borderColor: '#1976d2',
                    backgroundColor: 'rgba(25, 118, 210, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'System Performance'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    }
                }
            }
        });
    }

    // Load system alerts
    async loadSystemAlerts() {
        try {
            const adminId = localStorage.getItem('petCareUserId');
            const formData = new FormData();
            formData.append('action', 'get_system_alerts');
            formData.append('admin_id', adminId);

            const response = await fetch('backend/api.php', { method: 'POST', body: formData });
            const data = await response.json();

            if (data.success) {
                this.updateSystemAlerts(data.data);
            }
        } catch (error) {
            console.error('Error loading system alerts:', error);
        }
    }

    // Update system alerts display
    updateSystemAlerts(alerts) {
        const container = document.getElementById('admin-system-alerts');
        if (!container) return;

        if (alerts.length === 0) {
            container.innerHTML = '<p class="text-muted">No active alerts</p>';
            return;
        }

        container.innerHTML = alerts.map(alert => `
            <div class="alert alert-${alert.severity} alert-dismissible fade show" role="alert">
                <i class="fas fa-${this.getAlertIcon(alert.severity)}"></i>
                <strong>${alert.title}</strong> - ${alert.message}
                <small class="d-block text-muted">${alert.timestamp}</small>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `).join('');
    }

    // Get alert icon based on severity
    getAlertIcon(severity) {
        const icons = {
            'danger': 'exclamation-triangle',
            'warning': 'exclamation-circle',
            'info': 'info-circle',
            'success': 'check-circle'
        };
        return icons[severity] || 'info-circle';
    }

    // Load recent activity
    async loadRecentActivity() {
        try {
            const adminId = localStorage.getItem('petCareUserId');
            const formData = new FormData();
            formData.append('action', 'get_recent_activity');
            formData.append('admin_id', adminId);

            const response = await fetch('backend/api.php', { method: 'POST', body: formData });
            const data = await response.json();

            if (data.success) {
                this.updateRecentActivity(data.data);
            }
        } catch (error) {
            console.error('Error loading recent activity:', error);
        }
    }

    // Update recent activity display
    updateRecentActivity(activities) {
        const container = document.getElementById('admin-recent-activity');
        if (!container) return;

        container.innerHTML = activities.map(activity => `
            <div class="list-group-item">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <i class="fas fa-${this.getActivityIcon(activity.type)} text-${this.getActivityColor(activity.type)}"></i>
                        <strong>${activity.title}</strong>
                        <p class="mb-1">${activity.description}</p>
                    </div>
                    <small class="text-muted">${activity.timestamp}</small>
                </div>
            </div>
        `).join('');
    }

    // Get activity icon
    getActivityIcon(type) {
        const icons = {
            'user': 'user',
            'appointment': 'calendar',
            'payment': 'credit-card',
            'system': 'cog',
            'alert': 'exclamation-triangle'
        };
        return icons[type] || 'info-circle';
    }

    // Get activity color
    getActivityColor(type) {
        const colors = {
            'user': 'primary',
            'appointment': 'success',
            'payment': 'info',
            'system': 'secondary',
            'alert': 'warning'
        };
        return colors[type] || 'secondary';
    }

    // Generate comprehensive report
    async generateReport() {
        try {
            const adminId = localStorage.getItem('petCareUserId');
            const formData = new FormData();
            formData.append('action', 'generate_admin_report');
            formData.append('admin_id', adminId);
            formData.append('period', this.currentPeriod);

            const response = await fetch('backend/api.php', { method: 'POST', body: formData });
            const data = await response.json();

            if (data.success) {
                this.downloadReport(data.data);
            } else {
                showToast('Failed to generate report: ' + data.error, 'error');
            }
        } catch (error) {
            console.error('Error generating report:', error);
            showToast('Error generating report', 'error');
        }
    }

    // Download report
    downloadReport(reportData) {
        const blob = new Blob([reportData.content], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `admin_report_${new Date().toISOString().split('T')[0]}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        
        showToast('Report downloaded successfully', 'success');
    }

    // Start auto-refresh
    startAutoRefresh() {
        this.refreshInterval = setInterval(() => {
            this.loadDashboardStats();
            this.loadSystemAlerts();
        }, 30000); // Refresh every 30 seconds
    }

    // Stop auto-refresh
    stopAutoRefresh() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
            this.refreshInterval = null;
        }
    }

    // Setup event listeners
    setupEventListeners() {
        // Period selector
        const periodSelect = document.getElementById('analytics-period');
        if (periodSelect) {
            periodSelect.addEventListener('change', (e) => {
                this.currentPeriod = e.target.value;
                this.loadDashboardStats();
                this.loadCharts();
            });
        }

        // Refresh button
        const refreshBtn = document.getElementById('refresh-analytics');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => {
                this.loadDashboardStats();
                this.loadCharts();
                this.loadSystemAlerts();
                this.loadRecentActivity();
                showToast('Analytics refreshed', 'success');
            });
        }

        // Export button
        const exportBtn = document.getElementById('export-analytics');
        if (exportBtn) {
            exportBtn.addEventListener('click', () => {
                this.generateReport();
            });
        }
    }

    // Cleanup
    destroy() {
        this.stopAutoRefresh();
        
        // Destroy all charts
        Object.values(this.charts).forEach(chart => {
            if (chart && typeof chart.destroy === 'function') {
                chart.destroy();
            }
        });
        
        this.charts = {};
    }
}

// Global instance
let adminAnalytics = null;

// Initialize admin analytics when dashboard loads
function initAdminAnalytics() {
    if (adminAnalytics) {
        adminAnalytics.destroy();
    }
    
    adminAnalytics = new AdminAnalytics();
    adminAnalytics.init();
}

// Export functions for global use
window.initAdminAnalytics = initAdminAnalytics;
window.generateReport = () => adminAnalytics?.generateReport();
