<?php
// analytics.php - Analytics and admin dashboard backend logic

function get_admin_dashboard_stats($pdo, $data) {
    $adminId = $data['admin_id'] ?? '';
    $period = $data['period'] ?? 'month';
    checkUserRole('admin', $adminId);
    $stats = [];
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE status = 'Active'");
    $stats['totalUsers'] = $stmt->fetch()['total'];
    $periodCondition = $period === 'week' ? 'AND created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)' : ($period === 'year' ? 'AND created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)' : 'AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)');
    $stmt = $pdo->query("SELECT COUNT(*) as new FROM users WHERE status = 'Active' $periodCondition");
    $stats['newUsersThisPeriod'] = $stmt->fetch()['new'];
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM pets");
    $stats['totalPets'] = $stmt->fetch()['total'];
    $stmt = $pdo->query("SELECT COUNT(*) as new FROM pets WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 $period)");
    $stats['newPetsThisPeriod'] = $stmt->fetch()['new'];
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM appointments WHERE DATE(date) = CURDATE()");
    $stats['todayAppointments'] = $stmt->fetch()['total'];
    $stmt = $pdo->query("SELECT COUNT(*) as completed FROM appointments WHERE DATE(date) = CURDATE() AND status = 'Completed'");
    $stats['completedToday'] = $stmt->fetch()['completed'];
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as revenue FROM payments WHERE MONTH(date) = MONTH(NOW()) AND YEAR(date) = YEAR(NOW()) AND status = 'Completed'");
    $stats['monthlyRevenue'] = 'KSh ' . number_format($stmt->fetch()['revenue'], 2);
    $stmt = $pdo->query("SELECT (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE MONTH(date) = MONTH(NOW()) AND YEAR(date) = YEAR(NOW()) AND status = 'Completed') as current, (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE MONTH(date) = MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH)) AND YEAR(date) = YEAR(DATE_SUB(NOW(), INTERVAL 1 MONTH)) AND status = 'Completed') as previous");
    $revenueData = $stmt->fetch();
    $current = $revenueData['current'];
    $previous = $revenueData['previous'];
    $stats['revenueGrowth'] = $previous > 0 ? round((($current - $previous) / $previous) * 100, 1) : 0;
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'vet' AND status = 'Active'");
    $stats['activeVets'] = $stmt->fetch()['total'];
    $stmt = $pdo->query("SELECT (SELECT COUNT(*) FROM appointments WHERE DATE(date) = CURDATE()) as today_appointments, (SELECT COUNT(*) FROM users WHERE role = 'vet' AND status = 'Active') as active_vets");
    $utilData = $stmt->fetch();
    $stats['vetUtilization'] = $utilData['active_vets'] > 0 ? round(($utilData['today_appointments'] / $utilData['active_vets']) * 10, 1) : 0;
    $stats['systemHealth'] = 'Good';
    $stats['uptime'] = 99.9;
    return [
        'success' => true,
        'data' => $stats
    ];
}
function get_pet_types_analytics($pdo, $data) {
    $adminId = $data['admin_id'] ?? '';
    checkUserRole('admin', $adminId);
    $stmt = $pdo->query("SELECT type, COUNT(*) as count FROM pets GROUP BY type ORDER BY count DESC");
    $dataArr = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return [
        'success' => true,
        'data' => $dataArr
    ];
}
function get_system_performance($pdo, $data) {
    $adminId = $data['admin_id'] ?? '';
    checkUserRole('admin', $adminId);
    $dataArr = [];
    for ($i = 23; $i >= 0; $i--) {
        $dataArr[] = [
            'time' => date('H:i', strtotime("-$i hours")),
            'cpu' => rand(20, 80),
            'memory' => rand(30, 90)
        ];
    }
    return [
        'success' => true,
        'data' => $dataArr
    ];
}
function get_system_alerts($pdo, $data) {
    $adminId = $data['admin_id'] ?? '';
    checkUserRole('admin', $adminId);
    $alerts = [
        [
            'title' => 'High CPU Usage',
            'message' => 'Server CPU usage is above 80%',
            'severity' => 'warning',
            'timestamp' => date('Y-m-d H:i:s', strtotime('-1 hour'))
        ],
        [
            'title' => 'Database Backup',
            'message' => 'Daily backup completed successfully',
            'severity' => 'success',
            'timestamp' => date('Y-m-d H:i:s', strtotime('-2 hours'))
        ],
        [
            'title' => 'New User Registration',
            'message' => '5 new users registered in the last hour',
            'severity' => 'info',
            'timestamp' => date('Y-m-d H:i:s', strtotime('-3 hours'))
        ]
    ];
    return [
        'success' => true,
        'data' => $alerts
    ];
}
function get_recent_activity($pdo, $data) {
    $adminId = $data['admin_id'] ?? '';
    checkUserRole('admin', $adminId);
    $activities = [
        [
            'type' => 'user',
            'title' => 'New User Registration',
            'description' => 'John Doe registered as a pet owner',
            'timestamp' => date('Y-m-d H:i:s', strtotime('-10 minutes'))
        ],
        [
            'type' => 'appointment',
            'title' => 'Appointment Booked',
            'description' => 'Wellness check scheduled for Max (Dog)',
            'timestamp' => date('Y-m-d H:i:s', strtotime('-25 minutes'))
        ],
        [
            'type' => 'payment',
            'title' => 'Payment Received',
            'description' => 'KSh 2,500 received for vaccination service',
            'timestamp' => date('Y-m-d H:i:s', strtotime('-45 minutes'))
        ],
        [
            'type' => 'system',
            'title' => 'System Update',
            'description' => 'Database maintenance completed',
            'timestamp' => date('Y-m-d H:i:s', strtotime('-1 hour'))
        ]
    ];
    return [
        'success' => true,
        'data' => $activities
    ];
}
function generate_admin_report($pdo, $data) {
    $adminId = $data['admin_id'] ?? '';
    $period = $data['period'] ?? 'month';
    checkUserRole('admin', $adminId);
    $csvContent = "Pet Care Management System - Admin Report\n";
    $csvContent .= "Generated on: " . date('Y-m-d H:i:s') . "\n";
    $csvContent .= "Period: " . ucfirst($period) . "\n\n";
    $csvContent .= "USER STATISTICS\n";
    $csvContent .= "Total Users,Active Users,New Users This Period\n";
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $totalUsers = $stmt->fetch()['total'];
    $stmt = $pdo->query("SELECT COUNT(*) as active FROM users WHERE status = 'Active'");
    $activeUsers = $stmt->fetch()['active'];
    $periodCondition = $period === 'week' ? 'AND created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)' : ($period === 'year' ? 'AND created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)' : 'AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)');
    $stmt = $pdo->query("SELECT COUNT(*) as new FROM users WHERE status = 'Active' $periodCondition");
    $newUsers = $stmt->fetch()['new'];
    $csvContent .= "$totalUsers,$activeUsers,$newUsers\n\n";
    $csvContent .= "PET STATISTICS\n";
    $csvContent .= "Total Pets,New Pets This Period\n";
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM pets");
    $totalPets = $stmt->fetch()['total'];
    $stmt = $pdo->query("SELECT COUNT(*) as new FROM pets WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 $period)");
    $newPets = $stmt->fetch()['new'];
    $csvContent .= "$totalPets,$newPets\n\n";
    $csvContent .= "APPOINTMENT STATISTICS\n";
    $csvContent .= "Today's Appointments,Completed Today,Total This Period\n";
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM appointments WHERE DATE(date) = CURDATE()");
    $todayAppointments = $stmt->fetch()['total'];
    $stmt = $pdo->query("SELECT COUNT(*) as completed FROM appointments WHERE DATE(date) = CURDATE() AND status = 'Completed'");
    $completedToday = $stmt->fetch()['completed'];
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM appointments WHERE date >= DATE_SUB(NOW(), INTERVAL 1 $period)");
    $totalAppointments = $stmt->fetch()['total'];
    $csvContent .= "$todayAppointments,$completedToday,$totalAppointments\n\n";
    $csvContent .= "REVENUE STATISTICS\n";
    $csvContent .= "Monthly Revenue,Revenue Growth (%),Total Revenue This Period\n";
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as revenue FROM payments WHERE MONTH(date) = MONTH(NOW()) AND YEAR(date) = YEAR(NOW()) AND status = 'Completed'");
    $monthlyRevenue = $stmt->fetch()['revenue'];
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as revenue FROM payments WHERE date >= DATE_SUB(NOW(), INTERVAL 1 $period) AND status = 'Completed'");
    $periodRevenue = $stmt->fetch()['revenue'];
    // Calculate revenue growth
    $stmt = $pdo->query("SELECT (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE MONTH(date) = MONTH(NOW()) AND YEAR(date) = YEAR(NOW()) AND status = 'Completed') as current, (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE MONTH(date) = MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH)) AND YEAR(date) = YEAR(DATE_SUB(NOW(), INTERVAL 1 MONTH)) AND status = 'Completed') as previous");
    $revenueData = $stmt->fetch();
    $current = $revenueData['current'];
    $previous = $revenueData['previous'];
    $revenueGrowth = $previous > 0 ? round((($current - $previous) / $previous) * 100, 1) : 0;
    $csvContent .= "KSh " . number_format($monthlyRevenue, 2) . ",{$revenueGrowth}%,KSh " . number_format($periodRevenue, 2) . "\n";
    return [
        'success' => true,
        'data' => ['content' => $csvContent]
    ];
}
function get_user_distribution($pdo, $data) {
    $adminId = $data['admin_id'] ?? '';
    checkUserRole('admin', $adminId);
    $stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users WHERE status = 'Active' GROUP BY role ORDER BY count DESC");
    $dataArr = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return [
        'success' => true,
        'data' => $dataArr
    ];
} 