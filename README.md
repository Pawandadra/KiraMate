# KiraMate - Shop Rent Management System

A comprehensive web-based solution for managing shop rentals, tenant information, payments, and agreements. Built with modern web technologies and best practices in mind.

## Features

### Admin Management
- User management system
- Role-based access control
- Admin dashboard
- Password management and reset functionality

### Tenant Management
- Complete tenant profile management
- Document upload and storage
- Contact information
- Aadhaar and PAN card Storage
- Tenant history and payment tracking

### Shop Management
- Location and details management
- Agreement management
- Rent calculation and tracking
- Document management for agreements

### Payment Management
- Payment recording and tracking
- Multiple payment method support
- Payment receipt generation
- Payment history and reports
- Opening balance management

### Reporting System
- Payment reports
- Tenant reports
- Shop reports
- Opening balance reports
- Summary reports
- Export functionality

### Security Features
- Secure user authentication
- Role-based access control
- CSRF protection
- XSS prevention
- Input validation and sanitization
- Secure file upload handling

## Technology Stack

### Backend
- PHP 8.0+
- MySQL 8.0+
- Nginx web server

### Frontend
- HTML5
- CSS3
- JavaScript
- Bootstrap 5
- DataTables
- Bootstrap Icons

## Installation

1. Clone the repository:
```bash
git clone https://github.com/Pawandadra/KiraMate---Shop-Rent-Management-System.git
cd rent_manager
```

2. Create a MySQL database:
```sql
CREATE DATABASE rent_manager;
```

3. Create MySQL user and grant privileges:
```sql
CREATE USER 'rentmgr'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON rent_manager.* TO 'rentmgr'@'localhost';
FLUSH PRIVILEGES;
```

4. Import the database schema:
```bash
mysql -u rentmgr -p rent_manager < database/schema.sql
```

5. Configure the environment:
   - Copy `.env.example` to `.env`
   - Update database credentials
   - Set application URL
   - Configure timezone

6. Set up the web server:
   - Use configuration file nginx/rent_manager.conf
   - Ensure proper permissions for uploads and logs directories
   - Enable required PHP extensions

7. Create required directories:
```bash
mkdir -p public/uploads/tenant_documents
mkdir -p public/uploads/shop_documents
mkdir -p logs
chmod 755 public/uploads logs
```

## Configuration

### Environment Variables
- `DB_HOST`: Database host
- `DB_NAME`: Database name
- `DB_USER`: Database username
- `DB_PASS`: Database password
- `APP_NAME`: Application name
- `APP_URL`: Application URL
- `APP_TIMEZONE`: Application timezone
- `APP_ENV`: Environment (development/production)
- `APP_DEBUG`: Debug mode (true/false)

### Directory Structure
```
rent_manager/
├── config/             # Configuration files
├── database/           # Database schema and migrations
├── logs/              # Application logs
├── nginx/             # Nginx configuration file
├── public/            # Public directory
│   ├── admin/        # Admin interface files
│   ├── assets/       # Static assets (CSS, JS, images)
│   ├── payments/     # Payment related files
│   ├── rents/        # Rent management files
│   ├── shops/        # Shop management files
│   ├── tenants/      # Tenant management files
│   ├── uploads/      # Uploaded files
│   │   ├── tenant_documents/
│   │   └──  shop_documents/
│   └── index.php     # Entry point
└──  src/              # Source code
     └── utils/        # Utility classes
```

## Usage

1. Access the application through your web browser
2. Log in with your credentials
3. Navigate through the dashboard to access different features
4. Use the reporting system to generate various reports
5. Manage rents, payments, shops, tenants, opening balances through their respective sections

### Admin Features
   - Create new users
   - Edit user details
   - Manage user roles
   - Reset user passwords
   - Deactivate/activate users


## Security Considerations

- All user inputs are validated and sanitized
- File uploads are restricted to specific types and sizes
- Passwords are hashed using secure algorithms
- Session management includes security best practices
- CSRF tokens are implemented for all forms
- XSS protection is enabled throughout the application

## Contributing

1. Fork the repository
2. Create a feature branch
3. Commit your changes
4. Push to the branch
5. Create a Pull Request

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Support

For support, please contact:
- Email: [pawankumarpk3610@gmail.com](mailto:pawankumarpk3610@gmail.com)
- Create an issue in the repository

## Acknowledgments

- Bootstrap team for the frontend framework
- DataTables for the table functionality

## Customization

### Logo and Letterhead Customization

To customize the letterhead and logo for your organization:

1. **Logo Image**:
   - Place your organization's logo image in `public/assets/images/logo.png`
   - Recommended logo size: 150x150 pixels
   - The logo will appear in:
     - Receipts
     - Reports

2. **Letterhead Text**:
   - Open `public/letterhead.php`
   - Update the following details:
   ```php
      <div class="lh-text">
         <h2> (Place Your Company Name Here) </h2>
         <p> (Place Your Address Here) </p>
      </div>
   ```
   - The changes will reflect in all receipts and reports.