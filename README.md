# 🐾 Purrfect Pet Care Management System

A comprehensive web and mobile-based Pet Care Management System tailored for Kenya that digitizes pet healthcare services. This system allows pet owners to register pets, book online vet appointments, receive automated vaccination reminders, and access AI-powered health monitoring.

## 🌟 Features

### Core Features
- **Pet Profile Management** - Complete pet profiles with medical history
- **Online Appointment Booking** - Easy scheduling with SMS/email reminders
- **Telemedicine Consultations** - Virtual vet consultations with video support
- **Lost Pet Tracking** - Report and track lost pets with community support
- **Pet Adoption Services** - Connect pets with loving homes
- **Digital Health Records** - Secure, comprehensive medical documentation
- **AI Health Alert System** - Intelligent health monitoring and alerts
- **Community Forum** - Pet owner community with moderation
- **E-Payment Gateway** - Secure M-Pesa, Card, and PayPal payments

### Advanced Features
- **Real-time Notifications** - WebSocket-powered instant updates
- **Prescription Management** - Digital prescriptions with refill tracking
- **File Upload System** - Pet photos and medical documents
- **Audit Logging** - Comprehensive system activity tracking
- **Accessibility** - WCAG 2.1 AA compliant with screen reader support
- **PWA Support** - Install as mobile app on devices
- **Multi-language Support** - Ready for localization
- **Role-based Access** - Owner, Veterinarian, and Admin roles

## 🛠️ Technology Stack

### Frontend
- **HTML5** - Semantic markup with accessibility features
- **CSS3** - Responsive design with modern styling
- **JavaScript (ES6+)** - Interactive functionality and real-time features
- **Bootstrap 5** - Mobile-first responsive framework
- **Font Awesome** - Icon library
- **PWA** - Progressive Web App capabilities

### Backend
- **PHP 7.4+** - Server-side logic and API endpoints
- **MySQL 5.7+** - Relational database management
- **WebSocket Server** - Real-time communication
- **RESTful APIs** - Standardized API endpoints

### Integrations
- **M-Pesa** - Mobile money payments (Kenya)
- **Twilio** - SMS notifications and video calls
- **Google Maps** - Location services
- **PayPal/Stripe** - International payment processing

## 📋 Requirements

### System Requirements
- PHP 7.4 or higher
- MySQL 5.7 or higher (or MariaDB 10.2+)
- Web Server (Apache 2.4+ or Nginx)
- SSL Certificate (for production)
- Minimum 512MB RAM (1GB recommended)

### PHP Extensions
- PDO MySQL
- GD (for image processing)
- cURL
- JSON
- OpenSSL
- ZIP

## 🚀 Quick Start

### 1. Installation
```bash
# Clone the repository
git clone https://github.com/your-repo/pet-care-management.git
cd pet-care-management

# Install dependencies (if using Composer)
composer install

# Set up database
php backend/setup_database.php
```

### 2. Configuration
```bash
# Copy and edit configuration
cp config/config.example.php config/config.php
# Edit config.php with your settings
```

### 3. File Permissions
```bash
# Set proper permissions
chmod 755 uploads/
chmod 644 config/config.php
```

### 4. Start Services
```bash
# Start WebSocket server (for real-time features)
php backend/websocket_server.php
```

## ⚙️ Configuration

### Database Configuration
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'pet_care_system');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
```

### Email Configuration
```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
```

### SMS Configuration
```php
define('SMS_PROVIDER', 'twilio');
define('TWILIO_ACCOUNT_SID', 'your-account-sid');
define('TWILIO_AUTH_TOKEN', 'your-auth-token');
```

### M-Pesa Configuration
```php
define('MPESA_CONSUMER_KEY', 'your-consumer-key');
define('MPESA_CONSUMER_SECRET', 'your-consumer-secret');
define('MPESA_PASSKEY', 'your-passkey');
```

## 📱 PWA Features

The system includes Progressive Web App capabilities:
- Install as mobile app
- Offline functionality
- Push notifications
- App shortcuts
- Responsive design

## 🔒 Security Features

- **JWT Authentication** - Secure token-based authentication
- **Role-based Access Control** - Granular permissions
- **Audit Logging** - Complete activity tracking
- **Input Validation** - XSS and SQL injection protection
- **HTTPS Enforcement** - Secure communications
- **Password Hashing** - Bcrypt encryption

## 🌐 Accessibility

- **WCAG 2.1 AA Compliant** - Web Content Accessibility Guidelines
- **Screen Reader Support** - ARIA labels and semantic markup
- **Keyboard Navigation** - Full keyboard accessibility
- **High Contrast Mode** - Visual accessibility
- **Font Scaling** - Text size adjustments
- **Focus Indicators** - Clear focus management

## 📊 Admin Features

- **Analytics Dashboard** - Comprehensive system statistics
- **User Management** - Add, edit, and manage users
- **Content Moderation** - Forum and content management
- **System Monitoring** - Performance and error tracking
- **Backup Management** - Automated data backups
- **Audit Reports** - Detailed activity reports

## 🔧 API Endpoints

### Authentication
- `POST /backend/api.php?action=login`
- `POST /backend/api.php?action=register`
- `POST /backend/api.php?action=logout`

### Pets
- `GET /backend/api.php?action=get_pets`
- `POST /backend/api.php?action=add_pet`
- `PUT /backend/api.php?action=update_pet`
- `DELETE /backend/api.php?action=delete_pet`

### Appointments
- `GET /backend/api.php?action=get_appointments`
- `POST /backend/api.php?action=book_appointment`
- `PUT /backend/api.php?action=update_appointment`
- `DELETE /backend/api.php?action=cancel_appointment`

### Notifications
- `POST /backend/notification_service.php?action=send_email`
- `POST /backend/notification_service.php?action=send_sms`

### File Upload
- `POST /backend/file_upload.php?action=upload_pet_photo`
- `POST /backend/file_upload.php?action=upload_medical_document`

## 🚀 Deployment

### cPanel Deployment
1. Upload files to `public_html`
2. Create MySQL database
3. Import database schema
4. Configure settings in `config/config.php`
5. Set file permissions
6. Test all features

### VPS/Cloud Deployment
1. Set up LAMP/LEMP stack
2. Clone repository
3. Configure web server
4. Set up SSL certificate
5. Configure cron jobs
6. Start WebSocket server

## 📞 Support

### Documentation
- [Setup Guide](docs/SETUP_GUIDE.md)
- [Features Implementation](docs/FEATURES_IMPLEMENTATION.md)
- [API Documentation](docs/API_DOCUMENTATION.md)
- [Deployment Guide](docs/DEPLOYMENT_GUIDE.md)

### Troubleshooting
- Check error logs in `/logs/`
- Verify database connection
- Test API endpoints
- Monitor WebSocket server
- Review audit logs

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- Bootstrap for responsive design
- Font Awesome for icons
- Twilio for SMS and video services
- M-Pesa for mobile payments
- Google Maps for location services

## 📈 Roadmap

### Version 2.0
- [ ] AI-powered health diagnostics
- [ ] Mobile app (React Native)
- [ ] Multi-language support
- [ ] Advanced analytics
- [ ] Integration with veterinary equipment

### Version 3.0
- [ ] Blockchain for medical records
- [ ] IoT pet monitoring devices
- [ ] Machine learning predictions
- [ ] Telemedicine AI assistant
- [ ] Advanced payment options

## 🔐 Environment Variables & Security

- Set `APP_ENV=development` in your environment for debugging; use `APP_ENV=production` in production to hide errors from users.
- Store sensitive configuration (database credentials, API keys) in a `.env` file or environment variables, not in code.
- Never commit `.env` or sensitive config files to version control.
- Always keep `display_errors` off in production for security.

---

**Built with ❤️ for pet care in Kenya**

For support and questions, contact: info@purrfectpetcare.com 