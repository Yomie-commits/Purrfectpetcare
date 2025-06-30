// Role-Based Access Control Updates for Pet Care Management System
// These functions should replace the existing ones in index.html

// Updated showDashboardSection function with strict role enforcement
function showDashboardSection(sectionId) {
    // Hide all sections
    document.querySelectorAll('.dashboard-section').forEach(section => {
        section.classList.remove('active');
    });

    // Only allow access to sections in the current user's menu
    const allowedSections = dashboardData[currentUser.role].menu.map(item => `${currentUser.role}-${item.id}`);
    if (!allowedSections.includes(sectionId)) {
        showToast('Access denied: You do not have permission to view this section.', 'error');
        return;
    }

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

// Updated menu event listener code for setupDashboard function
// Replace the existing menu event listener code in setupDashboard with this:
/*
// Add event listeners to menu
document.querySelectorAll('#dashboard-menu a').forEach(link => {
    link.addEventListener('click', function (e) {
        e.preventDefault();

        // Only allow access to allowed sections
        const allowed = dashboardData[role].menu.map(item => item.id);
        if (!allowed.includes(this.dataset.section)) {
            showToast('Access denied: You do not have permission to view this section.', 'error');
            return;
        }

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
*/

// Backend role enforcement function (to be added to api.php)
/*
// Add this check at the beginning of sensitive API endpoints:
function checkUserRole($requiredRole, $userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || $user['role'] !== $requiredRole) {
        throw new Exception('Unauthorized access: Insufficient permissions');
    }
    
    return true;
}

// Usage example in API endpoints:
case 'get_users':
    checkUserRole('admin', $_POST['user_id'] ?? '');
    // ... rest of the endpoint code
*/ 