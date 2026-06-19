# AuthValid - Authentication & Validation Microservice

## Overview

**AuthValid** is a dedicated authentication and validation microservice built as part of a microservice architecture. It handles user authentication, data validation, and CSV file uploads while communicating with the ViewService through controllers.

## Project Type
**Final Year Project** - Microservice Architecture Implementation

## Key Features

- **User Authentication & Validation** - Validates user credentials and manages user authentication
- **CSV File Uploads** - Processes and handles CSV file uploads for bulk data operations
- **Microservice Communication** - Communicates with ViewService through AUTH controller APIs
- **Role-Based Access Control** - Supports multi-user authentication and authorization

## Technology Stack

- **Backend Framework:** Laravel (PHP)
- **Frontend Assets:** Blade Templates (35.2%)
- **Language Composition:**
  - PHP: 64.4%
  - Blade: 35.2%
  - Other: 0.4%
- **Build Tool:** Vite
- **Testing:** PHPUnit
- **Package Manager:** Composer

## Project Structure

```
AuthValid/
├── app/              # Application logic, controllers, models
├── config/           # Configuration files
├── database/         # Migrations and seeders
├── routes/           # API routes definition
├── resources/        # Blade templates and frontend assets
├── public/           # Public accessible files
├── storage/          # Logs and file storage
├── tests/            # Test cases (PHPUnit)
├── bootstrap/        # Bootstrap files
├── composer.json     # PHP dependencies
├── package.json      # NPM dependencies
├── phpunit.xml       # PHPUnit configuration
├── vite.config.js    # Vite configuration
└── .env.example      # Environment variables template
```

## Installation & Setup

### Prerequisites
- PHP >= 8.0
- Composer
- Node.js & NPM
- MySQL or similar database

### Steps

1. **Clone the repository:**
   ```bash
   git clone https://github.com/RogerEugen/AuthValid.git
   cd AuthValid
   ```

2. **Install dependencies:**
   ```bash
   composer install
   npm install
   ```

3. **Environment Configuration:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Database Setup:**
   ```bash
   php artisan migrate
   php artisan db:seed  # (if seeders exist)
   ```

5. **Build Frontend Assets:**
   ```bash
   npm run build
   ```

6. **Start Development Server:**
   ```bash
   php artisan serve
   ```

## API Endpoints

### Authentication
- User validation and credential verification
- Session management

### File Management
- CSV file upload and processing
- Bulk data import functionality

### Integration
- Communication endpoints with ViewService
- AUTH controller APIs

## Configuration

Edit `.env` file to configure:
- Database connection
- Authentication settings
- API endpoints for ViewService communication
- File upload settings

## Testing

Run unit and feature tests:
```bash
php artisan test
# or
phpunit
```

## Development

### Frontend Build
- Development: `npm run dev`
- Production: `npm run build`

### Code Standards
- Follow Laravel conventions
- Use PHP coding standards (PSR-12)

## Integration with ViewService

AuthValid communicates with **ViewService** through AUTH controllers to:
- Send authenticated user data
- Receive validation requests
- Sync user sessions

## Security Considerations

- Store sensitive data in `.env` file (never commit to repository)
- Implement proper authentication middleware
- Validate all user inputs
- Use CSRF protection for forms
- Keep dependencies updated

## Contributing

1. Create a feature branch
2. Commit changes
3. Push to repository
4. Create a Pull Request

## Project Authors
**RogerEugen** - Final Year Project

## License
[To be specified]

## Status
🚀 **In Development** - Final Year Project

---

**Related Service:** [ViewService](https://github.com/RogerEugen/ViewService)
