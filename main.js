// Dashboard data and configuration
                                                                    const dashboardData = {
                                                                        owner: {
                                                                            username: "John Peterson",
                                                                            stats: [
                                                                                { icon: "fa-paw", value: 2, label: "Pets" },
                                                                                { icon: "fa-calendar", value: 3, label: "Upcoming Appointments" },
                                                                                { icon: "fa-syringe", value: 1, label: "Vaccinations Due" },
                                                                                { icon: "fa-file-medical", value: 5, label: "Health Records" }
                                                                            ],
                                                                            menu: [
                                                                                { id: "overview", icon: "fa-home", label: "Overview" },
                                                                                { id: "pets", icon: "fa-paw", label: "My Pets" },
                                                                                { id: "appointments", icon: "fa-calendar", label: "Appointments" },
                                                                                { id: "health", icon: "fa-heartbeat", label: "Health Records" },
                                                                                { id: "vaccinations", icon: "fa-syringe", label: "Vaccinations" },
                                                                                { id: "payments", icon: "fa-credit-card", label: "Payments" },
                                                                                { id: "adoption", icon: "fa-home", label: "Adoption" },
                                                                                { id: "lostfound", icon: "fa-search", label: "Lost & Found" }
                                                                            ]
                                                                        },
                                                                        vet: {
                                                                            username: "Dr. Sarah Smith",
                                                                            stats: [
                                                                                { icon: "fa-calendar", value: 8, label: "Today's Appointments" },
                                                                                { icon: "fa-stethoscope", value: 3, label: "Telemedicine Sessions" },
                                                                                { icon: "fa-file-medical", value: 42, label: "Patient Records" },
                                                                                { icon: "fa-video", value: 2, label: "Upcoming Consultations" }
                                                                            ],
                                                                            menu: [
                                                                                { id: "overview", icon: "fa-home", label: "Overview" },
                                                                                { id: "appointments", icon: "fa-calendar", label: "Appointments" },
                                                                                { id: "records", icon: "fa-file-medical", label: "Patient Records" },
                                                                                { id: "telemedicine", icon: "fa-video", label: "Telemedicine" },
                                                                                { id: "prescriptions", icon: "fa-prescription", label: "Prescriptions" },
                                                                                { id: "adoption", icon: "fa-home", label: "Adoption" },
                                                                                { id: "lostfound", icon: "fa-search", label: "Lost & Found" }
                                                                            ]
                                                                        },
                                                                        admin: {
                                                                            username: "Admin User",
                                                                            stats: [
                                                                                { icon: "fa-users", value: 142, label: "Total Users" },
                                                                                { icon: "fa-paw", value: 287, label: "Registered Pets" },
                                                                                { icon: "fa-calendar", value: 56, label: "Today's Appointments" },
                                                                                { icon: "fa-money-bill", value: "KSh 120,450", label: "Monthly Revenue" }
                                                                            ],
                                                                            menu: [
                                                                                { id: "overview", icon: "fa-home", label: "Overview" },
                                                                                { id: "users", icon: "fa-users", label: "User Management" },
                                                                                { id: "pets", icon: "fa-paw", label: "Pet Management" },
                                                                                { id: "appointments", icon: "fa-calendar", label: "Appointments" },
                                                                                { id: "reports", icon: "fa-chart-bar", label: "Reports" },
                                                                                { id: "forum", icon: "fa-comments", label: "Forum Management" },
                                                                                { id: "payments", icon: "fa-credit-card", label: "Payments" },
                                                                                { id: "settings", icon: "fa-cog", label: "System Settings" }
                                                                            ]
                                                                        }
                                                                    };

                                                                    // --- SESSION MANAGEMENT ---
                                                                    let currentUser = null;

                                                                    // --- SMOOTH SCROLLING ---
                                                                    function smoothScrollTo(target) {
                                                                        const element = document.querySelector(target);
                                                                        if (element) {
                                                                            element.scrollIntoView({
                                                                                behavior: 'smooth',
                                                                                block: 'start'
                                                                            });
                                                                        }
                                                                    }

                                                                    // --- MODAL FUNCTIONS ---
                                                                    function openModal(modalId) {
                                                                        const modal = document.getElementById(modalId);
                                                                        if (modal) {
                                                                            modal.classList.add('active');
                                                                            document.body.style.overflow = 'hidden';

                                                                            // Focus first input if exists
                                                                            const firstInput = modal.querySelector('input, select, textarea');
                                                                            if (firstInput) {
                                                                                setTimeout(() => firstInput.focus(), 100);
                                                                            }
                                                                        }
                                                                    }

                                                                    function closeModal(modalId) {
                                                                        const modal = document.getElementById(modalId);
                                                                        if (modal) {
                                                                            modal.classList.remove('active');
                                                                            document.body.style.overflow = 'auto';

                                                                            // Reset form if exists
                                                                            const form = modal.querySelector('form');
                                                                            if (form) {
                                                                                form.reset();
                                                                            }
                                                                        }
                                                                    }

                                                                    // --- SERVICE BOOKING FUNCTIONS ---
                                                                    function bookService(serviceName, price = null) {
                                                                        // Check if user is logged in
                                                                        const loggedInUserId = localStorage.getItem('petCareUserId');
                                                                        if (!loggedInUserId) {
                                                                            showToast('Please login to book appointments', 'warning');
                                                                            openModal('login-modal');
                                                                            return;
                                                                        }

                                                                        // Open appointment modal and pre-fill service
                                                                        openModal('appointment-modal');

                                                                        // Pre-fill the service if provided
                                                                        if (serviceName) {
                                                                            const serviceSelect = document.getElementById('appointment-service');
                                                                            if (serviceSelect) {
                                                                                serviceSelect.value = serviceName;
                                                                            }
                                                                        }
                                                                    }

                                                                    function scrollToService(serviceName) {
                                                                        smoothScrollTo('#services');
                                                                        // Highlight the specific service card
                                                                        setTimeout(() => {
                                                                            const serviceCards = document.querySelectorAll('.feature-card');
                                                                            serviceCards.forEach(card => {
                                                                                const title = card.querySelector('h3');
                                                                                if (title && title.textContent.includes(serviceName)) {
                                                                                    card.style.transform = 'scale(1.05)';
                                                                                    card.style.boxShadow = '0 10px 30px rgba(0,0,0,0.3)';
                                                                                    setTimeout(() => {
                                                                                        card.style.transform = '';
                                                                                        card.style.boxShadow = '';
                                                                                    }, 2000);
                                                                                }
                                                                            });
                                                                        }, 500);
                                                                    }

                                                                    // --- APPOINTMENT BOOKING ---
                                                                    function handleAppointmentBooking(formData) {
                                                                        // Check if user is logged in
                                                                        const loggedInUserId = localStorage.getItem('petCareUserId');
                                                                        if (!loggedInUserId) {
                                                                            showToast('Please login to book appointments', 'warning');
                                                                            closeModal('appointment-modal');
                                                                            openModal('login-modal');
                                                                            return;
                                                                        }

                                                                        // Simulate booking
                                                                        showToast('Appointment booked successfully! We will contact you to confirm.', 'success');
                                                                        closeModal('appointment-modal');

                                                                        // Reset form
                                                                        document.getElementById('appointment-form').reset();
                                                                    }

                                                                    // --- RESPONSIVE UTILITIES ---
                                                                    function isMobile() {
                                                                        return window.innerWidth <= 768;
                                                                    }

                                                                    function isTablet() {
                                                                        return window.innerWidth > 768 && window.innerWidth <= 992;
                                                                    }

                                                                    function isDesktop() {
                                                                        return window.innerWidth > 992;
                                                                    }

                                                                    // --- MOBILE TABLE HANDLING ---
                                                                    function setupResponsiveTables() {
                                                                        const tables = document.querySelectorAll('.dashboard-table');
                                                                        tables.forEach(table => {
                                                                            if (isMobile()) {
                                                                                setupMobileTable(table);
                                                                            } else {
                                                                                setupDesktopTable(table);
                                                                            }
                                                                        });
                                                                    }

                                                                    function setupMobileTable(table) {
                                                                        const rows = table.querySelectorAll('tbody tr');
                                                                        rows.forEach(row => {
                                                                            row.addEventListener('click', function () {
                                                                                if (this.classList.contains('expanded')) {
                                                                                    this.classList.remove('expanded');
                                                                                } else {
                                                                                    // Close other expanded rows
                                                                                    rows.forEach(r => r.classList.remove('expanded'));
                                                                                    this.classList.add('expanded');
                                                                                }
                                                                            });

                                                                            // Add data attributes for mobile display
                                                                            const cells = row.querySelectorAll('td');
                                                                            const headers = table.querySelectorAll('th');
                                                                            cells.forEach((cell, index) => {
                                                                                if (headers[index]) {
                                                                                    cell.setAttribute('data-label', headers[index].textContent);
                                                                                }
                                                                            });
                                                                        });
                                                                    }

                                                                    function setupDesktopTable(table) {
                                                                        const rows = table.querySelectorAll('tbody tr');
                                                                        rows.forEach(row => {
                                                                            row.classList.remove('expanded');
                                                                            row.style.cursor = 'default';
                                                                        });
                                                                    }

                                                                    // --- RESPONSIVE FORM HANDLING ---
                                                                    function setupResponsiveForms() {
                                                                        const forms = document.querySelectorAll('form');
                                                                        forms.forEach(form => {
                                                                            const inputs = form.querySelectorAll('input, select, textarea');
                                                                            inputs.forEach(input => {
                                                                                // Prevent zoom on iOS
                                                                                if (input.type === 'text' || input.type === 'email' || input.type === 'password') {
                                                                                    input.style.fontSize = '16px';
                                                                                }

                                                                                // Add touch-friendly padding on mobile
                                                                                if (isMobile()) {
                                                                                    input.style.minHeight = '44px';
                                                                                    input.style.padding = '12px';
                                                                                }
                                                                            });
                                                                        });
                                                                    }

                                                                    // --- RESPONSIVE MODAL HANDLING ---
                                                                    function setupResponsiveModals() {
                                                                        const modals = document.querySelectorAll('.modal');
                                                                        modals.forEach(modal => {
                                                                            const content = modal.querySelector('.modal-content');
                                                                            if (isMobile()) {
                                                                                content.style.margin = '10px';
                                                                                content.style.maxHeight = '95vh';
                                                                                content.style.padding = '20px';
                                                                            } else {
                                                                                content.style.margin = 'auto';
                                                                                content.style.maxHeight = '90vh';
                                                                                content.style.padding = '30px';
                                                                            }
                                                                        });
                                                                    }

                                                                    // --- RESPONSIVE BUTTON HANDLING ---
                                                                    function setupResponsiveButtons() {
                                                                        const buttons = document.querySelectorAll('.btn');
                                                                        buttons.forEach(button => {
                                                                            if (isMobile()) {
                                                                                button.style.minHeight = '44px';
                                                                                button.style.minWidth = '44px';
                                                                                button.style.fontSize = '16px';
                                                                                button.style.padding = '12px 20px';
                                                                            }
                                                                        });
                                                                    }

                                                                    // --- RESPONSIVE NAVIGATION ---
                                                                    function setupResponsiveNavigation() {
                                                                        const sidebar = document.querySelector('.dashboard-sidebar');
                                                                        const content = document.querySelector('.dashboard-content');

                                                                        if (isMobile()) {
                                                                            // Create mobile navigation toggle
                                                                            if (!document.getElementById('mobile-nav-toggle')) {
                                                                                const toggle = document.createElement('button');
                                                                                toggle.id = 'mobile-nav-toggle';
                                                                                toggle.className = 'btn btn-primary d-md-none mb-3';
                                                                                toggle.innerHTML = '<i class="fas fa-bars"></i> Toggle Navigation';
                                                                                toggle.addEventListener('click', function () {
                                                                                    sidebar.classList.toggle('show-mobile');
                                                                                });
                                                                                content.insertBefore(toggle, content.firstChild);
                                                                            }

                                                                            // Add overlay for mobile sidebar
                                                                            if (!document.getElementById('mobile-overlay')) {
                                                                                const overlay = document.createElement('div');
                                                                                overlay.id = 'mobile-overlay';
                                                                                overlay.className = 'mobile-overlay';
                                                                                overlay.addEventListener('click', function () {
                                                                                    sidebar.classList.remove('show-mobile');
                                                                                });
                                                                                document.body.appendChild(overlay);
                                                                            }
                                                                        }
                                                                    }

                                                                    // --- RESPONSIVE STATS CARDS ---
                                                                    function setupResponsiveStats() {
                                                                        const statsGrid = document.querySelector('.stats-grid');
                                                                        if (statsGrid) {
                                                                            if (isMobile()) {
                                                                                statsGrid.style.gridTemplateColumns = '1fr';
                                                                                statsGrid.style.gap = '10px';
                                                                            } else if (isTablet()) {
                                                                                statsGrid.style.gridTemplateColumns = 'repeat(2, 1fr)';
                                                                                statsGrid.style.gap = '15px';
                                                                            } else {
                                                                                statsGrid.style.gridTemplateColumns = 'repeat(auto-fit, minmax(200px, 1fr))';
                                                                                statsGrid.style.gap = '20px';
                                                                            }
                                                                        }
                                                                    }

                                                                    // --- RESPONSIVE SEARCH AND FILTERS ---
                                                                    function setupResponsiveSearch() {
                                                                        const searchContainers = document.querySelectorAll('.search-filter-container');
                                                                        searchContainers.forEach(container => {
                                                                            if (isMobile()) {
                                                                                container.style.gridTemplateColumns = '1fr';
                                                                                container.style.gap = '10px';
                                                                            } else {
                                                                                container.style.gridTemplateColumns = 'repeat(auto-fit, minmax(200px, 1fr))';
                                                                                container.style.gap = '15px';
                                                                            }
                                                                        });
                                                                    }

                                                                    // --- RESPONSIVE PAGINATION ---
                                                                    function setupResponsivePagination() {
                                                                        const pagination = document.querySelectorAll('.pagination-responsive');
                                                                        pagination.forEach(pag => {
                                                                            if (isMobile()) {
                                                                                pag.style.flexWrap = 'wrap';
                                                                                pag.style.gap = '5px';
                                                                            }
                                                                        });
                                                                    }

                                                                    // --- TOUCH GESTURES ---
                                                                    function setupTouchGestures() {
                                                                        if (isMobile()) {
                                                                            // Swipe to navigate between dashboard sections
                                                                            let startX = 0;
                                                                            let endX = 0;

                                                                            document.addEventListener('touchstart', function (e) {
                                                                                startX = e.touches[0].clientX;
                                                                            });

                                                                            document.addEventListener('touchend', function (e) {
                                                                                endX = e.changedTouches[0].clientX;
                                                                                handleSwipe();
                                                                            });

                                                                            function handleSwipe() {
                                                                                const threshold = 50;
                                                                                const diff = startX - endX;

                                                                                if (Math.abs(diff) > threshold) {
                                                                                    const activeSection = document.querySelector('.dashboard-section.active');
                                                                                    const sections = document.querySelectorAll('.dashboard-section');
                                                                                    const currentIndex = Array.from(sections).indexOf(activeSection);

                                                                                    if (diff > 0 && currentIndex < sections.length - 1) {
                                                                                        // Swipe left - next section
                                                                                        showDashboardSection(sections[currentIndex + 1].id);
                                                                                    } else if (diff < 0 && currentIndex > 0) {
                                                                                        // Swipe right - previous section
                                                                                        showDashboardSection(sections[currentIndex - 1].id);
                                                                                    }
                                                                                }
                                                                            }
                                                                        }
                                                                    }

                                                                    // --- RESPONSIVE DASHBOARD SECTION NAVIGATION ---
                                                                    function showDashboardSection(sectionId) {
                                                                        // Hide all sections
                                                                        document.querySelectorAll('.dashboard-section').forEach(section => {
                                                                            section.classList.remove('active');
                                                                        });

                                                                        // Show target section
                                                                        const targetSection = document.getElementById(sectionId);
                                                                        if (targetSection) {
                                                                            targetSection.classList.add('active');
                                                                        }

                                                                        // Update menu active state
                                                                        document.querySelectorAll('#dashboard-menu a').forEach(link => {
                                                                            link.classList.remove('active');
                                                                            if (link.dataset.section === sectionId.replace(`${currentUser.role}-`, '')) {
                                                                                link.classList.add('active');
                                                                            }
                                                                        });

                                                                        // Close mobile sidebar if open
                                                                        if (isMobile()) {
                                                                            const sidebar = document.querySelector('.dashboard-sidebar');
                                                                            sidebar.classList.remove('show-mobile');
                                                                        }

                                                                        // Load section-specific content
                                                                        loadSectionContent(sectionId);
                                                                    }

                                                                    // --- LOAD SECTION CONTENT ---
                                                                    function loadSectionContent(sectionId) {
                                                                        const role = currentUser.role;

                                                                        switch (sectionId) {
                                                                            case `${role}-overview`:
                                                                                loadOverviewContent(role);
                                                                                break;
                                                                            case `${role}-appointments`:
                                                                                loadAppointmentsContent(role);
                                                                                break;
                                                                            case `${role}-pets`:
                                                                                loadPetsContent(role);
                                                                                break;
                                                                            case `${role}-health`:
                                                                                loadHealthContent(role);
                                                                                break;
                                                                            case `${role}-vaccinations`:
                                                                                loadVaccinationsContent(role);
                                                                                break;
                                                                            case `${role}-payments`:
                                                                                loadPaymentsContent(role);
                                                                                break;
                                                                            case `${role}-adoption`:
                                                                                loadAdoptionContent(role);
                                                                                break;
                                                                            case `${role}-lostfound`:
                                                                                loadLostFoundContent(role);
                                                                                break;
                                                                            case `${role}-records`:
                                                                                loadRecordsContent(role);
                                                                                break;
                                                                            case `${role}-telemedicine`:
                                                                                loadTelemedicineContent(role);
                                                                                break;
                                                                            case `${role}-prescriptions`:
                                                                                loadPrescriptionsContent(role);
                                                                                break;
                                                                            case `${role}-users`:
                                                                                loadUsersContent(role);
                                                                                break;
                                                                            case `${role}-reports`:
                                                                                loadReportsContent(role);
                                                                                break;
                                                                            case `${role}-forum`:
                                                                                loadForumContent(role);
                                                                                break;
                                                                            case `${role}-settings`:
                                                                                loadSettingsContent(role);
                                                                                break;
                                                                        }
                                                                    }

                                                                    // --- LOAD OVERVIEW CONTENT ---
                                                                    function loadOverviewContent(role) {
                                                                        const statsContainer = document.getElementById(`${role}-stats`);
                                                                        if (!statsContainer) return;

                                                                        const data = dashboardData[role];
                                                                        statsContainer.innerHTML = '';

                                                                        data.stats.forEach(stat => {
                                                                            statsContainer.innerHTML += `
                    <div class="stat-card">
                        <i class="fas ${stat.icon}"></i>
                        <h3>${stat.value}</h3>
                        <p>${stat.label}</p>
                    </div>
                `;
                                                                        });

                                                                        // Load recent activity based on role
                                                                        if (role === 'owner') {
                                                                            loadOwnerRecentActivity();
                                                                        } else if (role === 'vet') {
                                                                            loadVetRecentActivity();
                                                                        } else if (role === 'admin') {
                                                                            loadAdminRecentActivity();
                                                                        }
                                                                    }

                                                                    function loadOwnerRecentActivity() {
                                                                        const appointmentsContainer = document.getElementById('owner-recent-appointments');
                                                                        const notificationsContainer = document.getElementById('owner-notifications');

                                                                        if (appointmentsContainer) {
                                                                            appointmentsContainer.innerHTML = `
                    <div class="list-group">
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <span><i class="fas fa-calendar text-primary"></i> Wellness Exam - Max</span>
                                <small>Tomorrow, 10:00 AM</small>
                            </div>
                        </div>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <span><i class="fas fa-calendar text-success"></i> Vaccination - Luna</span>
                                <small>Dec 15, 2:30 PM</small>
                            </div>
                        </div>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <span><i class="fas fa-calendar text-info"></i> Dental Cleaning - Max</span>
                                <small>Dec 20, 11:00 AM</small>
                            </div>
                        </div>
                    </div>
                `;
                                                                        }

                                                                        if (notificationsContainer) {
                                                                            notificationsContainer.innerHTML = `
                    <div class="list-group">
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <span><i class="fas fa-bell text-warning"></i> Luna's vaccination due in 2 weeks</span>
                                <small>2 hours ago</small>
                            </div>
                        </div>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <span><i class="fas fa-bell text-success"></i> Appointment confirmed for tomorrow</span>
                                <small>1 day ago</small>
                            </div>
                        </div>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <span><i class="fas fa-bell text-info"></i> New pet care tips available</span>
                                <small>3 days ago</small>
                            </div>
                        </div>
                    </div>
                `;
                                                                        }
                                                                    }

                                                                    function loadVetRecentActivity() {
                                                                        const appointmentsContainer = document.getElementById('vet-today-appointments');
                                                                        const telemedicineContainer = document.getElementById('vet-telemedicine-sessions');

                                                                        if (appointmentsContainer) {
                                                                            appointmentsContainer.innerHTML = `
                    <div class="list-group">
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <span><i class="fas fa-calendar text-primary"></i> Wellness Exam - Max (John P.)</span>
                                <small>10:00 AM</small>
                            </div>
                        </div>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <span><i class="fas fa-calendar text-success"></i> Vaccination - Luna (Sarah M.)</span>
                                <small>2:30 PM</small>
                            </div>
                        </div>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <span><i class="fas fa-calendar text-info"></i> Dental Cleaning - Buddy (Mike T.)</span>
                                <small>4:00 PM</small>
                            </div>
                        </div>
                    </div>
                `;
                                                                        }

                                                                        if (telemedicineContainer) {
                                                                            telemedicineContainer.innerHTML = `
                    <div class="list-group">
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <span><i class="fas fa-video text-primary"></i> Follow-up - Max (John P.)</span>
                                <small>11:00 AM</small>
                            </div>
                        </div>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <span><i class="fas fa-video text-success"></i> Consultation - Bella (Amina K.)</span>
                                <small>3:00 PM</small>
                            </div>
                        </div>
                    </div>
                `;
                                                                        }
                                                                    }

                                                                    function loadAdminRecentActivity() {
                                                                        const statsContainer = document.getElementById('admin-system-stats');
                                                                        const alertsContainer = document.getElementById('admin-system-alerts');

                                                                        if (statsContainer) {
                                                                            statsContainer.innerHTML = `
                    <div class="list-group">
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <span><i class="fas fa-chart-line text-primary"></i> Monthly Revenue</span>
                                <strong>KSh 120,450</strong>
                            </div>
                        </div>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <span><i class="fas fa-users text-success"></i> New Users This Month</span>
                                <strong>23</strong>
                            </div>
                        </div>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <span><i class="fas fa-calendar text-info"></i> Appointments Today</span>
                                <strong>56</strong>
                            </div>
                        </div>
                    </div>
                `;
                                                                        }

                                                                        if (alertsContainer) {
                                                                            alertsContainer.innerHTML = `
                    <div class="list-group">
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <span><i class="fas fa-exclamation-triangle text-warning"></i> Low vaccine stock</span>
                                <small>1 hour ago</small>
                            </div>
                        </div>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <span><i class="fas fa-exclamation-triangle text-danger"></i> System backup needed</span>
                                <small>3 hours ago</small>
                            </div>
                        </div>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <span><i class="fas fa-info-circle text-info"></i> New user registration</span>
                                <small>5 hours ago</small>
                            </div>
                        </div>
                    </div>
                `;
                                                                        }
                                                                    }

                                                                    // --- LOAD APPOINTMENTS CONTENT ---
                                                                    async function loadAppointmentsContent(role) {
                                                                        const tbody = document.getElementById(`${role}-appointments-tbody`);
                                                                        if (!tbody) return;

                                                                        try {
                                                                            // Show loading state
                                                                            tbody.innerHTML = '<tr><td colspan="6" class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></td></tr>';

                                                                            // Fetch appointments from backend
                                                                            const formData = new FormData();
                                                                            formData.append('action', 'get_appointments');
                                                                            formData.append('user_id', currentUser.id);
                                                                            formData.append('role', role);

                                                                            const response = await fetch('backend/api.php', {
                                                                                method: 'POST',
                                                                                body: formData
                                                                            });

                                                                            const result = await response.json();

                                                                            if (result.success && result.data) {
                                                                                const appointments = result.data;
                                                                                tbody.innerHTML = '';

                                                                                if (appointments.length === 0) {
                                                                                    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No appointments found.</td></tr>';
                                                                                    return;
                                                                                }

                                                                                appointments.forEach(appointment => {
                                                                                    const statusClass = appointment.status === 'Confirmed' ? 'badge-success' :
                                                                                        appointment.status === 'Scheduled' ? 'badge-primary' :
                                                                                            appointment.status === 'Completed' ? 'badge-info' : 'badge-warning';

                                                                                    if (role === 'owner') {
                                                                                        tbody.innerHTML += `
                                <tr>
                                    <td>${appointment.date}</td>
                                    <td>${appointment.time}</td>
                                    <td>${appointment.pet_name || appointment.pet}</td>
                                    <td>${appointment.purpose}</td>
                                    <td><span class="badge ${statusClass}">${appointment.status}</span></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-sm btn-primary" onclick="editAppointment('${appointment.id}', '${appointment.date}', '${appointment.time}', '${appointment.pet_name || appointment.pet}', '${appointment.purpose}', '${appointment.status}')" title="Edit Appointment">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="cancelAppointment('${appointment.id}', '${appointment.date}')" title="Cancel Appointment">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            `;
                                                                                    } else if (role === 'vet') {
                                                                                        tbody.innerHTML += `
                                <tr>
                                    <td>${appointment.date}</td>
                                    <td>${appointment.time}</td>
                                    <td>${appointment.pet_name || appointment.pet}</td>
                                    <td>${appointment.owner_name || appointment.owner}</td>
                                    <td>${appointment.purpose || appointment.reason}</td>
                                    <td><span class="badge ${statusClass}">${appointment.status}</span></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-sm btn-primary" onclick="startAppointment('${appointment.id}')" title="Start Appointment">
                                                <i class="fas fa-play"></i>
                                            </button>
                                            <button class="btn btn-sm btn-success" onclick="completeAppointment('${appointment.id}')" title="Complete Appointment">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button class="btn btn-sm btn-info" onclick="viewAppointmentDetails('${appointment.id}')" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            `;
                                                                                    } else if (role === 'admin') {
                                                                                        tbody.innerHTML += `
                                <tr>
                                    <td>${appointment.date}</td>
                                    <td>${appointment.time}</td>
                                    <td>${appointment.pet_name || appointment.pet}</td>
                                    <td>${appointment.owner_name || appointment.owner}</td>
                                    <td>${appointment.vet_name || appointment.vet}</td>
                                    <td><span class="badge ${statusClass}">${appointment.status}</span></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-sm btn-primary" onclick="editAppointment('${appointment.id}', '${appointment.date}', '${appointment.time}', '${appointment.pet_name || appointment.pet}', '${appointment.owner_name || appointment.owner}', '${appointment.vet_name || appointment.vet}', '${appointment.status}')" title="Edit Appointment">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="cancelAppointment('${appointment.id}', '${appointment.date}')" title="Cancel Appointment">
                                                <i class="fas fa-times"></i>
                                            </button>
                                            <button class="btn btn-sm btn-info" onclick="viewAppointmentDetails('${appointment.id}')" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            `;
                                                                                    }
                                                                                });
                                                                            } else {
                                                                                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Failed to load appointments. Please try again.</td></tr>';
                                                                            }
                                                                        } catch (error) {
                                                                            console.error('Error loading appointments:', error);
                                                                            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error loading appointments. Please check your connection.</td></tr>';
                                                                        }
                                                                    }

                                                                    // --- APPOINTMENT CRUD OPERATIONS ---
                                                                    async function addAppointment() {
                                                                        const form = document.getElementById('add-appointment-form');
                                                                        const formData = new FormData(form);
                                                                        formData.append('action', 'add_appointment');
                                                                        formData.append('user_id', currentUser.id);

                                                                        try {
                                                                            const response = await fetch('backend/api.php', {
                                                                                method: 'POST',
                                                                                body: formData
                                                                            });

                                                                            const result = await response.json();

                                                                            if (result.success) {
                                                                                showToast('Appointment booked successfully!', 'success');
                                                                                closeModal('add-appointment-modal');
                                                                                form.reset();
                                                                                loadAppointmentsContent(currentUser.role);
                                                                            } else {
                                                                                showToast(result.error || 'Failed to book appointment', 'error');
                                                                            }
                                                                        } catch (error) {
                                                                            console.error('Error booking appointment:', error);
                                                                            showToast('Network error. Please try again.', 'error');
                                                                        }
                                                                    }

                                                                    async function editAppointment(id, date, time, pet, purpose, status, owner = '', vet = '') {
                                                                        // Populate edit form
                                                                        document.getElementById('edit-appointment-id').value = id;
                                                                        document.getElementById('edit-appointment-date').value = date;
                                                                        document.getElementById('edit-appointment-time').value = time;
                                                                        document.getElementById('edit-appointment-pet').value = pet;
                                                                        document.getElementById('edit-appointment-purpose').value = purpose;
                                                                        document.getElementById('edit-appointment-status').value = status;
                                                                        if (owner) document.getElementById('edit-appointment-owner').value = owner;
                                                                        if (vet) document.getElementById('edit-appointment-vet').value = vet;

                                                                        openModal('edit-appointment-modal');
                                                                    }

                                                                    async function updateAppointment() {
                                                                        const form = document.getElementById('edit-appointment-form');
                                                                        const formData = new FormData(form);
                                                                        formData.append('action', 'update_appointment');

                                                                        try {
                                                                            const response = await fetch('backend/api.php', {
                                                                                method: 'POST',
                                                                                body: formData
                                                                            });

                                                                            const result = await response.json();

                                                                            if (result.success) {
                                                                                showToast('Appointment updated successfully!', 'success');
                                                                                closeModal('edit-appointment-modal');
                                                                                loadAppointmentsContent(currentUser.role);
                                                                            } else {
                                                                                showToast(result.error || 'Failed to update appointment', 'error');
                                                                            }
                                                                        } catch (error) {
                                                                            console.error('Error updating appointment:', error);
                                                                            showToast('Network error. Please try again.', 'error');
                                                                        }
                                                                    }

                                                                    async function cancelAppointment(id, date) {
                                                                        // Show confirmation modal
                                                                        document.getElementById('confirmation-title').textContent = 'Cancel Appointment';
                                                                        document.getElementById('confirmation-message').textContent = `Are you sure you want to cancel the appointment on ${date}?`;
                                                                        document.getElementById('confirmation-btn-text').textContent = 'Cancel Appointment';

                                                                        openModal('confirmation-modal');

                                                                        // Set up confirmation action
                                                                        document.getElementById('confirmation-confirm-btn').onclick = async function () {
                                                                            try {
                                                                                const formData = new FormData();
                                                                                formData.append('action', 'cancel_appointment');
                                                                                formData.append('id', id);

                                                                                const response = await fetch('backend/api.php', {
                                                                                    method: 'POST',
                                                                                    body: formData
                                                                                });

                                                                                const result = await response.json();

                                                                                if (result.success) {
                                                                                    showToast('Appointment cancelled successfully!', 'success');
                                                                                    closeModal('confirmation-modal');
                                                                                    loadAppointmentsContent(currentUser.role);
                                                                                } else {
                                                                                    showToast(result.error || 'Failed to cancel appointment', 'error');
                                                                                }
                                                                            } catch (error) {
                                                                                console.error('Error cancelling appointment:', error);
                                                                                showToast('Network error. Please try again.', 'error');
                                                                            }
                                                                        };
                                                                    }

                                                                    async function startAppointment(id) {
                                                                        try {
                                                                            const formData = new FormData();
                                                                            formData.append('action', 'start_appointment');
                                                                            formData.append('id', id);

                                                                            const response = await fetch('backend/api.php', {
                                                                                method: 'POST',
                                                                                body: formData
                                                                            });

                                                                            const result = await response.json();

                                                                            if (result.success) {
                                                                                showToast('Appointment started!', 'success');
                                                                                loadAppointmentsContent(currentUser.role);
                                                                            } else {
                                                                                showToast(result.error || 'Failed to start appointment', 'error');
                                                                            }
                                                                        } catch (error) {
                                                                            console.error('Error starting appointment:', error);
                                                                            showToast('Network error. Please try again.', 'error');
                                                                        }
                                                                    }

                                                                    async function completeAppointment(id) {
                                                                        try {
                                                                            const formData = new FormData();
                                                                            formData.append('action', 'complete_appointment');
                                                                            formData.append('id', id);

                                                                            const response = await fetch('backend/api.php', {
                                                                                method: 'POST',
                                                                                body: formData
                                                                            });

                                                                            const result = await response.json();

                                                                            if (result.success) {
                                                                                showToast('Appointment completed!', 'success');
                                                                                loadAppointmentsContent(currentUser.role);
                                                                            } else {
                                                                                showToast(result.error || 'Failed to complete appointment', 'error');
                                                                            }
                                                                        } catch (error) {
                                                                            console.error('Error completing appointment:', error);
                                                                            showToast('Network error. Please try again.', 'error');
                                                                        }
                                                                    }

                                                                    async function viewAppointmentDetails(id) {
                                                                        try {
                                                                            const formData = new FormData();
                                                                            formData.append('action', 'get_appointment_details');
                                                                            formData.append('id', id);

                                                                            const response = await fetch('backend/api.php', {
                                                                                method: 'POST',
                                                                                body: formData
                                                                            });

                                                                            const result = await response.json();

                                                                            if (result.success && result.data) {
                                                                                const appointment = result.data;

                                                                                // Populate view modal
                                                                                document.getElementById('view-appointment-date').textContent = appointment.date;
                                                                                document.getElementById('view-appointment-time').textContent = appointment.time;
                                                                                document.getElementById('view-appointment-pet').textContent = appointment.pet_name || appointment.pet;
                                                                                document.getElementById('view-appointment-owner').textContent = appointment.owner_name || appointment.owner;
                                                                                document.getElementById('view-appointment-vet').textContent = appointment.vet_name || appointment.vet;
                                                                                document.getElementById('view-appointment-purpose').textContent = appointment.purpose;
                                                                                document.getElementById('view-appointment-status').textContent = appointment.status;
                                                                                document.getElementById('view-appointment-notes').textContent = appointment.notes || 'No notes';

                                                                                openModal('view-appointment-modal');
                                                                            } else {
                                                                                showToast(result.error || 'Failed to load appointment details', 'error');
                                                                            }
                                                                        } catch (error) {
                                                                            console.error('Error loading appointment details:', error);
                                                                            showToast('Network error. Please try again.', 'error');
                                                                        }
                                                                    }

                                                                    // --- LOAD PETS CONTENT ---
                                                                    async function loadPetsContent(role) {
                                                                        const tbody = document.getElementById(`${role}-pets-tbody`);
                                                                        if (!tbody) return;

                                                                        try {
                                                                            // Show loading state
                                                                            tbody.innerHTML = '<tr><td colspan="5" class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></td></tr>';

                                                                            // Fetch pets from backend
                                                                            const formData = new FormData();
                                                                            formData.append('action', 'get_pets');
                                                                            formData.append('user_id', currentUser.id);

                                                                            const response = await fetch('backend/api.php', {
                                                                                method: 'POST',
                                                                                body: formData
                                                                            });

                                                                            const result = await response.json();

                                                                            if (result.success && result.data) {
                                                                                const pets = result.data;
                                                                                tbody.innerHTML = '';

                                                                                if (pets.length === 0) {
                                                                                    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No pets found. Add your first pet!</td></tr>';
                                                                                    return;
                                                                                }

                                                                                pets.forEach(pet => {
                                                                                    const statusClass = pet.status === 'Active' ? 'badge-success' : 'badge-danger';
                                                                                    tbody.innerHTML += `
                            <tr>
                                <td>${pet.name}</td>
                                <td>${pet.type}</td>
                                <td>${pet.breed || 'N/A'}</td>
                                <td>${pet.age || 'N/A'}</td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-sm btn-primary" onclick="editPet('${pet.id}', '${pet.name}', '${pet.type}', '${pet.breed || ''}', '${pet.age || ''}', '${pet.status}')" title="Edit Pet">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-info" onclick="viewPetDetails('${pet.id}')" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="deletePet('${pet.id}', '${pet.name}')" title="Delete Pet">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `;
                                                                                });
                                                                            } else {
                                                                                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Failed to load pets. Please try again.</td></tr>';
                                                                            }
                                                                        } catch (error) {
                                                                            console.error('Error loading pets:', error);
                                                                            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Error loading pets. Please check your connection.</td></tr>';
                                                                        }
                                                                    }

                                                                    // --- PET CRUD OPERATIONS ---
                                                                    async function addPet() {
                                                                        const form = document.getElementById('add-pet-form');
                                                                        const formData = new FormData(form);
                                                                        formData.append('action', 'add_pet');
                                                                        formData.append('owner_id', currentUser.id);

                                                                        try {
                                                                            const response = await fetch('backend/api.php', {
                                                                                method: 'POST',
                                                                                body: formData
                                                                            });

                                                                            const result = await response.json();

                                                                            if (result.success) {
                                                                                showToast('Pet added successfully!', 'success');
                                                                                closeModal('add-pet-modal');
                                                                                form.reset();
                                                                                loadPetsContent(currentUser.role);
                                                                            } else {
                                                                                showToast(result.error || 'Failed to add pet', 'error');
                                                                            }
                                                                        } catch (error) {
                                                                            console.error('Error adding pet:', error);
                                                                            showToast('Network error. Please try again.', 'error');
                                                                        }
                                                                    }

                                                                    async function editPet(id, name, type, breed, age, status) {
                                                                        // Populate edit form
                                                                        document.getElementById('edit-pet-id').value = id;
                                                                        document.getElementById('edit-pet-name').value = name;
                                                                        document.getElementById('edit-pet-type').value = type;
                                                                        document.getElementById('edit-pet-breed').value = breed;
                                                                        document.getElementById('edit-pet-age').value = age;
                                                                        document.getElementById('edit-pet-status').value = status;

                                                                        openModal('edit-pet-modal');
                                                                    }

                                                                    async function updatePet() {
                                                                        const form = document.getElementById('edit-pet-form');
                                                                        const formData = new FormData(form);
                                                                        formData.append('action', 'update_pet');

                                                                        try {
                                                                            const response = await fetch('backend/api.php', {
                                                                                method: 'POST',
                                                                                body: formData
                                                                            });

                                                                            const result = await response.json();

                                                                            if (result.success) {
                                                                                showToast('Pet updated successfully!', 'success');
                                                                                closeModal('edit-pet-modal');
                                                                                loadPetsContent(currentUser.role);
                                                                            } else {
                                                                                showToast(result.error || 'Failed to update pet', 'error');
                                                                            }
                                                                        } catch (error) {
                                                                            console.error('Error updating pet:', error);
                                                                            showToast('Network error. Please try again.', 'error');
                                                                        }
                                                                    }

                                                                    async function viewPetDetails(id) {
                                                                        try {
                                                                            const formData = new FormData();
                                                                            formData.append('action', 'get_pet_details');
                                                                            formData.append('id', id);

                                                                            const response = await fetch('backend/api.php', {
                                                                                method: 'POST',
                                                                                body: formData
                                                                            });

                                                                            const result = await response.json();

                                                                            if (result.success && result.data) {
                                                                                const pet = result.data;

                                                                                // Populate view modal
                                                                                document.getElementById('view-pet-name').textContent = pet.name;
                                                                                document.getElementById('view-pet-type').textContent = pet.type;
                                                                                document.getElementById('view-pet-breed').textContent = pet.breed || 'N/A';
                                                                                document.getElementById('view-pet-age').textContent = pet.age || 'N/A';
                                                                                document.getElementById('view-pet-owner').textContent = pet.owner || 'N/A';
                                                                                document.getElementById('view-pet-status').textContent = pet.status;

                                                                                // Load medical history
                                                                                const medicalHistoryContainer = document.getElementById('view-pet-medical-history');
                                                                                if (pet.medical_records && pet.medical_records.length > 0) {
                                                                                    medicalHistoryContainer.innerHTML = pet.medical_records.map(record => `
                            <div class="mb-2 p-2 border rounded">
                                <strong>${record.type}</strong> - ${record.date}<br>
                                <small>${record.details}</small>
                            </div>
                        `).join('');
                                                                                } else {
                                                                                    medicalHistoryContainer.innerHTML = '<p class="text-muted">No medical records found.</p>';
                                                                                }

                                                                                openModal('view-pet-modal');
                                                                            } else {
                                                                                showToast(result.error || 'Failed to load pet details', 'error');
                                                                            }
                                                                        } catch (error) {
                                                                            console.error('Error loading pet details:', error);
                                                                            showToast('Network error. Please try again.', 'error');
                                                                        }
                                                                    }

                                                                    async function deletePet(id, name) {
                                                                        // Show confirmation modal
                                                                        document.getElementById('confirmation-title').textContent = 'Delete Pet';
                                                                        document.getElementById('confirmation-message').textContent = `Are you sure you want to delete ${name}? This action cannot be undone.`;
                                                                        document.getElementById('confirmation-btn-text').textContent = 'Delete Pet';

                                                                        openModal('confirmation-modal');

                                                                        // Set up confirmation action
                                                                        document.getElementById('confirmation-confirm-btn').onclick = async function () {
                                                                            try {
                                                                                const formData = new FormData();
                                                                                formData.append('action', 'delete_pet');
                                                                                formData.append('id', id);

                                                                                const response = await fetch('backend/api.php', {
                                                                                    method: 'POST',
                                                                                    body: formData
                                                                                });

                                                                                const result = await response.json();

                                                                                if (result.success) {
                                                                                    showToast('Pet deleted successfully!', 'success');
                                                                                    closeModal('confirmation-modal');
                                                                                    loadPetsContent(currentUser.role);
                                                                                } else {
                                                                                    showToast(result.error || 'Failed to delete pet', 'error');
                                                                                }
                                                                            } catch (error) {
                                                                                console.error('Error deleting pet:', error);
                                                                                showToast('Network error. Please try again.', 'error');
                                                                            }
                                                                        };
                                                                    }

                                                                    // --- LOAD HEALTH RECORDS CONTENT ---
                                                                    async function loadHealthContent(role) {
                                                                        const tbody = document.getElementById(`${role}-health-tbody`);
                                                                        if (!tbody) return;

                                                                        try {
                                                                            // Show loading state
                                                                            tbody.innerHTML = '<tr><td colspan="5" class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></td></tr>';

                                                                            // Fetch health records from backend
                                                                            const formData = new FormData();
                                                                            formData.append('action', 'get_health_records');
                                                                            formData.append('user_id', currentUser.id);

                                                                            const response = await fetch('backend/api.php', {
                                                                                method: 'POST',
                                                                                body: formData
                                                                            });

                                                                            const result = await response.json();

                                                                            if (result.success && result.data) {
                                                                                const records = result.data;
                                                                                tbody.innerHTML = '';

                                                                                if (records.length === 0) {
                                                                                    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No health records found.</td></tr>';
                                                                                    return;
                                                                                }

                                                                                records.forEach(record => {
                                                                                    tbody.innerHTML += `
                            <tr>
                                <td>${record.pet_name || record.pet}</td>
                                <td>${record.type}</td>
                                <td>${record.details}</td>
                                <td>${record.date}</td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-sm btn-primary" onclick="editHealthRecord('${record.id}', '${record.pet_name || record.pet}', '${record.type}', '${record.details}', '${record.date}')" title="Edit Record">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-info" onclick="viewHealthRecord('${record.id}')" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteHealthRecord('${record.id}', '${record.pet_name || record.pet}')" title="Delete Record">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `;
                                                                                });
                                                                            } else {
                                                                                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Failed to load health records. Please try again.</td></tr>';
                                                                            }
                                                                        } catch (error) {
                                                                            console.error('Error loading health records:', error);
                                                                            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Error loading health records. Please check your connection.</td></tr>';
                                                                        }
                                                                    }

                                                                    // --- LOAD VACCINATIONS CONTENT ---
                                                                    async function loadVaccinationsContent(role) {
                                                                        const tbody = document.getElementById(`${role}-vaccinations-tbody`);
                                                                        if (!tbody) return;

                                                                        try {
                                                                            // Show loading state
                                                                            tbody.innerHTML = '<tr><td colspan="6" class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></td></tr>';

                                                                            // Fetch vaccinations from backend
                                                                            const formData = new FormData();
                                                                            formData.append('action', 'get_vaccinations');
                                                                            formData.append('user_id', currentUser.id);

                                                                            const response = await fetch('backend/api.php', {
                                                                                method: 'POST',
                                                                                body: formData
                                                                            });

                                                                            const result = await response.json();

                                                                            if (result.success && result.data) {
                                                                                const vaccinations = result.data;
                                                                                tbody.innerHTML = '';

                                                                                if (vaccinations.length === 0) {
                                                                                    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No vaccination records found.</td></tr>';
                                                                                    return;
                                                                                }

                                                                                vaccinations.forEach(vaccination => {
                                                                                    const statusClass = vaccination.status === 'Up to date' ? 'badge-success' :
                                                                                        vaccination.status === 'Due soon' ? 'badge-warning' : 'badge-danger';

                                                                                    tbody.innerHTML += `
                            <tr>
                                <td>${vaccination.pet_name || vaccination.pet}</td>
                                <td>${vaccination.vaccine_name || vaccination.vaccine}</td>
                                <td>${vaccination.last_given}</td>
                                <td>${vaccination.next_due}</td>
                                <td><span class="badge ${statusClass}">${vaccination.status}</span></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-sm btn-primary" onclick="editVaccination('${vaccination.id}', '${vaccination.pet_name || vaccination.pet}', '${vaccination.vaccine_name || vaccination.vaccine}', '${vaccination.last_given}', '${vaccination.next_due}', '${vaccination.status}')" title="Edit Vaccination">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-success" onclick="recordVaccination('${vaccination.id}')" title="Record Vaccination">
                                            <i class="fas fa-syringe"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteVaccination('${vaccination.id}', '${vaccination.pet_name || vaccination.pet}')" title="Delete Vaccination">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `;
                                                                                });
                                                                            } else {
                                                                                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Failed to load vaccinations. Please try again.</td></tr>';
                                                                            }
                                                                        } catch (error) {
                                                                            console.error('Error loading vaccinations:', error);
                                                                            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error loading vaccinations. Please check your connection.</td></tr>';
                                                                        }
                                                                    }

                                                                    // --- LOAD PAYMENTS CONTENT ---
                                                                    async function loadPaymentsContent(role) {
                                                                        const tbody = document.getElementById(`${role}-payments-tbody`);
                                                                        if (!tbody) return;

                                                                        try {
                                                                            // Show loading state
                                                                            tbody.innerHTML = '<tr><td colspan="5" class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></td></tr>';

                                                                            // Fetch payments from backend
                                                                            const formData = new FormData();
                                                                            formData.append('action', 'get_payments');
                                                                            formData.append('user_id', currentUser.id);

                                                                            const response = await fetch('backend/api.php', {
                                                                                method: 'POST',
                                                                                body: formData
                                                                            });

                                                                            const result = await response.json();

                                                                            if (result.success && result.data) {
                                                                                const payments = result.data;
                                                                                tbody.innerHTML = '';

                                                                                if (payments.length === 0) {
                                                                                    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No payment records found.</td></tr>';
                                                                                    return;
                                                                                }

                                                                                payments.forEach(payment => {
                                                                                    const statusClass = payment.status === 'Completed' ? 'badge-success' :
                                                                                        payment.status === 'Pending' ? 'badge-warning' : 'badge-danger';

                                                                                    tbody.innerHTML += `
                            <tr>
                                <td>${payment.date}</td>
                                <td>KSh ${parseFloat(payment.amount).toLocaleString()}</td>
                                <td>${payment.method}</td>
                                <td><span class="badge ${statusClass}">${payment.status}</span></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-sm btn-info" onclick="viewPayment('${payment.id}')" title="View Payment">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-success" onclick="downloadReceipt('${payment.id}')" title="Download Receipt">
                                            <i class="fas fa-download"></i>
                                        </button>
                                        ${role === 'admin' ? `<button class="btn btn-sm btn-danger" onclick="deletePayment('${payment.id}')" title="Delete Payment">
                                            <i class="fas fa-trash"></i>
                                        </button>` : ''}
                                    </div>
                                </td>
                            </tr>
                        `;
                                                                                });
                                                                            } else {
                                                                                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Failed to load payments. Please try again.</td></tr>';
                                                                            }
                                                                        } catch (error) {
                                                                            console.error('Error loading payments:', error);
                                                                            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Error loading payments. Please check your connection.</td></tr>';
                                                                        }
                                                                    }

                                                                    // --- LOAD ADOPTION CONTENT ---
                                                                    async function loadAdoptionContent(role) {
                                                                        const tbody = document.getElementById(`${role}-adoption-tbody`);
                                                                        if (!tbody) return;

                                                                        try {
                                                                            // Show loading state
                                                                            tbody.innerHTML = '<tr><td colspan="5" class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></td></tr>';

                                                                            // Fetch adoptions from backend
                                                                            const formData = new FormData();
                                                                            formData.append('action', 'get_adoptions');

                                                                            const response = await fetch('backend/api.php', {
                                                                                method: 'POST',
                                                                                body: formData
                                                                            });

                                                                            const result = await response.json();

                                                                            if (result.success && result.data) {
                                                                                const adoptions = result.data;
                                                                                tbody.innerHTML = '';

                                                                                if (adoptions.length === 0) {
                                                                                    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No adoption listings found.</td></tr>';
                                                                                    return;
                                                                                }

                                                                                adoptions.forEach(adoption => {
                                                                                    const statusClass = adoption.status === 'Available' ? 'badge-success' :
                                                                                        adoption.status === 'Pending' ? 'badge-warning' : 'badge-info';

                                                                                    tbody.innerHTML += `
                            <tr>
                                <td>${adoption.pet_name}</td>
                                <td>${adoption.description}</td>
                                <td><span class="badge ${statusClass}">${adoption.status}</span></td>
                                <td>${adoption.date_posted}</td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-sm btn-primary" onclick="editAdoption('${adoption.id}', '${adoption.pet_name}', '${adoption.description}', '${adoption.status}')" title="Edit Adoption">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-success" onclick="approveAdoption('${adoption.id}')" title="Approve Adoption">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteAdoption('${adoption.id}', '${adoption.pet_name}')" title="Delete Adoption">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `;
                                                                                });
                                                                            } else {
                                                                                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Failed to load adoptions. Please try again.</td></tr>';
                                                                            }
                                                                        } catch (error) {
                                                                            console.error('Error loading adoptions:', error);
                                                                            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Error loading adoptions. Please check your connection.</td></tr>';
                                                                        }
                                                                    }

                                                                    // --- LOAD LOST & FOUND CONTENT ---
                                                                    async function loadLostFoundContent(role) {
                                                                        const tbody = document.getElementById(`${role}-lostfound-tbody`);
                                                                        if (!tbody) return;

                                                                        try {
                                                                            // Show loading state
                                                                            tbody.innerHTML = '<tr><td colspan="6" class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></td></tr>';

                                                                            // Fetch lost & found from backend
                                                                            const formData = new FormData();
                                                                            formData.append('action', 'get_lost_found');

                                                                            const response = await fetch('backend/api.php', {
                                                                                method: 'POST',
                                                                                body: formData
                                                                            });

                                                                            const result = await response.json();

                                                                            if (result.success && result.data) {
                                                                                const reports = result.data;
                                                                                tbody.innerHTML = '';

                                                                                if (reports.length === 0) {
                                                                                    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No lost & found reports found.</td></tr>';
                                                                                    return;
                                                                                }

                                                                                reports.forEach(report => {
                                                                                    const statusClass = report.status === 'Found' ? 'badge-success' :
                                                                                        report.status === 'Searching' ? 'badge-warning' : 'badge-info';
                                                                                    const typeClass = report.type === 'Lost' ? 'text-danger' : 'text-success';

                                                                                    tbody.innerHTML += `
                            <tr>
                                <td><span class="${typeClass}"><i class="fas fa-${report.type === 'Lost' ? 'search' : 'hand-holding-heart'}"></i> ${report.type}</span></td>
                                <td>${report.pet}</td>
                                <td>${report.location}</td>
                                <td>${report.date}</td>
                                <td><span class="badge ${statusClass}">${report.status}</span></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-sm btn-primary" onclick="editLostFound('${report.id}', '${report.type}', '${report.pet}', '${report.location}', '${report.date}', '${report.status}')" title="Edit Report">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-success" onclick="markFound('${report.id}')" title="Mark as Found">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteLostFound('${report.id}', '${report.pet}')" title="Delete Report">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `;
                                                                                });
                                                                            } else {
                                                                                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Failed to load lost & found reports. Please try again.</td></tr>';
                                                                            }
                                                                        } catch (error) {
                                                                            console.error('Error loading lost & found reports:', error);
                                                                            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error loading lost & found reports. Please check your connection.</td></tr>';
                                                                        }
                                                                    }

                                                                    // --- LOAD PATIENT RECORDS CONTENT (VET) ---
                                                                    function loadRecordsContent(role) {
                                                                        const tbody = document.getElementById(`${role}-records-tbody`);
                                                                        if (!tbody) return;

                                                                        const records = [
                                                                            { petName: 'Max', owner: 'John P.', lastVisit: '2024-11-15', medicalHistory: 'Annual checkup, vaccinations up to date' },
                                                                            { petName: 'Luna', owner: 'Sarah M.', lastVisit: '2024-11-10', medicalHistory: 'Dental cleaning, minor procedure' },
                                                                            { petName: 'Buddy', owner: 'Mike T.', lastVisit: '2024-10-20', medicalHistory: 'Emergency visit, surgery performed' }
                                                                        ];

                                                                        tbody.innerHTML = '';
                                                                        records.forEach(record => {
                                                                            tbody.innerHTML += `
                    <tr>
                        <td>${record.petName}</td>
                        <td>${record.owner}</td>
                        <td>${record.lastVisit}</td>
                        <td>${record.medicalHistory}</td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn btn-sm btn-primary" onclick="editRecord('${record.petName}')">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-info" onclick="viewRecord('${record.petName}')">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-success" onclick="addNote('${record.petName}')">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
                                                                        });
                                                                    }

                                                                    // --- LOAD TELEMEDICINE CONTENT (VET) ---
                                                                    function loadTelemedicineContent(role) {
                                                                        const tbody = document.getElementById(`${role}-telemedicine-tbody`);
                                                                        if (!tbody) return;

                                                                        const sessions = [
                                                                            { date: '2024-12-10', time: '11:00 AM', pet: 'Max', owner: 'John P.', status: 'Scheduled' },
                                                                            { date: '2024-12-10', time: '3:00 PM', pet: 'Bella', owner: 'Amina K.', status: 'Completed' },
                                                                            { date: '2024-12-11', time: '10:00 AM', pet: 'Luna', owner: 'Sarah M.', status: 'Scheduled' }
                                                                        ];

                                                                        tbody.innerHTML = '';
                                                                        sessions.forEach(session => {
                                                                            const statusClass = session.status === 'Completed' ? 'badge-success' :
                                                                                session.status === 'Scheduled' ? 'badge-primary' : 'badge-warning';

                                                                            tbody.innerHTML += `
                    <tr>
                        <td>${session.date}</td>
                        <td>${session.time}</td>
                        <td>${session.pet}</td>
                        <td>${session.owner}</td>
                        <td><span class="badge ${statusClass}">${session.status}</span></td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn btn-sm btn-primary" onclick="startSession('${session.pet}')">
                                    <i class="fas fa-video"></i>
                                </button>
                                <button class="btn btn-sm btn-success" onclick="completeSession('${session.pet}')">
                                    <i class="fas fa-check"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="cancelSession('${session.pet}')">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
                                                                        });
                                                                    }

                                                                    // --- LOAD PRESCRIPTIONS CONTENT (VET) ---
                                                                    function loadPrescriptionsContent(role) {
                                                                        const tbody = document.getElementById(`${role}-prescriptions-tbody`);
                                                                        if (!tbody) return;

                                                                        const prescriptions = [
                                                                            { pet: 'Max', medication: 'Amoxicillin', dosage: '250mg twice daily', duration: '7 days', date: '2024-12-05' },
                                                                            { pet: 'Luna', medication: 'Ivermectin', dosage: '0.1ml/kg once', duration: '1 day', date: '2024-12-03' },
                                                                            { pet: 'Buddy', medication: 'Rimadyl', dosage: '100mg once daily', duration: '5 days', date: '2024-12-01' }
                                                                        ];

                                                                        tbody.innerHTML = '';
                                                                        prescriptions.forEach(prescription => {
                                                                            tbody.innerHTML += `
                    <tr>
                        <td>${prescription.pet}</td>
                        <td>${prescription.medication}</td>
                        <td>${prescription.dosage}</td>
                        <td>${prescription.duration}</td>
                        <td>${prescription.date}</td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn btn-sm btn-primary" onclick="editPrescription('${prescription.pet}')">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-success" onclick="printPrescription('${prescription.pet}')">
                                    <i class="fas fa-print"></i>
                                </button>
                                <button class="btn btn-sm btn-info" onclick="refillPrescription('${prescription.pet}')">
                                    <i class="fas fa-redo"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
                                                                        });
                                                                    }

                                                                    // --- LOAD USERS CONTENT (ADMIN) ---
                                                                    function loadUsersContent(role) {
                                                                        const tbody = document.getElementById(`${role}-users-tbody`);
                                                                        if (!tbody) return;

                                                                        const users = [
                                                                            { name: 'Sample Pet Owner.*owner@example.com', role: 'Pet Owner', status: 'Active' },
                                                                            { name: 'Sample Veterinarian.*vet@example.com', role: 'Veterinarian', status: 'Active' },
                                                                            { name: 'Sample User.*user@example.com', role: 'Pet Owner', status: 'Inactive' }
                                                                        ];

                                                                        tbody.innerHTML = '';
                                                                        users.forEach(user => {
                                                                            const statusClass = user.status === 'Active' ? 'badge-success' : 'badge-danger';

                                                                            tbody.innerHTML += `
                    <tr>
                        <td>${user.name}</td>
                        <td>${user.email}</td>
                        <td>${user.role}</td>
                        <td><span class="badge ${statusClass}">${user.status}</span></td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn btn-sm btn-primary" onclick="editUser('${user.email}')">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-${user.status === 'Active' ? 'warning' : 'success'}" onclick="toggleUserStatus('${user.email}')">
                                    <i class="fas fa-${user.status === 'Active' ? 'ban' : 'check'}"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteUser('${user.email}')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
                                                                        });
                                                                    }

                                                                    // --- LOAD REPORTS CONTENT (ADMIN) ---
                                                                    function loadReportsContent(role) {
                                                                        // This would typically load charts and analytics
                                                                        showToast('Reports and analytics will be displayed here', 'info');
                                                                    }

                                                                    // --- LOAD FORUM CONTENT (ADMIN) ---
                                                                    function loadForumContent(role) {
                                                                        const tbody = document.getElementById(`${role}-forum-tbody`);
                                                                        if (!tbody) return;

                                                                        const posts = [
                                                                            { author: 'John P.', content: 'Great tips for pet care!', date: '2024-12-05', status: 'Approved' },
                                                                            { author: 'Sarah M.', content: 'Looking for vet recommendations', date: '2024-12-04', status: 'Pending' },
                                                                            { author: 'Mike T.', content: 'Pet adoption success story', date: '2024-12-03', status: 'Approved' }
                                                                        ];

                                                                        tbody.innerHTML = '';
                                                                        posts.forEach(post => {
                                                                            const statusClass = post.status === 'Approved' ? 'badge-success' :
                                                                                post.status === 'Pending' ? 'badge-warning' : 'badge-danger';

                                                                            tbody.innerHTML += `
                    <tr>
                        <td>${post.author}</td>
                        <td>${post.content.substring(0, 50)}...</td>
                        <td>${post.date}</td>
                        <td><span class="badge ${statusClass}">${post.status}</span></td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn btn-sm btn-success" onclick="approvePost('${post.author}')">
                                    <i class="fas fa-check"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="rejectPost('${post.author}')">
                                    <i class="fas fa-times"></i>
                                </button>
                                <button class="btn btn-sm btn-info" onclick="viewPost('${post.author}')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
                                                                        });
                                                                    }

                                                                    // --- LOAD SETTINGS CONTENT (ADMIN) ---
                                                                    function loadSettingsContent(role) {
                                                                        // Settings are already loaded in the HTML
                                                                        showToast('System settings loaded', 'info');
                                                                    }

                                                                    // --- ACTION FUNCTIONS ---
                                                                    function editAppointment(date) {
                                                                        showToast(`Edit appointment for ${date}`, 'info');
                                                                    }

                                                                    function cancelAppointment(date) {
                                                                        showToast(`Cancel appointment for ${date}`, 'warning');
                                                                    }

                                                                    function startAppointment(date) {
                                                                        showToast(`Start appointment for ${date}`, 'info');
                                                                    }

                                                                    function completeAppointment(date) {
                                                                        showToast(`Complete appointment for ${date}`, 'success');
                                                                    }

                                                                    function editPet(name) {
                                                                        showToast(`Edit pet ${name}`, 'info');
                                                                    }

                                                                    function viewPetDetails(name) {
                                                                        showToast(`View details for ${name}`, 'info');
                                                                    }

                                                                    function deletePet(name) {
                                                                        showToast(`Delete pet ${name}`, 'warning');
                                                                    }

                                                                    function editHealthRecord(pet) {
                                                                        showToast(`Edit health record for ${pet}`, 'info');
                                                                    }

                                                                    function viewHealthRecord(pet) {
                                                                        showToast(`View health record for ${pet}`, 'info');
                                                                    }

                                                                    function deleteHealthRecord(pet) {
                                                                        showToast(`Delete health record for ${pet}`, 'warning');
                                                                    }

                                                                    function scheduleVaccination(pet) {
                                                                        showToast(`Schedule vaccination for ${pet}`, 'info');
                                                                    }

                                                                    function recordVaccination(pet) {
                                                                        showToast(`Record vaccination for ${pet}`, 'success');
                                                                    }

                                                                    function viewPayment(date) {
                                                                        showToast(`View payment for ${date}`, 'info');
                                                                    }

                                                                    function downloadReceipt(date) {
                                                                        showToast(`Download receipt for ${date}`, 'info');
                                                                    }

                                                                    function editAdoption(petName) {
                                                                        showToast(`Edit adoption for ${petName}`, 'info');
                                                                    }

                                                                    function approveAdoption(petName) {
                                                                        showToast(`Approve adoption for ${petName}`, 'success');
                                                                    }

                                                                    function removeAdoption(petName) {
                                                                        showToast(`Remove adoption for ${petName}`, 'warning');
                                                                    }

                                                                    function editReport(pet) {
                                                                        showToast(`Edit report for ${pet}`, 'info');
                                                                    }

                                                                    function markFound(pet) {
                                                                        showToast(`Mark ${pet} as found`, 'success');
                                                                    }

                                                                    function closeReport(pet) {
                                                                        showToast(`Close report for ${pet}`, 'info');
                                                                    }

                                                                    function editRecord(petName) {
                                                                        showToast(`Edit record for ${petName}`, 'info');
                                                                    }

                                                                    function viewRecord(petName) {
                                                                        showToast(`View record for ${petName}`, 'info');
                                                                    }

                                                                    function addNote(petName) {
                                                                        showToast(`Add note for ${petName}`, 'info');
                                                                    }

                                                                    function startSession(pet) {
                                                                        showToast(`Start telemedicine session for ${pet}`, 'info');
                                                                    }

                                                                    function completeSession(pet) {
                                                                        showToast(`Complete telemedicine session for ${pet}`, 'success');
                                                                    }

                                                                    function cancelSession(pet) {
                                                                        showToast(`Cancel telemedicine session for ${pet}`, 'warning');
                                                                    }

                                                                    function editPrescription(pet) {
                                                                        showToast(`Edit prescription for ${pet}`, 'info');
                                                                    }

                                                                    function printPrescription(pet) {
                                                                        showToast(`Print prescription for ${pet}`, 'info');
                                                                    }

                                                                    function refillPrescription(pet) {
                                                                        showToast(`Refill prescription for ${pet}`, 'info');
                                                                    }

                                                                    function editUser(email) {
                                                                        showToast(`Edit user ${email}`, 'info');
                                                                    }

                                                                    function toggleUserStatus(email) {
                                                                        showToast(`Toggle status for ${email}`, 'info');
                                                                    }

                                                                    function deleteUser(email) {
                                                                        showToast(`Delete user ${email}`, 'warning');
                                                                    }

                                                                    function approvePost(author) {
                                                                        showToast(`Approve post by ${author}`, 'success');
                                                                    }

                                                                    function rejectPost(author) {
                                                                        showToast(`Reject post by ${author}`, 'warning');
                                                                    }

                                                                    function viewPost(author) {
                                                                        showToast(`View post by ${author}`, 'info');
                                                                    }

                                                                    // --- UI FUNCTIONS ---
                                                                    function showToast(message, type = 'info') {
                                                                        const toast = document.getElementById('toast');
                                                                        if (toast) {
                                                                            toast.textContent = message;
                                                                            toast.className = 'toast ' + type;
                                                                            toast.classList.add('show');

                                                                            setTimeout(() => {
                                                                                toast.classList.remove('show');
                                                                            }, 3000);
                                                                        }
                                                                    }

                                                                    function showDashboardAndCloseModals(role) {
                                                                        console.log('Showing dashboard for role:', role);
                                                                        console.log('Current user object:', currentUser);

                                                                        // Hide the entire main site (header, sections, footer)
                                                                        document.querySelector('header').style.display = 'none';
                                                                        document.querySelectorAll('section').forEach(section => {
                                                                            section.style.display = 'none';
                                                                        });
                                                                        document.querySelector('footer').style.display = 'none';

                                                                        // Show the dashboard container
                                                                        const dashboard = document.getElementById('dashboard');
                                                                        if (dashboard) {
                                                                            dashboard.style.display = 'block';
                                                                            console.log('Dashboard container shown');
                                                                        } else {
                                                                            console.error('Dashboard container not found!');
                                                                        }

                                                                        // Set dashboard content based on role
                                                                        setupDashboard(role);

                                                                        // Close all modals
                                                                        document.querySelectorAll('.modal').forEach(m => m.classList.remove('active'));

                                                                        // Setup responsive features
                                                                        setTimeout(() => {
                                                                            setupResponsiveTables();
                                                                            setupResponsiveForms();
                                                                            setupResponsiveModals();
                                                                            setupResponsiveButtons();
                                                                            setupResponsiveNavigation();
                                                                            setupResponsiveStats();
                                                                            setupResponsiveSearch();
                                                                            setupResponsivePagination();
                                                                            setupTouchGestures();
                                                                        }, 100);
                                                                    }

                                                                    function setupDashboard(role) {
                                                                        console.log('Setting up dashboard for role:', role);
                                                                        const data = dashboardData[role];
                                                                        console.log('Dashboard data for role:', data);

                                                                        // Set user info
                                                                        const usernameElement = document.getElementById('dashboard-username');
                                                                        const roleElement = document.getElementById('dashboard-role');
                                                                        const avatarElement = document.getElementById('user-avatar');

                                                                        if (usernameElement) {
                                                                            usernameElement.textContent = data.username;
                                                                            console.log('Set username to:', data.username);
                                                                        } else {
                                                                            console.error('Username element not found');
                                                                        }

                                                                        if (roleElement) {
                                                                            roleElement.textContent = role.charAt(0).toUpperCase() + role.slice(1);
                                                                            console.log('Set role to:', role);
                                                                        } else {
                                                                            console.error('Role element not found');
                                                                        }

                                                                        if (avatarElement) {
                                                                            avatarElement.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(data.username)}&background=random`;
                                                                            console.log('Set avatar for:', data.username);
                                                                        } else {
                                                                            console.error('Avatar element not found');
                                                                        }

                                                                        // Set stats
                                                                        const statsContainer = document.getElementById('dashboard-stats');
                                                                        if (statsContainer) {
                                                                            statsContainer.innerHTML = '';
                                                                            console.log('Setting up stats for role:', role);

                                                                            data.stats.forEach(stat => {
                                                                                statsContainer.innerHTML += `
                        <div class="stat-card">
                            <i class="fas ${stat.icon}"></i>
                            <h3>${stat.value}</h3>
                            <p>${stat.label}</p>
                        </div>
                    `;
                                                                            });
                                                                        } else {
                                                                            console.error('Stats container not found');
                                                                        }

                                                                        // Set menu
                                                                        const menuContainer = document.getElementById('dashboard-menu');
                                                                        if (menuContainer) {
                                                                            menuContainer.innerHTML = '';
                                                                            console.log('Setting up menu for role:', role);

                                                                            data.menu.forEach((item, index) => {
                                                                                const activeClass = index === 0 ? 'active' : '';
                                                                                menuContainer.innerHTML += `
                        <li>
                            <a href="#" class="${activeClass}" data-section="${item.id}">
                                <i class="fas ${item.icon}"></i> ${item.label}
                            </a>
                        </li>
                    `;
                                                                            });

                                                                            // Add event listeners to menu
                                                                            document.querySelectorAll('#dashboard-menu a').forEach(link => {
                                                                                link.addEventListener('click', function (e) {
                                                                                    e.preventDefault();

                                                                                    // Set active menu item
                                                                                    document.querySelectorAll('#dashboard-menu a').forEach(item => {
                                                                                        item.classList.remove('active');
                                                                                    });
                                                                                    this.classList.add('active');

                                                                                    // Show corresponding section
                                                                                    const sectionId = `${role}-${this.dataset.section}`;
                                                                                    showDashboardSection(sectionId);
                                                                                });
                                                                            });
                                                                        } else {
                                                                            console.error('Menu container not found');
                                                                        }

                                                                        // Hide all dashboard sections first
                                                                        document.querySelectorAll('.dashboard-section').forEach(section => {
                                                                            section.classList.remove('active');
                                                                        });

                                                                        // Show the overview section for the current role
                                                                        const overviewSection = document.getElementById(`${role}-overview`);
                                                                        if (overviewSection) {
                                                                            overviewSection.classList.add('active');
                                                                            console.log('Activated overview section for role:', role);
                                                                        } else {
                                                                            console.error('Overview section not found for role:', role);
                                                                        }

                                                                        // Load the overview content
                                                                        loadSectionContent(`${role}-overview`);

                                                                        // Set recent activity
                                                                        const activityContainer = document.getElementById('recent-activity');
                                                                        if (activityContainer) {
                                                                            activityContainer.innerHTML = `
                    <div class="list-group">
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <span><i class="fas fa-calendar text-primary"></i> Annual checkup for Max scheduled</span>
                                <small>2 hours ago</small>
                            </div>
                        </div>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <span><i class="fas fa-paw text-success"></i> Added new pet: Luna</span>
                                <small>Yesterday</small>
                            </div>
                        </div>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <span><i class="fas fa-file-invoice-dollar text-info"></i> Payment received for appointment</span>
                                <small>3 days ago</small>
                            </div>
                        </div>
                    </div>
                `;
                                                                        }
                                                                    }

                                                                    function logout() {
                                                                        currentUser = null;
                                                                        localStorage.removeItem('petCareUserId');
                                                                        localStorage.removeItem('petCareUserRole');

                                                                        // Show main site
                                                                        document.querySelector('header').style.display = 'block';
                                                                        document.querySelectorAll('section').forEach(section => {
                                                                            section.style.display = 'block';
                                                                        });
                                                                        document.querySelector('footer').style.display = 'block';

                                                                        // Hide dashboard
                                                                        const dashboard = document.getElementById('dashboard');
                                                                        if (dashboard) {
                                                                            dashboard.style.display = 'none';
                                                                        }

                                                                        showToast('Logged out successfully', 'success');
                                                                    }

                                                                    function handleDeleteAccount() {
                                                                        const email = document.getElementById('delete-account-email').value;
                                                                        const confirmText = document.getElementById('delete-account-confirm').value;
                                                                        const reason = document.getElementById('delete-account-reason').value;
                                                                        const otherReason = document.getElementById('delete-account-other-text').value;
                                                                        const understand = document.getElementById('delete-account-understand').checked;

                                                                        // Validation
                                                                        if (!email || !confirmText || !understand) {
                                                                            showToast('Please fill in all required fields', 'error');
                                                                            return;
                                                                        }

                                                                        if (confirmText !== 'DELETE') {
                                                                            showToast('Please type DELETE exactly as shown', 'error');
                                                                            return;
                                                                        }

                                                                        if (email !== currentUser.email) {
                                                                            showToast('Email does not match your account email', 'error');
                                                                            return;
                                                                        }

                                                                        // Show loading state
                                                                        const deleteBtnText = document.getElementById('delete-account-btn-text');
                                                                        deleteBtnText.textContent = 'Deleting Account...';

                                                                        // Call API to delete account
                                                                        const formData = new FormData();
                                                                        formData.append('action', 'delete_account');
                                                                        formData.append('user_id', currentUser.id);
                                                                        formData.append('email', email);
                                                                        formData.append('reason', reason);
                                                                        formData.append('other_reason', otherReason);

                                                                        fetch('backend/api.php', {
                                                                            method: 'POST',
                                                                            body: formData
                                                                        })
                                                                            .then(response => response.json())
                                                                            .then(data => {
                                                                                if (data.success) {
                                                                                    // Clear user data
                                                                                    currentUser = null;
                                                                                    localStorage.removeItem('petCareUserId');
                                                                                    localStorage.removeItem('petCareUserRole');

                                                                                    // Close modal
                                                                                    closeModal('delete-account-modal');

                                                                                    // Show main site
                                                                                    document.querySelector('header').style.display = 'block';
                                                                                    document.querySelectorAll('section').forEach(section => {
                                                                                        section.style.display = 'block';
                                                                                    });
                                                                                    document.querySelector('footer').style.display = 'block';

                                                                                    // Hide dashboard
                                                                                    document.getElementById('dashboard').style.display = 'none';

                                                                                    // Show success message
                                                                                    showToast('Your account has been permanently deleted. We\'re sorry to see you go!', 'success');

                                                                                    // Log deletion for analytics
                                                                                    console.log('Account deleted:', {
                                                                                        email: email,
                                                                                        reason: reason,
                                                                                        otherReason: otherReason,
                                                                                        timestamp: new Date().toISOString()
                                                                                    });
                                                                                } else {
                                                                                    showToast(data.error || 'Failed to delete account', 'error');
                                                                                }
                                                                            })
                                                                            .catch(error => {
                                                                                console.error('Error deleting account:', error);
                                                                                showToast('Network error. Please try again.', 'error');
                                                                            })
                                                                            .finally(() => {
                                                                                // Reset button text
                                                                                deleteBtnText.textContent = 'Delete My Account';
                                                                            });
                                                                    }

                                                                    // --- AUTH FUNCTIONS ---
                                                                    function login(email, password) {
                                                                        console.log('Attempting login for:', email);
                                                                        return new Promise((resolve, reject) => {
                                                                            const formData = new FormData();
                                                                            formData.append('action', 'login');
                                                                            formData.append('email', email);
                                                                            formData.append('password', password);

                                                                            fetch('backend/api.php', {
                                                                                method: 'POST',
                                                                                body: formData
                                                                            })
                                                                                .then(response => response.json())
                                                                                .then(data => {
                                                                                    console.log('Login response:', data);
                                                                                    if (data.success) {
                                                                                        console.log('Login successful, user data:', data.data);
                                                                                        resolve({
                                                                                            success: true,
                                                                                            user: data.data
                                                                                        });
                                                                                    } else {
                                                                                        console.log('Login failed:', data.message);
                                                                                        resolve({
                                                                                            success: false,
                                                                                            error: data.message || 'Login failed'
                                                                                        });
                                                                                    }
                                                                                })
                                                                                .catch(error => {
                                                                                    console.error('Login error:', error);
                                                                                    resolve({
                                                                                        success: false,
                                                                                        error: 'Network error. Please try again.'
                                                                                    });
                                                                                });
                                                                        });
                                                                    }

                                                                    function register(name, email, password, role) {
                                                                        console.log('Attempting registration for:', email, 'as', role);
                                                                        return new Promise((resolve, reject) => {
                                                                            const formData = new FormData();
                                                                            formData.append('action', 'register');
                                                                            formData.append('name', name);
                                                                            formData.append('email', email);
                                                                            formData.append('password', password);
                                                                            formData.append('role', role);

                                                                            fetch('backend/api.php', {
                                                                                method: 'POST',
                                                                                body: formData
                                                                            })
                                                                                .then(response => response.json())
                                                                                .then(data => {
                                                                                    console.log('Registration response:', data);
                                                                                    if (data.success) {
                                                                                        console.log('Registration successful, user data:', data.data);
                                                                                        resolve({
                                                                                            success: true,
                                                                                            user: data.data
                                                                                        });
                                                                                    } else {
                                                                                        console.log('Registration failed:', data.message);
                                                                                        resolve({
                                                                                            success: false,
                                                                                            error: data.message || 'Registration failed'
                                                                                        });
                                                                                    }
                                                                                })
                                                                                .catch(error => {
                                                                                    console.error('Registration error:', error);
                                                                                    resolve({
                                                                                        success: false,
                                                                                        error: 'Network error. Please try again.'
                                                                                    });
                                                                                });
                                                                        });
                                                                    }

                                                                    function forgotPassword(email) {
                                                                        console.log('Attempting password reset for:', email);
                                                                        return new Promise((resolve, reject) => {
                                                                            const formData = new FormData();
                                                                            formData.append('action', 'forgot_password');
                                                                            formData.append('email', email);

                                                                            fetch('backend/api.php', {
                                                                                method: 'POST',
                                                                                body: formData
                                                                            })
                                                                                .then(response => response.json())
                                                                                .then(data => {
                                                                                    console.log('Forgot password response:', data);
                                                                                    if (data.success) {
                                                                                        console.log('Password reset request successful');
                                                                                        resolve({
                                                                                            success: true,
                                                                                            message: data.message
                                                                                        });
                                                                                    } else {
                                                                                        console.log('Password reset request failed:', data.message);
                                                                                        resolve({
                                                                                            success: false,
                                                                                            error: data.message || 'Password reset request failed'
                                                                                        });
                                                                                    }
                                                                                })
                                                                                .catch(error => {
                                                                                    console.error('Forgot password error:', error);
                                                                                    resolve({
                                                                                        success: false,
                                                                                        error: 'Network error. Please try again.'
                                                                                    });
                                                                                });
                                                                        });
                                                                    }

                                                                    // --- WINDOW RESIZE HANDLER ---
                                                                    function handleResize() {
                                                                        setupResponsiveTables();
                                                                        setupResponsiveForms();
                                                                        setupResponsiveModals();
                                                                        setupResponsiveButtons();
                                                                        setupResponsiveStats();
                                                                        setupResponsiveSearch();
                                                                        setupResponsivePagination();
                                                                    }

                                                                    // --- EVENT LISTENERS ---
                                                                    document.addEventListener('DOMContentLoaded', function () {
                                                                        // Smooth scrolling for navigation links
                                                                        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                                                                            anchor.addEventListener('click', function (e) {
                                                                                e.preventDefault();
                                                                                const target = this.getAttribute('href');
                                                                                if (target === '#') return;

                                                                                smoothScrollTo(target);

                                                                                // Update active navigation
                                                                                document.querySelectorAll('.nav-links a').forEach(link => {
                                                                                    link.classList.remove('active');
                                                                                });
                                                                                this.classList.add('active');
                                                                            });
                                                                        });

                                                                        // Login Modal Logic
                                                                        const loginBtn = document.getElementById('login-btn');
                                                                        const mobileLoginBtn = document.getElementById('mobile-login-btn');
                                                                        const heroBookBtn = document.getElementById('hero-book-appointment-btn');
                                                                        const ctaBookBtn = document.getElementById('cta-book-btn');

                                                                        if (loginBtn) {
                                                                            loginBtn.addEventListener('click', function (e) {
                                                                                e.preventDefault();
                                                                                openModal('login-modal');
                                                                            });
                                                                        }

                                                                        if (mobileLoginBtn) {
                                                                            mobileLoginBtn.addEventListener('click', function (e) {
                                                                                e.preventDefault();
                                                                                openModal('login-modal');
                                                                            });
                                                                        }

                                                                        if (heroBookBtn) {
                                                                            heroBookBtn.addEventListener('click', function (e) {
                                                                                e.preventDefault();
                                                                                bookService();
                                                                            });
                                                                        }

                                                                        if (ctaBookBtn) {
                                                                            ctaBookBtn.addEventListener('click', function (e) {
                                                                                e.preventDefault();
                                                                                bookService();
                                                                            });
                                                                        }

                                                                        // Tab switching logic for login/register
                                                                        const loginTabBtn = document.querySelector('.tab[data-tab="login"]');
                                                                        const registerTabBtn = document.querySelector('.tab[data-tab="register"]');
                                                                        const loginTab = document.getElementById('login-tab');
                                                                        const registerTab = document.getElementById('register-tab');

                                                                        if (loginTabBtn && registerTabBtn && loginTab && registerTab) {
                                                                            loginTabBtn.addEventListener('click', function (e) {
                                                                                e.preventDefault();
                                                                                loginTabBtn.classList.add('active');
                                                                                registerTabBtn.classList.remove('active');
                                                                                loginTab.classList.add('active');
                                                                                registerTab.classList.remove('active');
                                                                                document.getElementById('login-email').focus();
                                                                            });

                                                                            registerTabBtn.addEventListener('click', function (e) {
                                                                                e.preventDefault();
                                                                                registerTabBtn.classList.add('active');
                                                                                loginTabBtn.classList.remove('active');
                                                                                registerTab.classList.add('active');
                                                                                loginTab.classList.remove('active');
                                                                                document.getElementById('register-name').focus();
                                                                            });
                                                                        }

                                                                        // Register link below login form
                                                                        const registerLink = document.getElementById('register-link');
                                                                        if (registerLink && registerTabBtn) {
                                                                            registerLink.addEventListener('click', function (e) {
                                                                                e.preventDefault();
                                                                                registerTabBtn.click();
                                                                            });
                                                                        }

                                                                        // Forgot password link
                                                                        const forgotPasswordLink = document.getElementById('forgot-password-link');
                                                                        if (forgotPasswordLink) {
                                                                            forgotPasswordLink.addEventListener('click', function (e) {
                                                                                e.preventDefault();
                                                                                closeModal('login-modal');
                                                                                openModal('forgot-password-modal');
                                                                            });
                                                                        }

                                                                        // Show login from forgot password
                                                                        const showLoginFromForgot = document.getElementById('show-login-from-forgot');
                                                                        if (showLoginFromForgot) {
                                                                            showLoginFromForgot.addEventListener('click', function (e) {
                                                                                e.preventDefault();
                                                                                closeModal('forgot-password-modal');
                                                                                openModal('login-modal');
                                                                            });
                                                                        }

                                                                        // Show register from login
                                                                        const showRegister = document.getElementById('show-register');
                                                                        if (showRegister) {
                                                                            showRegister.addEventListener('click', function (e) {
                                                                                e.preventDefault();
                                                                                closeModal('login-modal');
                                                                                openModal('register-modal');
                                                                            });
                                                                        }

                                                                        // Show login from register
                                                                        const showLogin = document.getElementById('show-login');
                                                                        if (showLogin) {
                                                                            showLogin.addEventListener('click', function (e) {
                                                                                e.preventDefault();
                                                                                closeModal('register-modal');
                                                                                openModal('login-modal');
                                                                            });
                                                                        }

                                                                        // Modal close logic
                                                                        document.querySelectorAll('.modal .close-btn').forEach(btn => {
                                                                            btn.addEventListener('click', function () {
                                                                                const modal = btn.closest('.modal');
                                                                                if (modal) {
                                                                                    closeModal(modal.id);
                                                                                }
                                                                            });
                                                                        });

                                                                        // Close modal when clicking outside
                                                                        document.querySelectorAll('.modal').forEach(modal => {
                                                                            modal.addEventListener('click', function (e) {
                                                                                if (e.target === this) {
                                                                                    closeModal(this.id);
                                                                                }
                                                                            });
                                                                        });

                                                                        // Login Form Submission
                                                                        const loginForm = document.getElementById('login-form');
                                                                        if (loginForm) {
                                                                            loginForm.addEventListener('submit', function (e) {
                                                                                e.preventDefault();
                                                                                const email = document.getElementById('login-email').value;
                                                                                const password = document.getElementById('login-password').value;

                                                                                console.log('Login form submitted for:', email);

                                                                                // Show loading state
                                                                                const loginBtnText = document.getElementById('login-btn-text');
                                                                                loginBtnText.textContent = 'Logging in...';

                                                                                login(email, password).then(res => {
                                                                                    console.log('Login result:', res);
                                                                                    if (res.success) {
                                                                                        currentUser = res.user;
                                                                                        console.log('Setting currentUser to:', currentUser);
                                                                                        localStorage.setItem('petCareUserId', res.user.id);
                                                                                        localStorage.setItem('petCareUserRole', res.user.role);
                                                                                        showToast('Login successful!', 'success');
                                                                                        console.log('Calling showDashboardAndCloseModals with role:', res.user.role);
                                                                                        showDashboardAndCloseModals(res.user.role);
                                                                                    } else {
                                                                                        showToast(res.error || 'Login failed', 'error');
                                                                                    }
                                                                                    loginBtnText.textContent = 'Login';
                                                                                });
                                                                            });
                                                                        }

                                                                        // Registration Form Submission
                                                                        const registerForm = document.getElementById('register-form');
                                                                        if (registerForm) {
                                                                            registerForm.addEventListener('submit', function (e) {
                                                                                e.preventDefault();
                                                                                const name = document.getElementById('register-name').value;
                                                                                const email = document.getElementById('register-email').value;
                                                                                const password = document.getElementById('register-password').value;
                                                                                const confirmPassword = document.getElementById('register-confirm-password').value;
                                                                                const role = document.getElementById('registerRole').value;

                                                                                console.log('Registration form submitted for:', email, 'as', role);

                                                                                if (password !== confirmPassword) {
                                                                                    showToast('Passwords do not match', 'error');
                                                                                    return;
                                                                                }

                                                                                // Show loading state
                                                                                const registerBtnText = document.getElementById('register-btn-text');
                                                                                registerBtnText.textContent = 'Registering...';

                                                                                register(name, email, password, role).then(res => {
                                                                                    console.log('Registration result:', res);
                                                                                    if (res.success) {
                                                                                        currentUser = res.user;
                                                                                        console.log('Setting currentUser to:', currentUser);
                                                                                        localStorage.setItem('petCareUserId', res.user.id);
                                                                                        localStorage.setItem('petCareUserRole', res.user.role);
                                                                                        showToast('Registration successful!', 'success');
                                                                                        console.log('Calling showDashboardAndCloseModals with role:', res.user.role);
                                                                                        showDashboardAndCloseModals(res.user.role);
                                                                                    } else {
                                                                                        showToast(res.error || 'Registration failed', 'error');
                                                                                    }
                                                                                    registerBtnText.textContent = 'Register';
                                                                                });
                                                                            });
                                                                        }

                                                                        // Forgot Password Form Submission
                                                                        const forgotPasswordForm = document.getElementById('forgot-password-form');
                                                                        if (forgotPasswordForm) {
                                                                            forgotPasswordForm.addEventListener('submit', function (e) {
                                                                                e.preventDefault();
                                                                                const email = document.getElementById('forgot-email').value;

                                                                                console.log('Forgot password form submitted for:', email);

                                                                                // Show loading state
                                                                                const resetBtn = this.querySelector('button[type="submit"]');
                                                                                const originalText = resetBtn.textContent;
                                                                                resetBtn.textContent = 'Sending...';
                                                                                resetBtn.disabled = true;

                                                                                forgotPassword(email).then(res => {
                                                                                    console.log('Forgot password result:', res);
                                                                                    if (res.success) {
                                                                                        showToast(res.message || 'Password reset instructions sent!', 'success');
                                                                                        closeModal('forgot-password-modal');
                                                                                        openModal('login-modal');
                                                                                    } else {
                                                                                        showToast(res.error || 'Failed to send reset instructions', 'error');
                                                                                    }
                                                                                    resetBtn.textContent = originalText;
                                                                                    resetBtn.disabled = false;
                                                                                });
                                                                            });
                                                                        }

                                                                        // Appointment Form Submission
                                                                        const appointmentForm = document.getElementById('appointment-form');
                                                                        if (appointmentForm) {
                                                                            appointmentForm.addEventListener('submit', function (e) {
                                                                                e.preventDefault();

                                                                                const formData = {
                                                                                    service: document.getElementById('appointment-service').value,
                                                                                    petName: document.getElementById('appointment-pet-name').value,
                                                                                    petType: document.getElementById('appointment-pet-type').value,
                                                                                    date: document.getElementById('appointment-date').value,
                                                                                    time: document.getElementById('appointment-time').value,
                                                                                    notes: document.getElementById('appointment-notes').value
                                                                                };

                                                                                // Show loading state
                                                                                const appointmentBtnText = document.getElementById('appointment-btn-text');
                                                                                appointmentBtnText.textContent = 'Booking...';

                                                                                // Simulate booking process
                                                                                setTimeout(() => {
                                                                                    handleAppointmentBooking(formData);
                                                                                    appointmentBtnText.textContent = 'Book Appointment';
                                                                                }, 1500);
                                                                            });
                                                                        }

                                                                        // Logout button
                                                                        const logoutBtn = document.getElementById('logout-btn');
                                                                        if (logoutBtn) {
                                                                            logoutBtn.addEventListener('click', logout);
                                                                        }

                                                                        // Delete account button
                                                                        const deleteAccountBtn = document.getElementById('delete-account-btn');
                                                                        if (deleteAccountBtn) {
                                                                            deleteAccountBtn.addEventListener('click', function () {
                                                                                openModal('delete-account-modal');
                                                                            });
                                                                        }

                                                                        // Delete account form submission
                                                                        const deleteAccountForm = document.getElementById('delete-account-form');
                                                                        if (deleteAccountForm) {
                                                                            deleteAccountForm.addEventListener('submit', function (e) {
                                                                                e.preventDefault();
                                                                                handleDeleteAccount();
                                                                            });
                                                                        }

                                                                        // Delete account reason change handler
                                                                        const deleteAccountReason = document.getElementById('delete-account-reason');
                                                                        const deleteAccountOtherReason = document.getElementById('delete-account-other-reason');
                                                                        if (deleteAccountReason) {
                                                                            deleteAccountReason.addEventListener('change', function () {
                                                                                if (this.value === 'other') {
                                                                                    deleteAccountOtherReason.style.display = 'block';
                                                                                } else {
                                                                                    deleteAccountOtherReason.style.display = 'none';
                                                                                }
                                                                            });
                                                                        }

                                                                        // Mobile menu toggle
                                                                        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
                                                                        const mobileMenu = document.getElementById('mobile-menu');
                                                                        if (mobileMenuBtn && mobileMenu) {
                                                                            mobileMenuBtn.addEventListener('click', function () {
                                                                                mobileMenu.classList.toggle('active');
                                                                            });
                                                                        }

                                                                        // Window resize handler
                                                                        window.addEventListener('resize', handleResize);

                                                                        // Check if user is already logged in
                                                                        const loggedInUserId = localStorage.getItem('petCareUserId');
                                                                        const userRole = localStorage.getItem('petCareUserRole');
                                                                        if (loggedInUserId && userRole) {
                                                                            // Simulate getting user data
                                                                            currentUser = {
                                                                                id: loggedInUserId,
                                                                                role: userRole,
                                                                                name: userRole === "owner" ? "John Peterson" :
                                                                                    userRole === "vet" ? "Dr. Sarah Smith" : "Admin User"
                                                                            };
                                                                            showDashboardAndCloseModals(userRole);
                                                                        }

                                                                        // Setup responsive features on load
                                                                        setupResponsiveTables();
                                                                        setupResponsiveForms();
                                                                        setupResponsiveModals();
                                                                        setupResponsiveButtons();
                                                                        setupResponsiveNavigation();
                                                                        setupResponsiveStats();
                                                                        setupResponsiveSearch();
                                                                        setupResponsivePagination();
                                                                        setupTouchGestures();
                                                                    });

                                                                    // --- DASHBOARD MODAL FUNCTIONS ---
                                                                    function openAddPetModal() {
                                                                        // Reset form
                                                                        document.getElementById('add-pet-form').reset();
                                                                        openModal('add-pet-modal');
                                                                    }

                                                                    function openAddAppointmentModal() {
                                                                        showToast('Add Appointment modal will open', 'info');
                                                                    }

                                                                    function openAddHealthRecordModal() {
                                                                        showToast('Add Health Record modal will open', 'info');
                                                                    }

                                                                    function openAddVaccinationModal() {
                                                                        showToast('Add Vaccination modal will open', 'info');
                                                                    }

                                                                    function openPaymentModal() {
                                                                        // Reset form
                                                                        document.getElementById('payment-form').reset();
                                                                        openModal('payment-modal');
                                                                    }

                                                                    function openAddAdoptionModal() {
                                                                        showToast('Add Adoption modal will open', 'info');
                                                                    }

                                                                    function openVetAppointmentModal() {
                                                                        showToast('Vet Appointment modal will open', 'info');
                                                                    }

                                                                    function openAddPatientRecordModal() {
                                                                        showToast('Add Patient Record modal will open', 'info');
                                                                    }

                                                                    function openTelemedicineModal() {
                                                                        showToast('Telemedicine modal will open', 'info');
                                                                    }

                                                                    function openPrescriptionModal() {
                                                                        showToast('Prescription modal will open', 'info');
                                                                    }

                                                                    function openVetAdoptionModal() {
                                                                        showToast('Vet Adoption modal will open', 'info');
                                                                    }

                                                                    function openLostFoundModal() {
                                                                        showToast('Lost & Found modal will open', 'info');
                                                                    }

                                                                    function openAddUserModal() {
                                                                        showToast('Add User modal will open', 'info');
                                                                    }

                                                                    function openForumPostModal() {
                                                                        showToast('Forum Post modal will open', 'info');
                                                                    }

                                                                    function generateReport() {
                                                                        showToast('Report generation will be implemented', 'info');
                                                                    }

                                                                    function saveSettings() {
                                                                        showToast('Settings saved successfully!', 'success');
                                                                    }

                                                                    // --- WINDOW RESIZE HANDLER ---

                                                                    // Add payment form submission handler
                                                                    document.addEventListener('DOMContentLoaded', function () {
                                                                        const paymentForm = document.getElementById('payment-form');
                                                                        if (paymentForm) {
                                                                            paymentForm.addEventListener('submit', function (e) {
                                                                                e.preventDefault();

                                                                                const formData = new FormData(this);
                                                                                formData.append('action', 'add_payment');
                                                                                formData.append('user', currentUser ? currentUser.name : 'Unknown User');

                                                                                // Show loading state
                                                                                const submitBtn = this.querySelector('button[type="submit"]');
                                                                                const originalText = submitBtn.innerHTML;
                                                                                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                                                                                submitBtn.disabled = true;

                                                                                fetch('backend/api.php', {
                                                                                    method: 'POST',
                                                                                    body: formData
                                                                                })
                                                                                    .then(response => response.json())
                                                                                    .then(data => {
                                                                                        if (data.success) {
                                                                                            showToast('Payment processed successfully!', 'success');
                                                                                            closeModal('payment-modal');
                                                                                            // Refresh payments list
                                                                                            if (currentUser) {
                                                                                                loadPaymentsContent(currentUser.role);
                                                                                            }
                                                                                        } else {
                                                                                            showToast(data.message || 'Payment failed', 'error');
                                                                                        }
                                                                                    })
                                                                                    .catch(error => {
                                                                                        console.error('Payment error:', error);
                                                                                        showToast('Payment processing failed. Please try again.', 'error');
                                                                                    })
                                                                                    .finally(() => {
                                                                                        submitBtn.innerHTML = originalText;
                                                                                        submitBtn.disabled = false;
                                                                                    });
                                                                            });
                                                                        }
                                                                    });

                                                                    // --- EVENT LISTENERS FOR CLEAN HTML ---
                                                                    document.addEventListener('DOMContentLoaded', function() {
                                                                        // Service booking buttons
                                                                        const serviceButtons = document.querySelectorAll('[data-service]');
                                                                        serviceButtons.forEach(button => {
                                                                            button.addEventListener('click', function() {
                                                                                const service = this.getAttribute('data-service');
                                                                                const price = this.getAttribute('data-price');
                                                                                bookService(service, price);
                                                                            });
                                                                        });

                                                                        // Quick action buttons
                                                                        const quickActionButtons = document.querySelectorAll('.quick-action-btn');
                                                                        quickActionButtons.forEach(button => {
                                                                            button.addEventListener('click', function() {
                                                                                const action = this.getAttribute('data-action');
                                                                                handleQuickAction(action);
                                                                            });
                                                                        });

                                                                        // Service links in contact section
                                                                        const serviceLinks = document.querySelectorAll('a[data-service]');
                                                                        serviceLinks.forEach(link => {
                                                                            link.addEventListener('click', function(e) {
                                                                                e.preventDefault();
                                                                                const service = this.getAttribute('data-service');
                                                                                scrollToService(service);
                                                                            });
                                                                        });

                                                                        // Login and register buttons
                                                                        const loginBtn = document.getElementById('login-btn');
                                                                        const mobileLoginBtn = document.getElementById('mobile-login-btn');
                                                                        const heroBookBtn = document.getElementById('hero-book-appointment-btn');

                                                                        if (loginBtn) {
                                                                            loginBtn.addEventListener('click', function(e) {
                                                                                e.preventDefault();
                                                                                openModal('login-modal');
                                                                            });
                                                                        }

                                                                        if (mobileLoginBtn) {
                                                                            mobileLoginBtn.addEventListener('click', function(e) {
                                                                                e.preventDefault();
                                                                                openModal('login-modal');
                                                                            });
                                                                        }

                                                                        if (heroBookBtn) {
                                                                            heroBookBtn.addEventListener('click', function(e) {
                                                                                e.preventDefault();
                                                                                openModal('appointment-modal');
                                                                            });
                                                                        }

                                                                        // Modal close buttons
                                                                        const closeButtons = document.querySelectorAll('.close-btn, .btn-secondary');
                                                                        closeButtons.forEach(button => {
                                                                            button.addEventListener('click', function() {
                                                                                const modal = this.closest('.modal');
                                                                                if (modal) {
                                                                                    closeModal(modal.id);
                                                                                }
                                                                            });
                                                                        });

                                                                        // Modal overlay clicks
                                                                        const modalOverlays = document.querySelectorAll('.modal-overlay');
                                                                        modalOverlays.forEach(overlay => {
                                                                            overlay.addEventListener('click', function() {
                                                                                const modal = this.closest('.modal');
                                                                                if (modal) {
                                                                                    closeModal(modal.id);
                                                                                }
                                                                            });
                                                                        });

                                                                        // Form submissions
                                                                        const appointmentForm = document.getElementById('appointment-form');
                                                                        if (appointmentForm) {
                                                                            appointmentForm.addEventListener('submit', function(e) {
                                                                                e.preventDefault();
                                                                                const formData = new FormData(this);
                                                                                handleAppointmentBooking(formData);
                                                                            });
                                                                        }

                                                                        const loginForm = document.getElementById('login-form');
                                                                        if (loginForm) {
                                                                            loginForm.addEventListener('submit', function(e) {
                                                                                e.preventDefault();
                                                                                const email = document.getElementById('login-email').value;
                                                                                const password = document.getElementById('login-password').value;
                                                                                login(email, password);
                                                                            });
                                                                        }

                                                                        const registerForm = document.getElementById('register-form');
                                                                        if (registerForm) {
                                                                            registerForm.addEventListener('submit', function(e) {
                                                                                e.preventDefault();
                                                                                const name = document.getElementById('register-name').value;
                                                                                const email = document.getElementById('register-email').value;
                                                                                const password = document.getElementById('register-password').value;
                                                                                const role = document.getElementById('register-role').value;
                                                                                register(name, email, password, role);
                                                                            });
                                                                        }

                                                                        // Modal switching
                                                                        const showRegisterLink = document.getElementById('show-register');
                                                                        const showLoginLink = document.getElementById('show-login');

                                                                        if (showRegisterLink) {
                                                                            showRegisterLink.addEventListener('click', function(e) {
                                                                                e.preventDefault();
                                                                                closeModal('login-modal');
                                                                                openModal('register-modal');
                                                                            });
                                                                        }

                                                                        if (showLoginLink) {
                                                                            showLoginLink.addEventListener('click', function(e) {
                                                                                e.preventDefault();
                                                                                closeModal('register-modal');
                                                                                openModal('login-modal');
                                                                            });
                                                                        }

                                                                        // Password toggle buttons
                                                                        const passwordToggleButtons = document.querySelectorAll('.password-toggle-btn');
                                                                        passwordToggleButtons.forEach(button => {
                                                                            button.addEventListener('click', function() {
                                                                                const input = this.previousElementSibling;
                                                                                const icon = this.querySelector('i');
                                                                                
                                                                                if (input.type === 'password') {
                                                                                    input.type = 'text';
                                                                                    icon.classList.remove('fa-eye');
                                                                                    icon.classList.add('fa-eye-slash');
                                                                                } else {
                                                                                    input.type = 'password';
                                                                                    icon.classList.remove('fa-eye-slash');
                                                                                    icon.classList.add('fa-eye');
                                                                                }
                                                                            });
                                                                        });

                                                                        // FAB (Floating Action Button)
                                                                        const fab = document.getElementById('fab');
                                                                        if (fab) {
                                                                            fab.addEventListener('click', function() {
                                                                                openModal('quick-actions-modal');
                                                                            });
                                                                        }

                                                                        // Navigation links
                                                                        const navLinks = document.querySelectorAll('.nav-links a[href^="#"]');
                                                                        navLinks.forEach(link => {
                                                                            link.addEventListener('click', function(e) {
                                                                                e.preventDefault();
                                                                                const target = this.getAttribute('href');
                                                                                smoothScrollTo(target);
                                                                                
                                                                                // Update active state
                                                                                navLinks.forEach(l => l.classList.remove('active'));
                                                                                this.classList.add('active');
                                                                            });
                                                                        });

                                                                        // Mobile menu toggle
                                                                        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
                                                                        const mobileMenu = document.getElementById('mobile-menu');
                                                                        if (mobileMenuBtn && mobileMenu) {
                                                                            mobileMenuBtn.addEventListener('click', function() {
                                                                                mobileMenu.classList.toggle('active');
                                                                            });
                                                                        }
                                                                    });

                                                                    // --- QUICK ACTION HANDLER ---
                                                                    function handleQuickAction(action) {
                                                                        closeModal('quick-actions-modal');
                                                                        
                                                                        switch(action) {
                                                                            case 'add-pet':
                                                                                showToast('Add Pet functionality will be implemented', 'info');
                                                                                break;
                                                                            case 'add-appointment':
                                                                                openModal('appointment-modal');
                                                                                break;
                                                                            case 'add-health-record':
                                                                                showToast('Add Health Record functionality will be implemented', 'info');
                                                                                break;
                                                                            case 'add-vaccination':
                                                                                showToast('Add Vaccination functionality will be implemented', 'info');
                                                                                break;
                                                                            case 'payment':
                                                                                showToast('Payment functionality will be implemented', 'info');
                                                                                break;
                                                                            case 'adoption':
                                                                                showToast('Adoption functionality will be implemented', 'info');
                                                                                break;
                                                                            default:
                                                                                showToast('Action not implemented yet', 'warning');
                                                                        }
                                                                    }

                                                                    // Utility to fetch CSRF token
                                                                    async function getCSRFToken() {
                                                                        const res = await fetch('backend/api.php', {
                                                                            method: 'POST',
                                                                            headers: { 'Content-Type': 'application/json' },
                                                                            body: JSON.stringify({ action: 'get_csrf_token' })
                                                                        });
                                                                        const data = await res.json();
                                                                        return data.token;
                                                                    }

                                                                    // Helper to add CSRF token to FormData or URLSearchParams
                                                                    async function addCSRFTokenToBody(body) {
                                                                        const token = await getCSRFToken();
                                                                        if (body instanceof FormData || body instanceof URLSearchParams) {
                                                                            body.append('csrf_token', token);
                                                                            return body;
                                                                        } else if (typeof body === 'object') {
                                                                            body.csrf_token = token;
                                                                            return JSON.stringify(body);
                                                                        }
                                                                        return body;
                                                                    }

                                                                    // Example usage for a fetch call:
                                                                    // const body = new FormData();
                                                                    // ...
                                                                    // const response = await fetch('backend/api.php', { method: 'POST', body: await addCSRFTokenToBody(body) });
                                                                    // ...

