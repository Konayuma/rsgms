<?php
$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? '';
$status = $_SESSION['status'] ?? 'active';
?>
<div class="sidebar" id="appSidebar">
    <div class="sidebar-header">
        <h3><i class="fa-solid fa-landmark brand-icon"></i><span class="nav-label"> RSGMS</span></h3>
        <p class="sidebar-version">v1.0</p>
    </div>
    <div class="sidebar-nav">
        <a href="dashboard.php" class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fa-solid fa-chart-column nav-icon"></i><span class="nav-label"> Dashboard</span>
        </a>
        <?php if ($status === 'pending' || ($role === 'member' && empty($_SESSION['group_id']))): ?>
            <a href="notifications.php" class="<?php echo $current_page == 'notifications.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-bell nav-icon"></i><span class="nav-label"> Notifications</span>
            </a>
            <a href="profile.php" class="<?php echo $current_page == 'profile.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-user nav-icon"></i><span class="nav-label"> Profile</span>
            </a>
        <?php elseif ($role == 'admin'): ?>
            <div class="sidebar-label">System</div>
            <a href="groups.php" class="<?php echo $current_page == 'groups.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-layer-group nav-icon"></i><span class="nav-label"> Groups</span>
            </a>
            <a href="members.php" class="<?php echo $current_page == 'members.php' || $current_page == 'add_member.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-users nav-icon"></i><span class="nav-label"> Members</span>
            </a>
            <a href="savings.php" class="<?php echo $current_page == 'savings.php' || $current_page == 'record_savings.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-sack-dollar nav-icon"></i><span class="nav-label"> Savings</span>
            </a>
            <a href="loans.php" class="<?php echo $current_page == 'loans.php' || $current_page == 'new_loan.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-chart-line nav-icon"></i><span class="nav-label"> Loans</span>
            </a>
            <a href="reports.php" class="<?php echo $current_page == 'reports.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-file-lines nav-icon"></i><span class="nav-label"> Reports</span>
            </a>
            <a href="meetings.php" class="<?php echo $current_page == 'meetings.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-calendar-days nav-icon"></i><span class="nav-label"> Meetings</span>
            </a>
        <?php elseif ($role == 'group_admin'): ?>
            <div class="sidebar-label">My Group</div>
            <a href="group_settings.php" class="<?php echo $current_page == 'group_settings.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-gear nav-icon"></i><span class="nav-label"> Group Settings</span>
            </a>
            <a href="members.php" class="<?php echo $current_page == 'members.php' || $current_page == 'add_member.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-users nav-icon"></i><span class="nav-label"> Members</span>
            </a>
            <a href="savings.php" class="<?php echo $current_page == 'savings.php' || $current_page == 'record_savings.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-sack-dollar nav-icon"></i><span class="nav-label"> Savings</span>
            </a>
            <a href="loans.php" class="<?php echo $current_page == 'loans.php' || $current_page == 'new_loan.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-chart-line nav-icon"></i><span class="nav-label"> Loans</span>
            </a>
            <a href="reports.php" class="<?php echo $current_page == 'reports.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-file-lines nav-icon"></i><span class="nav-label"> Reports</span>
            </a>
            <a href="meetings.php" class="<?php echo $current_page == 'meetings.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-calendar-days nav-icon"></i><span class="nav-label"> Meetings</span>
            </a>
        <?php elseif ($role == 'loan_officer'): ?>
            <a href="members.php" class="<?php echo $current_page == 'members.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-users nav-icon"></i><span class="nav-label"> Members</span>
            </a>
            <a href="loans.php" class="<?php echo $current_page == 'loans.php' || $current_page == 'record_repayment.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-chart-line nav-icon"></i><span class="nav-label"> Loans</span>
            </a>
            <a href="reports.php" class="<?php echo $current_page == 'reports.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-file-lines nav-icon"></i><span class="nav-label"> Reports</span>
            </a>
        <?php elseif ($role == 'member'): ?>
            <a href="my_savings.php" class="<?php echo $current_page == 'my_savings.php' || $current_page == 'record_savings.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-piggy-bank nav-icon"></i><span class="nav-label"> My Savings</span>
            </a>
            <a href="my_loans.php" class="<?php echo $current_page == 'my_loans.php' || $current_page == 'new_loan.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-chart-line nav-icon"></i><span class="nav-label"> My Loans</span>
            </a>
            <a href="statements.php" class="<?php echo $current_page == 'statements.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-file-contract nav-icon"></i><span class="nav-label"> Statements</span>
            </a>
        <?php endif; ?>
        <a href="notifications.php" class="<?php echo $current_page == 'notifications.php' ? 'active' : ''; ?>">
            <i class="fa-solid fa-bell nav-icon"></i><span class="nav-label"> Notifications</span>
        </a>
        <a href="profile.php" class="<?php echo $current_page == 'profile.php' ? 'active' : ''; ?>">
            <i class="fa-solid fa-user nav-icon"></i><span class="nav-label"> Profile</span>
        </a>
    </div>
    <div class="sidebar-footer">
        <button class="sidebar-collapse-btn" id="sidebarCollapseBtn" aria-label="Toggle sidebar"><i class="fa-solid fa-chevron-left"></i></button>
        <a href="logout.php" class="sidebar-logout"><i class="fa-solid fa-right-from-bracket"></i><span class="nav-label"> Logout</span></a>
    </div>
</div>
