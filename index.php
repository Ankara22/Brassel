<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Brassel System - Home</title>
    <link rel="stylesheet" href="css/index.css">
</head>
<body>
    <div class="header">
        <h1>Welcome to Brassel System</h1>
        <p>Streamline your human resources with our comprehensive management solution</p>
    </div>
    
    <div class="nav">
        <a href="index.php">🏠 Home</a>
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="dashboard.php">📊 Dashboard</a>
            <a href="logout.php">🚪 Logout</a>
        <?php else: ?>
            <a href="signup.php">📝 Sign Up</a>
            <a href="login.php">🔐 Login</a>
        <?php endif; ?>
        <a href="contact.php">📞 Contact</a>
    </div>
    
    <div class="container">
        <div class="hero-section">
            <h2>Transform Your HR Operations</h2>
            <p>
                Our comprehensive HR Management System is designed to streamline every aspect of human resources within your organization. 
                From employee onboarding to attendance tracking, leave management, and payroll processing - we provide an all-in-one solution 
                that empowers your team to work more efficiently and effectively.
            </p>
            
            <div class="image-placeholder">
                📊 HR Analytics Dashboard Preview
            </div>
        </div>
        
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">👥</div>
                <h3>Employee Management</h3>
                <p>Comprehensive employee profiles, onboarding, and performance tracking with real-time updates and secure data management.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">⏰</div>
                <h3>Attendance Tracking</h3>
                <p>Advanced clock-in/out system with GPS verification, overtime calculation, and detailed attendance reports for better workforce management.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">📋</div>
                <h3>Leave Management</h3>
                <p>Streamlined leave request processing with automated approvals, balance tracking, and comprehensive leave history for all employees.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">💰</div>
                <h3>Payroll & Finance</h3>
                <p>Automated payroll processing, tax calculations, expense tracking, and financial reporting with secure payment integration.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">📊</div>
                <h3>Advanced Analytics</h3>
                <p>Comprehensive reporting and analytics with customizable dashboards, performance metrics, and data-driven insights for informed decision making.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">🔒</div>
                <h3>Security & Compliance</h3>
                <p>Enterprise-grade security with role-based access control, audit trails, and compliance features to protect sensitive HR data.</p>
            </div>
        </div>
        
        <div class="cta-section">
            <h2>Ready to Get Started?</h2>
            <p>Join thousands of organizations that trust our HR Management System to streamline their operations and boost productivity.</p>
            
            <div class="cta-buttons">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="dashboard.php" class="cta-btn primary">Go to Dashboard</a>
                    <a href="logout.php" class="cta-btn secondary">Logout</a>
                <?php else: ?>
                    <a href="signup.php" class="cta-btn primary">Create Account</a>
                    <a href="login.php" class="cta-btn secondary">Sign In</a>
                <?php endif; ?>
                <a href="about.php" class="cta-btn secondary">Learn More</a>
            </div>
        </div>
    </div>
</body>
</html>



